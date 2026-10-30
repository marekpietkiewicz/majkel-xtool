<?php
/** Editable content for the landing page. */
if (!defined('ABSPATH')) {
    exit;
}

/** Editable page texts: field => [admin label, control type, translated default]. */
function lasery_xtool_text_fields() {
    $fields = [
        'hero_eyebrow' => ['Hero: mały nagłówek', 'text', __('LASERY I URZĄDZENIA XTOOL', 'lasery-xtool')],
        'hero_title' => ['Hero: główny nagłówek strony (SEO) i tekst przy braku produktów', 'text', __('Twórz więcej. Z xTool.', 'lasery-xtool')],
        'hero_description' => ['Hero: opis (także opis strony w wyszukiwarkach)', 'textarea', __('Poznaj urządzenia do grawerowania i cięcia. Wybierz model dla siebie lub zapytaj nas o demo.', 'lasery-xtool')],
        'hero_details_button' => ['Hero: przycisk produktu', 'text', __('Zobacz szczegóły', 'lasery-xtool')],
        'hero_demo_button' => ['Przycisk demo (nagłówek, hero, strona produktu)', 'text', __('Zapytaj o demo', 'lasery-xtool')],
        'products_eyebrow' => ['Produkty: mały nagłówek', 'text', __('KATALOG XTOOL', 'lasery-xtool')],
        'products_title' => ['Produkty: nagłówek', 'text', __('Znajdź urządzenie dla siebie.', 'lasery-xtool')],
        'products_description' => ['Produkty: opis', 'textarea', __('Poznaj dostępne urządzenia i wybierz rozwiązanie do swoich projektów.', 'lasery-xtool')],
        'products_button' => ['Produkty: przycisk katalogu', 'text', __('Zobacz cały katalog', 'lasery-xtool')],
        'accessories_eyebrow' => ['Akcesoria: mały nagłówek', 'text', __('AKCESORIA', 'lasery-xtool')],
        'accessories_title' => ['Akcesoria: nagłówek', 'text', __('Rozbuduj swoje urządzenie.', 'lasery-xtool')],
        'accessories_description' => ['Akcesoria: opis', 'textarea', __('Moduły laserowe, stoły robocze i dodatki do urządzeń xTool.', 'lasery-xtool')],
        'demo_eyebrow' => ['Demo: mały nagłówek', 'text', __('POZNAJ XTOOL', 'lasery-xtool')],
        'demo_title' => ['Demo: nagłówek', 'text', __('Chcesz zobaczyć urządzenie w akcji?', 'lasery-xtool')],
        'demo_description' => ['Demo: opis', 'textarea', __('Wybierz dogodny termin na stronie demonstracji xTool lub otwórz ją w nowej karcie.', 'lasery-xtool')],
        'demo_button' => ['Demo: przycisk rezerwacji', 'text', __('Otwórz rezerwację demo', 'lasery-xtool')],
        'demo_step_1' => ['Demo: krok 1', 'text', __('Wybierz termin i urządzenie w kalendarzu xTool.', 'lasery-xtool')],
        'demo_step_2' => ['Demo: krok 2', 'text', __('Połącz się online z naszym ekspertem.', 'lasery-xtool')],
        'demo_step_3' => ['Demo: krok 3', 'text', __('Zobacz laser w akcji i zadaj pytania.', 'lasery-xtool')],
        'demo_models_title' => ['Demo: nagłówek listy modeli', 'text', __('Na demo pokażemy te modele:', 'lasery-xtool')],
        'demo_callout_title' => ['Produkt z demo: nagłówek ramki', 'text', __('Zobacz ten model na żywo', 'lasery-xtool')],
        'demo_callout_text' => ['Produkt z demo: opis ramki', 'textarea', __('Ten model mamy w naszej pracowni. Umów demo online i zobacz go w akcji, zanim zdecydujesz się na zakup.', 'lasery-xtool')],
        'demo_callout_button' => ['Produkt z demo: przycisk', 'text', __('Umów demo', 'lasery-xtool')],
        'demo_frame_note' => ['Demo: opis ramki', 'textarea', __('Formularz „Book a Service” znajduje się poniżej profilu na stronie xTool. Przewiń zawartość ramki, aby go zobaczyć.', 'lasery-xtool')],
        'blog_products_button' => ['Blog: przycisk do urządzeń pod artykułem', 'text', __('Zobacz urządzenia', 'lasery-xtool')],
        'blog_eyebrow' => ['Blog: mały nagłówek', 'text', __('BLOG', 'lasery-xtool')],
        'blog_title' => ['Blog: nagłówek', 'text', __('Poradniki i inspiracje', 'lasery-xtool')],
        'blog_description' => ['Blog: opis (także opis strony w wyszukiwarkach)', 'textarea', __('Podpowiadamy, jak wybrać laser, co na nim tworzyć i jak rozwinąć pracownię.', 'lasery-xtool')],
        'blog_cta_title' => ['Blog: nagłówek ramki pod artykułem', 'text', __('Zobacz urządzenia xTool na żywo', 'lasery-xtool')],
        'blog_cta_text' => ['Blog: opis ramki pod artykułem', 'textarea', __('Umów demo online – pokażemy wybrane modele w naszej pracowni i odpowiemy na Twoje pytania.', 'lasery-xtool')],
        'footer_tagline' => ['Stopka: opis', 'textarea', __('Urządzenia xTool do grawerowania i cięcia laserowego. Pokażemy je na demo i pomożemy w zamówieniu.', 'lasery-xtool')],
    ];
    $benefits = [
        [__('Demo na żywo', 'lasery-xtool'), __('Zobacz urządzenie w akcji podczas spotkania online i zadaj pytania.', 'lasery-xtool')],
        [__('Zamówienie bez konta', 'lasery-xtool'), __('Wyślij formularz – potwierdzimy cenę i prześlemy link do płatności.', 'lasery-xtool')],
        [__('Pomoc w wyborze', 'lasery-xtool'), __('Doradzimy model i akcesoria do Twoich materiałów i projektów.', 'lasery-xtool')],
        [__('Oryginalne urządzenia xTool', 'lasery-xtool'), __('Zamówienia realizujemy w oficjalnym sklepie xTool.', 'lasery-xtool')],
    ];
    foreach ($benefits as $index => $benefit) {
        $number = $index + 1;
        $fields['benefit_' . $number . '_title'] = ['Zaleta ' . $number . ': tytuł', 'text', $benefit[0]];
        $fields['benefit_' . $number . '_text'] = ['Zaleta ' . $number . ': opis', 'textarea', $benefit[1]];
    }
    return $fields;
}

