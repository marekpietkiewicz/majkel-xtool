<?php
/**
 * Plugin Name: Lasery xTool — katalog
 * Description: Szkice produktów, ręczny import pliku JSON i pobieranie z xTool.
 * Version: 0.2.0
 * Text Domain: lasery-xtool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_PRODUCT_TYPE = 'lasery_product';
const LASERY_XTOOL_SOURCE_ID    = '_lasery_shopify_id';
const LASERY_XTOOL_MAX_BYTES    = 4 * 1024 * 1024;
const LASERY_XTOOL_MAX_PRODUCTS = 500;
// Shopify's largest products.json page; eight pages cover the catalogue (about 800 products) with room to grow.
const LASERY_XTOOL_PAGE_SIZE    = 250;
const LASERY_XTOOL_MAX_PAGES    = 8;
// Raise when lasery_xtool_install() has new work for existing sites.
const LASERY_XTOOL_SCHEMA       = '8';

require_once __DIR__ . '/reviewed-product-content.php';

add_action( 'init', 'lasery_xtool_register_product_type' );
function lasery_xtool_register_product_type() {
	register_post_type(
		LASERY_XTOOL_PRODUCT_TYPE,
		array(
			'labels'       => array(
				'name'          => 'Produkty xTool',
				'singular_name' => 'Produkt xTool',
			),
			'public'       => true,
			'has_archive'  => true,
			// Posts live under /blog/; products keep their own base.
			'rewrite'      => array( 'slug' => 'produkty', 'with_front' => false ),
			'show_ui'      => true,
			'show_in_menu' => true,
			// The order field sets the product order on the website.
			'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		)
	);
}

// Products moved from /lasery_product/ to /produkty/ in October 2026. Runs before
// redirect_canonical(), whose fuzzy slug guess could pick a different product.
add_action( 'template_redirect', 'lasery_xtool_redirect_old_urls', 9 );
function lasery_xtool_redirect_old_urls() {
	$path = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
	if ( ! is_404() || ! is_string( $path ) || ! preg_match( '~^/lasery_product(?:/([a-z0-9-]+))?/?$~', $path, $match ) ) {
		return;
	}
	$product = isset( $match[1] ) ? get_page_by_path( $match[1], OBJECT, LASERY_XTOOL_PRODUCT_TYPE ) : null;
	$target = $product && 'publish' === $product->post_status ? get_permalink( $product ) : get_post_type_archive_link( LASERY_XTOOL_PRODUCT_TYPE );
	if ( $target ) {
		// Without a language the theme sends the visitor on to theirs.
		wp_safe_redirect( isset( $_GET['lang'] ) ? $target : remove_query_arg( 'lang', $target ), 301 );
		exit;
	}
}

register_activation_hook( __FILE__, 'lasery_xtool_activate' );
function lasery_xtool_activate() {
	lasery_xtool_register_product_type();
	lasery_xtool_register_kind_taxonomy();
	lasery_xtool_register_badge_taxonomy();
	lasery_xtool_install();
}

// Updates of an already active plugin do not run the activation hook.
add_action( 'init', 'lasery_xtool_maybe_install', 20 );
function lasery_xtool_maybe_install() {
	// The lock keeps concurrent first requests after an update from creating the blog page twice.
	if ( LASERY_XTOOL_SCHEMA !== get_option( 'lasery_xtool_schema' ) && lasery_xtool_lock_import() ) {
		lasery_xtool_install();
		lasery_xtool_release_import_lock();
	}
}

/** Create the terms templates rely on and refresh URLs. Names can be changed in the panel. */
function lasery_xtool_install() {
	if ( ! term_exists( LASERY_XTOOL_ACCESSORY_TERM, LASERY_XTOOL_KIND_TAXONOMY ) ) {
		wp_insert_term( 'Akcesoria', LASERY_XTOOL_KIND_TAXONOMY, array( 'slug' => LASERY_XTOOL_ACCESSORY_TERM ) );
	}
	if ( ! term_exists( LASERY_XTOOL_DEMO_BADGE, LASERY_XTOOL_BADGE_TAXONOMY ) ) {
		$term = wp_insert_term( 'Demo na żywo', LASERY_XTOOL_BADGE_TAXONOMY, array( 'slug' => LASERY_XTOOL_DEMO_BADGE ) );
		if ( is_array( $term ) ) {
			foreach ( array( 'en' => 'Live demo', 'de' => 'Live-Demo', 'ru' => 'Демо вживую', 'uk' => 'Демо наживо' ) as $language => $name ) {
				update_term_meta( $term['term_id'], 'lasery_name_' . $language, $name );
			}
		}
	}
	// The badge of the machines xTool published recently; the sync sets it (novelties.php).
	if ( ! term_exists( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY ) ) {
		$term = wp_insert_term( 'Nowość', LASERY_XTOOL_BADGE_TAXONOMY, array( 'slug' => LASERY_XTOOL_NEW_BADGE ) );
		if ( is_array( $term ) ) {
			foreach ( array( 'en' => 'New', 'de' => 'Neu', 'ru' => 'Новинка', 'uk' => 'Новинка' ) as $language => $name ) {
				update_term_meta( $term['term_id'], 'lasery_name_' . $language, $name );
			}
		}
	}
	// The theme renders the blog, cart and information pages (page-<slug>.php).
	foreach ( array( 'blog' => 'Blog', LASERY_XTOOL_CART_PAGE => 'Koszyk', 'bezpieczenstwo' => 'Bezpieczeństwo', 'informacje-o-zakupach' => 'Informacje o zakupach' ) as $slug => $title ) {
		if ( ! get_page_by_path( $slug ) ) {
			$created = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'comment_status' => 'closed' ), true );
			if ( is_wp_error( $created ) || ! $created ) {
				// Keep the old schema so the next request can retry without duplicating pages already created.
				return false;
			}
			$page = get_post( $created );
			if ( ! $page || $page->post_name !== $slug ) {
				// A conflicting reserved slug must not leave behind a duplicate page on each retry.
				if ( $page ) {
					wp_delete_post( $created, true );
				}
				return false;
			}
		}
	}
	// Products shown in existing articles; saving a post keeps this up to date (schema 6).
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post_id ) {
		lasery_xtool_index_article_products( $post_id );
	}
	// Left over from the per-product sync replaced in schema 4.
	delete_post_meta_by_key( '_lasery_source_handle' );
	delete_post_meta_by_key( '_lasery_handle_retry_after' );
	flush_rewrite_rules();
	update_option( 'lasery_xtool_schema', LASERY_XTOOL_SCHEMA );
}

