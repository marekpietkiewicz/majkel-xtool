<?php
/** Order settings, texts, prices, the add-to-cart form and order emails. The cart is in cart.php. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The recipient must be configured in the admin panel. */
function lasery_xtool_order_recipient() {
	$recipient = get_option( 'lasery_xtool_order_recipient', '' );
	return is_string( $recipient ) && is_email( $recipient ) ? $recipient : '';
}

function lasery_xtool_mail_from_name() {
	$name = get_option( 'lasery_xtool_mail_from_name', '' );
	return is_string( $name ) && '' !== trim( $name ) ? $name : 'LaseryXTOOL.pl';
}

/** Whether an address is in the site's domain; another domain's address would fail SPF and DMARC. */
function lasery_xtool_in_site_domain( $address ) {
	$host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	return is_string( $address ) && is_email( $address ) && strtolower( substr( strrchr( $address, '@' ), 1 ) ) === $host;
}

/** The sender address of site emails: the setting, else the order recipient in the site's domain, else '' for WordPress's own. */
function lasery_xtool_mail_from_address() {
	$address = get_option( 'lasery_xtool_mail_from', '' );
	if ( is_string( $address ) && is_email( $address ) ) {
		return $address;
	}
	$recipient = lasery_xtool_order_recipient();
	return lasery_xtool_in_site_domain( $recipient ) ? $recipient : '';
}

// Site emails come from the shop (name and address) instead of WordPress's defaults.
add_filter( 'wp_mail_from_name', 'lasery_xtool_filter_mail_from_name' );
function lasery_xtool_filter_mail_from_name( $name ) {
	return 'WordPress' === $name ? lasery_xtool_mail_from_name() : $name;
}

add_filter( 'wp_mail_from', 'lasery_xtool_filter_mail_from' );
function lasery_xtool_filter_mail_from( $address ) {
	$from = lasery_xtool_mail_from_address();
	return 0 === strpos( $address, 'wordpress@' ) && '' !== $from ? $from : $address;
}

add_action( 'admin_init', 'lasery_xtool_register_order_settings' );
function lasery_xtool_register_order_settings() {
	register_setting( 'lasery_xtool_order_settings', 'lasery_xtool_order_recipient', array(
		'type' => 'string',
		'sanitize_callback' => 'lasery_xtool_sanitize_order_recipient',
		'default' => '',
	) );
	register_setting( 'lasery_xtool_order_settings', 'lasery_xtool_mail_from', array(
		'type' => 'string',
		'sanitize_callback' => 'lasery_xtool_sanitize_mail_from',
		'default' => '',
	) );
	register_setting( 'lasery_xtool_order_settings', 'lasery_xtool_mail_from_name', array(
		'type' => 'string',
		'sanitize_callback' => 'lasery_xtool_sanitize_mail_from_name',
		'default' => '',
	) );
}

function lasery_xtool_sanitize_mail_from_name( $name ) {
	// Header-safe: a single line without characters that would need quoting.
	return is_string( $name ) ? trim( preg_replace( '/\s+/', ' ', str_replace( array( '"', '<', '>', ',', ';' ), '', sanitize_text_field( $name ) ) ) ) : '';
}

function lasery_xtool_sanitize_mail_from( $address ) {
	$address = is_string( $address ) ? sanitize_email( trim( $address ) ) : '';
	if ( '' !== $address && ! lasery_xtool_in_site_domain( $address ) ) {
		add_settings_error( 'lasery_xtool_order_recipient', 'mail_from_domain', 'Adres nadawcy musi być w domenie strony.' );
		$saved = get_option( 'lasery_xtool_mail_from', '' );
		return is_string( $saved ) ? $saved : '';
	}
	return $address;
}

function lasery_xtool_sanitize_order_recipient( $recipient ) {
	$recipient = is_string( $recipient ) ? sanitize_email( trim( $recipient ) ) : '';
	if ( ! is_email( $recipient ) ) {
		add_settings_error( 'lasery_xtool_order_recipient', 'invalid_email', 'Podaj poprawny adres e-mail odbiorcy zamówień.' );
		return lasery_xtool_order_recipient();
	}
	return $recipient;
}

add_action( 'admin_menu', 'lasery_xtool_order_settings_menu' );
function lasery_xtool_order_settings_menu() {
	add_submenu_page( 'edit.php?post_type=' . LASERY_XTOOL_PRODUCT_TYPE, 'Ustawienia zamówień', 'Ustawienia zamówień', 'manage_options', 'lasery-xtool-orders', 'lasery_xtool_order_settings_page' );
}

function lasery_xtool_order_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) );
	}
	?>
	<div class="wrap"><h1>Ustawienia zamówień</h1>
	<?php settings_errors( 'lasery_xtool_order_recipient' ); ?>
	<p>Ustaw adres odbiorcy zamówień oraz nadawcę wiadomości. Podczas testów użyj własnej skrzynki, a przed publikacją wpisz właściwy adres obsługi zamówień.</p>
	<form action="options.php" method="post">
		<?php settings_fields( 'lasery_xtool_order_settings' ); ?>
		<p><label for="lasery-order-recipient">Adres odbiorcy zamówień</label></p>
		<input id="lasery-order-recipient" name="lasery_xtool_order_recipient" type="email" class="regular-text" required value="<?php echo esc_attr( lasery_xtool_order_recipient() ); ?>">
		<p><label for="lasery-mail-from">Adres nadawcy wiadomości</label></p>
		<input id="lasery-mail-from" name="lasery_xtool_mail_from" type="email" class="regular-text" placeholder="<?php echo esc_attr( lasery_xtool_in_site_domain( lasery_xtool_order_recipient() ) ? lasery_xtool_order_recipient() : '' ); ?>" value="<?php echo esc_attr( get_option( 'lasery_xtool_mail_from', '' ) ); ?>">
		<p class="description">Wiadomości ze strony (zamówienia, potwierdzenia dla klientów i powiadomienia WordPressa) przychodzą z tego adresu. Puste pole oznacza adres odbiorcy zamówień, jeśli jest w domenie strony. Adres musi być w domenie strony, inaczej wiadomości mogą trafiać do spamu.</p>
		<p><label for="lasery-mail-from-name">Nazwa nadawcy wiadomości</label></p>
		<input id="lasery-mail-from-name" name="lasery_xtool_mail_from_name" type="text" class="regular-text" maxlength="60" placeholder="LaseryXTOOL.pl" value="<?php echo esc_attr( get_option( 'lasery_xtool_mail_from_name', '' ) ); ?>">
		<p class="description">Widoczna w skrzynce klienta i właściciela zamiast „WordPress”. Puste pole oznacza „LaseryXTOOL.pl”.</p>
		<?php submit_button(); ?>
	</form></div>
	<?php
}

/** The price is entered manually and is only an estimate. Returns [amount, currency] or null. */
function lasery_xtool_order_price_amount( $post_id ) {
	$price = get_post_meta( $post_id, '_lasery_order_price', true );
	$currency = get_post_meta( $post_id, '_lasery_order_currency', true );
	if ( ! is_string( $price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $price ) || (float) $price <= 0 || ! in_array( $currency, array( 'PLN', 'EUR', 'USD' ), true ) ) {
		return null;
	}
	return array( $price, $currency );
}

