<?php
/** Manual, ordered media-library gallery for a product. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Null means use the automatically imported gallery; an empty array is deliberate. */
function lasery_xtool_get_manual_gallery( $post_id ) {
	if ( ! metadata_exists( 'post', $post_id, '_lasery_manual_gallery' ) ) {
		return null;
	}
	$ids = get_post_meta( $post_id, '_lasery_manual_gallery', true );
	$valid = array();
	foreach ( is_array( $ids ) ? $ids : array() as $id ) {
		$id = absint( $id );
		$file = $id ? get_attached_file( $id ) : false;
		if ( $id && wp_attachment_is_image( $id ) && is_string( $file ) && file_exists( $file ) && ! in_array( $id, $valid, true ) ) {
			$valid[] = $id;
		}
	}
	return $valid;
}

add_action( 'add_meta_boxes_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_gallery_box_register' );
function lasery_xtool_gallery_box_register() {
	add_meta_box( 'lasery-gallery', 'Galeria produktu', 'lasery_xtool_gallery_box', LASERY_XTOOL_PRODUCT_TYPE, 'normal' );
}

function lasery_xtool_gallery_box( $post ) {
	$manual = lasery_xtool_get_manual_gallery( $post->ID );
	$ids = null === $manual ? lasery_xtool_product_gallery( $post->ID ) : $manual;
	wp_nonce_field( 'lasery_xtool_save_gallery_' . $post->ID, 'lasery_xtool_gallery_nonce' );
	?>
	<style>#lasery-gallery [hidden] { display: none !important; }</style>
	<div data-lasery-gallery>
		<p>Wybierz zdjęcia z biblioteki i ustaw ich kolejność. Zdjęcie wyróżniające ustawisz w osobnym polu WordPressa.</p>
		<p data-gallery-status><?php echo null === $manual ? 'Galeria jest aktualizowana automatycznie.' : 'Używana jest własna galeria.'; ?></p>
		<input type="hidden" name="lasery_gallery_mode" data-gallery-mode value="<?php echo null === $manual ? 'auto' : 'manual'; ?>">
		<ul data-gallery-list>
			<?php foreach ( $ids as $id ) : ?>
				<li data-gallery-item>
					<?php echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'max-width:80px;height:auto;vertical-align:middle' ) ); ?>
					<input type="hidden" name="lasery_gallery_ids[]" value="<?php echo esc_attr( $id ); ?>">
					<span><?php echo esc_html( get_the_title( $id ) ); ?></span>
					<button type="button" class="button" data-gallery-up hidden>W górę</button>
					<button type="button" class="button" data-gallery-down hidden>W dół</button>
					<button type="button" class="button" data-gallery-remove hidden>Usuń</button>
				</li>
			<?php endforeach; ?>
		</ul>
		<p><button type="button" class="button" data-gallery-add hidden>Dodaj zdjęcia</button> <button type="button" class="button" data-gallery-restore hidden>Przywróć automatyczną galerię</button></p>
		<noscript><p>Do zmiany galerii potrzebny jest JavaScript. Obecna galeria pozostanie bez zmian.</p></noscript>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'lasery_xtool_gallery_admin_assets' );
function lasery_xtool_gallery_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || LASERY_XTOOL_PRODUCT_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'lasery-xtool-gallery-editor', plugins_url( 'assets/gallery-editor.js', __FILE__ ), array( 'media-views' ), '1.0.0', true );
}

add_action( 'save_post_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_save_manual_gallery' );
function lasery_xtool_save_manual_gallery( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lasery_xtool_gallery_nonce'], $_POST['lasery_gallery_mode'] ) || ! is_string( $_POST['lasery_xtool_gallery_nonce'] ) || ! is_string( $_POST['lasery_gallery_mode'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lasery_xtool_gallery_nonce'] ) ), 'lasery_xtool_save_gallery_' . $post_id ) ) {
		return;
	}
	$mode = wp_unslash( $_POST['lasery_gallery_mode'] );
	if ( 'auto' === $mode ) {
		delete_post_meta( $post_id, '_lasery_manual_gallery' );
		if ( metadata_exists( 'post', $post_id, '_lasery_manual_gallery' ) ) {
			lasery_xtool_gallery_save_error();
		}
		return;
	}
	if ( 'manual' !== $mode ) {
		return;
	}
	if ( isset( $_POST['lasery_gallery_ids'] ) && ! is_array( $_POST['lasery_gallery_ids'] ) ) {
		lasery_xtool_gallery_save_error();
		return;
	}
	$input = isset( $_POST['lasery_gallery_ids'] ) ? wp_unslash( $_POST['lasery_gallery_ids'] ) : array();
	if ( count( $input ) > 40 ) {
		lasery_xtool_gallery_save_error();
		return;
	}
	$ids = array();
	foreach ( $input as $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]{0,18}$/D', $value ) ) {
			lasery_xtool_gallery_save_error();
			return;
		}
		$id = (int) $value;
		$file = get_attached_file( $id );
		if ( (string) $id !== $value || ! wp_attachment_is_image( $id ) || ! is_string( $file ) || ! file_exists( $file ) || in_array( $id, $ids, true ) ) {
			lasery_xtool_gallery_save_error();
			return;
		}
		$ids[] = $id;
	}
	update_post_meta( $post_id, '_lasery_manual_gallery', $ids );
	if ( get_post_meta( $post_id, '_lasery_manual_gallery', true ) !== $ids ) {
		lasery_xtool_gallery_save_error();
	}
}

function lasery_xtool_gallery_save_error() {
	set_transient( 'lasery_gallery_error_' . get_current_user_id(), true, MINUTE_IN_SECONDS );
}

add_action( 'admin_notices', 'lasery_xtool_gallery_error_notice' );
function lasery_xtool_gallery_error_notice() {
	$key = 'lasery_gallery_error_' . get_current_user_id();
	if ( get_transient( $key ) ) {
		delete_transient( $key );
		echo '<div class="notice notice-error"><p>Nie zapisano galerii. Sprawdź wybrane zdjęcia i spróbuj ponownie.</p></div>';
	}
}
