<?php
/** Manually reviewed product descriptions in supported site languages. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_description_languages() {
	return array( 'pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'uk' => 'Українська' );
}

/** Return a translated title when present, otherwise the original title. */
function lasery_xtool_product_title( $post_id, $language ) {
	if ( is_string( $language ) && isset( lasery_xtool_description_languages()[ $language ] ) ) {
		$title = get_post_meta( $post_id, '_lasery_title_' . $language, true );
		if ( is_string( $title ) && '' !== trim( $title ) ) {
			return $title;
		}
	}
	return get_the_title( $post_id );
}

/** Return whether a separately saved description exists for this language. */
function lasery_xtool_has_product_description( $post_id, $language ) {
	if ( ! is_string( $language ) || ! isset( lasery_xtool_description_languages()[ $language ] ) ) {
		return false;
	}
	$description = get_post_meta( $post_id, '_lasery_description_' . $language, true );
	return is_string( $description ) && '' !== trim( $description );
}

/** Return a saved translation, or the original post content when unavailable. */
function lasery_xtool_product_description( $post_id, $language ) {
	if ( lasery_xtool_has_product_description( $post_id, $language ) ) {
		return get_post_meta( $post_id, '_lasery_description_' . $language, true );
	}
	$post = get_post( $post_id );
	return $post && is_string( $post->post_content ) ? $post->post_content : '';
}

/** HTML as one line of plain text, with entities decoded. */
function lasery_xtool_plain_text( $html ) {
	// Keep words from adjacent elements apart before removing the tags; no-break spaces count as spaces.
	$text = wp_strip_all_tags( str_replace( '<', ' <', (string) $html ) );
	return trim( preg_replace( '/[\s\x{00A0}\x{202F}]+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/** Return the first point of the description with text, or the whole text, as plain text for cards and previews. */
function lasery_xtool_product_summary( $post_id, $language ) {
	$html = lasery_xtool_product_description( $post_id, $language );
	preg_match_all( '~<(li|p)\b[^>]*>(.*?)</\1>~is', $html, $matches );
	foreach ( $matches[2] as $item ) {
		$text = lasery_xtool_plain_text( $item );
		if ( '' !== $text ) {
			return $text;
		}
	}
	return lasery_xtool_plain_text( $html );
}

add_action( 'add_meta_boxes_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_description_box_register' );
function lasery_xtool_description_box_register() {
	add_meta_box( 'lasery-descriptions', 'Opisy produktów w językach strony', 'lasery_xtool_description_box', LASERY_XTOOL_PRODUCT_TYPE, 'normal', 'default' );
}

function lasery_xtool_description_box( $post ) {
	wp_nonce_field( 'lasery_xtool_save_descriptions', 'lasery_xtool_descriptions_nonce' );
	echo '<p>Wpisz przygotowane i sprawdzone nazwy oraz opisy. Puste pole pokazuje treść źródłową; import nie tłumaczy automatycznie. W opisach można użyć bezpiecznego HTML.</p>';
	foreach ( lasery_xtool_description_languages() as $language => $label ) {
		$title = get_post_meta( $post->ID, '_lasery_title_' . $language, true );
		$title = is_string( $title ) ? $title : '';
		$description = get_post_meta( $post->ID, '_lasery_description_' . $language, true );
		$description = is_string( $description ) ? $description : '';
		?>
		<p><label for="lasery-title-<?php echo esc_attr( $language ); ?>"><strong><?php echo esc_html( $label ); ?> — nazwa</strong></label><br>
		<input class="large-text" id="lasery-title-<?php echo esc_attr( $language ); ?>" name="lasery_title_<?php echo esc_attr( $language ); ?>" maxlength="200" value="<?php echo esc_attr( $title ); ?>"></p>
		<p><label for="lasery-description-<?php echo esc_attr( $language ); ?>"><strong><?php echo esc_html( $label ); ?> — opis</strong></label><br>
		<textarea class="large-text" rows="6" id="lasery-description-<?php echo esc_attr( $language ); ?>" name="lasery_description_<?php echo esc_attr( $language ); ?>"><?php echo esc_textarea( $description ); ?></textarea></p>
		<?php
	}
}

add_action( 'save_post_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_save_descriptions' );
function lasery_xtool_save_descriptions( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lasery_xtool_descriptions_nonce'] ) || ! is_string( $_POST['lasery_xtool_descriptions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lasery_xtool_descriptions_nonce'] ) ), 'lasery_xtool_save_descriptions' ) ) {
		return;
	}
	foreach ( lasery_xtool_description_languages() as $language => $label ) {
		$title_field = 'lasery_title_' . $language;
		if ( isset( $_POST[ $title_field ] ) && is_string( $_POST[ $title_field ] ) ) {
			$title = wp_unslash( $_POST[ $title_field ] );
			if ( strlen( $title ) > 800 ) {
				set_transient( 'lasery_description_error_' . get_current_user_id(), 'title_size', MINUTE_IN_SECONDS );
			} else {
				$title = sanitize_text_field( $title );
				$title_key = '_lasery_title_' . $language;
				if ( '' === trim( $title ) ) {
					delete_post_meta( $post_id, $title_key );
					if ( metadata_exists( 'post', $post_id, $title_key ) ) {
						set_transient( 'lasery_description_error_' . get_current_user_id(), 'save', MINUTE_IN_SECONDS );
					}
				} else {
					update_post_meta( $post_id, $title_key, wp_slash( $title ) );
					if ( get_post_meta( $post_id, $title_key, true ) !== $title ) {
						set_transient( 'lasery_description_error_' . get_current_user_id(), 'save', MINUTE_IN_SECONDS );
					}
				}
			}
		}
		$field = 'lasery_description_' . $language;
		if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
			continue;
		}
		$value = wp_unslash( $_POST[ $field ] );
		if ( strlen( $value ) > 500000 ) {
			set_transient( 'lasery_description_error_' . get_current_user_id(), 'size', MINUTE_IN_SECONDS );
			continue;
		}
		$value = wp_kses_post( $value );
		$key = '_lasery_description_' . $language;
		if ( '' === trim( $value ) ) {
			delete_post_meta( $post_id, $key );
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				set_transient( 'lasery_description_error_' . get_current_user_id(), 'save', MINUTE_IN_SECONDS );
			}
		} else {
			update_post_meta( $post_id, $key, wp_slash( $value ) );
			if ( get_post_meta( $post_id, $key, true ) !== $value ) {
				set_transient( 'lasery_description_error_' . get_current_user_id(), 'save', MINUTE_IN_SECONDS );
			}
		}
	}
}

add_action( 'admin_notices', 'lasery_xtool_description_size_notice' );
function lasery_xtool_description_size_notice() {
	$key = 'lasery_description_error_' . get_current_user_id();
	$error = get_transient( $key );
	if ( $error ) {
		delete_transient( $key );
		$messages = array(
			'size' => 'Nie zapisano co najmniej jednego opisu, ponieważ przekracza limit 500 000 bajtów. Skróć go i spróbuj ponownie.',
			'title_size' => 'Nie zapisano co najmniej jednej nazwy, ponieważ przekracza limit 800 bajtów. Skróć ją i spróbuj ponownie.',
			'save' => 'Nie udało się zapisać co najmniej jednej nazwy lub opisu. Spróbuj ponownie.',
		);
		$message = isset( $messages[ $error ] ) ? $messages[ $error ] : $messages['save'];
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}
