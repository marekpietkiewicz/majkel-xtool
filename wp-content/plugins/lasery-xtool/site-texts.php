<?php
/**
 * Site texts that administrators change per language (Produkty xTool → Teksty strony): order, cart and
 * email texts, the privacy banner and the theme's interface texts. An empty field keeps the built-in text.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Changed texts of a group and language: [key => text]. */
function lasery_xtool_text_overrides( $group, $language ) {
	// Read once per request: interface texts ask for every string on the page.
	static $all = null;
	$all ??= get_option( 'lasery_xtool_text_overrides', array() );
	$texts = is_array( $all ) && isset( $all[ $language ][ $group ] ) && is_array( $all[ $language ][ $group ] ) ? $all[ $language ][ $group ] : array();
	return array_filter( $texts, 'is_string' );
}

/** Built-in texts [key => text] with the changed ones in their place. */
function lasery_xtool_apply_text_overrides( $group, $language, $texts ) {
	return array_merge( $texts, array_intersect_key( lasery_xtool_text_overrides( $group, $language ), $texts ) );
}

// Interface texts on the site; the admin screens keep the theme's own texts.
add_filter( 'gettext_lasery-xtool', 'lasery_xtool_interface_text', 10, 2 );
function lasery_xtool_interface_text( $translation, $text ) {
	if ( is_admin() || ! function_exists( 'lasery_xtool_interface_language' ) ) {
		return $translation;
	}
	$overrides = lasery_xtool_text_overrides( 'ui', lasery_xtool_interface_language() );
	return $overrides[ md5( $text ) ] ?? $translation;
}

/** The theme's interface texts of a language from its translation files: [md5(source) => [Polish source, text]]. */
function lasery_xtool_interface_default_texts( $language ) {
	require_once ABSPATH . WPINC . '/pomo/po.php';
	$folder = get_template_directory() . '/languages/';
	$locales = function_exists( 'lasery_xtool_locales' ) ? lasery_xtool_locales() : array();
	$source = new PO();
	if ( ! $source->import_from_file( $folder . 'en_US.po' ) ) {
		return array();
	}
	$translations = new PO();
	if ( 'pl' !== $language && isset( $locales[ $language ] ) ) {
		$translations->import_from_file( $folder . $locales[ $language ] . '.po' );
	}
	$texts = array();
	foreach ( $source->entries as $entry ) {
		$texts[ md5( $entry->singular ) ] = array( mb_strimwidth( $entry->singular, 0, 90, '…' ), 'pl' === $language ? $entry->singular : $translations->translate( $entry->singular ) );
	}
	return $texts;
}

