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
const LASERY_XTOOL_MAX_BYTES    = 2 * 1024 * 1024;
const LASERY_XTOOL_MAX_PRODUCTS = 500;
// Raise when lasery_xtool_install() has new work for existing sites.
const LASERY_XTOOL_SCHEMA       = '3';

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
		wp_safe_redirect( $target, 301 );
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
	if ( LASERY_XTOOL_SCHEMA !== get_option( 'lasery_xtool_schema' ) ) {
		lasery_xtool_install();
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
	// The theme renders the blog listing on this page (page-blog.php).
	if ( ! get_page_by_path( 'blog' ) ) {
		wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Blog', 'post_name' => 'blog', 'comment_status' => 'closed' ) );
	}
	flush_rewrite_rules();
	update_option( 'lasery_xtool_schema', LASERY_XTOOL_SCHEMA );
}

register_deactivation_hook( __FILE__, 'lasery_xtool_deactivate' );
function lasery_xtool_deactivate() {
	if ( post_type_exists( LASERY_XTOOL_PRODUCT_TYPE ) ) {
		unregister_post_type( LASERY_XTOOL_PRODUCT_TYPE );
	}
	flush_rewrite_rules();
}

require_once __DIR__ . '/product-kind.php';
require_once __DIR__ . '/product-badges.php';
require_once __DIR__ . '/order-form.php';
require_once __DIR__ . '/image-sync.php';
require_once __DIR__ . '/featured-products.php';
require_once __DIR__ . '/product-translations.php';
require_once __DIR__ . '/privacy-settings.php';
require_once __DIR__ . '/handle-sync.php';
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
		'file'     => 'Nie udało się odczytać pliku JSON. Limit pliku to 2 MB.',
		'format'   => 'Nieprawidłowy format danych. Sprawdź wymagania pliku w dokumentacji wtyczki.',
		'network'  => 'Nie udało się pobrać produktów. Sprawdź dostępność źródła i spróbuj ponownie.',
		'paused'   => 'Pobieranie jest wstrzymane po odpowiedzi 429. Wznów je dopiero po ustaniu ograniczenia.',
		'locked'   => 'Inny import jest w trakcie. Spróbuj ponownie później.',
		'locklost' => 'Import przerwany: utracono blokadę. Sprawdź szkice i ponów import.',
		'complete' => 'Import zakończony.',
		'batch'    => 'Zakończono partię synchronizacji. Pozostałe produkty zostaną sprawdzone w kolejnych przebiegach.',
		'limit'    => 'Import zakończony. Osiągnięto limit pobierania — w źródle mogą być dalsze produkty.',
		'incomplete' => 'Nie udało się zsynchronizować części produktów. Sprawdź ich dane źródłowe i ponów próbę.',
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
		<?php endif; ?>
		<?php if ( get_option( 'lasery_xtool_fetch_paused' ) ) : ?>
			<p>Pobieranie wstrzymano po odpowiedzi 429. Wznów dopiero po ustaniu ograniczenia źródła.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lasery_xtool_resume_fetch">
				<?php wp_nonce_field( 'lasery_xtool_resume_fetch' ); ?>
				<?php submit_button( 'Wznów pobieranie' ); ?>
			</form>
		<?php endif; ?>
		<p>Ręczne pobranie z xtool.eu obejmuje maksymalnie 500 produktów. Nowe wpisy zostaną zapisane jako szkice.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_fetch">
			<?php wp_nonce_field( 'lasery_xtool_fetch' ); ?>
			<?php submit_button( 'Pobierz do szkiców' ); ?>
		</form>
	</div>
	<?php
}

/** Validate the complete document before creating any drafts. */
function lasery_xtool_parse_products( $json ) {
	$data = json_decode( $json, false, 512, JSON_BIGINT_AS_STRING );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $data ) || ! isset( $data->products ) || ! is_array( $data->products ) || count( $data->products ) > LASERY_XTOOL_MAX_PRODUCTS ) {
		return false;
	}
	$products = array();
	$seen     = array();
	foreach ( $data->products as $product ) {
		if ( ! is_object( $product ) || ! isset( $product->id, $product->title ) || ( ! is_int( $product->id ) && ! is_string( $product->id ) ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $product->id ) || ! is_string( $product->title ) || ( isset( $product->body_html ) && ! is_string( $product->body_html ) ) ) {
			return false;
		}
		$id    = (string) $product->id;
		$title = sanitize_text_field( $product->title );
		if ( '' === $title || isset( $seen[ $id ] ) ) {
			return false;
		}
		$seen[ $id ] = true;
		$item = array(
			'id'      => $id,
			'title'   => $title,
			'content' => isset( $product->body_html ) ? wp_kses_post( $product->body_html ) : '',
		);
		if ( isset( $product->handle ) && lasery_xtool_valid_source_handle( $product->handle ) ) {
			$item['handle'] = $product->handle;
		}
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
		$products[] = $item;
	}
	return $products;
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
	delete_option( 'lasery_xtool_fetch_paused' );
	lasery_xtool_redirect_import( array() );
}