register_deactivation_hook( __FILE__, 'lasery_xtool_deactivate' );
function lasery_xtool_deactivate() {
	if ( post_type_exists( LASERY_XTOOL_PRODUCT_TYPE ) ) {
		unregister_post_type( LASERY_XTOOL_PRODUCT_TYPE );
	}
	wp_clear_scheduled_hook( 'lasery_xtool_sync_existing' );
	wp_clear_scheduled_hook( 'lasery_xtool_sync_images' );
	flush_rewrite_rules();
}

require_once __DIR__ . '/product-kind.php';
require_once __DIR__ . '/product-badges.php';
require_once __DIR__ . '/novelties.php';
require_once __DIR__ . '/order-form.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/product-relations.php';
require_once __DIR__ . '/image-sync.php';
require_once __DIR__ . '/featured-products.php';
require_once __DIR__ . '/product-translations.php';
require_once __DIR__ . '/privacy-settings.php';
require_once __DIR__ . '/site-texts.php';
require_once __DIR__ . '/gallery-editor.php';
require_once __DIR__ . '/blog.php';

add_action( 'admin_menu', 'lasery_xtool_register_import_page' );
function lasery_xtool_register_import_page() {
	add_submenu_page(
		'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE,
		'Import produktów',
		'Import',
		'manage_options',
		'lasery-xtool-import',
		'lasery_xtool_import_page'
	);
}

function lasery_xtool_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'lasery-xtool' ), '', array( 'response' => 403 ) );
	}
	$messages = array(
		'file'     => 'Nie udało się odczytać pliku JSON. Limit pliku to 4 MB.',
		'format'   => 'Nieprawidłowy format danych. Sprawdź wymagania pliku w dokumentacji wtyczki.',
		'network'  => 'Nie udało się pobrać produktów. Sprawdź dostępność źródła i spróbuj ponownie.',
		'paused'   => 'Pobieranie jest chwilowo wstrzymane po odpowiedzi 429 i wznowi się samo.',
		'locked'   => 'Inny import jest w trakcie. Spróbuj ponownie później.',
		'locklost' => 'Import przerwany: utracono blokadę. Sprawdź szkice i ponów import.',
		'complete' => 'Import zakończony.',
		'limit'    => 'Import zakończony. Osiągnięto limit pobierania — w źródle mogą być dalsze produkty.',
		'incomplete' => 'Nie udało się zsynchronizować części produktów. Sprawdź ich dane źródłowe i ponów próbę.',
		'anomaly'  => 'Z katalogu xTool zniknęła naraz duża część produktów, więc nic nie zostało ukryte. Sprawdź źródło.',
	);
	$result = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
	?>
	<div class="wrap">
		<h1>Import produktów xTool</h1>
		<p>Wybierz plik JSON z danymi, do których użycia masz uprawnienie. Nowe produkty zostaną zapisane jako szkice. W istniejących produktach zostaną zaktualizowane tylko dane źródłowe wariantów i zdjęcia.</p>
		<?php if ( isset( $messages[ $result ] ) ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $messages[ $result ] ); ?>
			<?php if ( 'complete' === $result || 'limit' === $result || 'incomplete' === $result ) : ?>
				<?php
				$created = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
				$updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
				$failed  = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
				printf( ' Utworzono: %d, zaktualizowano dane źródłowe: %d, błędy zapisu: %d.', $created, $updated, $failed );
				?>
			<?php endif; ?></p></div>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_import">
			<?php wp_nonce_field( 'lasery_xtool_import' ); ?>
			<label for="lasery-xtool-products">Plik JSON produktów</label>
			<input id="lasery-xtool-products" type="file" name="products" accept=".json,application/json" required>
			<?php submit_button( 'Importuj do szkiców' ); ?>
		</form>
		<h2>Pobierz produkty xTool</h2>
		<?php $last_sync = get_option( 'lasery_xtool_last_sync' ); ?>
		<?php if ( is_array( $last_sync ) && isset( $last_sync['time'], $last_sync['result'] ) && is_numeric( $last_sync['time'] ) && is_string( $last_sync['result'] ) ) : ?>
			<p><?php echo esc_html( 'Ostatnia synchronizacja: ' . wp_date( 'Y-m-d H:i', (int) $last_sync['time'] ) . '. ' . ( isset( $messages[ $last_sync['result'] ] ) ? $messages[ $last_sync['result'] ] : 'Nieznany wynik.' ) ); ?>
			<?php if ( isset( $last_sync['updated'], $last_sync['failed'] ) ) : ?><?php printf( ' Zaktualizowano: %d, błędy: %d.', absint( $last_sync['updated'] ), absint( $last_sync['failed'] ) ); ?><?php endif; ?></p>
			<?php if ( ! empty( $last_sync['stale'] ) && is_array( $last_sync['stale'] ) ) : ?>
				<p>Bez aktualnej oferty (brak w katalogu xTool lub błędne dane): <?php echo esc_html( implode( ', ', array_map( function ( $id ) { return lasery_xtool_plain_text( get_the_title( absint( $id ) ) ); }, $last_sync['stale'] ) ) ); ?>.</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( lasery_xtool_fetching_paused() || lasery_xtool_fetching_paused( LASERY_XTOOL_IMAGES_PAUSE ) ) : ?>
			<p><?php echo esc_html( 'Pobieranie wstrzymano po odpowiedzi 429 do ' . wp_date( 'H:i', max( (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ), (int) get_option( LASERY_XTOOL_IMAGES_PAUSE ) ) ) . '; potem wznowi się samo. Wcześniej wznów je tylko, gdy ograniczenie źródła na pewno minęło.' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lasery_xtool_resume_fetch">
				<?php wp_nonce_field( 'lasery_xtool_resume_fetch' ); ?>
				<?php submit_button( 'Wznów pobieranie' ); ?>
			</form>
		<?php endif; ?>
		<p>Ręczne pobranie z xtool.eu obejmuje maksymalnie <?php echo esc_html( LASERY_XTOOL_MAX_PAGES * LASERY_XTOOL_PAGE_SIZE ); ?> produktów. Nowe wpisy zostaną zapisane jako szkice.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_fetch">
			<?php wp_nonce_field( 'lasery_xtool_fetch' ); ?>
			<?php submit_button( 'Pobierz do szkiców' ); ?>
		</form>
	</div>
	<?php
}

