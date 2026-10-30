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

add_action( 'admin_init', 'lasery_xtool_register_order_settings' );
function lasery_xtool_register_order_settings() {
	register_setting( 'lasery_xtool_order_settings', 'lasery_xtool_order_recipient', array(
		'type' => 'string',
		'sanitize_callback' => 'lasery_xtool_sanitize_order_recipient',
		'default' => '',
	) );
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
	<p>Ustaw adres odbiorcy zamówień. Podczas testów użyj własnej skrzynki, a przed publikacją wpisz właściwy adres obsługi zamówień.</p>
	<form action="options.php" method="post">
		<?php settings_fields( 'lasery_xtool_order_settings' ); ?>
		<p><label for="lasery-order-recipient">Adres odbiorcy zamówień</label></p>
		<input id="lasery-order-recipient" name="lasery_xtool_order_recipient" type="email" class="regular-text" required value="<?php echo esc_attr( lasery_xtool_order_recipient() ); ?>">
		<?php submit_button(); ?>
	</form></div>
	<?php
}

/** The price is entered manually and is only an estimate. */
function lasery_xtool_order_price( $post_id ) {
	$price = get_post_meta( $post_id, '_lasery_order_price', true );
	$currency = get_post_meta( $post_id, '_lasery_order_currency', true );
	if ( ! is_string( $price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $price ) || (float) $price <= 0 || ! in_array( $currency, array( 'PLN', 'EUR', 'USD' ), true ) ) {
		return '';
	}
	return $price . ' ' . $currency;
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

/** Texts are kept together until the site has a configured multilingual plugin. */
function lasery_xtool_order_texts( $language ) {
	$texts = array(
		'pl' => array(
			'title' => 'Zamów produkt', 'intro' => 'Prześlij nam dane zamówienia. Skontaktujemy się z Tobą, aby potwierdzić szczegóły i przekazać link do płatności.',
			'first_name' => 'Imię', 'last_name' => 'Nazwisko', 'email' => 'Adres e-mail', 'phone' => 'Telefon (opcjonalnie)', 'address1' => 'Ulica i numer', 'address2' => 'Dodatkowy adres (opcjonalnie)', 'city' => 'Miasto', 'zip' => 'Kod pocztowy', 'country' => 'Kraj dostawy', 'select_country' => 'Wybierz kraj', 'variant' => 'Wariant lub konfiguracja', 'select_variant' => 'Wybierz wariant', 'unavailable' => 'Obecnie brak dostępnych wariantów.', 'stale' => 'Dane oferty wymagają aktualizacji. Skontaktuj się z nami.', 'changed' => 'Dane oferty zmieniły się. Odśwież stronę i wybierz wariant ponownie.', 'quantity' => 'Liczba sztuk', 'invoice' => 'Dane do faktury (opcjonalnie)', 'notes' => 'Dodatkowe informacje (opcjonalnie)', 'submit' => 'Wyślij zamówienie',
			'privacy_link' => 'Polityka prywatności', 'privacy' => 'Dane wykorzystamy do obsługi zamówienia. Link do formularza xTool zawierający Twoje dane może zostać zapisany w historii przeglądarki i logach.', 'sent' => 'Dziękujemy! Twoje zgłoszenie zostało wysłane. Skontaktujemy się z Tobą w sprawie szczegółów i linku do płatności.', 'sent_no_receipt' => 'Zgłoszenie zostało wysłane, ale nie udało się wysłać potwierdzenia na Twój adres. Nie wysyłaj zamówienia ponownie; w razie pytań napisz na Michal@laseryxtool.pl.', 'failed' => 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie lub napisz na Michal@laseryxtool.pl.', 'invalid' => 'Sprawdź dane formularza i spróbuj ponownie.', 'busy' => 'Odczekaj chwilę przed ponownym wysłaniem zgłoszenia.',
			'owner_subject' => 'Nowe zamówienie: %s', 'customer_subject' => 'Otrzymaliśmy zgłoszenie: %s', 'customer_body' => 'Dziękujemy za przesłanie zgłoszenia. Skontaktujemy się z Tobą, aby potwierdzić szczegóły zamówienia i przekazać link do płatności.', 'product' => 'Produkt', 'details' => 'Szczegóły zamówienia', 'price' => 'Cena orientacyjna', 'no_price' => 'Cena do potwierdzenia', 'price_note' => 'Ostateczna cena, VAT i koszt dostawy mogą się różnić.',
		),
		'en' => array(
			'title' => 'Order this product', 'intro' => 'Send us your order details. We will contact you to confirm the details and provide a payment link.',
			'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number (optional)', 'address1' => 'Street address', 'address2' => 'Additional address (optional)', 'city' => 'City', 'zip' => 'Postal code', 'country' => 'Delivery country', 'select_country' => 'Choose a country', 'variant' => 'Variant or configuration', 'select_variant' => 'Choose a variant', 'unavailable' => 'No variants are currently available.', 'stale' => 'The offer needs updating. Please contact us.', 'changed' => 'The offer has changed. Refresh the page and select a variant again.', 'quantity' => 'Quantity', 'invoice' => 'Billing details (optional)', 'notes' => 'Additional information (optional)', 'submit' => 'Send order request',
			'privacy_link' => 'Privacy policy', 'privacy' => 'We use your details to handle your order. A link to the xTool checkout containing your data may be stored in browser history and logs.', 'sent' => 'Thank you! Your request has been sent. We will contact you about the details and payment link.', 'sent_no_receipt' => 'Your request was sent, but we could not send your confirmation email. Please do not resubmit; contact Michal@laseryxtool.pl with any questions.', 'failed' => 'Your request could not be sent. Please try again or email Michal@laseryxtool.pl.', 'invalid' => 'Please check the form and try again.', 'busy' => 'Please wait a moment before sending another request.',
			'owner_subject' => 'New order request: %s', 'customer_subject' => 'We received your request: %s', 'customer_body' => 'Thank you for your request. We will contact you to confirm the order details and provide a payment link.', 'product' => 'Product', 'details' => 'Order details', 'price' => 'Estimated price', 'no_price' => 'Price to be confirmed', 'price_note' => 'The final price, VAT and delivery costs may differ.',
		),
		'de' => array(
			'title' => 'Produkt bestellen', 'intro' => 'Senden Sie uns Ihre Bestelldaten. Wir kontaktieren Sie, um die Einzelheiten zu bestätigen und Ihnen einen Zahlungslink zu senden.',
			'first_name' => 'Vorname', 'last_name' => 'Nachname', 'email' => 'E-Mail-Adresse', 'phone' => 'Telefonnummer (optional)', 'address1' => 'Straße und Hausnummer', 'address2' => 'Adresszusatz (optional)', 'city' => 'Ort', 'zip' => 'Postleitzahl', 'country' => 'Lieferland', 'select_country' => 'Land auswählen', 'variant' => 'Variante oder Konfiguration', 'select_variant' => 'Variante auswählen', 'unavailable' => 'Derzeit sind keine Varianten verfügbar.', 'stale' => 'Die Angebotsdaten müssen aktualisiert werden. Bitte kontaktieren Sie uns.', 'changed' => 'Das Angebot hat sich geändert. Laden Sie die Seite neu und wählen Sie die Variante erneut.', 'quantity' => 'Anzahl', 'invoice' => 'Rechnungsdaten (optional)', 'notes' => 'Weitere Informationen (optional)', 'submit' => 'Bestellanfrage senden',
			'privacy_link' => 'Datenschutzerklärung', 'privacy' => 'Wir verwenden Ihre Daten zur Bearbeitung Ihrer Bestellung. Ein Link zum xTool-Checkout mit Ihren Daten kann im Browserverlauf und in Protokollen gespeichert werden.', 'sent' => 'Vielen Dank! Ihre Anfrage wurde gesendet. Wir kontaktieren Sie wegen der Einzelheiten und des Zahlungslinks.', 'sent_no_receipt' => 'Ihre Anfrage wurde gesendet, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte senden Sie die Anfrage nicht erneut; bei Fragen schreiben Sie an Michal@laseryxtool.pl.', 'failed' => 'Ihre Anfrage konnte nicht gesendet werden. Versuchen Sie es erneut oder schreiben Sie an Michal@laseryxtool.pl.', 'invalid' => 'Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.', 'busy' => 'Bitte warten Sie einen Moment, bevor Sie eine weitere Anfrage senden.',
			'owner_subject' => 'Neue Bestellanfrage: %s', 'customer_subject' => 'Wir haben Ihre Anfrage erhalten: %s', 'customer_body' => 'Vielen Dank für Ihre Anfrage. Wir kontaktieren Sie, um die Bestelldetails zu bestätigen und Ihnen einen Zahlungslink zu senden.', 'product' => 'Produkt', 'details' => 'Bestelldetails', 'price' => 'Unverbindlicher Preis', 'no_price' => 'Preis wird bestätigt', 'price_note' => 'Endpreis, MwSt. und Lieferkosten können abweichen.',
		),
		'ru' => array(
			'title' => 'Заказать товар', 'intro' => 'Отправьте нам данные заказа. Мы свяжемся с вами, чтобы подтвердить детали и предоставить ссылку для оплаты.',
			'first_name' => 'Имя', 'last_name' => 'Фамилия', 'email' => 'Адрес электронной почты', 'phone' => 'Телефон (необязательно)', 'address1' => 'Улица и номер дома', 'address2' => 'Дополнительный адрес (необязательно)', 'city' => 'Город', 'zip' => 'Почтовый индекс', 'country' => 'Страна доставки', 'select_country' => 'Выберите страну', 'variant' => 'Вариант или конфигурация', 'select_variant' => 'Выберите вариант', 'unavailable' => 'Сейчас нет доступных вариантов.', 'stale' => 'Данные предложения требуют обновления. Свяжитесь с нами.', 'changed' => 'Предложение изменилось. Обновите страницу и выберите вариант снова.', 'quantity' => 'Количество', 'invoice' => 'Данные для счёта (необязательно)', 'notes' => 'Дополнительная информация (необязательно)', 'submit' => 'Отправить заявку',
			'privacy_link' => 'Политика конфиденциальности', 'privacy' => 'Мы используем данные для обработки заказа. Ссылка на оформление xTool с вашими данными может сохраняться в истории браузера и журналах.', 'sent' => 'Спасибо! Ваша заявка отправлена. Мы свяжемся с вами по поводу деталей и ссылки для оплаты.', 'sent_no_receipt' => 'Заявка отправлена, но письмо с подтверждением отправить не удалось. Не отправляйте заявку повторно; по вопросам пишите на Michal@laseryxtool.pl.', 'failed' => 'Не удалось отправить заявку. Попробуйте ещё раз или напишите на Michal@laseryxtool.pl.', 'invalid' => 'Проверьте данные формы и попробуйте ещё раз.', 'busy' => 'Подождите немного перед повторной отправкой заявки.',
			'owner_subject' => 'Новая заявка на заказ: %s', 'customer_subject' => 'Мы получили вашу заявку: %s', 'customer_body' => 'Спасибо за заявку. Мы свяжемся с вами, чтобы подтвердить детали заказа и предоставить ссылку для оплаты.', 'product' => 'Товар', 'details' => 'Данные заказа', 'price' => 'Ориентировочная цена', 'no_price' => 'Цена уточняется', 'price_note' => 'Окончательная цена, НДС и стоимость доставки могут отличаться.',
		),
		'uk' => array(
			'title' => 'Замовити товар', 'intro' => 'Надішліть нам дані замовлення. Ми зв’яжемося з вами, щоб підтвердити деталі та надати посилання для оплати.',
			'first_name' => 'Ім’я', 'last_name' => 'Прізвище', 'email' => 'Адреса електронної пошти', 'phone' => 'Телефон (необов’язково)', 'address1' => 'Вулиця і номер будинку', 'address2' => 'Додаткова адреса (необов’язково)', 'city' => 'Місто', 'zip' => 'Поштовий індекс', 'country' => 'Країна доставки', 'select_country' => 'Оберіть країну', 'variant' => 'Варіант або конфігурація', 'select_variant' => 'Оберіть варіант', 'unavailable' => 'Зараз немає доступних варіантів.', 'stale' => 'Дані пропозиції потребують оновлення. Зв’яжіться з нами.', 'changed' => 'Пропозиція змінилася. Оновіть сторінку та оберіть варіант знову.', 'quantity' => 'Кількість', 'invoice' => 'Дані для рахунку (необов’язково)', 'notes' => 'Додаткова інформація (необов’язково)', 'submit' => 'Надіслати заявку',
			'privacy_link' => 'Політика конфіденційності', 'privacy' => 'Ми використовуємо дані для обробки замовлення. Посилання на оформлення xTool з вашими даними може зберігатися в історії браузера та журналах.', 'sent' => 'Дякуємо! Вашу заявку надіслано. Ми зв’яжемося з вами щодо деталей і посилання для оплати.', 'sent_no_receipt' => 'Заявку надіслано, але лист із підтвердженням надіслати не вдалося. Не надсилайте заявку повторно; із запитаннями пишіть на Michal@laseryxtool.pl.', 'failed' => 'Не вдалося надіслати заявку. Спробуйте ще раз або напишіть на Michal@laseryxtool.pl.', 'invalid' => 'Перевірте дані форми та спробуйте ще раз.', 'busy' => 'Зачекайте трохи перед повторним надсиланням заявки.',
			'owner_subject' => 'Нова заявка на замовлення: %s', 'customer_subject' => 'Ми отримали вашу заявку: %s', 'customer_body' => 'Дякуємо за заявку. Ми зв’яжемося з вами, щоб підтвердити деталі замовлення та надати посилання для оплати.', 'product' => 'Товар', 'details' => 'Дані замовлення', 'price' => 'Орієнтовна ціна', 'no_price' => 'Ціна уточнюється', 'price_note' => 'Остаточна ціна, ПДВ і вартість доставки можуть відрізнятися.',
		),
	);
	return isset( $texts[ $language ] ) ? $texts[ $language ] : $texts['pl'];
}

function lasery_xtool_order_language() {
	$language = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
	if ( ! is_string( $language ) || '' === $language ) {
		$language = get_locale();
	}
	$language = strtolower( substr( $language, 0, 2 ) );
	return in_array( $language, array( 'pl', 'en', 'de', 'ru', 'uk' ), true ) ? $language : 'pl';
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

/** Read source variants without accepting prices or availability from the browser. */
function lasery_xtool_order_variants( $post_id ) {
	$variants = get_post_meta( $post_id, '_lasery_source_variants', true );
	return is_array( $variants ) ? $variants : array();
}

function lasery_xtool_source_variant_price( $post_id, $variant ) {
	$currency = get_post_meta( $post_id, '_lasery_source_currency', true );
	$price = isset( $variant['price'] ) ? $variant['price'] : null;
	if ( ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! is_string( $price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $price ) ) {
		return '';
	}
	return $price . ' ' . $currency;
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
	$price = lasery_xtool_order_price( $post_id );
	$variants = lasery_xtool_order_variants( $post_id );
	$uses_source_variants = metadata_exists( 'post', $post_id, LASERY_XTOOL_SOURCE_ID ) || (bool) $variants;
	$fresh = lasery_xtool_source_is_fresh( $post_id );
	$available_variants = array_filter( $variants, function ( $variant ) { return is_array( $variant ) && isset( $variant['id'], $variant['title'], $variant['available'] ) && is_string( $variant['id'] ) && is_string( $variant['title'] ) && true === $variant['available']; } );
	$result = isset( $_GET['order_result'] ) && is_string( $_GET['order_result'] ) ? sanitize_key( wp_unslash( $_GET['order_result'] ) ) : '';
	?>
	<section class="order-form" id="zamow" aria-labelledby="order-form-title">
		<h2 id="order-form-title"><?php echo esc_html( $text['title'] ); ?></h2>
		<p><?php echo esc_html( $text['intro'] ); ?></p>
		<p><?php if ( ! $uses_source_variants ) : ?><strong><?php echo esc_html( $price ? $text['price'] . ': ' . $price : $text['no_price'] ); ?></strong><br><?php endif; ?><?php echo esc_html( $text['price_note'] ); ?></p>
		<?php if ( in_array( $result, array( 'sent', 'sent_no_receipt', 'failed', 'invalid', 'busy', 'changed' ), true ) ) : ?>
			<p class="order-form-notice" role="status"><?php echo esc_html( $text[ $result ] ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_order">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $post_id ); ?>">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<?php if ( $uses_source_variants ) : ?><input type="hidden" name="snapshot" value="<?php echo esc_attr( lasery_xtool_variant_snapshot( $post_id, $variants ) ); ?>"><?php endif; ?>
			<?php wp_nonce_field( 'lasery_xtool_order_' . $post_id ); ?>
			<div class="order-form-trap" aria-hidden="true"><label for="order-website">Website</label><input id="order-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
			<p><label for="order-first-name"><?php echo esc_html( $text['first_name'] ); ?></label><input id="order-first-name" name="first_name" type="text" maxlength="150" autocomplete="given-name" required></p>
			<p><label for="order-last-name"><?php echo esc_html( $text['last_name'] ); ?></label><input id="order-last-name" name="last_name" type="text" maxlength="150" autocomplete="family-name" required></p>
			<p><label for="order-email"><?php echo esc_html( $text['email'] ); ?></label><input id="order-email" name="email" type="email" maxlength="254" autocomplete="email" required></p>
			<p><label for="order-phone"><?php echo esc_html( $text['phone'] ); ?></label><input id="order-phone" name="phone" type="tel" maxlength="50" autocomplete="tel"></p>
			<p><label for="order-address1"><?php echo esc_html( $text['address1'] ); ?></label><input id="order-address1" name="address1" type="text" maxlength="250" autocomplete="address-line1" required></p>
			<p><label for="order-address2"><?php echo esc_html( $text['address2'] ); ?></label><input id="order-address2" name="address2" type="text" maxlength="250" autocomplete="address-line2"></p>
			<p><label for="order-city"><?php echo esc_html( $text['city'] ); ?></label><input id="order-city" name="city" type="text" maxlength="150" autocomplete="address-level2" required></p>
			<p><label for="order-zip"><?php echo esc_html( $text['zip'] ); ?></label><input id="order-zip" name="zip" type="text" maxlength="30" autocomplete="postal-code" required></p>
			<p><label for="order-country"><?php echo esc_html( $text['country'] ); ?></label>
				<select id="order-country" name="country" autocomplete="country" required>
					<option value=""><?php echo esc_html( $text['select_country'] ); ?></option>
					<?php foreach ( lasery_xtool_shipping_countries() as $code => $fallback ) : ?>
						<?php $label = class_exists( 'Locale' ) ? Locale::getDisplayRegion( 'und_' . $code, $language ) : ''; ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( is_string( $label ) && '' !== $label ? $label : $fallback ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p><label for="order-variant"><?php echo esc_html( $text['variant'] ); ?></label>
			<?php if ( $uses_source_variants ) : ?>
				<select id="order-variant" name="variant" required <?php disabled( ! $fresh || empty( $available_variants ) ); ?>>
					<option value=""><?php echo esc_html( ! $available_variants ? $text['unavailable'] : ( ! $fresh ? $text['stale'] : $text['select_variant'] ) ); ?></option>
					<?php foreach ( $fresh ? $available_variants : array() as $variant ) : ?>
						<?php $variant_price = lasery_xtool_source_variant_price( $post_id, $variant ); ?>
						<option value="<?php echo esc_attr( $variant['id'] ); ?>"><?php echo esc_html( $variant['title'] . ' — ' . ( $variant_price ? $text['price'] . ': ' . $variant_price : $text['no_price'] ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input id="order-variant" name="variant" type="text" maxlength="250" required>
			<?php endif; ?></p>
			<p><label for="order-quantity"><?php echo esc_html( $text['quantity'] ); ?></label><input id="order-quantity" name="quantity" type="number" min="1" max="100" value="1" required></p>
			<p><label for="order-invoice"><?php echo esc_html( $text['invoice'] ); ?></label><textarea id="order-invoice" name="invoice" maxlength="1000"></textarea></p>
			<p><label for="order-notes"><?php echo esc_html( $text['notes'] ); ?></label><textarea id="order-notes" name="notes" maxlength="1000"></textarea></p>
			<p><?php echo esc_html( $text['privacy'] ); ?> <?php $privacy_url = get_privacy_policy_url(); if ( $privacy_url ) : ?><?php $privacy_url = add_query_arg( 'lang', $language, $privacy_url ); ?><a href="<?php echo esc_url( $privacy_url ); ?>"><?php echo esc_html( $text['privacy_link'] ); ?></a><?php endif; ?></p>
			<button type="submit" class="button button-dark" <?php disabled( $uses_source_variants && ( ! $fresh || ! $available_variants ) ); ?>><?php echo esc_html( $text['submit'] ); ?></button>
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

function lasery_xtool_order_mail( $to, $subject, $message, $headers ) {
	add_action( 'phpmailer_init', 'lasery_xtool_order_mail_sender' );
	try {
		return wp_mail( $to, $subject, $message, $headers );
	} finally {
		remove_action( 'phpmailer_init', 'lasery_xtool_order_mail_sender' );
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
	if ( '' === $fields['first_name'] || '' === $fields['last_name'] || '' === $fields['address1'] || '' === $fields['city'] || '' === $fields['zip'] || ! isset( $countries[ $fields['country'] ] ) || '' === $fields['variant'] || ! is_email( $fields['email'] ) || ! preg_match( '/^(?:[1-9]|[1-9][0-9]|100)$/D', $fields['quantity'] ) || ! in_array( $fields['language'], array( 'pl', 'en', 'de', 'ru', 'uk' ), true ) ) {
		lasery_xtool_order_redirect( $post_id, 'invalid' );
	}
	$variants = lasery_xtool_order_variants( $post_id );
	$selected_variant = null;
	$uses_source_variants = metadata_exists( 'post', $post_id, LASERY_XTOOL_SOURCE_ID ) || (bool) $variants;
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
		foreach ( $variants as $variant ) {
			if ( is_array( $variant ) && isset( $variant['id'], $variant['title'], $variant['available'] ) && is_string( $variant['id'] ) && is_string( $variant['title'] ) && true === $variant['available'] && $variant['id'] === $fields['variant'] ) {
				$selected_variant = $variant;
				break;
			}
		}
		if ( null === $selected_variant ) {
			lasery_xtool_order_redirect( $post_id, 'invalid' );
		}
		$fields['variant'] = sanitize_text_field( html_entity_decode( $selected_variant['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . ' (ID: ' . $selected_variant['id'] . ')';
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
	$product = sanitize_text_field( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ), true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$body = $owner_text['details'] . "\n\n" . $owner_text['product'] . ': ' . $product . "\nJęzyk klienta: " . $fields['language'] . "\n";
	$price = $selected_variant ? lasery_xtool_source_variant_price( $post_id, $selected_variant ) : lasery_xtool_order_price( $post_id );
	$body .= $owner_text['price'] . ': ' . ( $price ? $price : $owner_text['no_price'] ) . "\n" . $owner_text['price_note'] . "\n";
	foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'address1', 'address2', 'city', 'zip', 'country', 'variant', 'quantity', 'invoice', 'notes' ) as $field ) {
		$body .= $owner_text[ $field ] . ': ' . $fields[ $field ] . "\n";
	}
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
		$body .= "\nLink do koszyka xTool zawiera dane osobowe klienta. Może zostać zapisany w historii przeglądarki i logach — nie udostępniaj go osobom nieupoważnionym.\n";
		$body .= 'Koszyk xTool: https://www.xtool.eu/cart/' . $selected_variant['id'] . ':' . $fields['quantity'] . '?' . $query . "\n";
	}
	$headers = array( 'Reply-To: ' . $fields['email'] );
	$recipient = lasery_xtool_order_recipient();
	if ( '' === $recipient || ! lasery_xtool_order_mail( $recipient, sprintf( $owner_text['owner_subject'], $product ), $body, $headers ) ) {
		if ( false !== $reservation ) {
			lasery_xtool_order_release_ip( $reservation );
		}
		lasery_xtool_order_redirect( $post_id, 'failed' );
	}
	$receipt_sent = lasery_xtool_order_mail( $fields['email'], sprintf( $text['customer_subject'], $product ), $text['customer_body'], array( 'Reply-To: Michal@laseryxtool.pl' ) );
	lasery_xtool_order_redirect( $post_id, $receipt_sent ? 'sent' : 'sent_no_receipt' );
}