add_action( 'admin_post_lasery_xtool_fetch', 'lasery_xtool_handle_fetch' );
function lasery_xtool_handle_fetch() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'lasery-xtool' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'lasery_xtool_fetch' );
	if ( ! lasery_xtool_lock_import() ) {
		lasery_xtool_redirect_import( array( 'result' => 'locked' ) );
	}
	$fetched = lasery_xtool_fetch_source();
	if ( is_wp_error( $fetched ) ) {
		lasery_xtool_redirect_import( array( 'result' => $fetched->get_error_code() ) );
	}
	lasery_xtool_save_products( $fetched['products'], $fetched['result'] );
}

/** Refuse compressed bodies, which could exceed the limit when decompressed. */
function lasery_xtool_identity_response( $response ) {
	$encoding = wp_remote_retrieve_header( $response, 'content-encoding' );
	return is_string( $encoding ) && ( '' === $encoding || 'identity' === strtolower( trim( $encoding ) ) );
}

/** Return a complete bounded snapshot, without writes to product records. */
function lasery_xtool_fetch_source( $target_ids = null, $target_handles = array() ) {
	if ( get_option( 'lasery_xtool_fetch_paused' ) ) {
		return new WP_Error( 'paused' );
	}
	if ( is_array( $target_ids ) && ! $target_ids ) {
		return array( 'products' => array(), 'result' => 'complete' );
	}
	$remaining = is_array( $target_ids ) ? array_fill_keys( $target_ids, true ) : null;
	// Shopify's shop metadata describes the currency of the same storefront.
	// A missing or invalid response leaves prices without a currency.
	$currency = '';
	$meta_response = wp_safe_remote_get(
		'https://www.xtool.eu/meta.json',
		array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 32769, 'decompress' => false, 'headers' => array( 'Accept-Encoding' => 'identity' ) )
	);
	if ( ! is_wp_error( $meta_response ) && 429 === wp_remote_retrieve_response_code( $meta_response ) ) {
		update_option( 'lasery_xtool_fetch_paused', 1, false );
		return new WP_Error( 'paused' );
	}
	if ( ! is_wp_error( $meta_response ) && 200 === wp_remote_retrieve_response_code( $meta_response ) && lasery_xtool_identity_response( $meta_response ) ) {
		$meta_body = wp_remote_retrieve_body( $meta_response );
		$meta = strlen( $meta_body ) <= 32768 ? json_decode( $meta_body ) : null;
		if ( is_object( $meta ) && isset( $meta->currency ) && is_string( $meta->currency ) && preg_match( '/^[A-Z]{3}$/D', $meta->currency ) ) {
			$currency = $meta->currency;
		}
	}
	$products = array();
	$seen     = array();
	$result   = 'complete';
	$handle_errors = array();
	$fetched_at = time();
	if ( null !== $remaining && is_array( $target_handles ) ) {
		foreach ( $target_handles as $id => $handle ) {
			$id = (string) $id;
			if ( ! isset( $remaining[ $id ] ) || ! lasery_xtool_valid_source_handle( $handle ) ) {
				return new WP_Error( 'format' );
			}
			$product = lasery_xtool_fetch_known_handle( $id, $handle, $currency, $fetched_at );
			if ( is_wp_error( $product ) ) {
				if ( 'paused' === $product->get_error_code() ) {
					return $product;
				}
				$handle_errors[ $id ] = $product->get_error_code();
				unset( $remaining[ $id ] );
				continue;
			}
			$products[] = $product;
			unset( $remaining[ $id ] );
		}
		if ( ! $remaining ) {
			return array( 'products' => $products, 'result' => $handle_errors ? 'incomplete' : 'complete', 'handle_errors' => $handle_errors );
		}
	}
	for ( $page = 1; $page <= 5; ++$page ) {
		$url = add_query_arg( array( 'limit' => 100, 'page' => $page ), 'https://www.xtool.eu/products.json' );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => LASERY_XTOOL_MAX_BYTES + 1,
				'decompress' => false,
				'headers' => array( 'Accept-Encoding' => 'identity' ),
			)
		);
		if ( ! is_wp_error( $response ) && 429 === wp_remote_retrieve_response_code( $response ) ) {
			update_option( 'lasery_xtool_fetch_paused', 1, false );
			return new WP_Error( 'paused' );
		}
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! lasery_xtool_identity_response( $response ) ) {
			return new WP_Error( 'network' );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > LASERY_XTOOL_MAX_BYTES ) {
			return new WP_Error( 'network' );
		}
		$page_products = lasery_xtool_parse_products( $body );
		if ( false === $page_products || count( $page_products ) > 100 ) {
			return new WP_Error( 'format' );
		}
		foreach ( $page_products as $product ) {
			if ( isset( $seen[ $product['id'] ] ) ) {
				return new WP_Error( 'format' );
			}
			$seen[ $product['id'] ] = true;
			if ( null !== $remaining && ! isset( $remaining[ $product['id'] ] ) ) {
				continue;
			}
			$product['currency'] = $currency;
			$product['fetched_at'] = $fetched_at;
			$products[] = $product;
			if ( null !== $remaining ) {
				unset( $remaining[ $product['id'] ] );
			}
		}
		if ( null !== $remaining && ! $remaining ) {
			break;
		}
		if ( count( $page_products ) < 100 ) {
			$result = null !== $remaining ? 'incomplete' : 'complete';
			break;
		}
		if ( 5 === $page ) {
			$result = null !== $remaining ? 'incomplete' : 'limit';
		}
	}
	return array( 'products' => $products, 'result' => $handle_errors ? 'incomplete' : $result, 'handle_errors' => $handle_errors );
}