/** One validated product from Shopify's products.json, or false. The sync has no use for the description, so it skips sanitising it. */
function lasery_xtool_parse_product( $product, $with_content = true ) {
	if ( ! is_object( $product ) || ! isset( $product->id, $product->title ) || ( ! is_int( $product->id ) && ! is_string( $product->id ) ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $product->id ) || ! is_string( $product->title ) || ( isset( $product->body_html ) && ! is_string( $product->body_html ) ) ) {
		return false;
	}
	$title = sanitize_text_field( $product->title );
	if ( '' === $title ) {
		return false;
	}
	$item = array(
		'id'      => (string) $product->id,
		'title'   => $title,
		'content' => $with_content && isset( $product->body_html ) ? wp_kses_post( $product->body_html ) : '',
	);
	if ( property_exists( $product, 'variants' ) ) {
		if ( ! is_array( $product->variants ) ) {
			return false;
		}
		$item['variants'] = array();
		$variant_ids = array();
		foreach ( $product->variants as $variant ) {
			if ( ! is_object( $variant ) || ! isset( $variant->id, $variant->title, $variant->price, $variant->available ) || ( ! is_int( $variant->id ) && ! is_string( $variant->id ) ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $variant->id ) || ! is_string( $variant->title ) || ! is_string( $variant->price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $variant->price ) || ! is_bool( $variant->available ) || ( isset( $variant->sku ) && ! is_string( $variant->sku ) ) || ( isset( $variant->compare_at_price ) && ( ! is_string( $variant->compare_at_price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $variant->compare_at_price ) ) ) ) {
				return false;
			}
			$variant_id = (string) $variant->id;
			$variant_title = sanitize_text_field( $variant->title );
			if ( '' === $variant_title || isset( $variant_ids[ $variant_id ] ) ) {
				return false;
			}
			$variant_ids[ $variant_id ] = true;
			$item['variants'][] = array(
				'id' => $variant_id,
				'title' => $variant_title,
				'sku' => isset( $variant->sku ) ? sanitize_text_field( $variant->sku ) : '',
				'price' => $variant->price,
				'compare_at_price' => isset( $variant->compare_at_price ) ? $variant->compare_at_price : null,
				'available' => $variant->available,
			);
		}
	}
	if ( property_exists( $product, 'images' ) ) {
		if ( ! is_array( $product->images ) ) {
			return false;
		}
		$item['image_urls'] = array();
		foreach ( $product->images as $image ) {
			if ( ! is_object( $image ) || ! isset( $image->src ) || ! is_string( $image->src ) ) {
				return false;
			}
			$url = esc_url_raw( $image->src, array( 'https' ) );
			if ( lasery_xtool_is_source_image_url( $url ) && ! in_array( $url, $item['image_urls'], true ) && count( $item['image_urls'] ) < 20 ) {
				$item['image_urls'][] = $url;
			}
		}
		$item['image_url'] = $item['image_urls'] ? $item['image_urls'][0] : '';
	}
	return $item;
}

/** The ID of a raw products.json entry as a string, '' when it has none. */
function lasery_xtool_raw_product_id( $entry ) {
	return is_object( $entry ) && isset( $entry->id ) && ( is_int( $entry->id ) || is_string( $entry->id ) ) ? (string) $entry->id : '';
}

/** The raw products of a products.json document with at most $limit entries, or false. */
function lasery_xtool_decode_products( $json, $limit ) {
	$data = json_decode( $json, false, 512, JSON_BIGINT_AS_STRING );
	return JSON_ERROR_NONE === json_last_error() && is_object( $data ) && isset( $data->products ) && is_array( $data->products ) && count( $data->products ) <= $limit ? $data->products : false;
}

/** Validate the complete document before creating any drafts. */
function lasery_xtool_parse_products( $json ) {
	$raw = lasery_xtool_decode_products( $json, LASERY_XTOOL_MAX_PRODUCTS );
	if ( false === $raw ) {
		return false;
	}
	$products = array();
	foreach ( $raw as $product ) {
		$item = lasery_xtool_parse_product( $product );
		if ( false === $item || isset( $products[ $item['id'] ] ) ) {
			return false;
		}
		$products[ $item['id'] ] = $item;
	}
	return array_values( $products );
}

function lasery_xtool_redirect_import( $args ) {
	$url = add_query_arg(
		$args,
		admin_url( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE . '&page=lasery-xtool-import' )
	);
	wp_safe_redirect( $url );
	exit;
}

/** An atomic, expiring database lease shared by both import paths. */
function lasery_xtool_lock_import() {
	global $wpdb;
	$name = 'lasery_xtool_import_lock';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d",
			$name,
			time()
		)
	);
	$value = ( time() + 900 ) . ':' . wp_generate_uuid4();
	$inserted = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$name,
			$value
		)
	);
	if ( 1 !== $inserted ) {
		return false;
	}
	$GLOBALS['lasery_xtool_lock_value'] = $value;
	register_shutdown_function( 'lasery_xtool_release_import_lock' );
	return true;
}

function lasery_xtool_refresh_import_lock() {
	global $wpdb;
	$old_value = $GLOBALS['lasery_xtool_lock_value'];
	$new_value = ( time() + 900 ) . ':' . wp_generate_uuid4();
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
			$new_value,
			'lasery_xtool_import_lock',
			$old_value
		)
	);
	if ( 1 !== $updated ) {
		return false;
	}
	$GLOBALS['lasery_xtool_lock_value'] = $new_value;
	return true;
}