/** The text saved for the current language, or its translated default. */
function lasery_xtool_text($field) {
    static $fields = null;
    $fields ??= lasery_xtool_text_fields();
    $value = get_theme_mod('lasery_xtool_' . $field . '_' . lasery_xtool_interface_language(), '');
    return is_string($value) && trim($value) !== '' ? $value : $fields[$field][2];
}

function lasery_xtool_contact_email() {
    $email = get_theme_mod('lasery_xtool_contact_email', 'Michal@laseryxtool.pl');
    return is_string($email) && is_email($email) ? $email : 'Michal@laseryxtool.pl';
}

function lasery_xtool_company() {
    $company = get_theme_mod('lasery_xtool_company', '');
    return is_string($company) && trim($company) !== '' ? $company : "MAJKEL MICHAŁ MAGALSKI\nul. Stanisława Skarżyńskiego 5a\n85-791 Bydgoszcz, Polska\nNIP 9532666193";
}

function lasery_xtool_valid_demo_url($url) {
    if (!is_string($url) || strlen($url) > 2048) {
        return false;
    }
    $parts = wp_parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'], $parts['query']) || strtolower($parts['scheme']) !== 'https' || strtolower($parts['host']) !== 'support.xtool.com' || $parts['path'] !== '/demo-room/host-instruction' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
        return false;
    }
    parse_str($parts['query'], $query);
    return isset($query['id'], $query['type']) && is_string($query['id']) && ctype_digit($query['id']) && $query['type'] === 'demoPreSale';
}

function lasery_xtool_demo_url() {
    $url = get_theme_mod('lasery_xtool_demo_url', 'https://support.xtool.com/demo-room/host-instruction?id=1252&type=demoPreSale');
    return lasery_xtool_valid_demo_url($url) ? $url : 'https://support.xtool.com/demo-room/host-instruction?id=1252&type=demoPreSale';
}

add_action('customize_register', function ($customizer) {
    $customizer->add_panel('lasery_xtool_content', ['title' => 'Treść strony xTool', 'priority' => 35]);
    $customizer->add_section('lasery_xtool_contact', ['title' => 'Kontakt i rezerwacja', 'panel' => 'lasery_xtool_content']);
    $customizer->add_setting('lasery_xtool_contact_email', ['default' => 'Michal@laseryxtool.pl', 'sanitize_callback' => function ($value) { return is_string($value) ? sanitize_email($value) : ''; }, 'validate_callback' => function ($validity, $value) {
        if (!is_string($value) || !is_email($value)) $validity->add('invalid_email', 'Podaj poprawny adres e-mail.');
        return $validity;
    }]);
    $customizer->add_control('lasery_xtool_contact_email', ['label' => 'Adres kontaktowy', 'description' => 'Jeśli edytowano własną politykę prywatności, zmień adres także w jej treści.', 'section' => 'lasery_xtool_contact', 'type' => 'email']);
    $customizer->add_setting('lasery_xtool_demo_url', ['default' => 'https://support.xtool.com/demo-room/host-instruction?id=1252&type=demoPreSale', 'sanitize_callback' => function ($value) { return is_string($value) ? esc_url_raw($value, ['https']) : ''; }, 'validate_callback' => function ($validity, $value) {
        if (!lasery_xtool_valid_demo_url($value)) $validity->add('invalid_demo_url', 'Podaj adres HTTPS profilu demo xTool z identyfikatorem gospodarza.');
        return $validity;
    }]);
    $customizer->add_control('lasery_xtool_demo_url', ['label' => 'Link do profilu demonstracji xTool', 'section' => 'lasery_xtool_contact', 'type' => 'url']);
    $customizer->add_setting('lasery_xtool_company', ['default' => '', 'sanitize_callback' => 'sanitize_textarea_field']);
    $customizer->add_control('lasery_xtool_company', ['label' => 'Dane firmy w stopce', 'description' => 'Nazwa, adres i NIP — każda informacja w osobnej linii. Puste pole pokazuje dane domyślne.', 'section' => 'lasery_xtool_contact', 'type' => 'textarea']);
    $fields = lasery_xtool_text_fields();
    foreach (lasery_xtool_languages() as $language => $label) {
        $section = 'lasery_xtool_content_' . $language;
        $customizer->add_section($section, ['title' => 'Teksty: ' . $label, 'panel' => 'lasery_xtool_content', 'description' => 'Puste pole wyświetla domyślne tłumaczenie.']);
        foreach ($fields as $key => $field) {
            $id = 'lasery_xtool_' . $key . '_' . $language;
            $customizer->add_setting($id, ['default' => '', 'sanitize_callback' => $field[1] === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field']);
            $customizer->add_control($id, ['label' => $field[0], 'section' => $section, 'type' => $field[1]]);
        }
    }
});

add_action('customize_save_after', function () {
    do_action('litespeed_purge_all');
});