/** ICU locale for numbers and dates; English uses European conventions (EUR prices). */
function lasery_xtool_icu_locale( $language ) {
	$locales = array( 'pl' => 'pl_PL', 'en' => 'en_IE', 'de' => 'de_DE', 'ru' => 'ru_RU', 'uk' => 'uk_UA' );
	return isset( $locales[ $language ] ) ? $locales[ $language ] : '';
}

/** Format a price for visitors, e.g. "3 427,33 €" in Polish. */
function lasery_xtool_format_price( $amount, $currency, $language ) {
	$locale = lasery_xtool_icu_locale( $language );
	if ( class_exists( 'NumberFormatter' ) && '' !== $locale ) {
		$formatted = ( new NumberFormatter( $locale, NumberFormatter::CURRENCY ) )->formatCurrency( (float) $amount, $currency );
		if ( is_string( $formatted ) ) {
			// Some locales (Ukrainian) write the code instead of the sign; every other price shows "€".
			return 'EUR' === $currency ? str_replace( 'EUR', '€', $formatted ) : $formatted;
		}
	}
	return number_format_i18n( (float) $amount, 2 ) . ' ' . $currency;
}

add_action( 'add_meta_boxes_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_order_price_box_register' );
function lasery_xtool_order_price_box_register() {
	add_meta_box( 'lasery-order-price', 'Cena orientacyjna', 'lasery_xtool_order_price_box', LASERY_XTOOL_PRODUCT_TYPE, 'side' );
}

function lasery_xtool_order_price_box( $post ) {
	wp_nonce_field( 'lasery_xtool_order_price', 'lasery_xtool_order_price_nonce' );
	$price = get_post_meta( $post->ID, '_lasery_order_price', true );
	$currency = get_post_meta( $post->ID, '_lasery_order_currency', true );
	?>
	<p><label for="lasery-order-price">Cena orientacyjna (bez symbolu waluty)</label></p>
	<input id="lasery-order-price" class="widefat" name="lasery_order_price" type="text" inputmode="decimal" maxlength="12" value="<?php echo esc_attr( $price ); ?>">
	<p><label for="lasery-order-currency">Waluta</label></p>
	<select id="lasery-order-currency" name="lasery_order_currency"><?php foreach ( array( 'PLN', 'EUR', 'USD' ) as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>" <?php selected( $currency, $item ); ?>><?php echo esc_html( $item ); ?></option><?php endforeach; ?></select>
	<p class="description">Pozostaw cenę pustą, jeśli wymaga potwierdzenia. Cena końcowa, VAT i dostawa mogą się różnić.</p>
	<?php
}

add_action( 'save_post_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_save_order_price' );
function lasery_xtool_save_order_price( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lasery_xtool_order_price_nonce'], $_POST['lasery_order_price'], $_POST['lasery_order_currency'] ) || ! is_string( $_POST['lasery_xtool_order_price_nonce'] ) || ! is_string( $_POST['lasery_order_price'] ) || ! is_string( $_POST['lasery_order_currency'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lasery_xtool_order_price_nonce'] ) ), 'lasery_xtool_order_price' ) ) {
		return;
	}
	$price = str_replace( ',', '.', trim( wp_unslash( $_POST['lasery_order_price'] ) ) );
	$currency = wp_unslash( $_POST['lasery_order_currency'] );
	if ( '' === $price ) {
		delete_post_meta( $post_id, '_lasery_order_price' );
		delete_post_meta( $post_id, '_lasery_order_currency' );
		return;
	}
	if ( ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $price ) || (float) $price <= 0 || ! in_array( $currency, array( 'PLN', 'EUR', 'USD' ), true ) ) {
		set_transient( 'lasery_order_price_error_' . get_current_user_id(), true, MINUTE_IN_SECONDS );
		return;
	}
	update_post_meta( $post_id, '_lasery_order_price', $price );
	update_post_meta( $post_id, '_lasery_order_currency', $currency );
}

add_action( 'admin_notices', 'lasery_xtool_order_price_error_notice' );
function lasery_xtool_order_price_error_notice() {
	$key = 'lasery_order_price_error_' . get_current_user_id();
	if ( get_transient( $key ) ) {
		delete_transient( $key );
		echo '<div class="notice notice-error"><p>Nie zapisano ceny. Podaj dodatnią kwotę z maksymalnie dwoma miejscami po przecinku i wybierz walutę.</p></div>';
	}
}

/** The theme may expose an editable contact; validate it before using it in email. */
function lasery_xtool_order_contact_email() {
	$email = function_exists( 'lasery_xtool_contact_email' ) ? lasery_xtool_contact_email() : '';
	return is_string( $email ) && is_email( $email ) ? $email : 'Michal@laseryxtool.pl';
}