function lasery_xtool_release_import_lock() {
	global $wpdb;
	if ( ! isset( $GLOBALS['lasery_xtool_lock_value'] ) ) {
		return;
	}
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			'lasery_xtool_import_lock',
			$GLOBALS['lasery_xtool_lock_value']
		)
	);
	unset( $GLOBALS['lasery_xtool_lock_value'] );
}

/** State that can affect public product pages, cards, search and set contents. */
function lasery_xtool_public_product_state( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return null;
	}
	$variants = lasery_xtool_order_variants( $post_id );
	$fresh = (bool) $variants && lasery_xtool_source_is_fresh( $post_id );
	return array(
		'variants' => $variants,
		'fresh' => $fresh,
		'currency' => $fresh ? get_post_meta( $post_id, '_lasery_source_currency', true ) : '',
		'thumbnail' => get_post_thumbnail_id( $post_id ),
		'gallery' => lasery_xtool_product_gallery( $post_id ),
	);
}

/** Coalesce all changes in this request, including partial failures, into one purge. */
function lasery_xtool_product_state_changed( $post_id, $before ) {
	if ( $before !== lasery_xtool_public_product_state( $post_id ) ) {
		lasery_xtool_schedule_cache_purge();
	}
}

function lasery_xtool_schedule_cache_purge() {
	static $scheduled = false;
	if ( ! $scheduled ) {
		$scheduled = true;
		// LiteSpeed emits headers and kicks its CLI queue on shutdown at priority 0.
		add_action( 'shutdown', 'lasery_xtool_purge_catalog_cache', -1 );
	}
}

function lasery_xtool_purge_catalog_cache() {
	if ( function_exists( 'lasery_xtool_search_refresh' ) ) {
		lasery_xtool_search_refresh();
	}
	// LiteSpeed's public purge hook with * clears page cache, including cards in articles.
	do_action( 'litespeed_purge', '*' );
}

add_action( 'admin_post_lasery_xtool_import', 'lasery_xtool_handle_import' );
function lasery_xtool_handle_import() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'lasery-xtool' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'lasery_xtool_import' );
	if ( ! lasery_xtool_lock_import() ) {
		lasery_xtool_redirect_import( array( 'result' => 'locked' ) );
	}
	if ( ! isset( $_FILES['products'] ) || ! is_array( $_FILES['products'] ) ) {
		lasery_xtool_redirect_import( array( 'result' => 'file' ) );
	}
	$file = $_FILES['products'];
	if ( ! isset( $file['error'], $file['name'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) || 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		lasery_xtool_redirect_import( array( 'result' => 'file' ) );
	}
	$size = filesize( $file['tmp_name'] );
	if ( false === $size || 0 === $size || $size > LASERY_XTOOL_MAX_BYTES ) {
		lasery_xtool_redirect_import( array( 'result' => 'file' ) );
	}
	$json = file_get_contents( $file['tmp_name'] );
	if ( false === $json ) {
		lasery_xtool_redirect_import( array( 'result' => 'file' ) );
	}
	$products = lasery_xtool_parse_products( $json );
	if ( false === $products ) {
		lasery_xtool_redirect_import( array( 'result' => 'format' ) );
	}
	lasery_xtool_save_products( $products, 'complete' );
}

add_action( 'admin_post_lasery_xtool_resume_fetch', 'lasery_xtool_resume_fetch' );
function lasery_xtool_resume_fetch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'lasery-xtool' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'lasery_xtool_resume_fetch' );
	delete_option( LASERY_XTOOL_OFFERS_PAUSE );
	delete_option( LASERY_XTOOL_IMAGES_PAUSE );
	lasery_xtool_redirect_import( array() );
}

add_action( 'admin_post_lasery_xtool_fetch', 'lasery_xtool_handle_fetch' );
function lasery_xtool_handle_fetch() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'lasery-xtool' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'lasery_xtool_fetch' );
	// Up to a few thousand drafts take longer than a usual request.
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 600 );
	}
	if ( ! lasery_xtool_lock_import() ) {
		lasery_xtool_redirect_import( array( 'result' => 'locked' ) );
	}
	$fetched = lasery_xtool_fetch_source();
	if ( is_wp_error( $fetched ) ) {
		lasery_xtool_redirect_import( array( 'result' => $fetched->get_error_code() ) );
	}
	lasery_xtool_save_products( $fetched['products'], $fetched['result'] );
}

const LASERY_XTOOL_OFFERS_PAUSE = 'lasery_xtool_fetch_paused';
const LASERY_XTOOL_IMAGES_PAUSE = 'lasery_xtool_images_paused';
const LASERY_XTOOL_SOURCE_URL   = 'https://www.xtool.eu';
// Page ETags and product IDs of the last complete walk of products.json; they make an unchanged page a 304.
const LASERY_XTOOL_STATE        = 'lasery_xtool_catalog_state';
// An offer the source stopped listing is hidden after this many consecutive complete walks without it.
const LASERY_XTOOL_MISSING_RUNS = 3;

/**
 * After HTTP 429 the source is left alone for as long as Retry-After asks (at least five minutes,
 * at most a day) and fetching then resumes by itself. Offers and images are paused separately, so
 * a throttled image download cannot keep prices from refreshing. The import screen can resume earlier.
 */
function lasery_xtool_pause_fetching( $response, $option = LASERY_XTOOL_OFFERS_PAUSE ) {
	$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
	update_option( $option, time() + min( DAY_IN_SECONDS, max( 5 * MINUTE_IN_SECONDS, $retry_after ) ), false );
}

function lasery_xtool_fetching_paused( $option = LASERY_XTOOL_OFFERS_PAUSE ) {
	return (int) get_option( $option ) > time();
}

/**
 * One GET from the source, shaped like a wp_remote_get() response, compressed on the wire and
 * conditional when $etag is given (an unchanged document answers 304 without a body). The system curl
 * is preferred: the source answers PHP's HTTP client on this shared host with 429 "local_rate_limited"
 * whatever the pause, while the same request from curl succeeds. It is still one polite request with the
 * same caps; 429 is honoured by the caller. Without a usable curl the WordPress HTTP API is used.
 * The 'lasery_xtool_pre_request' filter can answer instead (tests).
 */
