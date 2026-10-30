<?php
/** Editor selection for the homepage carousel. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lasery_xtool_featured_menu' );
function lasery_xtool_featured_menu() {
	add_submenu_page( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE, 'Wyróżnione produkty', 'Wyróżnione produkty', 'manage_options', 'lasery-xtool-featured', 'lasery_xtool_featured_page' );
}

function lasery_xtool_featured_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	$selected = get_option( 'lasery_xtool_featured_products', array() );
	$selected = is_array( $selected ) ? array_values( $selected ) : array();
	$products = get_posts( array(
		'post_type' => LASERY_XTOOL_PRODUCT_TYPE,
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'title',
		'order' => 'ASC',
		'no_found_rows' => true,
	) );
	$result = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
	?>
	<div class="wrap">
		<h1>Wyróżnione produkty</h1>
		<p>Wybierz maksymalnie cztery opublikowane produkty, w kolejności wyświetlania na stronie głównej. Aby wrócić do automatycznego wyboru, pozostaw wszystkie pola puste.</p>
		<?php if ( 'saved' === $result ) : ?><div class="notice notice-success"><p>Zapisano wybór produktów.</p></div><?php endif; ?>
		<?php if ( 'invalid' === $result ) : ?><div class="notice notice-error"><p>Nie zapisano wyboru. Wybierz różne, opublikowane produkty.</p></div><?php endif; ?>
		<?php if ( 'save_failed' === $result ) : ?><div class="notice notice-error"><p>Nie udało się zapisać wyboru. Spróbuj ponownie.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_save_featured">
			<?php wp_nonce_field( 'lasery_xtool_save_featured' ); ?>
			<?php for ( $index = 0; $index < 4; ++$index ) : ?>
				<p><label for="lasery-featured-<?php echo esc_attr( $index ); ?>">Produkt <?php echo esc_html( (string) ( $index + 1 ) ); ?></label><br>
				<select id="lasery-featured-<?php echo esc_attr( $index ); ?>" name="featured[]">
					<option value="">— Brak —</option>
					<?php foreach ( $products as $product ) : ?>
						<option value="<?php echo esc_attr( $product->ID ); ?>" <?php selected( isset( $selected[ $index ] ) ? $selected[ $index ] : 0, $product->ID ); ?>><?php echo esc_html( get_the_title( $product ) ); ?></option>
					<?php endforeach; ?>
				</select></p>
			<?php endfor; ?>
			<?php submit_button( 'Zapisz wybór' ); ?>
		</form>
	</div>
	<?php
}

/** Reject the whole selection if any ID is duplicated or no longer published. */
function lasery_xtool_validate_featured_products( $input ) {
	if ( ! is_array( $input ) || count( $input ) !== 4 ) {
		return false;
	}
	$ids = array();
	foreach ( $input as $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		if ( '' === $value ) {
			continue;
		}
		if ( ! preg_match( '/^[1-9][0-9]{0,18}$/D', $value ) ) {
			return false;
		}
		$id = (int) $value;
		if ( (string) $id !== $value || in_array( $id, $ids, true ) || LASERY_XTOOL_PRODUCT_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return false;
		}
		$ids[] = $id;
	}
	return $ids;
}

add_action( 'admin_post_lasery_xtool_save_featured', 'lasery_xtool_handle_featured_products' );
function lasery_xtool_handle_featured_products() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'lasery_xtool_save_featured' );
	$input = isset( $_POST['featured'] ) && is_array( $_POST['featured'] ) ? wp_unslash( $_POST['featured'] ) : null;
	$ids = lasery_xtool_validate_featured_products( $input );
	$result = false === $ids ? 'invalid' : 'saved';
	if ( false !== $ids ) {
		update_option( 'lasery_xtool_featured_products', $ids, false );
		if ( get_option( 'lasery_xtool_featured_products', null ) !== $ids ) {
			$result = 'save_failed';
		} else {
			// LiteSpeed's URL purge uses the path tag, covering query variants.
			do_action( 'litespeed_purge_url', home_url( '/' ) );
		}
	}
	$url = add_query_arg( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'page' => 'lasery-xtool-featured', 'result' => $result ), admin_url( 'edit.php' ) );
	wp_safe_redirect( $url );
	exit;
}