/** Use the reviewed translation when available; email bodies remain plain text. */
function lasery_xtool_order_product_title( $post_id, $language ) {
	$title = function_exists( 'lasery_xtool_product_title' ) ? lasery_xtool_product_title( $post_id, $language ) : get_the_title( $post_id );
	return sanitize_text_field( html_entity_decode( wp_strip_all_tags( $title, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/** Texts are kept together until the site has a configured multilingual plugin. */
/** The built-in order, cart and email texts of a language (Polish for unknown languages), with placeholders. */
function lasery_xtool_order_default_texts( $language ) {
	$texts = array(
		'pl' => array(
			'title' => 'Zamów produkt', 'intro' => 'Wybierz wariant i liczbę sztuk, a potem dodaj produkt do koszyka. W koszyku podasz dane do zamówienia.',
			'first_name' => 'Imię', 'last_name' => 'Nazwisko', 'email' => 'Adres e-mail', 'phone' => 'Telefon (opcjonalnie)', 'address1' => 'Ulica i numer', 'address2' => 'Dodatkowy adres (opcjonalnie)', 'city' => 'Miasto', 'zip' => 'Kod pocztowy', 'country' => 'Kraj dostawy', 'select_country' => 'Wybierz kraj', 'variant' => 'Wariant lub konfiguracja', 'unavailable' => 'Obecnie brak dostępnych wariantów.', 'stale' => 'Dane oferty wymagają aktualizacji. Skontaktuj się z nami.', 'changed' => 'Dane oferty zmieniły się. Sprawdź koszyk i wyślij zamówienie ponownie.', 'quantity' => 'Liczba sztuk', 'invoice' => 'Dane do faktury (opcjonalnie)', 'notes' => 'Dodatkowe informacje (opcjonalnie)', 'submit' => 'Wyślij zamówienie',
			'privacy_link' => 'Polityka prywatności', 'privacy' => 'Dane wykorzystamy wyłącznie do obsługi zamówienia.', 'sent' => 'Zamówienie zostało wysłane, a na Twój adres wysłaliśmy potwierdzenie. Po sprawdzeniu dostępności i ceny prześlemy Ci link do płatności.', 'sent_no_receipt' => 'Zamówienie zostało wysłane, ale nie udało się wysłać potwierdzenia na Twój adres. Nie wysyłaj go ponownie; w razie pytań napisz na Michal@laseryxtool.pl.', 'failed' => 'Nie udało się wysłać zamówienia. Spróbuj ponownie lub napisz na Michal@laseryxtool.pl.', 'invalid' => 'Sprawdź dane formularza i spróbuj ponownie.', 'busy' => 'Odczekaj chwilę przed ponownym wysłaniem zamówienia.',
			'owner_subject' => 'Nowe zamówienie: %s', 'customer_subject' => 'Otrzymaliśmy Twoje zamówienie: %s', 'customer_body' => 'Dziękujemy za zamówienie. Potwierdzimy dostępność, ostateczną cenę i koszt dostawy, a następnie wyślemy Ci link do płatności. Do tego czasu nie musisz nic robić.', 'price' => 'Cena orientacyjna', 'no_price' => 'Cena do potwierdzenia', 'price_note' => 'Ostateczna cena, VAT i koszt dostawy mogą się różnić.',
			'summary' => 'Podsumowanie', 'contact' => 'Dane kontaktowe', 'address' => 'Adres dostawy', 'extras' => 'Faktura i uwagi', 'summary_empty' => 'Wybierz wariant powyżej', 'next_steps' => 'Po wysłaniu zamówienia potwierdzimy dostępność i cenę, a następnie wyślemy link do płatności.',
			'reply_note' => 'Masz pytania? Odpowiedz na tę wiadomość.',
			'add' => 'Dodaj do koszyka',
			'go_to_cart' => 'Przejdź do koszyka',
			'set' => 'W zestawie',
			'set_more' => 'Zobacz, co jest w zestawie',
			'cart_title' => 'Koszyk',
			'cart_intro' => 'Sprawdź produkty i podaj dane do zamówienia. Płatność nie jest pobierana na stronie: po potwierdzeniu dostępności i ceny wyślemy Ci link do płatności.',
			'cart_empty' => 'Koszyk jest pusty.',
			'browse' => 'Zobacz produkty',
			'continue' => 'Kontynuuj zakupy',
			'cart_added' => 'Produkt został dodany do koszyka.', 'cart_limit' => 'W koszyku jest teraz 100 szt. tego produktu – to najwięcej w jednej pozycji.',
			'cart_full' => 'Koszyk może zawierać najwyżej %d różnych produktów.',
			'cart_updated' => 'Koszyk został zaktualizowany.',
			'cart_removed' => 'Produkt został usunięty z koszyka.',
			'cart_unavailable' => 'Ten wariant jest obecnie niedostępny.',
			'cart_stale' => 'Dane tej oferty wymagają aktualizacji. Usuń produkt albo skontaktuj się z nami.',
			'remove' => 'Usuń',
			'update' => 'Zaktualizuj koszyk',
			'total' => 'Razem (orientacyjnie)',
			'items' => 'Produkty',
			'each' => '%s / szt.', 'pieces' => '%d szt.', 'quantity_hint' => 'Liczba od 1 do 100', 'unit_price' => 'Cena za sztukę', 'line_total' => 'Wartość', 'sent_title' => 'Dziękujemy za zamówienie',
			'view_product' => 'Zobacz produkt', 'close' => 'Zamknij',
		),
		'en' => array(
			'title' => 'Order this product', 'intro' => 'Choose a variant and quantity, then add the product to your cart. You will enter your order details in the cart.',
			'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number (optional)', 'address1' => 'Street address', 'address2' => 'Additional address (optional)', 'city' => 'City', 'zip' => 'Postal code', 'country' => 'Delivery country', 'select_country' => 'Choose a country', 'variant' => 'Variant or configuration', 'unavailable' => 'No variants are currently available.', 'stale' => 'The offer needs updating. Please contact us.', 'changed' => 'The offer has changed. Please check your cart and send the order again.', 'quantity' => 'Quantity', 'invoice' => 'Billing details (optional)', 'notes' => 'Additional information (optional)', 'submit' => 'Send order',
			'privacy_link' => 'Privacy policy', 'privacy' => 'We use your details only to handle your order.', 'sent' => 'Your order has been sent and we have emailed you a confirmation. Once we confirm availability and price, we will send you a payment link.', 'sent_no_receipt' => 'Your order was sent, but we could not send your confirmation email. Please do not send it again; contact Michal@laseryxtool.pl with any questions.', 'failed' => 'Your order could not be sent. Please try again or email Michal@laseryxtool.pl.', 'invalid' => 'Please check the form and try again.', 'busy' => 'Please wait a moment before sending another order.',
			'owner_subject' => 'New order: %s', 'customer_subject' => 'We received your order: %s', 'customer_body' => 'Thank you for your order. We will confirm availability, the final price and delivery cost, then send you a payment link. You do not need to do anything until then.', 'price' => 'Estimated price', 'no_price' => 'Price to be confirmed', 'price_note' => 'The final price, VAT and delivery costs may differ.',
			'summary' => 'Summary', 'contact' => 'Contact details', 'address' => 'Delivery address', 'extras' => 'Invoice and notes', 'summary_empty' => 'Choose a variant above', 'next_steps' => 'After you send the order, we will confirm availability and price, then send you a payment link.',
			'reply_note' => 'Questions? Just reply to this email.',
			'add' => 'Add to cart',
			'go_to_cart' => 'Go to cart',
			'set' => 'What’s included',
			'set_more' => 'See what’s included',
			'cart_title' => 'Cart',
			'cart_intro' => 'Check the products and enter your order details. No payment is taken on this site: once we confirm availability and price, we will send you a payment link.',
			'cart_empty' => 'Your cart is empty.',
			'browse' => 'Browse products',
			'continue' => 'Continue shopping',
			'cart_added' => 'The product has been added to your cart.', 'cart_limit' => 'Your cart now holds 100 pieces of this product, the most one line can have.',
			'cart_full' => 'Your cart can hold up to %d different products.',
			'cart_updated' => 'Your cart has been updated.',
			'cart_removed' => 'The product has been removed from your cart.',
			'cart_unavailable' => 'This variant is currently unavailable.',
			'cart_stale' => 'The offer details need updating. Remove the product or contact us.',
			'remove' => 'Remove',
			'update' => 'Update cart',
			'total' => 'Total (estimated)',
			'items' => 'Products',
			'each' => '%s each', 'pieces' => '%d pcs', 'quantity_hint' => 'A number from 1 to 100', 'unit_price' => 'Unit price', 'line_total' => 'Subtotal', 'sent_title' => 'Thank you for your order',
			'view_product' => 'View product', 'close' => 'Close',
		),
		'de' => array(
			'title' => 'Produkt bestellen', 'intro' => 'Wählen Sie Variante und Anzahl und legen Sie das Produkt in den Warenkorb. Die Bestelldaten geben Sie im Warenkorb an.',
			'first_name' => 'Vorname', 'last_name' => 'Nachname', 'email' => 'E-Mail-Adresse', 'phone' => 'Telefonnummer (optional)', 'address1' => 'Straße und Hausnummer', 'address2' => 'Adresszusatz (optional)', 'city' => 'Ort', 'zip' => 'Postleitzahl', 'country' => 'Lieferland', 'select_country' => 'Land auswählen', 'variant' => 'Variante oder Konfiguration', 'unavailable' => 'Derzeit sind keine Varianten verfügbar.', 'stale' => 'Die Angebotsdaten müssen aktualisiert werden. Bitte kontaktieren Sie uns.', 'changed' => 'Das Angebot hat sich geändert. Bitte prüfen Sie den Warenkorb und senden Sie die Bestellung erneut.', 'quantity' => 'Anzahl', 'invoice' => 'Rechnungsdaten (optional)', 'notes' => 'Weitere Informationen (optional)', 'submit' => 'Bestellung senden',
			'privacy_link' => 'Datenschutzerklärung', 'privacy' => 'Wir verwenden Ihre Daten ausschließlich zur Bearbeitung Ihrer Bestellung.', 'sent' => 'Ihre Bestellung wurde gesendet, und wir haben Ihnen eine Bestätigung geschickt. Nach Prüfung von Verfügbarkeit und Preis senden wir Ihnen einen Zahlungslink.', 'sent_no_receipt' => 'Ihre Bestellung wurde gesendet, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte senden Sie sie nicht erneut; bei Fragen schreiben Sie an Michal@laseryxtool.pl.', 'failed' => 'Ihre Bestellung konnte nicht gesendet werden. Versuchen Sie es erneut oder schreiben Sie an Michal@laseryxtool.pl.', 'invalid' => 'Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.', 'busy' => 'Bitte warten Sie einen Moment, bevor Sie eine weitere Bestellung senden.',
			'owner_subject' => 'Neue Bestellung: %s', 'customer_subject' => 'Wir haben Ihre Bestellung erhalten: %s', 'customer_body' => 'Vielen Dank für Ihre Bestellung. Wir bestätigen Verfügbarkeit, Endpreis und Lieferkosten und senden Ihnen anschließend einen Zahlungslink. Bis dahin müssen Sie nichts weiter tun.', 'price' => 'Unverbindlicher Preis', 'no_price' => 'Preis wird bestätigt', 'price_note' => 'Endpreis, MwSt. und Lieferkosten können abweichen.',
			'summary' => 'Zusammenfassung', 'contact' => 'Kontaktdaten', 'address' => 'Lieferadresse', 'extras' => 'Rechnung und Hinweise', 'summary_empty' => 'Wählen Sie oben eine Variante', 'next_steps' => 'Nach dem Absenden bestätigen wir Verfügbarkeit und Preis und senden Ihnen anschließend einen Zahlungslink.',
			'reply_note' => 'Fragen? Antworten Sie einfach auf diese E-Mail.',
			'add' => 'In den Warenkorb',
			'go_to_cart' => 'Zum Warenkorb',
			'set' => 'Im Set enthalten',
			'set_more' => 'Inhalt des Sets ansehen',
			'cart_title' => 'Warenkorb',
			'cart_intro' => 'Prüfen Sie die Produkte und geben Sie Ihre Bestelldaten an. Auf dieser Website wird nicht bezahlt: Nach Bestätigung von Verfügbarkeit und Preis senden wir Ihnen einen Zahlungslink.',
			'cart_empty' => 'Ihr Warenkorb ist leer.',
			'browse' => 'Produkte ansehen',
			'continue' => 'Weiter einkaufen',
			'cart_added' => 'Das Produkt wurde in den Warenkorb gelegt.', 'cart_limit' => 'Im Warenkorb sind jetzt 100 Stück dieses Produkts – mehr geht in einer Position nicht.',
			'cart_full' => 'Der Warenkorb kann höchstens %d verschiedene Produkte enthalten.',
			'cart_updated' => 'Der Warenkorb wurde aktualisiert.',
			'cart_removed' => 'Das Produkt wurde aus dem Warenkorb entfernt.',
			'cart_unavailable' => 'Diese Variante ist derzeit nicht verfügbar.',
			'cart_stale' => 'Die Angebotsdaten müssen aktualisiert werden. Entfernen Sie das Produkt oder kontaktieren Sie uns.',
			'remove' => 'Entfernen',
			'update' => 'Warenkorb aktualisieren',
			'total' => 'Summe (unverbindlich)',
			'items' => 'Produkte',
			'each' => '%s / Stk.', 'pieces' => '%d Stk.', 'quantity_hint' => 'Eine Zahl von 1 bis 100', 'unit_price' => 'Stückpreis', 'line_total' => 'Summe', 'sent_title' => 'Vielen Dank für Ihre Bestellung',
			'view_product' => 'Produkt ansehen', 'close' => 'Schließen',
		),
		'ru' => array(
			'title' => 'Заказать товар', 'intro' => 'Выберите вариант и количество, затем добавьте товар в корзину. Данные для заказа вы укажете в корзине.',
			'first_name' => 'Имя', 'last_name' => 'Фамилия', 'email' => 'Адрес электронной почты', 'phone' => 'Телефон (необязательно)', 'address1' => 'Улица и номер дома', 'address2' => 'Дополнительный адрес (необязательно)', 'city' => 'Город', 'zip' => 'Почтовый индекс', 'country' => 'Страна доставки', 'select_country' => 'Выберите страну', 'variant' => 'Вариант или конфигурация', 'unavailable' => 'Сейчас нет доступных вариантов.', 'stale' => 'Данные предложения требуют обновления. Свяжитесь с нами.', 'changed' => 'Предложение изменилось. Проверьте корзину и отправьте заказ ещё раз.', 'quantity' => 'Количество', 'invoice' => 'Данные для счёта (необязательно)', 'notes' => 'Дополнительная информация (необязательно)', 'submit' => 'Отправить заказ',
			'privacy_link' => 'Политика конфиденциальности', 'privacy' => 'Мы используем ваши данные только для обработки заказа.', 'sent' => 'Заказ отправлен, а на ваш адрес мы отправили подтверждение. После проверки наличия и цены мы пришлём ссылку для оплаты.', 'sent_no_receipt' => 'Заказ отправлен, но письмо с подтверждением отправить не удалось. Не отправляйте его повторно; по вопросам пишите на Michal@laseryxtool.pl.', 'failed' => 'Не удалось отправить заказ. Попробуйте ещё раз или напишите на Michal@laseryxtool.pl.', 'invalid' => 'Проверьте данные формы и попробуйте ещё раз.', 'busy' => 'Подождите немного перед повторной отправкой заказа.',
			'owner_subject' => 'Новый заказ: %s', 'customer_subject' => 'Мы получили ваш заказ: %s', 'customer_body' => 'Спасибо за заказ. Мы подтвердим наличие, окончательную цену и стоимость доставки, а затем пришлём ссылку для оплаты. До тех пор ничего делать не нужно.', 'price' => 'Ориентировочная цена', 'no_price' => 'Цена уточняется', 'price_note' => 'Окончательная цена, НДС и стоимость доставки могут отличаться.',
			'summary' => 'Итог', 'contact' => 'Контактные данные', 'address' => 'Адрес доставки', 'extras' => 'Счёт и комментарии', 'summary_empty' => 'Выберите вариант выше', 'next_steps' => 'После отправки заказа мы подтвердим наличие и цену, а затем пришлём ссылку для оплаты.',
			'reply_note' => 'Есть вопросы? Просто ответьте на это письмо.',
			'add' => 'В корзину',
			'go_to_cart' => 'Перейти в корзину',
			'set' => 'В комплекте',
			'set_more' => 'Что входит в комплект',
			'cart_title' => 'Корзина',
			'cart_intro' => 'Проверьте товары и укажите данные для заказа. Оплата на сайте не производится: после подтверждения наличия и цены мы пришлём ссылку для оплаты.',
			'cart_empty' => 'Корзина пуста.',
			'browse' => 'Смотреть товары',
			'continue' => 'Продолжить покупки',
			'cart_added' => 'Товар добавлен в корзину.', 'cart_limit' => 'В корзине теперь 100 шт. этого товара — это максимум для одной позиции.',
			'cart_full' => 'В корзине может быть не более %d разных товаров.',
			'cart_updated' => 'Корзина обновлена.',
			'cart_removed' => 'Товар удалён из корзины.',
			'cart_unavailable' => 'Этот вариант сейчас недоступен.',
			'cart_stale' => 'Данные предложения требуют обновления. Удалите товар или свяжитесь с нами.',
			'remove' => 'Удалить',
			'update' => 'Обновить корзину',
			'total' => 'Итого (ориентировочно)',
			'items' => 'Товары',
			'each' => '%s / шт.', 'pieces' => '%d шт.', 'quantity_hint' => 'Число от 1 до 100', 'unit_price' => 'Цена за штуку', 'line_total' => 'Сумма', 'sent_title' => 'Спасибо за заказ',
			'view_product' => 'Смотреть товар', 'close' => 'Закрыть',
		),
		'uk' => array(
			'title' => 'Замовити товар', 'intro' => 'Оберіть варіант і кількість, а потім додайте товар до кошика. Дані для замовлення ви вкажете в кошику.',
			'first_name' => 'Ім’я', 'last_name' => 'Прізвище', 'email' => 'Адреса електронної пошти', 'phone' => 'Телефон (необов’язково)', 'address1' => 'Вулиця і номер будинку', 'address2' => 'Додаткова адреса (необов’язково)', 'city' => 'Місто', 'zip' => 'Поштовий індекс', 'country' => 'Країна доставки', 'select_country' => 'Оберіть країну', 'variant' => 'Варіант або конфігурація', 'unavailable' => 'Зараз немає доступних варіантів.', 'stale' => 'Дані пропозиції потребують оновлення. Зв’яжіться з нами.', 'changed' => 'Пропозиція змінилася. Перевірте кошик і надішліть замовлення ще раз.', 'quantity' => 'Кількість', 'invoice' => 'Дані для рахунку (необов’язково)', 'notes' => 'Додаткова інформація (необов’язково)', 'submit' => 'Надіслати замовлення',
			'privacy_link' => 'Політика конфіденційності', 'privacy' => 'Ми використовуємо ваші дані лише для обробки замовлення.', 'sent' => 'Замовлення надіслано, а на вашу адресу ми надіслали підтвердження. Після перевірки наявності й ціни ми надішлемо посилання для оплати.', 'sent_no_receipt' => 'Замовлення надіслано, але лист із підтвердженням надіслати не вдалося. Не надсилайте його повторно; із запитаннями пишіть на Michal@laseryxtool.pl.', 'failed' => 'Не вдалося надіслати замовлення. Спробуйте ще раз або напишіть на Michal@laseryxtool.pl.', 'invalid' => 'Перевірте дані форми та спробуйте ще раз.', 'busy' => 'Зачекайте трохи перед повторним надсиланням замовлення.',
			'owner_subject' => 'Нове замовлення: %s', 'customer_subject' => 'Ми отримали ваше замовлення: %s', 'customer_body' => 'Дякуємо за замовлення. Ми підтвердимо наявність, остаточну ціну та вартість доставки, а потім надішлемо посилання для оплати. До того часу нічого робити не потрібно.', 'price' => 'Орієнтовна ціна', 'no_price' => 'Ціна уточнюється', 'price_note' => 'Остаточна ціна, ПДВ і вартість доставки можуть відрізнятися.',
			'summary' => 'Підсумок', 'contact' => 'Контактні дані', 'address' => 'Адреса доставки', 'extras' => 'Рахунок і примітки', 'summary_empty' => 'Оберіть варіант вище', 'next_steps' => 'Після надсилання замовлення ми підтвердимо наявність і ціну, а потім надішлемо посилання для оплати.',
			'reply_note' => 'Маєте запитання? Просто дайте відповідь на цей лист.',
			'add' => 'Додати до кошика',
			'go_to_cart' => 'Перейти до кошика',
			'set' => 'У комплекті',
			'set_more' => 'Що входить до комплекту',
			'cart_title' => 'Кошик',
			'cart_intro' => 'Перевірте товари та вкажіть дані для замовлення. Оплата на сайті не здійснюється: після підтвердження наявності й ціни ми надішлемо посилання для оплати.',
			'cart_empty' => 'Кошик порожній.',
			'browse' => 'Переглянути товари',
			'continue' => 'Продовжити покупки',
			'cart_added' => 'Товар додано до кошика.', 'cart_limit' => 'У кошику тепер 100 шт. цього товару — це максимум для однієї позиції.',
			'cart_full' => 'У кошику може бути не більше %d різних товарів.',
			'cart_updated' => 'Кошик оновлено.',
			'cart_removed' => 'Товар видалено з кошика.',
			'cart_unavailable' => 'Цей варіант зараз недоступний.',
			'cart_stale' => 'Дані пропозиції потребують оновлення. Видаліть товар або зв’яжіться з нами.',
			'remove' => 'Видалити',
			'update' => 'Оновити кошик',
			'total' => 'Разом (орієнтовно)',
			'items' => 'Товари',
			'each' => '%s / шт.', 'pieces' => '%d шт.', 'quantity_hint' => 'Число від 1 до 100', 'unit_price' => 'Ціна за штуку', 'line_total' => 'Сума', 'sent_title' => 'Дякуємо за замовлення',
			'view_product' => 'Переглянути товар', 'close' => 'Закрити',
		),
	);
	return isset( $texts[ $language ] ) ? $texts[ $language ] : $texts['pl'];
}

/** Order, cart and email texts as shown: built-in texts with the administrator's changes (Teksty strony). */
function lasery_xtool_order_texts( $language ) {
	$text = lasery_xtool_apply_text_overrides( 'order', $language, lasery_xtool_order_default_texts( $language ) );
	foreach ( array( 'failed', 'sent_no_receipt' ) as $key ) {
		$text[ $key ] = str_replace( 'Michal@laseryxtool.pl', lasery_xtool_order_contact_email(), $text[ $key ] );
	}
	$text['cart_full'] = sprintf( $text['cart_full'], LASERY_XTOOL_CART_MAX_LINES );
	return $text;
}

function lasery_xtool_order_language() {
	$language = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
	if ( ! is_string( $language ) || '' === $language ) {
		$language = get_locale();
	}
	$language = strtolower( substr( $language, 0, 2 ) );
	return isset( lasery_xtool_description_languages()[ $language ] ) ? $language : 'pl';
}

/** Checkout countries observed on xtool.eu on 2026-10-30; review as the store changes. */
function lasery_xtool_shipping_countries() {
	return array(
		'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'BE' => 'Belgium',
		'CH' => 'Switzerland', 'AT' => 'Austria', 'BO' => 'Bolivia', 'BG' => 'Bulgaria',
		'HR' => 'Croatia', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark',
		'EE' => 'Estonia', 'FI' => 'Finland', 'FR' => 'France', 'TF' => 'French Southern Territories',
		'DE' => 'Germany', 'GR' => 'Greece', 'HU' => 'Hungary', 'IS' => 'Iceland',
		'IE' => 'Ireland', 'LV' => 'Latvia', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
		'MT' => 'Malta', 'MD' => 'Moldova', 'MC' => 'Monaco', 'NO' => 'Norway',
		'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'SK' => 'Slovakia',
		'SI' => 'Slovenia', 'SE' => 'Sweden',
	);
}

/** Shipping countries as code => name in the given language, in that language's alphabetical order. */
function lasery_xtool_country_options( $language ) {
	$options = array();
	foreach ( array_keys( lasery_xtool_shipping_countries() ) as $code ) {
		$options[ $code ] = lasery_xtool_country_name( $code, $language );
	}
	$collator = class_exists( 'Collator' ) ? new Collator( lasery_xtool_icu_locale( $language ) ) : null;
	uasort( $options, $collator ? array( $collator, 'compare' ) : 'strcmp' );
	return $options;
}

function lasery_xtool_country_name( $code, $language ) {
	$countries = lasery_xtool_shipping_countries();
	$label = class_exists( 'Locale' ) ? Locale::getDisplayRegion( 'und_' . $code, $language ) : '';
	return is_string( $label ) && '' !== $label ? $label : ( isset( $countries[ $code ] ) ? $countries[ $code ] : $code );
}

/** Read source variants without accepting prices or availability from the browser. */
function lasery_xtool_order_variants( $post_id ) {
	$variants = get_post_meta( $post_id, '_lasery_source_variants', true );
	return is_array( $variants ) ? $variants : array();
}

function lasery_xtool_uses_source_variants( $post_id ) {
	return metadata_exists( 'post', $post_id, LASERY_XTOOL_SOURCE_ID ) || (bool) lasery_xtool_order_variants( $post_id );
}

function lasery_xtool_available_variants( $post_id ) {
	return array_values(
		array_filter(
			lasery_xtool_order_variants( $post_id ),
			function ( $variant ) {
				return is_array( $variant ) && isset( $variant['id'], $variant['title'], $variant['available'] ) && is_string( $variant['id'] ) && is_string( $variant['title'] ) && true === $variant['available'];
			}
		)
	);
}

/** Returns [amount, currency] of a source variant, or null. */
function lasery_xtool_variant_amount( $post_id, $variant ) {
	$currency = get_post_meta( $post_id, '_lasery_source_currency', true );
	$price = isset( $variant['price'] ) ? $variant['price'] : null;
	if ( ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! is_string( $price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $price ) || (float) $price <= 0 ) {
		return null;
	}
	return array( $price, $currency );
}

/**
 * The lowest offer shown to visitors as [amount, currency, varies]: fresh available
 * variants or the manual estimate. "varies" is true when variant prices differ.
 */
function lasery_xtool_lowest_price( $post_id ) {
	if ( ! lasery_xtool_uses_source_variants( $post_id ) ) {
		$amount = lasery_xtool_order_price_amount( $post_id );
		return $amount ? array( $amount[0], $amount[1], false ) : null;
	}
	if ( ! lasery_xtool_source_is_fresh( $post_id ) ) {
		return null;
	}
	$prices = array();
	foreach ( lasery_xtool_available_variants( $post_id ) as $variant ) {
		$amount = lasery_xtool_variant_amount( $post_id, $variant );
		if ( $amount ) {
			$prices[ number_format( (float) $amount[0], 2, '.', '' ) ] = $amount;
		}
	}
	if ( ! $prices ) {
		return null;
	}
	uksort( $prices, function ( $a, $b ) { return (float) $a <=> (float) $b; } );
	$lowest = reset( $prices );
	return array( $lowest[0], $lowest[1], count( $prices ) > 1 );
}

function lasery_xtool_source_is_fresh( $post_id ) {
	$fetched_at = get_post_meta( $post_id, '_lasery_source_updated_at', true );
	return is_string( $fetched_at ) && ctype_digit( $fetched_at ) && (int) $fetched_at <= time() && (int) $fetched_at >= time() - DAY_IN_SECONDS;
}

/** A theme icon for order and cart buttons. */
function lasery_xtool_order_icon( $name ) {
	return function_exists( 'lasery_xtool_icon' ) ? lasery_xtool_icon( $name ) : '';
}

/**
 * A quantity field: a whole number from 1 to 100 (leading zeros allowed), which the browser checks
 * also without JavaScript and explains with the title. A text field, so the mouse wheel cannot change it by accident.
 */
function lasery_xtool_quantity_field( $id, $name, $quantity, $text ) {
	printf( '<input class="quantity-input" id="%s" name="%s" type="text" inputmode="numeric" pattern="0*(?:[1-9][0-9]?|100)" maxlength="3" autocomplete="off" title="%s" value="%d" required data-quantity>', esc_attr( $id ), esc_attr( $name ), esc_attr( $text['quantity_hint'] ), (int) $quantity );
}

/** Prices as cents with their currency, which the script multiplies by the typed quantity. */
function lasery_xtool_amount_attributes( $amount ) {
	return $amount ? sprintf( ' data-amount="%d" data-currency="%s"', lasery_xtool_cents( $amount[0] ), esc_attr( $amount[1] ) ) : '';
}

/** The locale and texts with which the script formats prices of several pieces. */
function lasery_xtool_price_format_attributes( $language, $text ) {
	return sprintf( ' data-locale="%s" data-each="%s" data-pieces="%s"', esc_attr( str_replace( '_', '-', lasery_xtool_icu_locale( $language ) ) ), esc_attr( $text['each'] ), esc_attr( $text['pieces'] ) );
}

/** Variant and quantity on the product page; the customer details are entered in the cart. */
function lasery_xtool_render_order_form( $post_id ) {
	if ( LASERY_XTOOL_PRODUCT_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return;
	}
	$language = lasery_xtool_order_language();
	$text = lasery_xtool_order_texts( $language );
	$variants = lasery_xtool_order_variants( $post_id );
	$uses_source_variants = lasery_xtool_uses_source_variants( $post_id );
	$fresh = lasery_xtool_source_is_fresh( $post_id );
	$available_variants = $fresh ? lasery_xtool_available_variants( $post_id ) : array();
	$selected = 1 === count( $available_variants ) ? $available_variants[0] : null;
	// The only variant of a product without options has no name to choose by.
	$single = $selected && '' === lasery_xtool_variant_label( $selected );
	$selected_amount = $selected ? lasery_xtool_variant_amount( $post_id, $selected ) : ( $uses_source_variants ? null : lasery_xtool_order_price_amount( $post_id ) );
	$cart = lasery_xtool_cart_url( $language );
	$previews = false;
	?>
	<section class="order-form" id="zamow" aria-labelledby="order-form-title" data-set-previews>
		<header class="order-form-header">
			<h2 id="order-form-title"><?php echo esc_html( $text['title'] ); ?></h2>
			<p><?php echo esc_html( $text['intro'] ); ?></p>
		</header>
		<?php if ( isset( $_GET['cart'] ) && 'unavailable' === $_GET['cart'] ) : ?>
			<p class="order-form-notice order-form-notice--error" role="alert" data-cart-error><?php echo esc_html( $text['cart_unavailable'] ); ?></p>
		<?php endif; ?>
		<?php // With JavaScript the product is added without leaving the page; the confirmation shows here. ?>
		<p class="cart-toast" role="status" data-cart-toast></p>
		<form class="order-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-order-form<?php echo lasery_xtool_price_format_attributes( $language, $text ); ?>>
			<input type="hidden" name="action" value="lasery_xtool_cart_add">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $post_id ); ?>">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<div class="order-form-steps">
				<fieldset class="order-step">
					<?php if ( ! $uses_source_variants || $single ) : ?>
						<input type="hidden" name="variant" value="<?php echo esc_attr( $single ? $selected['id'] : '' ); ?>">
					<?php else : ?>
						<legend><?php echo esc_html( $text['variant'] ); ?></legend>
					<?php endif; ?>
					<?php if ( $available_variants && ! $single ) : ?>
						<div class="variant-options">
							<?php foreach ( $available_variants as $variant ) : ?>
								<?php $amount = lasery_xtool_variant_amount( $post_id, $variant ); ?>
								<?php $set = lasery_xtool_set_contents( $post_id, $variant ); ?>
								<?php $set_id = 'variant-set-' . $variant['id']; ?>
								<div class="variant-option">
									<label class="variant-option-choice">
										<input type="radio" name="variant" value="<?php echo esc_attr( $variant['id'] ); ?>" required<?php echo lasery_xtool_amount_attributes( $amount ); ?> <?php checked( $selected === $variant ); ?><?php if ( $set ) : ?> aria-describedby="<?php echo esc_attr( $set_id ); ?>"<?php endif; ?>>
										<span class="variant-option-title"><?php echo esc_html( lasery_xtool_variant_label( $variant ) ); ?></span>
										<span class="variant-option-price"><?php echo esc_html( $amount ? lasery_xtool_format_price( $amount[0], $amount[1], $language ) : $text['no_price'] ); ?></span>
										<?php if ( $set ) : ?><span class="variant-option-more" aria-hidden="true"><?php echo esc_html( $text['set_more'] ); ?></span><?php endif; ?>
									</label>
									<?php if ( $set ) : ?>
										<?php // Shown when the variant is chosen, also without JavaScript (CSS :has). ?>
										<div class="variant-set" id="<?php echo esc_attr( $set_id ); ?>">
											<p class="variant-set-title"><?php echo esc_html( $text['set'] ); ?></p>
											<ul class="variant-set-list">
												<?php foreach ( $set as $part ) : ?>
													<?php $part_title = lasery_xtool_order_product_title( $part['product'], $language ); ?>
													<?php // Other products of the set get a larger photo for the preview (script). ?>
													<?php $photo = $part['product'] !== $post_id ? (int) get_post_thumbnail_id( $part['product'] ) : 0; ?>
													<?php $previews = $previews || $photo; ?>
													<li class="variant-set-item"<?php if ( $photo ) : ?> data-set-preview="<?php echo esc_url( (string) wp_get_attachment_image_url( $photo, 'medium_large' ) ); ?>" data-set-preview-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $photo, 'medium_large' ) ); ?>"<?php endif; ?>>
														<?php echo get_the_post_thumbnail( $part['product'], 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
														<span class="variant-set-name">
															<?php if ( $part['product'] === $post_id ) : ?>
																<?php echo esc_html( $part_title ); ?>
															<?php else : ?>
																<a href="<?php echo esc_url( add_query_arg( 'lang', $language, get_permalink( $part['product'] ) ) ); ?>"><?php echo esc_html( $part_title ); ?></a>
															<?php endif; ?>
															<?php $part_label = lasery_xtool_variant_label( $part['variant'] ); ?>
															<?php if ( '' !== $part_label ) : ?><small><?php echo esc_html( $part_label ); ?></small><?php endif; ?>
														</span>
														<?php if ( $part['quantity'] > 1 ) : ?><span class="variant-set-quantity">× <?php echo esc_html( $part['quantity'] ); ?></span><?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php elseif ( $uses_source_variants ) : ?>
						<p class="order-form-notice order-form-notice--error"><?php echo esc_html( $variants && ! $fresh ? $text['stale'] : $text['unavailable'] ); ?></p>
					<?php endif; ?>
					<p class="field field-quantity"><label for="order-quantity"><?php echo esc_html( $text['quantity'] ); ?></label><?php lasery_xtool_quantity_field( 'order-quantity', 'quantity', 1, $text ); ?></p>
				</fieldset>
			</div>
			<aside class="order-summary" aria-labelledby="order-summary-title">
				<h3 id="order-summary-title"><?php echo esc_html( $text['summary'] ); ?></h3>
				<div class="order-summary-product">
					<?php echo get_the_post_thumbnail( $post_id, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
					<p><?php echo esc_html( lasery_xtool_order_product_title( $post_id, $language ) ); ?></p>
				</div>
				<dl class="order-summary-list">
					<?php if ( $available_variants && ! $single ) : ?>
						<dt><?php echo esc_html( $text['variant'] ); ?></dt>
						<dd data-order-summary-variant><?php echo esc_html( $selected ? lasery_xtool_variant_label( $selected ) : $text['summary_empty'] ); ?></dd>
					<?php endif; ?>
					<dt><?php echo esc_html( $text['price'] ); ?></dt>
					<?php // The price of the chosen quantity; with several pieces the script adds the price of one. ?>
					<dd class="order-summary-price" data-order-summary-price<?php echo lasery_xtool_amount_attributes( $selected_amount ); ?>><?php echo esc_html( $selected_amount ? lasery_xtool_format_price( $selected_amount[0], $selected_amount[1], $language ) : ( $available_variants ? '—' : $text['no_price'] ) ); ?></dd>
				</dl>
				<p class="order-summary-note"><?php echo esc_html( $text['price_note'] ); ?></p>
				<button type="submit" class="btn btn-primary btn-block" <?php disabled( $uses_source_variants && ! $available_variants ); ?>><?php echo esc_html( $text['add'] ); ?></button>
				<?php // After adding, the script turns the link into a button: the next step. ?>
				<?php if ( '' !== $cart ) : ?><p class="order-summary-note"><a href="<?php echo esc_url( $cart ); ?>" data-cart-link><?php echo esc_html( $text['go_to_cart'] ); ?></a></p><?php endif; ?>
				<p class="order-summary-note"><?php echo esc_html( $text['next_steps'] ); ?></p>
			</aside>
		</form>
		<?php if ( $previews ) : ?>
			<?php // Mouse: a card on hover or focus. The part's own link stays the way for keyboards and screen readers. ?>
			<div class="set-card" data-set-card hidden aria-hidden="true">
				<a class="set-card-link" href="#" tabindex="-1" data-set-link>
					<span class="set-card-media"><img alt="" sizes="256px" data-set-image></span>
					<span class="set-card-title" data-set-title></span>
					<span class="set-card-details" data-set-details></span>
					<span class="set-card-more"><?php echo esc_html( $text['view_product'] ); ?><?php echo lasery_xtool_order_icon( 'arrow-right' ); ?></span>
				</a>
			</div>
			<?php // Touch screens: a sheet after a tap, so choosing a set does not leave the page. ?>
			<dialog class="set-sheet" aria-labelledby="set-sheet-title" data-set-sheet>
				<div class="set-sheet-body">
					<button type="button" class="set-sheet-close" data-set-close><span class="visually-hidden"><?php echo esc_html( $text['close'] ); ?></span><?php echo lasery_xtool_order_icon( 'close' ); ?></button>
					<div class="set-sheet-media"><img alt="" sizes="(min-width: 600px) 440px, 100vw" data-set-image></div>
					<h3 id="set-sheet-title" data-set-title></h3>
					<p class="set-sheet-details" data-set-details></p>
					<a class="btn btn-primary btn-block" href="#" data-set-link><?php echo esc_html( $text['view_product'] ); ?><?php echo lasery_xtool_order_icon( 'arrow-right' ); ?></a>
				</div>
			</dialog>
		<?php endif; ?>
	</section>
	<?php
}

/** Reserve a rate limit slot atomically, including across concurrent requests. */
function lasery_xtool_order_reserve_ip( $ip ) {
	global $wpdb;
	$key = '_lasery_order_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	$now = time();
	$expires = $now + MINUTE_IN_SECONDS;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $key, $now ) );
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, (string) $expires ) );
	if ( 1 !== $inserted ) {
		return false;
	}
	// Clean up expired rate-limit entries without leaving visitor data in options.
	$prefix = $wpdb->esc_like( '_lasery_order_rate_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 20", $prefix, $now ) );
	return array( $key, $expires );
}