function lasery_xtool_request( $url, $max_bytes, $etag = '' ) {
	$pre = apply_filters( 'lasery_xtool_pre_request', null, $url, $etag );
	if ( null !== $pre ) {
		return $pre;
	}
	$etag     = lasery_xtool_valid_etag( $etag );
	$fallback = static function () use ( $url, $max_bytes, $etag ) {
		$headers = array( 'Accept-Encoding' => 'identity' );
		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}
		$response = wp_safe_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'limit_response_size' => $max_bytes + 1, 'decompress' => false, 'headers' => $headers ) );
		// The cap above is only safe for an uncompressed body.
		$encoding = is_wp_error( $response ) ? '' : wp_remote_retrieve_header( $response, 'content-encoding' );
		return is_string( $encoding ) && ( '' === $encoding || 'identity' === strtolower( trim( $encoding ) ) ) ? $response : new WP_Error( 'lasery_xtool_request', 'compressed response' );
	};
	if ( ! function_exists( 'proc_open' ) ) {
		return $fallback();
	}
	$headers_file = tempnam( get_temp_dir(), 'lasery-h' );
	$body_file    = tempnam( get_temp_dir(), 'lasery-b' );
	if ( false === $headers_file || false === $body_file ) {
		return $fallback();
	}
	$command = array( 'curl', '--silent', '--compressed', '--proto', '=https', '--max-time', '30', '--max-filesize', (string) ( $max_bytes + 1 ), '--dump-header', $headers_file, '--output', $body_file );
	if ( '' !== $etag ) {
		array_push( $command, '--header', 'If-None-Match: ' . $etag );
	}
	$command[] = $url;
	$process   = proc_open( $command, array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes );
	$exit      = is_resource( $process ) ? proc_close( $process ) : 127;
	// The cap above counts bytes on the wire; the decoded body must fit too.
	$size = (int) filesize( $body_file );
	$head = (string) file_get_contents( $headers_file );
	$body = $size <= $max_bytes ? (string) file_get_contents( $body_file ) : '';
	unlink( $headers_file );
	unlink( $body_file );
	if ( 127 === $exit ) {
		return $fallback();
	}
	// The last header block belongs to the final response.
	$blocks = preg_split( '/\r?\n\r?\n/', trim( $head ) );
	$lines  = preg_split( '/\r?\n/', (string) end( $blocks ) );
	if ( 0 !== $exit || $size > $max_bytes || ! preg_match( '~^HTTP/\S+ (\d{3})~', (string) $lines[0], $status ) ) {
		return new WP_Error( 'lasery_xtool_request', 'curl exit ' . $exit );
	}
	$headers = array();
	foreach ( array_slice( $lines, 1 ) as $line ) {
		$parts = explode( ':', $line, 2 );
		if ( 2 === count( $parts ) ) {
			$headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
		}
	}
	return array( 'headers' => $headers, 'body' => $body, 'response' => array( 'code' => (int) $status[1], 'message' => '' ) );
}

/** The ETag if it is a plain quoted token (it goes into a request header), otherwise ''. */
function lasery_xtool_valid_etag( $etag ) {
	return is_string( $etag ) && preg_match( '~^(?:W/)?"[A-Za-z0-9:_.-]{1,200}"$~D', $etag ) ? $etag : '';
}

/**
 * GET from the source. A short HTTP 429 (Retry-After within two minutes) is waited out and retried
 * while $deadline allows; a longer one pauses fetching and returns WP_Error( 'paused' ).
 */
function lasery_xtool_source_get( $url, $max_bytes, $deadline, $etag = '' ) {
	for ( $attempt = 1; ; ++$attempt ) {
		$response = lasery_xtool_request( $url, $max_bytes, $etag );
		if ( is_wp_error( $response ) || 429 !== wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}
		$wait = max( 5, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ) + 1;
		if ( $attempt >= 4 || $wait > 120 || time() + $wait > $deadline ) {
			lasery_xtool_pause_fetching( $response );
			return new WP_Error( 'paused' );
		}
		sleep( $wait );
	}
}

/**
 * The currency of the source's storefront (meta.json). It practically never changes, so it is
 * fetched once a day; a failed refresh keeps the last known value. Returns WP_Error( 'paused' ) when
 * the source asked to be left alone.
 */
function lasery_xtool_currency( $deadline ) {
	$known = get_option( 'lasery_xtool_currency' );
	$known = is_array( $known ) && isset( $known['code'], $known['at'] ) && is_string( $known['code'] ) ? $known : array( 'code' => '', 'at' => 0 );
	if ( '' !== $known['code'] && (int) $known['at'] > time() - DAY_IN_SECONDS ) {
		return $known['code'];
	}
	$response = lasery_xtool_source_get( LASERY_XTOOL_SOURCE_URL . '/meta.json', 32768, $deadline );
	if ( is_wp_error( $response ) && 'paused' === $response->get_error_code() ) {
		return $response;
	}
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$meta = json_decode( wp_remote_retrieve_body( $response ) );
		if ( is_object( $meta ) && isset( $meta->currency ) && is_string( $meta->currency ) && preg_match( '/^[A-Z]{3}$/D', $meta->currency ) ) {
			$known = array( 'code' => $meta->currency, 'at' => time() );
			update_option( 'lasery_xtool_currency', $known, false );
		}
	}
	return $known['code'];
}

/**
 * Walk products.json page by page. $handle( $raw ) receives the products of every page that has
 * changed since $known (page => array( 'etag', 'ids', 'count' ) of the previous walk) and returns false
 * to reject the page; an unchanged page answers 304 and is carried over. Returns array( 'pages' => the
 * same structure for this walk, 'complete' => whether the last page was reached ) or a WP_Error
 * ('paused', 'network' or 'format').
 */
