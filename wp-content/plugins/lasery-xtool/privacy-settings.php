<?php
/** Administrator-editable privacy policy translations. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_privacy_languages() {
	return array( 'pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'uk' => 'Українська' );
}

/** Return null when the theme should display its default translation. */
function lasery_xtool_privacy_override( $language ) {
	$languages = lasery_xtool_privacy_languages();
	if ( ! is_string( $language ) || ! isset( $languages[ $language ] ) ) {
		return null;
	}
	$texts = get_option( 'lasery_xtool_privacy_texts', array() );
	if ( ! is_array( $texts ) || ! isset( $texts[ $language ] ) || ! is_string( $texts[ $language ] ) || '' === trim( $texts[ $language ] ) ) {
		return null;
	}
	return $texts[ $language ];
}

add_action( 'admin_menu', 'lasery_xtool_privacy_settings_menu' );
function lasery_xtool_privacy_settings_menu() {
	add_submenu_page( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE, 'Polityka prywatności', 'Polityka prywatności', 'manage_options', 'lasery-xtool-privacy', 'lasery_xtool_privacy_settings_page' );
}

function lasery_xtool_privacy_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	$languages = lasery_xtool_privacy_languages();
	$requested = isset( $_GET['language'] ) && is_string( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : 'pl';
	$language = isset( $languages[ $requested ] ) ? $requested : 'pl';
	$result = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
	$override = lasery_xtool_privacy_override( $language );
	$default = function_exists( 'lasery_xtool_default_privacy_html' ) ? lasery_xtool_default_privacy_html( $language ) : '';
	$content = null !== $override ? $override : ( is_string( $default ) ? $default : '' );
	$base = admin_url( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE . '&page=lasery-xtool-privacy' );
	?>
	<div class="wrap">
		<h1>Polityka prywatności</h1>
		<p>Edytuj każdą wersję językową osobno. Zapis dotyczy wyłącznie wybranego języka.</p>
		<nav class="nav-tab-wrapper" aria-label="Języki polityki prywatności">
			<?php foreach ( $languages as $code => $label ) : ?>
				<a class="nav-tab <?php echo $language === $code ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'language', $code, $base ) ); ?>" <?php if ( $language === $code ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php if ( 'saved' === $result ) : ?><div class="notice notice-success"><p>Zapisano wersję językową.</p></div><?php endif; ?>
		<?php if ( 'restored' === $result ) : ?><div class="notice notice-success"><p>Przywrócono domyślną wersję językową.</p></div><?php endif; ?>
		<?php if ( 'invalid' === $result ) : ?><div class="notice notice-error"><p>Nie zapisano treści. Tekst nie może być pusty ani przekraczać 40 KB.</p></div><?php endif; ?>
		<?php if ( 'save_failed' === $result ) : ?><div class="notice notice-error"><p>Nie udało się zapisać treści. Spróbuj ponownie.</p></div><?php endif; ?>
		<p><?php echo null === $override ? 'Wyświetlana jest treść domyślna. Możesz ją zmienić i zapisać jako własną.' : 'Wyświetlana jest Twoja zapisana wersja. Możesz przywrócić treść domyślną.'; ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_save_privacy">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<?php wp_nonce_field( 'lasery_xtool_save_privacy_' . $language ); ?>
			<?php wp_editor( $content, 'lasery_privacy_content', array( 'textarea_name' => 'privacy_content', 'media_buttons' => false, 'textarea_rows' => 18 ) ); ?>
			<p><button type="submit" class="button button-primary" name="operation" value="save">Zapisz tę wersję</button>
			<button type="submit" class="button" name="operation" value="restore">Przywróć domyślną wersję</button></p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_lasery_xtool_save_privacy', 'lasery_xtool_handle_privacy_settings' );
function lasery_xtool_handle_privacy_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	$languages = lasery_xtool_privacy_languages();
	$language = isset( $_POST['language'] ) && is_string( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
	if ( ! isset( $languages[ $language ] ) ) {
		wp_die( 'Nieprawidłowy język.', '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'lasery_xtool_save_privacy_' . $language );
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : '';
	$texts = get_option( 'lasery_xtool_privacy_texts', array() );
	$texts = is_array( $texts ) ? $texts : array();
	$result = 'invalid';
	if ( 'restore' === $operation ) {
		unset( $texts[ $language ] );
		$result = 'restored';
	} elseif ( 'save' === $operation && isset( $_POST['privacy_content'] ) && is_string( $_POST['privacy_content'] ) ) {
		$content = wp_unslash( $_POST['privacy_content'] );
		if ( strlen( $content ) <= 40 * 1024 ) {
			$content = trim( wp_kses_post( $content ) );
			if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
				$texts[ $language ] = $content;
				$result = 'saved';
			}
		}
	}
	if ( 'invalid' !== $result ) {
		update_option( 'lasery_xtool_privacy_texts', $texts, false );
		if ( get_option( 'lasery_xtool_privacy_texts', null ) !== $texts ) {
			$result = 'save_failed';
		} else {
			$url = get_privacy_policy_url();
			if ( $url ) {
				do_action( 'litespeed_purge_url', $url );
			}
		}
	}
	$redirect = add_query_arg( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'page' => 'lasery-xtool-privacy', 'language' => $language, 'result' => $result ), admin_url( 'edit.php' ) );
	wp_safe_redirect( $redirect );
	exit;
}