function lasery_xtool_order_release_ip( $reservation ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $reservation[0], (string) $reservation[1] ) );
}

/** Bounces of order messages go to the sender address. */
function lasery_xtool_order_mail_sender( $mailer ) {
	if ( is_email( $mailer->From ) ) {
		$mailer->Sender = $mailer->From;
	}
}

/**
 * Branded email. $email: language, title, intro, sections (heading and rows of
 * [label, plain-text value]), note, footer.
 */
function lasery_xtool_email_html( $email ) {
	$font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
	$cell = 'padding:9px 0;border-bottom:1px solid #e3e5ea;vertical-align:top;font-size:14px;line-height:1.5;';
	ob_start();
	?>
<!doctype html>
<html lang="<?php echo esc_attr( $email['language'] ); ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( $email['title'] ); ?></title></head>
<body style="margin:0;padding:0;background:#f3f4f6;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?php echo esc_html( $email['intro'] ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:16px;<?php echo esc_attr( $font ); ?>color:#0d0f12;">
<tr><td style="padding:22px 32px;background:#0d0f12;border-radius:16px 16px 0 0;font-size:18px;font-weight:700;color:#ffffff;"><span style="display:inline-block;width:10px;height:10px;margin-right:10px;border-radius:50%;background:#ff5a1f;"></span><?php echo esc_html( lasery_xtool_mail_from_name() ); ?></td></tr>
<tr><td style="padding:32px 32px 4px;">
<h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;font-weight:700;"><?php echo esc_html( $email['title'] ); ?></h1>
<p style="margin:0;font-size:15px;line-height:1.6;color:#4b515c;"><?php echo esc_html( $email['intro'] ); ?></p>
</td></tr>
<?php foreach ( $email['sections'] as $section ) : ?>
<tr><td style="padding:24px 32px 0;">
<p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#c2410c;"><?php echo esc_html( $section['heading'] ); ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<?php foreach ( $section['rows'] as $row ) : ?>
<tr><td width="38%" style="<?php echo esc_attr( $cell ); ?>padding-right:12px;color:#5b616c;"><?php echo esc_html( $row[0] ); ?></td><td style="<?php echo esc_attr( $cell ); ?>font-weight:600;"><?php echo nl2br( esc_html( '' !== $row[1] ? $row[1] : '—' ) ); ?></td></tr>
<?php endforeach; ?>
</table>
</td></tr>
<?php endforeach; ?>
<tr><td style="padding:20px 32px 32px;font-size:12px;line-height:1.6;color:#5b616c;"><?php echo nl2br( esc_html( $email['note'] ) ); ?></td></tr>
</table>
<p style="margin:16px 0 0;<?php echo esc_attr( $font ); ?>font-size:12px;line-height:1.6;color:#8b909a;"><?php echo nl2br( esc_html( $email['footer'] ) ); ?></p>
</td></tr>
</table>
</body>
</html>
	<?php
	return ob_get_clean();
}

function lasery_xtool_email_text( $email ) {
	$text = $email['title'] . "\n\n" . $email['intro'] . "\n";
	foreach ( $email['sections'] as $section ) {
		$text .= "\n" . $section['heading'] . "\n";
		foreach ( $section['rows'] as $row ) {
			$text .= $row[0] . ': ' . ( '' !== $row[1] ? $row[1] : '—' ) . "\n";
		}
	}
	return $text . "\n" . $email['note'] . "\n\n--\n" . $email['footer'] . "\n";
}

function lasery_xtool_order_mail( $to, $email, $headers ) {
	$alternative = static function ( $mailer ) use ( $email ) {
		$mailer->AltBody = lasery_xtool_email_text( $email );
	};
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	add_action( 'phpmailer_init', 'lasery_xtool_order_mail_sender' );
	add_action( 'phpmailer_init', $alternative );
	try {
		return wp_mail( $to, $email['title'], lasery_xtool_email_html( $email ), $headers );
	} finally {
		remove_action( 'phpmailer_init', 'lasery_xtool_order_mail_sender' );
		remove_action( 'phpmailer_init', $alternative );
	}
}