function lasery_xtool_walk_catalog( $known, $deadline, $handle ) {
	$pages = array();
	for ( $page = 1; $page <= LASERY_XTOOL_MAX_PAGES; ++$page ) {
		if ( $page > 1 ) {
			// A short pause between pages keeps the requests within the storefront's rate limit.
			sleep( 1 );
		}
		$url      = add_query_arg( array( 'limit' => LASERY_XTOOL_PAGE_SIZE, 'page' => $page ), LASERY_XTOOL_SOURCE_URL . '/products.json' );
		$etag     = isset( $known[ $page ]['etag'] ) ? $known[ $page ]['etag'] : '';
		$response = lasery_xtool_source_get( $url, LASERY_XTOOL_MAX_BYTES, $deadline, $etag );
		if ( is_wp_error( $response ) ) {
			return 'paused' === $response->get_error_code() ? $response : new WP_Error( 'network' );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 304 === $code && '' !== $etag ) {
			$pages[ $page ] = $known[ $page ];
		} elseif ( 200 === $code ) {
			$raw = lasery_xtool_decode_products( wp_remote_retrieve_body( $response ), LASERY_XTOOL_PAGE_SIZE );
			if ( false === $raw || ! $handle( $raw ) ) {
				return new WP_Error( 'format' );
			}
			$pages[ $page ] = array(
				'etag'  => lasery_xtool_valid_etag( wp_remote_retrieve_header( $response, 'etag' ) ),
				'ids'   => array_values( array_filter( array_map( 'lasery_xtool_raw_product_id', $raw ), 'strlen' ) ),
				'count' => count( $raw ),
			);
		} else {
			return new WP_Error( 'network' );
		}
		if ( $pages[ $page ]['count'] < LASERY_XTOOL_PAGE_SIZE ) {
			return array( 'pages' => $pages, 'complete' => true );
		}
	}
	return array( 'pages' => $pages, 'complete' => false );
}

/** Every product of the source for the draft import: all pages, each product valid and unique. */
function lasery_xtool_fetch_source() {
	if ( lasery_xtool_fetching_paused() ) {
		return new WP_Error( 'paused' );
	}
	// Short rate limits are waited out, but a run stays well inside the 15-minute import lock.
	$deadline = time() + 420;
	$currency = lasery_xtool_currency( $deadline );
	if ( is_wp_error( $currency ) ) {
		return $currency;
	}
	$source   = array( 'currency' => $currency, 'fetched_at' => time() );
	$products = array();
	$walk     = lasery_xtool_walk_catalog(
		array(),
		$deadline,
		static function ( $raw ) use ( &$products, $source ) {
			foreach ( $raw as $entry ) {
				$product = lasery_xtool_parse_product( $entry );
				if ( false === $product || isset( $products[ $product['id'] ] ) ) {
					return false;
				}
				$products[ $product['id'] ] = $product + $source;
			}
			return true;
		}
	);
	if ( is_wp_error( $walk ) ) {
		return $walk;
	}
	return array( 'products' => array_values( $products ), 'result' => $walk['complete'] ? 'complete' : 'limit' );
}

// The system cron is the main trigger. WP-Cron backs it up, so a stopped or misconfigured system
// cron cannot leave every offer to expire after a day.
add_action( 'init', 'lasery_xtool_schedule_sync' );
function lasery_xtool_schedule_sync() {
	foreach ( array( 'lasery_xtool_sync_existing', 'lasery_xtool_sync_images' ) as $hook ) {
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', $hook );
		}
	}
}

/** Try again after a failed fetch: in ten minutes, or when a requested pause ends. */
function lasery_xtool_schedule_sync_retry() {
	wp_schedule_single_event( max( time() + 10 * MINUTE_IN_SECONDS, (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) ), 'lasery_xtool_sync_existing' );
}

/** Tell the administrator when offers have not been refreshed for six hours, at most once a day. */
function lasery_xtool_alert_when_stale() {
	$ok = (int) get_option( 'lasery_xtool_last_ok' );
	if ( ! $ok ) {
		update_option( 'lasery_xtool_last_ok', time(), false );
		return;
	}
	if ( time() - $ok < 6 * HOUR_IN_SECONDS || time() - (int) get_option( 'lasery_xtool_last_alert' ) < DAY_IN_SECONDS ) {
		return;
	}
	update_option( 'lasery_xtool_last_alert', time(), false );
	$last = get_option( 'lasery_xtool_last_sync' );
	lasery_xtool_notify(
		'laseryxtool.pl: oferty nie są aktualizowane',
		sprintf(
			"Od %s synchronizacja nie pobrała katalogu xTool (ostatni wynik: %s). Po 24 godzinach od ostatniej udanej synchronizacji oferty znikną ze stron produktów.\n\nSprawdź Produkty xTool → Import (ostatnia synchronizacja i ewentualne wstrzymanie po odpowiedzi 429) oraz zadania cron na hostingu.",
			wp_date( 'Y-m-d H:i', $ok ),
			is_array( $last ) && isset( $last['result'] ) && is_string( $last['result'] ) ? $last['result'] : 'brak'
		)
	);
}

/** The products with a source ID, grouped by it, with everything the sync needs in one query. */
function lasery_xtool_sync_targets() {
	global $wpdb;
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID AS id, p.post_status AS status, s.meta_value AS source, h.meta_value AS hash, u.meta_value AS updated, m.meta_value AS missing, a.meta_value AS hidden
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = '_lasery_source_hash'
			LEFT JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = '_lasery_source_updated_at'
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lasery_missing_runs'
			LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_lasery_auto_hidden'
			WHERE p.post_type = %s AND p.post_status IN ('publish', 'future', 'draft', 'pending', 'private')",
			LASERY_XTOOL_SOURCE_ID,
			LASERY_XTOOL_PRODUCT_TYPE
		)
	);
	$targets = array();
	foreach ( $rows as $row ) {
		if ( preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $row->source ) ) {
			$targets[ $row->source ][] = $row;
		}
	}
	return $targets;
}

/** Content hash of an offer: what a visitor can see of the source data except the time it was read. */
function lasery_xtool_source_hash( $product ) {
	return md5( (string) wp_json_encode( array( isset( $product['currency'] ) ? $product['currency'] : '', $product['variants'], isset( $product['image_urls'] ) ? $product['image_urls'] : null ) ) );
}