/** Called by a configured system scheduler; never creates product drafts. */
add_action( 'lasery_xtool_sync_existing', 'lasery_xtool_sync_existing' );
function lasery_xtool_sync_existing() {
	if ( ! lasery_xtool_lock_import() ) {
		update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'locked' ), false );
		return;
	}
	$targets = array();
	$existing = get_posts( array(
		'post_type' => LASERY_XTOOL_PRODUCT_TYPE,
		'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'meta_key' => LASERY_XTOOL_SOURCE_ID,
		'fields' => 'ids',
		'posts_per_page' => -1,
		'no_found_rows' => true,
	) );
	foreach ( $existing as $post_id ) {
		$id = get_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, true );
		if ( is_string( $id ) && preg_match( '/^[1-9][0-9]{0,19}$/D', $id ) ) {
			$targets[ $id ][] = $post_id;
		}
	}
	$handles = array();
	$unresolved = 0;
	foreach ( $targets as $id => $post_ids ) {
		$source_handles = array();
		$oldest = PHP_INT_MAX;
		$retry_after = 0;
		foreach ( $post_ids as $post_id ) {
			$handle = get_post_meta( $post_id, '_lasery_source_handle', true );
			if ( lasery_xtool_valid_source_handle( $handle ) ) {
				$source_handles[ $handle ] = true;
			}
			$oldest = min( $oldest, (int) get_post_meta( $post_id, '_lasery_source_updated_at', true ) );
			$retry_after = max( $retry_after, (int) get_post_meta( $post_id, '_lasery_handle_retry_after', true ) );
		}
		if ( 1 === count( $source_handles ) ) {
			if ( $retry_after > time() ) {
				unset( $targets[ $id ] );
				++$unresolved;
			} else {
				$handles[ $id ] = array( 'handle' => key( $source_handles ), 'oldest' => $oldest );
			}
		} else {
			foreach ( $post_ids as $post_id ) {
				update_post_meta( $post_id, '_lasery_source_updated_at', '' );
			}
			unset( $targets[ $id ] );
			++$unresolved;
		}
	}
	uasort( $handles, function ( $a, $b ) { return $a['oldest'] <=> $b['oldest']; } );
	// One known handle per run keeps each scheduled batch small (JSON + JS).
	$deferred = array_slice( $handles, 1, null, true );
	foreach ( $deferred as $id => $item ) {
		unset( $targets[ $id ] );
	}
	$handles = array_slice( $handles, 0, 1, true );
	$handles = array_map( function ( $item ) { return $item['handle']; }, $handles );
	$fetched = lasery_xtool_fetch_source( array_keys( $targets ), $handles );
	if ( is_wp_error( $fetched ) ) {
		// A failed batch does not invalidate unrelated observed offers. Their
		// existing freshness limit still applies.
		update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => $fetched->get_error_code() ), false );
		lasery_xtool_release_import_lock();
		return;
	}
	$updated = 0;
	$failed = 0;
	foreach ( $fetched['products'] as $product ) {
		if ( ! lasery_xtool_refresh_import_lock() ) {
			update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'locklost', 'updated' => $updated, 'failed' => $failed ), false );
			lasery_xtool_release_import_lock();
			return;
		}
		foreach ( $targets[ $product['id'] ] as $post_id ) {
			if ( lasery_xtool_save_source_data( $post_id, $product ) ) {
				delete_post_meta( $post_id, '_lasery_handle_retry_after' );
				++$updated;
			} else {
				++$failed;
			}
		}
		unset( $targets[ $product['id'] ] );
	}
	// An absent product must not retain a fresh availability or price.
	foreach ( $targets as $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_lasery_source_updated_at', '' );
			update_post_meta( $post_id, '_lasery_handle_retry_after', (string) ( time() + 6 * HOUR_IN_SECONDS ) );
			++$failed;
		}
	}
	$sync_result = $unresolved || $failed || 'incomplete' === $fetched['result'] ? 'incomplete' : ( $deferred ? 'batch' : $fetched['result'] );
	update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => $sync_result, 'updated' => $updated, 'failed' => $failed ), false );
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
					'post_content' => $product['content'],
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
	$fields = array( 'variants' => '_lasery_source_variants', 'image_url' => '_lasery_source_image_url', 'image_urls' => '_lasery_source_image_urls', 'handle' => '_lasery_source_handle' );
	if ( array_key_exists( 'variants', $product ) ) {
		// Hide the snapshot while any of its fields are being changed. A failed
		// write must never leave new prices paired with an old currency or time.
		update_post_meta( $post_id, '_lasery_source_updated_at', '' );
		if ( '' !== get_post_meta( $post_id, '_lasery_source_updated_at', true ) ) {
			return false;
		}
		// Files have no verified storefront context; never reuse an old currency.
		$product['currency'] = isset( $product['currency'] ) ? $product['currency'] : '';
		$product['fetched_at'] = isset( $product['fetched_at'] ) ? (string) $product['fetched_at'] : '';
		$fields['currency'] = '_lasery_source_currency';
		$fields['fetched_at'] = '_lasery_source_updated_at';
	}
	foreach ( $fields as $field => $key ) {
		if ( ! array_key_exists( $field, $product ) ) {
			continue;
		}
		update_post_meta( $post_id, $key, wp_slash( $product[ $field ] ) );
		if ( get_post_meta( $post_id, $key, true ) !== $product[ $field ] ) {
			return false;
		}
	}
	return true;
}

