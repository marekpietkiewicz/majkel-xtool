<?php
/** Product enquiry form. Loaded by the Lasery xTool catalogue plugin. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The form contains a nonce and a submission result, so a shared page cache
// must not serve a stale product page or another visitor's result.
add_action( 'template_redirect', 'lasery_xtool_order_disable_product_cache', 0 );
function lasery_xtool_order_disable_product_cache() {
	if ( ! is_singular( LASERY_XTOOL_PRODUCT_TYPE ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'Product order form' );
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

// Site emails show the shop name instead of WordPress's default sender name.
add_filter( 'wp_mail_from_name', 'lasery_xtool_filter_mail_from_name' );
function lasery_xtool_filter_mail_from_name( $name ) {
	return 'WordPress' === $name ? lasery_xtool_mail_from_name() : $name;
}

add_action( 'admin_init', 'lasery_xtool_register_order_settings' );
function lasery_xtool_register_order_settings() {
	register_setting( 'lasery_xtool_order_settings', 'lasery_xtool_order_recipient', array(
		'type' => 'string',
		'sanitize_callback' => 'lasery_xtool_sanitize_order_recipient',
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
	<p>Ustaw adres odbiorcy zamówień i nazwę nadawcy wiadomości. Podczas testów użyj własnej skrzynki, a przed publikacją wpisz właściwy adres obsługi zamówień.</p>
	<form action="options.php" method="post">
		<?php settings_fields( 'lasery_xtool_order_settings' ); ?>
		<p><label for="lasery-order-recipient">Adres odbiorcy zamówień</label></p>
		<input id="lasery-order-recipient" name="lasery_xtool_order_recipient" type="email" class="regular-text" required value="<?php echo esc_attr( lasery_xtool_order_recipient() ); ?>">
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
			return $formatted;
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
function lasery_xtool_order_texts( $language ) {
	$texts = array(
		'pl' => array(
			'title' => 'Zamów produkt', 'intro' => 'Prześlij nam dane zamówienia. Skontaktujemy się z Tobą, aby potwierdzić szczegóły i przekazać link do płatności.',
			'first_name' => 'Imię', 'last_name' => 'Nazwisko', 'email' => 'Adres e-mail', 'phone' => 'Telefon (opcjonalnie)', 'address1' => 'Ulica i numer', 'address2' => 'Dodatkowy adres (opcjonalnie)', 'city' => 'Miasto', 'zip' => 'Kod pocztowy', 'country' => 'Kraj dostawy', 'select_country' => 'Wybierz kraj', 'variant' => 'Wariant lub konfiguracja', 'unavailable' => 'Obecnie brak dostępnych wariantów.', 'stale' => 'Dane oferty wymagają aktualizacji. Skontaktuj się z nami.', 'changed' => 'Dane oferty zmieniły się. Odśwież stronę i wybierz wariant ponownie.', 'quantity' => 'Liczba sztuk', 'invoice' => 'Dane do faktury (opcjonalnie)', 'notes' => 'Dodatkowe informacje (opcjonalnie)', 'submit' => 'Wyślij zamówienie',
			'privacy_link' => 'Polityka prywatności', 'privacy' => 'Dane wykorzystamy do obsługi zamówienia. Link do formularza xTool zawierający Twoje dane może zostać zapisany w historii przeglądarki i logach.', 'sent' => 'Dziękujemy! Twoje zgłoszenie zostało wysłane. Skontaktujemy się z Tobą w sprawie szczegółów i linku do płatności.', 'sent_no_receipt' => 'Zgłoszenie zostało wysłane, ale nie udało się wysłać potwierdzenia na Twój adres. Nie wysyłaj zamówienia ponownie; w razie pytań napisz na Michal@laseryxtool.pl.', 'failed' => 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie lub napisz na Michal@laseryxtool.pl.', 'invalid' => 'Sprawdź dane formularza i spróbuj ponownie.', 'busy' => 'Odczekaj chwilę przed ponownym wysłaniem zgłoszenia.',
			'owner_subject' => 'Nowe zamówienie: %s', 'customer_subject' => 'Otrzymaliśmy zgłoszenie: %s', 'customer_body' => 'Dziękujemy za przesłanie zgłoszenia. Skontaktujemy się z Tobą, aby potwierdzić szczegóły zamówienia i przekazać link do płatności.', 'product' => 'Produkt', 'details' => 'Szczegóły zamówienia', 'price' => 'Cena orientacyjna', 'no_price' => 'Cena do potwierdzenia', 'price_note' => 'Ostateczna cena, VAT i koszt dostawy mogą się różnić.',
			'summary' => 'Podsumowanie', 'contact' => 'Dane kontaktowe', 'address' => 'Adres dostawy', 'extras' => 'Faktura i uwagi', 'summary_empty' => 'Wybierz wariant powyżej', 'next_steps' => 'Po wysłaniu formularza potwierdzimy dostępność i cenę, a następnie wyślemy link do płatności.',
			'view_product' => 'Zobacz produkt', 'reply_note' => 'Masz pytania? Odpowiedz na tę wiadomość.',
		),
		'en' => array(
			'title' => 'Order this product', 'intro' => 'Send us your order details. We will contact you to confirm the details and provide a payment link.',
			'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number (optional)', 'address1' => 'Street address', 'address2' => 'Additional address (optional)', 'city' => 'City', 'zip' => 'Postal code', 'country' => 'Delivery country', 'select_country' => 'Choose a country', 'variant' => 'Variant or configuration', 'unavailable' => 'No variants are currently available.', 'stale' => 'The offer needs updating. Please contact us.', 'changed' => 'The offer has changed. Refresh the page and select a variant again.', 'quantity' => 'Quantity', 'invoice' => 'Billing details (optional)', 'notes' => 'Additional information (optional)', 'submit' => 'Send order request',
			'privacy_link' => 'Privacy policy', 'privacy' => 'We use your details to handle your order. A link to the xTool checkout containing your data may be stored in browser history and logs.', 'sent' => 'Thank you! Your request has been sent. We will contact you about the details and payment link.', 'sent_no_receipt' => 'Your request was sent, but we could not send your confirmation email. Please do not resubmit; contact Michal@laseryxtool.pl with any questions.', 'failed' => 'Your request could not be sent. Please try again or email Michal@laseryxtool.pl.', 'invalid' => 'Please check the form and try again.', 'busy' => 'Please wait a moment before sending another request.',
			'owner_subject' => 'New order request: %s', 'customer_subject' => 'We received your request: %s', 'customer_body' => 'Thank you for your request. We will contact you to confirm the order details and provide a payment link.', 'product' => 'Product', 'details' => 'Order details', 'price' => 'Estimated price', 'no_price' => 'Price to be confirmed', 'price_note' => 'The final price, VAT and delivery costs may differ.',
			'summary' => 'Summary', 'contact' => 'Contact details', 'address' => 'Delivery address', 'extras' => 'Invoice and notes', 'summary_empty' => 'Choose a variant above', 'next_steps' => 'After you send the form, we will confirm availability and price, then send you a payment link.',
			'view_product' => 'View product', 'reply_note' => 'Questions? Just reply to this email.',
		),
		'de' => array(
			'title' => 'Produkt bestellen', 'intro' => 'Senden Sie uns Ihre Bestelldaten. Wir kontaktieren Sie, um die Einzelheiten zu bestätigen und Ihnen einen Zahlungslink zu senden.',
			'first_name' => 'Vorname', 'last_name' => 'Nachname', 'email' => 'E-Mail-Adresse', 'phone' => 'Telefonnummer (optional)', 'address1' => 'Straße und Hausnummer', 'address2' => 'Adresszusatz (optional)', 'city' => 'Ort', 'zip' => 'Postleitzahl', 'country' => 'Lieferland', 'select_country' => 'Land auswählen', 'variant' => 'Variante oder Konfiguration', 'unavailable' => 'Derzeit sind keine Varianten verfügbar.', 'stale' => 'Die Angebotsdaten müssen aktualisiert werden. Bitte kontaktieren Sie uns.', 'changed' => 'Das Angebot hat sich geändert. Laden Sie die Seite neu und wählen Sie die Variante erneut.', 'quantity' => 'Anzahl', 'invoice' => 'Rechnungsdaten (optional)', 'notes' => 'Weitere Informationen (optional)', 'submit' => 'Bestellanfrage senden',
			'privacy_link' => 'Datenschutzerklärung', 'privacy' => 'Wir verwenden Ihre Daten zur Bearbeitung Ihrer Bestellung. Ein Link zum xTool-Checkout mit Ihren Daten kann im Browserverlauf und in Protokollen gespeichert werden.', 'sent' => 'Vielen Dank! Ihre Anfrage wurde gesendet. Wir kontaktieren Sie wegen der Einzelheiten und des Zahlungslinks.', 'sent_no_receipt' => 'Ihre Anfrage wurde gesendet, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte senden Sie die Anfrage nicht erneut; bei Fragen schreiben Sie an Michal@laseryxtool.pl.', 'failed' => 'Ihre Anfrage konnte nicht gesendet werden. Versuchen Sie es erneut oder schreiben Sie an Michal@laseryxtool.pl.', 'invalid' => 'Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.', 'busy' => 'Bitte warten Sie einen Moment, bevor Sie eine weitere Anfrage senden.',
			'owner_subject' => 'Neue Bestellanfrage: %s', 'customer_subject' => 'Wir haben Ihre Anfrage erhalten: %s', 'customer_body' => 'Vielen Dank für Ihre Anfrage. Wir kontaktieren Sie, um die Bestelldetails zu bestätigen und Ihnen einen Zahlungslink zu senden.', 'product' => 'Produkt', 'details' => 'Bestelldetails', 'price' => 'Unverbindlicher Preis', 'no_price' => 'Preis wird bestätigt', 'price_note' => 'Endpreis, MwSt. und Lieferkosten können abweichen.',
			'summary' => 'Zusammenfassung', 'contact' => 'Kontaktdaten', 'address' => 'Lieferadresse', 'extras' => 'Rechnung und Hinweise', 'summary_empty' => 'Wählen Sie oben eine Variante', 'next_steps' => 'Nach dem Absenden bestätigen wir Verfügbarkeit und Preis und senden Ihnen anschließend einen Zahlungslink.',
			'view_product' => 'Produkt ansehen', 'reply_note' => 'Fragen? Antworten Sie einfach auf diese E-Mail.',
		),
		'ru' => array(
			'title' => 'Заказать товар', 'intro' => 'Отправьте нам данные заказа. Мы свяжемся с вами, чтобы подтвердить детали и предоставить ссылку для оплаты.',
			'first_name' => 'Имя', 'last_name' => 'Фамилия', 'email' => 'Адрес электронной почты', 'phone' => 'Телефон (необязательно)', 'address1' => 'Улица и номер дома', 'address2' => 'Дополнительный адрес (необязательно)', 'city' => 'Город', 'zip' => 'Почтовый индекс', 'country' => 'Страна доставки', 'select_country' => 'Выберите страну', 'variant' => 'Вариант или конфигурация', 'unavailable' => 'Сейчас нет доступных вариантов.', 'stale' => 'Данные предложения требуют обновления. Свяжитесь с нами.', 'changed' => 'Предложение изменилось. Обновите страницу и выберите вариант снова.', 'quantity' => 'Количество', 'invoice' => 'Данные для счёта (необязательно)', 'notes' => 'Дополнительная информация (необязательно)', 'submit' => 'Отправить заявку',
			'privacy_link' => 'Политика конфиденциальности', 'privacy' => 'Мы используем данные для обработки заказа. Ссылка на оформление xTool с вашими данными может сохраняться в истории браузера и журналах.', 'sent' => 'Спасибо! Ваша заявка отправлена. Мы свяжемся с вами по поводу деталей и ссылки для оплаты.', 'sent_no_receipt' => 'Заявка отправлена, но письмо с подтверждением отправить не удалось. Не отправляйте заявку повторно; по вопросам пишите на Michal@laseryxtool.pl.', 'failed' => 'Не удалось отправить заявку. Попробуйте ещё раз или напишите на Michal@laseryxtool.pl.', 'invalid' => 'Проверьте данные формы и попробуйте ещё раз.', 'busy' => 'Подождите немного перед повторной отправкой заявки.',
			'owner_subject' => 'Новая заявка на заказ: %s', 'customer_subject' => 'Мы получили вашу заявку: %s', 'customer_body' => 'Спасибо за заявку. Мы свяжемся с вами, чтобы подтвердить детали заказа и предоставить ссылку для оплаты.', 'product' => 'Товар', 'details' => 'Данные заказа', 'price' => 'Ориентировочная цена', 'no_price' => 'Цена уточняется', 'price_note' => 'Окончательная цена, НДС и стоимость доставки могут отличаться.',
			'summary' => 'Итог', 'contact' => 'Контактные данные', 'address' => 'Адрес доставки', 'extras' => 'Счёт и комментарии', 'summary_empty' => 'Выберите вариант выше', 'next_steps' => 'После отправки формы мы подтвердим наличие и цену, а затем пришлём ссылку для оплаты.',
			'view_product' => 'Посмотреть товар', 'reply_note' => 'Есть вопросы? Просто ответьте на это письмо.',
		),
		'uk' => array(
			'title' => 'Замовити товар', 'intro' => 'Надішліть нам дані замовлення. Ми зв’яжемося з вами, щоб підтвердити деталі та надати посилання для оплати.',
			'first_name' => 'Ім’я', 'last_name' => 'Прізвище', 'email' => 'Адреса електронної пошти', 'phone' => 'Телефон (необов’язково)', 'address1' => 'Вулиця і номер будинку', 'address2' => 'Додаткова адреса (необов’язково)', 'city' => 'Місто', 'zip' => 'Поштовий індекс', 'country' => 'Країна доставки', 'select_country' => 'Оберіть країну', 'variant' => 'Варіант або конфігурація', 'unavailable' => 'Зараз немає доступних варіантів.', 'stale' => 'Дані пропозиції потребують оновлення. Зв’яжіться з нами.', 'changed' => 'Пропозиція змінилася. Оновіть сторінку та оберіть варіант знову.', 'quantity' => 'Кількість', 'invoice' => 'Дані для рахунку (необов’язково)', 'notes' => 'Додаткова інформація (необов’язково)', 'submit' => 'Надіслати заявку',
			'privacy_link' => 'Політика конфіденційності', 'privacy' => 'Ми використовуємо дані для обробки замовлення. Посилання на оформлення xTool з вашими даними може зберігатися в історії браузера та журналах.', 'sent' => 'Дякуємо! Вашу заявку надіслано. Ми зв’яжемося з вами щодо деталей і посилання для оплати.', 'sent_no_receipt' => 'Заявку надіслано, але лист із підтвердженням надіслати не вдалося. Не надсилайте заявку повторно; із запитаннями пишіть на Michal@laseryxtool.pl.', 'failed' => 'Не вдалося надіслати заявку. Спробуйте ще раз або напишіть на Michal@laseryxtool.pl.', 'invalid' => 'Перевірте дані форми та спробуйте ще раз.', 'busy' => 'Зачекайте трохи перед повторним надсиланням заявки.',
			'owner_subject' => 'Нова заявка на замовлення: %s', 'customer_subject' => 'Ми отримали вашу заявку: %s', 'customer_body' => 'Дякуємо за заявку. Ми зв’яжемося з вами, щоб підтвердити деталі замовлення та надати посилання для оплати.', 'product' => 'Товар', 'details' => 'Дані замовлення', 'price' => 'Орієнтовна ціна', 'no_price' => 'Ціна уточнюється', 'price_note' => 'Остаточна ціна, ПДВ і вартість доставки можуть відрізнятися.',
			'summary' => 'Підсумок', 'contact' => 'Контактні дані', 'address' => 'Адреса доставки', 'extras' => 'Рахунок і примітки', 'summary_empty' => 'Оберіть варіант вище', 'next_steps' => 'Після надсилання форми ми підтвердимо наявність і ціну, а потім надішлемо посилання для оплати.',
			'view_product' => 'Переглянути товар', 'reply_note' => 'Маєте запитання? Просто дайте відповідь на цей лист.',
		),
	);
	$text = isset( $texts[ $language ] ) ? $texts[ $language ] : $texts['pl'];
	foreach ( array( 'failed', 'sent_no_receipt' ) as $key ) {
		$text[ $key ] = str_replace( 'Michal@laseryxtool.pl', lasery_xtool_order_contact_email(), $text[ $key ] );
	}
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

function lasery_xtool_variant_snapshot( $post_id, $variants ) {
	return hash_hmac( 'sha256', wp_json_encode( array( $variants, get_post_meta( $post_id, '_lasery_source_currency', true ), get_post_meta( $post_id, '_lasery_source_updated_at', true ) ) ), wp_salt( 'nonce' ) );
}

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
	$manual_price = $uses_source_variants ? null : lasery_xtool_order_price_amount( $post_id );
	$result = isset( $_GET['order_result'] ) && is_string( $_GET['order_result'] ) ? sanitize_key( wp_unslash( $_GET['order_result'] ) ) : '';
	$title = lasery_xtool_order_product_title( $post_id, $language );
	$selected = 1 === count( $available_variants ) ? $available_variants[0] : null;
	$selected_amount = $selected ? lasery_xtool_variant_amount( $post_id, $selected ) : $manual_price;
	?>
	<section class="order-form" id="zamow" aria-labelledby="order-form-title">
		<header class="order-form-header">
			<h2 id="order-form-title"><?php echo esc_html( $text['title'] ); ?></h2>
			<p><?php echo esc_html( $text['intro'] ); ?></p>
		</header>
		<?php if ( in_array( $result, array( 'sent', 'sent_no_receipt', 'failed', 'invalid', 'busy', 'changed' ), true ) ) : ?>
			<p class="order-form-notice order-form-notice--<?php echo esc_attr( in_array( $result, array( 'sent', 'sent_no_receipt' ), true ) ? 'success' : 'error' ); ?>" role="status"><?php echo esc_html( $text[ $result ] ); ?></p>
		<?php endif; ?>
		<form class="order-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-order-form>
			<input type="hidden" name="action" value="lasery_xtool_order">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $post_id ); ?>">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<?php if ( $uses_source_variants ) : ?><input type="hidden" name="snapshot" value="<?php echo esc_attr( lasery_xtool_variant_snapshot( $post_id, $variants ) ); ?>"><?php endif; ?>
			<?php wp_nonce_field( 'lasery_xtool_order_' . $post_id ); ?>
			<div class="order-form-trap" aria-hidden="true"><label for="order-website">Website</label><input id="order-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
			<div class="order-form-steps">
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">1</span><?php echo esc_html( $text['variant'] ); ?></legend>
					<?php if ( ! $uses_source_variants ) : ?>
						<p class="field"><label class="visually-hidden" for="order-variant"><?php echo esc_html( $text['variant'] ); ?></label><input id="order-variant" name="variant" type="text" maxlength="250" required></p>
					<?php elseif ( $available_variants ) : ?>
						<div class="variant-options">
							<?php foreach ( $available_variants as $variant ) : ?>
								<?php $amount = lasery_xtool_variant_amount( $post_id, $variant ); ?>
								<?php $variant_price = $amount ? lasery_xtool_format_price( $amount[0], $amount[1], $language ) : $text['no_price']; ?>
								<label class="variant-option">
									<input type="radio" name="variant" value="<?php echo esc_attr( $variant['id'] ); ?>" required data-price="<?php echo esc_attr( $variant_price ); ?>" <?php checked( $selected === $variant ); ?>>
									<span class="variant-option-title"><?php echo esc_html( str_replace( ' / ', ' · ', $variant['title'] ) ); ?></span>
									<span class="variant-option-price"><?php echo esc_html( $variant_price ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="order-form-notice order-form-notice--error"><?php echo esc_html( $variants && ! $fresh ? $text['stale'] : $text['unavailable'] ); ?></p>
					<?php endif; ?>
					<p class="field field-quantity"><label for="order-quantity"><?php echo esc_html( $text['quantity'] ); ?></label><input id="order-quantity" name="quantity" type="number" min="1" max="100" value="1" required></p>
				</fieldset>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">2</span><?php echo esc_html( $text['contact'] ); ?></legend>
					<div class="field-grid">
						<p class="field"><label for="order-first-name"><?php echo esc_html( $text['first_name'] ); ?></label><input id="order-first-name" name="first_name" type="text" maxlength="150" autocomplete="given-name" required></p>
						<p class="field"><label for="order-last-name"><?php echo esc_html( $text['last_name'] ); ?></label><input id="order-last-name" name="last_name" type="text" maxlength="150" autocomplete="family-name" required></p>
						<p class="field"><label for="order-email"><?php echo esc_html( $text['email'] ); ?></label><input id="order-email" name="email" type="email" maxlength="254" autocomplete="email" required></p>
						<p class="field"><label for="order-phone"><?php echo esc_html( $text['phone'] ); ?></label><input id="order-phone" name="phone" type="tel" maxlength="50" autocomplete="tel"></p>
					</div>
				</fieldset>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">3</span><?php echo esc_html( $text['address'] ); ?></legend>
					<div class="field-grid">
						<p class="field field-full"><label for="order-address1"><?php echo esc_html( $text['address1'] ); ?></label><input id="order-address1" name="address1" type="text" maxlength="250" autocomplete="address-line1" required></p>
						<p class="field field-full"><label for="order-address2"><?php echo esc_html( $text['address2'] ); ?></label><input id="order-address2" name="address2" type="text" maxlength="250" autocomplete="address-line2"></p>
						<p class="field"><label for="order-zip"><?php echo esc_html( $text['zip'] ); ?></label><input id="order-zip" name="zip" type="text" maxlength="30" autocomplete="postal-code" required></p>
						<p class="field"><label for="order-city"><?php echo esc_html( $text['city'] ); ?></label><input id="order-city" name="city" type="text" maxlength="150" autocomplete="address-level2" required></p>
						<p class="field field-full"><label for="order-country"><?php echo esc_html( $text['country'] ); ?></label>
							<select id="order-country" name="country" autocomplete="country" required>
								<option value=""><?php echo esc_html( $text['select_country'] ); ?></option>
								<?php foreach ( array_keys( lasery_xtool_shipping_countries() ) as $code ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( lasery_xtool_country_name( $code, $language ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					</div>
				</fieldset>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">4</span><?php echo esc_html( $text['extras'] ); ?></legend>
					<div class="field-grid">
						<p class="field field-full"><label for="order-invoice"><?php echo esc_html( $text['invoice'] ); ?></label><textarea id="order-invoice" name="invoice" maxlength="1000" rows="3"></textarea></p>
						<p class="field field-full"><label for="order-notes"><?php echo esc_html( $text['notes'] ); ?></label><textarea id="order-notes" name="notes" maxlength="1000" rows="3"></textarea></p>
					</div>
				</fieldset>
			</div>
			<aside class="order-summary" aria-labelledby="order-summary-title">
				<h3 id="order-summary-title"><?php echo esc_html( $text['summary'] ); ?></h3>
				<div class="order-summary-product">
					<?php echo get_the_post_thumbnail( $post_id, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
					<p><?php echo esc_html( $title ); ?></p>
				</div>
				<dl class="order-summary-list">
					<?php if ( $available_variants ) : ?>
						<dt><?php echo esc_html( $text['variant'] ); ?></dt>
						<dd data-order-summary-variant><?php echo esc_html( $selected ? str_replace( ' / ', ' · ', $selected['title'] ) : $text['summary_empty'] ); ?></dd>
					<?php endif; ?>
					<dt><?php echo esc_html( $text['price'] ); ?></dt>
					<dd class="order-summary-price" data-order-summary-price><?php echo esc_html( $selected_amount ? lasery_xtool_format_price( $selected_amount[0], $selected_amount[1], $language ) : ( $uses_source_variants && $available_variants ? '—' : $text['no_price'] ) ); ?></dd>
				</dl>
				<p class="order-summary-note"><?php echo esc_html( $text['price_note'] ); ?></p>
				<button type="submit" class="btn btn-primary btn-block" <?php disabled( $uses_source_variants && ! $available_variants ); ?>><?php echo esc_html( $text['submit'] ); ?></button>
				<p class="order-summary-note"><?php echo esc_html( $text['next_steps'] ); ?></p>
				<p class="order-summary-privacy"><?php echo esc_html( $text['privacy'] ); ?> <?php $privacy_url = get_privacy_policy_url(); if ( $privacy_url ) : ?><?php $privacy_url = add_query_arg( 'lang', $language, $privacy_url ); ?><a href="<?php echo esc_url( $privacy_url ); ?>"><?php echo esc_html( $text['privacy_link'] ); ?></a><?php endif; ?></p>
			</aside>
		</form>
	</section>
	<?php
}

function lasery_xtool_order_redirect( $post_id, $result ) {
	$url = get_permalink( $post_id );
	if ( ! is_string( $url ) || '' === $url ) {
		$url = home_url( '/' );
	}
	wp_safe_redirect( add_query_arg( 'order_result', $result, $url ) . '#zamow' );
	exit;
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

/** Use the site's domain for the envelope sender of order messages only. */
function lasery_xtool_order_mail_sender( $mailer ) {
	$domain = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	if ( ! is_string( $domain ) ) {
		return;
	}
	$domain = preg_replace( '/^www\./i', '', $domain );
	$sender = 'wordpress@' . $domain;
	if ( is_email( $sender ) ) {
		$mailer->Sender = $sender;
	}
}