/** Confirm many unchanged offers with one query. Direct SQL, so the meta cache is cleared by hand. */
function lasery_xtool_confirm_offers( $post_ids, $time ) {
	global $wpdb;
	foreach ( array_chunk( $post_ids, 200 ) as $chunk ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_lasery_source_updated_at' AND post_id IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
				array_merge( array( (string) $time ), $chunk )
			)
		);
	}
	foreach ( $post_ids as $post_id ) {
		wp_cache_delete( $post_id, 'post_meta' );
	}
}

/** The source lists the product again: forget its absence and show it if only the sync hid it. */
function lasery_xtool_mark_present( $row ) {
	if ( null !== $row->missing ) {
		delete_post_meta( $row->id, '_lasery_missing_runs' );
	}
	if ( null !== $row->hidden ) {
		delete_post_meta( $row->id, '_lasery_auto_hidden' );
		if ( 'draft' === $row->status ) {
			wp_update_post( array( 'ID' => $row->id, 'post_status' => 'publish' ) );
		}
		lasery_xtool_schedule_cache_purge();
	}
}

/**
 * A complete walk did not list the product. Its offer stops being fresh at once; after
 * LASERY_XTOOL_MISSING_RUNS such walks in a row the product is hidden (not deleted), and shown again
 * by lasery_xtool_mark_present() if the source lists it again.
 */
function lasery_xtool_mark_absent( $row ) {
	$runs = (int) $row->missing + 1;
	if ( '' !== (string) $row->updated ) {
		update_post_meta( $row->id, '_lasery_source_updated_at', '' );
		lasery_xtool_schedule_cache_purge();
	}
	if ( $runs <= LASERY_XTOOL_MISSING_RUNS ) {
		update_post_meta( $row->id, '_lasery_missing_runs', $runs );
	}
	if ( $runs >= LASERY_XTOOL_MISSING_RUNS && 'publish' === $row->status ) {
		update_post_meta( $row->id, '_lasery_auto_hidden', '1' );
		wp_update_post( array( 'ID' => $row->id, 'post_status' => 'draft' ) );
		lasery_xtool_schedule_cache_purge();
	}
}

/**
 * Called hourly by the system cron and WP-Cron; refreshes every existing product and never creates
 * drafts. The walk is conditional: pages that did not change since the last complete walk answer 304,
 * so a quiet hour costs a few empty responses and two writes; only products whose offer changed are
 * saved. A run shortly after one that reached the source is skipped unless $force is set, so the two
 * triggers do not walk the catalogue twice an hour.
 */
add_action( 'lasery_xtool_sync_existing', 'lasery_xtool_sync_existing' );
function lasery_xtool_sync_existing( $force = false ) {
	$last = get_option( 'lasery_xtool_last_sync' );
	if ( true !== $force && is_array( $last ) && isset( $last['time'], $last['result'], $last['confirmed'] ) && 'locklost' !== $last['result'] && (int) $last['time'] > time() - 30 * MINUTE_IN_SECONDS ) {
		return;
	}
	// A run from WP-Cron is a web request with the default time limit.
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 600 );
	}
	if ( ! lasery_xtool_lock_import() ) {
		// Another import, or the image sync, holds the lock; this one is needed within minutes.
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'lasery_xtool_sync_existing' );
		return;
	}
	$targets = lasery_xtool_sync_targets();
	$failure = static function ( $code ) {
		// A failed run does not invalidate observed offers; their 24-hour freshness limit still applies.
		update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => $code ), false );
		lasery_xtool_schedule_sync_retry();
		lasery_xtool_alert_when_stale();
		lasery_xtool_release_import_lock();
	};
	if ( ! $targets ) {
		lasery_xtool_release_import_lock();
		return;
	}
	if ( lasery_xtool_fetching_paused() ) {
		$failure( 'paused' );
		return;
	}
	$deadline = time() + 420;
	$currency = lasery_xtool_currency( $deadline );
	if ( is_wp_error( $currency ) ) {
		$failure( $currency->get_error_code() );
		return;
	}
	// A 304 page confirms the stored offers, so pages are requested conditionally only if every product
	// that the last walk listed already holds its data (a product added since then needs a full walk).
	$state         = get_option( LASERY_XTOOL_STATE );
	$known         = is_array( $state ) && isset( $state['pages'] ) && is_array( $state['pages'] ) ? $state['pages'] : array();
	$conditional   = (bool) $known;
	$listed_before = array();
	foreach ( $known as $page ) {
		$listed_before += array_fill_keys( $page['ids'], true );
	}
	foreach ( $targets as $id => $rows ) {
		foreach ( $rows as $row ) {
			$conditional = $conditional && ( ! isset( $listed_before[ $id ] ) || ! empty( $row->hash ) );
		}
	}
	$source   = array( 'currency' => $currency, 'fetched_at' => time() );
	$data     = array(); // Offers of the products on pages that changed.
	$listed   = array(); // Products on pages that changed, valid or not.
	$walk     = lasery_xtool_walk_catalog(
		$conditional ? $known : array(),
		$deadline,
		static function ( $raw ) use ( $targets, &$data, &$listed ) {
			// Only the site's products are validated, so an unrelated malformed product
			// or one shifted between pages while xTool edits the catalogue cannot stop the sync.
			foreach ( $raw as $entry ) {
				$id = lasery_xtool_raw_product_id( $entry );
				if ( isset( $targets[ $id ] ) ) {
					$listed[ $id ] = true;
					$product       = lasery_xtool_parse_product( $entry, false );
					// A snapshot without variants cannot confirm an offer.
					if ( $product && array_key_exists( 'variants', $product ) ) {
						$data[ $id ] = $product;
					}
				}
			}
			return true;
		}
	);
	if ( is_wp_error( $walk ) ) {
		$failure( $walk->get_error_code() );
		return;
	}
	$present = array();
	foreach ( $walk['pages'] as $page ) {
		$present += array_fill_keys( $page['ids'], true );
	}
	$now       = time();
	$confirmed = array();
	$updated   = 0;
	$failed    = 0;
	$absent    = array();
	$stale     = array();
	foreach ( $targets as $id => $rows ) {
		foreach ( $rows as $row ) {
			if ( ! isset( $present[ $id ] ) ) {
				$absent[] = $row;
			} elseif ( isset( $data[ $id ] ) ) {
				$product = array_merge( $data[ $id ], $source );
				if ( lasery_xtool_source_hash( $product ) === $row->hash ) {
					$confirmed[] = $row;
				} elseif ( ! lasery_xtool_refresh_import_lock() ) {
					update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'locklost', 'updated' => $updated, 'failed' => $failed ), false );
					lasery_xtool_release_import_lock();
					return;
				} elseif ( lasery_xtool_save_source_data( $row->id, $product ) ) {
					++$updated;
					lasery_xtool_mark_present( $row );
				} else {
					++$failed;
				}
			} elseif ( isset( $listed[ $id ] ) ) {
				// Listed with unusable data: the offer cannot stay fresh.
				++$failed;
				$stale[] = $row->id;
				if ( '' !== (string) $row->updated ) {
					update_post_meta( $row->id, '_lasery_source_updated_at', '' );
					lasery_xtool_schedule_cache_purge();
				}
			} else {
				// On a page that answered 304: the stored offer is still the current one.
				$confirmed[] = $row;
			}
		}
	}
	foreach ( $confirmed as $row ) {
		// An offer that was not fresh becomes visible again.
		if ( ! lasery_xtool_timestamp_is_fresh( $row->updated ) ) {
			lasery_xtool_schedule_cache_purge();
		}
		lasery_xtool_mark_present( $row );
	}
	lasery_xtool_confirm_offers( wp_list_pluck( $confirmed, 'id' ), $now );
	// A walk that stopped before the last page, or one that lost a large part of the catalogue at once,
	// says nothing reliable about absence.
	$result = 'complete';
	if ( ! $walk['complete'] ) {
		$result = 'limit';
	} elseif ( count( $absent ) > max( 5, count( $targets ) / 4 ) ) {
		$result = 'anomaly';
	} else {
		foreach ( $absent as $row ) {
			lasery_xtool_mark_absent( $row );
		}
	}
	foreach ( $absent as $row ) {
		$stale[] = $row->id;
	}
	if ( $walk['complete'] ) {
		update_option( LASERY_XTOOL_STATE, array( 'pages' => $walk['pages'] ), false );
	}
	update_option( 'lasery_xtool_last_ok', time(), false );
	lasery_xtool_sync_novelties( $currency, $deadline );
	update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => $failed ? 'incomplete' : $result, 'updated' => $updated, 'confirmed' => count( $confirmed ), 'failed' => $failed, 'stale' => $stale ), false );
	lasery_xtool_release_import_lock();
}