/** Groups of the settings page: [group => [title, [key => [label, built-in text]]]]. */
function lasery_xtool_text_groups( $language ) {
	$pairs = static function ( $texts, $labels ) {
		$rows = array();
		foreach ( $texts as $key => $text ) {
			$rows[ $key ] = array( mb_strimwidth( isset( $labels[ $key ] ) ? $labels[ $key ] : $key, 0, 90, '…' ), $text );
		}
		return $rows;
	};
	// Polish texts name the fields in every language.
	$groups = array(
		'order' => array( 'Zamówienie, koszyk i e-maile', $pairs( lasery_xtool_order_default_texts( $language ), lasery_xtool_order_default_texts( 'pl' ) ) ),
	);
	if ( function_exists( 'lasery_xtool_purchase_info_default_texts' ) ) {
		$labels = array(
			'title' => 'Tytuł i etykieta odnośnika',
			'operator_heading' => 'Nagłówek: operator i sprzedawca', 'operator' => 'Operator i sprzedawca',
			'xtool_heading' => 'Nagłówek danych xTool', 'xtool_intro' => 'Wyjaśnienie danych xTool',
			'xtool_entity' => 'xTool: nazwa operatora', 'xtool_address' => 'xTool: adres operatora',
			'xtool_email' => 'xTool: adres e-mail', 'xtool_phone' => 'xTool: numer telefonu',
			'xtool_imprint' => 'Etykieta danych firmy xTool', 'xtool_contact_source' => 'Etykieta strony kontaktowej xTool',
			'process_heading' => 'Nagłówek: zgłoszenie i płatność', 'process' => 'Zgłoszenie i płatność',
			'rights_heading' => 'Nagłówek: reklamacje i zwroty', 'rights' => 'Prawa kupującego',
			'contact' => 'Kontakt z prowadzącym stronę', 'seller_contact' => 'Kontakt ze sprzedawcą',
			'service_title' => 'Regulamin: tytuł', 'service_scope' => 'Regulamin: zakres usług', 'service_requirements' => 'Regulamin: wymagania i zakaz treści bezprawnych',
			'commercial_disclosure' => 'Informacja o reklamie i partnerstwie',
			'service_duration' => 'Regulamin: rozpoczęcie i zakończenie korzystania', 'service_complaints' => 'Regulamin: reklamacje serwisu',
			'links_heading' => 'Nagłówek: dokumenty xTool', 'terms' => 'Etykieta warunków xTool', 'aftersales' => 'Etykieta polityki posprzedażowej xTool',
			'returns' => 'Etykieta zwrotów xTool', 'support' => 'Etykieta pomocy xTool',
		);
		$groups['purchase_info'] = array( 'Informacje o zakupach', $pairs( lasery_xtool_purchase_info_default_texts( $language ), $labels ) );
	}
	if ( function_exists( 'lasery_xtool_consent_default_texts' ) ) {
		$groups['consent'] = array( 'Baner prywatności', $pairs( lasery_xtool_consent_default_texts( $language ), lasery_xtool_consent_default_texts( 'pl' ) ) );
	}
	if ( function_exists( 'lasery_xtool_safety_page_defaults' ) ) {
		$labels = array( 'intro' => 'Wprowadzenie', 'general' => 'Przygotowanie stanowiska', 'materials' => 'Materiały i opary', 'f2' => 'xTool F2', 'f2_package' => 'Okulary w zestawie F2', 'ultra' => 'xTool F2 Ultra', 'uv' => 'xTool F2 Ultra UV i RA3', 'p3' => 'xTool P3 z podajnikiem' );
		$groups['safety'] = array( 'Informacja o bezpieczeństwie (ostrzeżenia produktów są niezależne)', $pairs( lasery_xtool_safety_page_defaults( $language ), $labels ) );
	}
	$groups['ui'] = array( 'Interfejs strony (przyciski, nagłówki, stopka, wyszukiwarka, 404)', lasery_xtool_interface_default_texts( $language ) );
	return $groups;
}

/** Placeholders such as %s, %d or %1$s, which a changed text must keep. */
function lasery_xtool_text_placeholders( $text ) {
	preg_match_all( '/%(?:\d+\$)?[sd]/', $text, $matches );
	sort( $matches[0] );
	return $matches[0];
}

/**
 * Whether a changed text may replace a built-in one: the same placeholders, and in texts with
 * placeholders no other "%" (the site fills them in with sprintf, which would fail on "100%").
 */
function lasery_xtool_text_fits( $text, $default ) {
	$placeholders = lasery_xtool_text_placeholders( $default );
	return lasery_xtool_text_placeholders( $text ) === $placeholders && ( ! $placeholders || false === strpos( preg_replace( '/%(?:\d+\$)?[sd]/', '', $text ), '%' ) );
}

add_action( 'admin_menu', 'lasery_xtool_site_texts_menu' );
function lasery_xtool_site_texts_menu() {
	add_submenu_page( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE, 'Teksty strony', 'Teksty strony', 'manage_options', 'lasery-xtool-texts', 'lasery_xtool_site_texts_page' );
}