/**
 * Branded email. $email: language, title, intro, sections (heading and rows of
 * [label, plain-text value]), button ([label, url] or null), note, footer.
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
<?php if ( $email['button'] ) : ?>
<tr><td style="padding:32px 32px 4px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-radius:999px;background:#0d0f12;">
<a href="<?php echo esc_url( $email['button'][1] ); ?>" target="_blank" style="display:inline-block;padding:15px 28px;border-radius:999px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;"><?php echo esc_html( $email['button'][0] ); ?> &rarr;</a>
</td></tr></table>
</td></tr>
<?php endif; ?>
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
	if ( $email['button'] ) {
		$text .= "\n" . $email['button'][0] . ":\n" . $email['button'][1] . "\n";
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

add_action( 'admin_post_lasery_xtool_order', 'lasery_xtool_handle_order' );
add_action( 'admin_post_nopriv_lasery_xtool_order', 'lasery_xtool_handle_order' );
function lasery_xtool_handle_order() {
	$post_id = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	if ( ! $post_id || LASERY_XTOOL_PRODUCT_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		wp_die( 'Invalid product.', '', array( 'response' => 400 ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lasery_xtool_order_' . $post_id ) ) {
		lasery_xtool_order_redirect( $post_id, 'invalid' );
	}
	if ( ! isset( $_POST['website'] ) || ! is_string( $_POST['website'] ) || '' !== trim( wp_unslash( $_POST['website'] ) ) ) {
		lasery_xtool_order_redirect( $post_id, 'failed' );
	}
	$fields = array();
	foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'address1', 'address2', 'city', 'zip', 'country', 'variant', 'quantity', 'invoice', 'notes', 'language' ) as $field ) {
		if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
			lasery_xtool_order_redirect( $post_id, 'invalid' );
		}
		$fields[ $field ] = trim( wp_unslash( $_POST[ $field ] ) );
	}
	$limits = array( 'first_name' => 600, 'last_name' => 600, 'email' => 254, 'phone' => 200, 'address1' => 1000, 'address2' => 1000, 'city' => 600, 'zip' => 120, 'country' => 600, 'variant' => 1000, 'invoice' => 4000, 'notes' => 4000 );
	foreach ( $limits as $field => $limit ) {
		if ( strlen( $fields[ $field ] ) > $limit ) {
			lasery_xtool_order_redirect( $post_id, 'invalid' );
		}
	}
	if ( ! is_email( $fields['email'] ) ) {
		lasery_xtool_order_redirect( $post_id, 'invalid' );
	}
	foreach ( array( 'first_name', 'last_name', 'address1', 'address2', 'city', 'zip', 'country' ) as $field ) {
		$fields[ $field ] = sanitize_text_field( $fields[ $field ] );
	}
	$fields['email'] = sanitize_email( $fields['email'] );
	$fields['phone'] = sanitize_text_field( $fields['phone'] );
	$fields['variant'] = sanitize_text_field( $fields['variant'] );
	foreach ( array( 'invoice', 'notes' ) as $field ) {
		$fields[ $field ] = sanitize_textarea_field( $fields[ $field ] );
	}
	$countries = lasery_xtool_shipping_countries();
	if ( '' === $fields['first_name'] || '' === $fields['last_name'] || '' === $fields['address1'] || '' === $fields['city'] || '' === $fields['zip'] || ! isset( $countries[ $fields['country'] ] ) || '' === $fields['variant'] || ! is_email( $fields['email'] ) || ! preg_match( '/^(?:[1-9]|[1-9][0-9]|100)$/D', $fields['quantity'] ) || ! isset( lasery_xtool_description_languages()[ $fields['language'] ] ) ) {
		lasery_xtool_order_redirect( $post_id, 'invalid' );
	}
	$variants = lasery_xtool_order_variants( $post_id );
	$selected_variant = null;
	$variant_title = $fields['variant'];
	$uses_source_variants = lasery_xtool_uses_source_variants( $post_id );
	if ( ! $uses_source_variants && isset( $_POST['snapshot'] ) ) {
		lasery_xtool_order_redirect( $post_id, 'changed' );
	}
	if ( $uses_source_variants ) {
		if ( ! $variants ) {
			lasery_xtool_order_redirect( $post_id, 'invalid' );
		}
		$snapshot = isset( $_POST['snapshot'] ) && is_string( $_POST['snapshot'] ) ? wp_unslash( $_POST['snapshot'] ) : '';
		if ( ! lasery_xtool_source_is_fresh( $post_id ) || ! hash_equals( lasery_xtool_variant_snapshot( $post_id, $variants ), $snapshot ) ) {
			lasery_xtool_order_redirect( $post_id, 'changed' );
		}
		foreach ( lasery_xtool_available_variants( $post_id ) as $variant ) {
			if ( $variant['id'] === $fields['variant'] ) {
				$selected_variant = $variant;
				break;
			}
		}
		if ( null === $selected_variant ) {
			lasery_xtool_order_redirect( $post_id, 'invalid' );
		}
		$variant_title = sanitize_text_field( html_entity_decode( $selected_variant['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$fields['variant'] = $variant_title . ' (ID: ' . $selected_variant['id'] . ')';
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	$reservation = false;
	if ( '' !== $ip ) {
		$reservation = lasery_xtool_order_reserve_ip( $ip );
		if ( false === $reservation ) {
			lasery_xtool_order_redirect( $post_id, 'busy' );
		}
	}
	$text = lasery_xtool_order_texts( $fields['language'] );
	$owner_text = lasery_xtool_order_texts( 'pl' );
	$product = lasery_xtool_order_product_title( $post_id, 'pl' );
	$customer_product = lasery_xtool_order_product_title( $post_id, $fields['language'] );
	$source_product = lasery_xtool_order_product_title( $post_id, '' );
	$amount = $selected_variant ? lasery_xtool_variant_amount( $post_id, $selected_variant ) : lasery_xtool_order_price_amount( $post_id );
	$price = static function ( $texts, $language ) use ( $amount ) {
		return $amount ? lasery_xtool_format_price( $amount[0], $amount[1], $language ) : $texts['no_price'];
	};
	$order_rows = array( array( $owner_text['product'], $product ) );
	if ( $source_product !== $product ) {
		$order_rows[] = array( 'Nazwa źródłowa', $source_product );
	}
	$order_rows[] = array( $owner_text['variant'], $fields['variant'] );
	$order_rows[] = array( $owner_text['quantity'], $fields['quantity'] );
	$order_rows[] = array( $owner_text['price'], $price( $owner_text, 'pl' ) );
	$order_rows[] = array( 'Język klienta', strtoupper( $fields['language'] ) );
	$checkout = '';
	if ( $selected_variant ) {
		$shipping = array(
			'first_name' => $fields['first_name'],
			'last_name'  => $fields['last_name'],
			'address1'   => $fields['address1'],
			'city'       => $fields['city'],
			'country'    => $fields['country'],
			'zip'        => $fields['zip'],
		);
		if ( '' !== $fields['address2'] ) {
			$shipping['address2'] = $fields['address2'];
		}
		$query = http_build_query( array( 'checkout' => array( 'email' => $fields['email'], 'shipping_address' => $shipping ) ), '', '&', PHP_QUERY_RFC3986 );
		$checkout = 'https://www.xtool.eu/cart/' . $selected_variant['id'] . ':' . $fields['quantity'] . '?' . $query;
	}
	$owner_email = array(
		'language' => 'pl',
		'title'    => sprintf( $owner_text['owner_subject'], $product ),
		'intro'    => 'Klient czeka na potwierdzenie ceny i link do płatności. Odpowiedź na tę wiadomość trafi bezpośrednio do klienta.',
		'sections' => array(
			array( 'heading' => $owner_text['details'], 'rows' => $order_rows ),
			array( 'heading' => $owner_text['contact'], 'rows' => array( array( $owner_text['first_name'], $fields['first_name'] ), array( $owner_text['last_name'], $fields['last_name'] ), array( $owner_text['email'], $fields['email'] ), array( $owner_text['phone'], $fields['phone'] ) ) ),
			array( 'heading' => $owner_text['address'], 'rows' => array( array( $owner_text['address1'], $fields['address1'] ), array( $owner_text['address2'], $fields['address2'] ), array( $owner_text['zip'], $fields['zip'] ), array( $owner_text['city'], $fields['city'] ), array( $owner_text['country'], lasery_xtool_country_name( $fields['country'], 'pl' ) . ' (' . $fields['country'] . ')' ) ) ),
			array( 'heading' => $owner_text['extras'], 'rows' => array( array( $owner_text['invoice'], $fields['invoice'] ), array( $owner_text['notes'], $fields['notes'] ) ) ),
		),
		'button'   => $checkout ? array( 'Otwórz koszyk xTool z danymi klienta', $checkout ) : null,
		'note'     => ( $checkout ? "Link do koszyka xTool zawiera dane osobowe klienta. Może zostać zapisany w historii przeglądarki i logach — nie udostępniaj go osobom nieupoważnionym.\n" : '' ) . $owner_text['price_note'],
		'footer'   => lasery_xtool_mail_from_name(),
	);
	$company = function_exists( 'lasery_xtool_company' ) ? "\n" . lasery_xtool_company() : '';
	// The address is unverified, so the receipt repeats only data from our side: it must
	// not let anyone send their own text from this domain to an arbitrary mailbox.
	$customer_rows = array( array( $text['product'], $customer_product ) );
	if ( $selected_variant ) {
		$customer_rows[] = array( $text['variant'], str_replace( ' / ', ' · ', $variant_title ) );
	}
	$customer_rows[] = array( $text['quantity'], $fields['quantity'] );
	$customer_rows[] = array( $text['price'], $price( $text, $fields['language'] ) );
	$customer_email = array(
		'language' => $fields['language'],
		'title'    => sprintf( $text['customer_subject'], $customer_product ),
		'intro'    => $text['customer_body'],
		'sections' => array( array( 'heading' => $text['details'], 'rows' => $customer_rows ) ),
		'button'   => array( $text['view_product'], add_query_arg( 'lang', $fields['language'], get_permalink( $post_id ) ) ),
		'note'     => $text['price_note'] . "\n" . $text['reply_note'],
		'footer'   => lasery_xtool_mail_from_name() . ' · ' . lasery_xtool_order_contact_email() . $company,
	);
	$headers = array( 'Reply-To: ' . $fields['email'] );
	$recipient = lasery_xtool_order_recipient();
	if ( '' === $recipient || ! lasery_xtool_order_mail( $recipient, $owner_email, $headers ) ) {
		if ( false !== $reservation ) {
			lasery_xtool_order_release_ip( $reservation );
		}
		lasery_xtool_order_redirect( $post_id, 'failed' );
	}
	$receipt_sent = lasery_xtool_order_mail( $fields['email'], $customer_email, array( 'Reply-To: ' . lasery_xtool_order_contact_email() ) );
	lasery_xtool_order_redirect( $post_id, $receipt_sent ? 'sent' : 'sent_no_receipt' );
}