function lasery_xtool_save_products( $products, $result ) {
	$created = 0;
	$updated = 0;
	$failed  = 0;
	foreach ( $products as $product ) {
		if ( ! lasery_xtool_refresh_import_lock() ) {
			lasery_xtool_redirect_import( array( 'result' => 'locklost' ) );
		}
		$existing = get_posts(
			array(
				'post_type'      => LASERY_XTOOL_PRODUCT_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
				'meta_key'       => LASERY_XTOOL_SOURCE_ID,
				'meta_value'     => $product['id'],
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( $existing ) {
			if ( lasery_xtool_save_source_data( $existing[0], $product ) ) {
				++$updated;
			} else {
				++$failed;
			}
			continue;
		}
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => LASERY_XTOOL_PRODUCT_TYPE,
					'post_status'  => 'draft',
					'post_title'   => $product['title'],
					'post_content' => lasery_xtool_initial_product_content( $product ),
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			++$failed;
			continue;
		}
		if ( ! add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, $product['id'], true ) ) {
			wp_delete_post( $post_id, true );
			++$failed;
			continue;
		}
		++$created;
		if ( ! lasery_xtool_save_source_data( $post_id, $product ) ) {
			++$failed;
		}
	}
	lasery_xtool_redirect_import( compact( 'created', 'updated', 'failed' ) + array( 'result' => $result ) );
}

/** Source data never overwrites edited content, prices or featured images. */
function lasery_xtool_save_source_data( $post_id, $product ) {
	$before = lasery_xtool_public_product_state( $post_id );
	try {
		$fields = array( 'variants' => '_lasery_source_variants', 'image_url' => '_lasery_source_image_url', 'image_urls' => '_lasery_source_image_urls' );
		if ( array_key_exists( 'variants', $product ) ) {
			// Files have no verified storefront context; never reuse an old currency.
			$product['currency'] = isset( $product['currency'] ) ? $product['currency'] : '';
			$product['fetched_at'] = isset( $product['fetched_at'] ) ? (string) $product['fetched_at'] : '';
			// Hide a changing offer until all its fields are saved. For an identical
			// offer, preserve the old timestamp while refreshing it: concurrent page
			// loads must not cache a spurious stale message during an ordinary sync.
			if ( get_post_meta( $post_id, '_lasery_source_variants', true ) !== $product['variants'] || get_post_meta( $post_id, '_lasery_source_currency', true ) !== $product['currency'] ) {
				update_post_meta( $post_id, '_lasery_source_updated_at', '' );
				if ( '' !== get_post_meta( $post_id, '_lasery_source_updated_at', true ) ) {
					return false;
				}
			}
			$fields['currency'] = '_lasery_source_currency';
			$fields['fetched_at'] = '_lasery_source_updated_at';
		}
		$new_images = array_key_exists( 'image_urls', $product ) && get_post_meta( $post_id, '_lasery_source_image_urls', true ) !== $product['image_urls'];
		foreach ( $fields as $field => $key ) {
			if ( ! array_key_exists( $field, $product ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, wp_slash( $product[ $field ] ) );
			if ( get_post_meta( $post_id, $key, true ) !== $product[ $field ] ) {
				return false;
			}
		}
		if ( $new_images ) {
			lasery_xtool_queue_images( $post_id );
		}
		// Last, so that a half-saved offer never matches its hash and is saved again by the next sync.
		if ( array_key_exists( 'variants', $product ) ) {
			update_post_meta( $post_id, '_lasery_source_hash', lasery_xtool_source_hash( $product ) );
		}
		return true;
	} finally {
		lasery_xtool_product_state_changed( $post_id, $before );
	}
}