function lasery_xtool_site_texts_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	$languages = lasery_xtool_description_languages();
	$requested = isset( $_GET['language'] ) && is_string( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : 'pl';
	$language = isset( $languages[ $requested ] ) ? $requested : 'pl';
	$result = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
	$rejected = isset( $_GET['rejected'] ) ? absint( $_GET['rejected'] ) : 0;
	$base = admin_url( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE . '&page=lasery-xtool-texts' );
	?>
	<div class="wrap">
		<h1>Teksty strony</h1>
		<p>Puste pole oznacza tekst wbudowany (pokazany w polu na szaro). Wpisz własny tekst, aby go zastąpić; usuń go, aby wrócić do wbudowanego. Znaczniki takie jak <code>%s</code> i <code>%d</code> muszą zostać w tekście, a w tekstach z takimi znacznikami nie można użyć innego znaku <code>%</code>. Zmiany dotyczą tylko wybranego języka.</p>
		<p>Treść produktów, artykułów, strony głównej (Wygląd → Dostosuj) i polityki prywatności edytuje się w ich własnych miejscach.</p>
		<nav class="nav-tab-wrapper" aria-label="Języki tekstów">
			<?php foreach ( $languages as $code => $label ) : ?>
				<a class="nav-tab <?php echo $language === $code ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'language', $code, $base ) ); ?>" <?php if ( $language === $code ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php if ( 'saved' === $result ) : ?><div class="notice notice-success"><p>Zapisano teksty.</p></div><?php endif; ?>
		<?php if ( $rejected ) : ?><div class="notice notice-warning"><p><?php echo esc_html( sprintf( 'Pominięto zmienione teksty: %d. Muszą zawierać te same znaczniki (%%s, %%d) co tekst wbudowany i nie mogą mieć w nich innego znaku %%.', $rejected ) ); ?></p></div><?php endif; ?>
		<?php if ( 'save_failed' === $result ) : ?><div class="notice notice-error"><p>Nie udało się zapisać tekstów. Spróbuj ponownie.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_save_texts">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<?php wp_nonce_field( 'lasery_xtool_save_texts_' . $language ); ?>
			<?php foreach ( lasery_xtool_text_groups( $language ) as $group => list( $title, $rows ) ) : ?>
				<?php $overrides = lasery_xtool_text_overrides( $group, $language ); ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $rows as $key => list( $label, $default ) ) : ?>
						<?php $id = 'text-' . $group . '-' . $key; ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><textarea class="large-text" rows="<?php echo mb_strlen( $default ) > 90 ? 3 : 1; ?>" id="<?php echo esc_attr( $id ); ?>" name="texts[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $key ); ?>]" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $overrides[ $key ] ?? '' ); ?></textarea></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button( 'Zapisz teksty' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_lasery_xtool_save_texts', 'lasery_xtool_handle_site_texts' );
function lasery_xtool_handle_site_texts() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	$language = isset( $_POST['language'] ) && is_string( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
	if ( ! isset( lasery_xtool_description_languages()[ $language ] ) ) {
		wp_die( 'Nieprawidłowy język.', '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'lasery_xtool_save_texts_' . $language );
	$posted = isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array();
	$all = get_option( 'lasery_xtool_text_overrides', array() );
	$all = is_array( $all ) ? $all : array();
	$all[ $language ] = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : array();
	$rejected = 0;
	foreach ( lasery_xtool_text_groups( $language ) as $group => list( , $rows ) ) {
		foreach ( $rows as $key => list( , $default ) ) {
			if ( ! isset( $posted[ $group ][ $key ] ) || ! is_string( $posted[ $group ][ $key ] ) ) {
				continue;
			}
			$text = sanitize_text_field( $posted[ $group ][ $key ] );
			if ( '' === $text || $text === $default ) {
				unset( $all[ $language ][ $group ][ $key ] );
				continue;
			}
			if ( ! lasery_xtool_text_fits( $text, $default ) ) {
				++$rejected;
				continue;
			}
			$all[ $language ][ $group ][ $key ] = $text;
		}
		if ( empty( $all[ $language ][ $group ] ) ) {
			unset( $all[ $language ][ $group ] );
		}
	}
	$all = array_filter( $all );
	update_option( 'lasery_xtool_text_overrides', $all );
	$result = get_option( 'lasery_xtool_text_overrides', null ) === $all ? 'saved' : 'save_failed';
	if ( 'saved' === $result ) {
		// The texts appear on every page and in the cached search index.
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'lasery_xtool_search_refresh' ) ) {
			lasery_xtool_search_refresh();
		}
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'page' => 'lasery-xtool-texts', 'language' => $language, 'result' => $result, 'rejected' => $rejected ), admin_url( 'edit.php' ) ) );
	exit;
}