/** Apply a verified snapshot to specific published products without network access. */
function lasery_xtool_apply_existing_snapshot( $json, $fetched_at, $currency, $source_ids ) {
	if ( ! is_string( $json ) || strlen( $json ) > LASERY_XTOOL_MAX_BYTES || ! is_int( $fetched_at ) || $fetched_at < 1 || $fetched_at > time() || ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! is_array( $source_ids ) || ! $source_ids || count( $source_ids ) > 50 ) {
		return new WP_Error( 'snapshot_arguments' );
	}
	$wanted = array();
	foreach ( $source_ids as $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', $id ) || isset( $wanted[ $id ] ) ) {
			return new WP_Error( 'snapshot_arguments' );
		}
		$wanted[ $id ] = true;
	}
	$products = lasery_xtool_parse_products( $json );
	if ( false === $products ) {
		return new WP_Error( 'format' );
	}
	$matches = array();
	foreach ( $products as $product ) {
		if ( isset( $wanted[ $product['id'] ] ) ) {
			$matches[ $product['id'] ] = $product;
		}
	}
	if ( count( $matches ) !== count( $wanted ) ) {
		return new WP_Error( 'incomplete' );
	}
	if ( ! lasery_xtool_lock_import() ) {
		return new WP_Error( 'locked' );
	}
	try {
		$posts = array();
		foreach ( $wanted as $id => $unused ) {
			$found = get_posts( array(
				'post_type' => LASERY_XTOOL_PRODUCT_TYPE,
				'post_status' => 'publish',
				'meta_key' => LASERY_XTOOL_SOURCE_ID,
				'meta_value' => (string) $id,
				'fields' => 'ids',
				'posts_per_page' => 2,
				'no_found_rows' => true,
			) );
			if ( 1 !== count( $found ) ) {
				return new WP_Error( 'snapshot_target' );
			}
			$posts[ $id ] = $found[0];
		}
		$updated = 0;
		foreach ( $matches as $id => $product ) {
			if ( ! lasery_xtool_refresh_import_lock() ) {
				return new WP_Error( 'locklost' );
			}
			$product['currency'] = $currency;
			$product['fetched_at'] = $fetched_at;
			if ( ! lasery_xtool_save_source_data( $posts[ $id ], $product ) ) {
				return new WP_Error( 'snapshot_write', '', array( 'updated' => $updated ) );
			}
			++$updated;
		}
		return $updated;
	} finally {
		lasery_xtool_release_import_lock();
	}
}
