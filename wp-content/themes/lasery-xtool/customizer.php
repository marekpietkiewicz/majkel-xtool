<?php
/** Editable content for the landing page. */
if (!defined('ABSPATH')) {
    exit;
}

function lasery_xtool_text($field, $default) {
    $value = get_theme_mod('lasery_xtool_' . $field . '_' . lasery_xtool_interface_language(), '');
    return is_string($value) && trim($value) !== '' ? $value : $default;
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
    $fields = [
        'hero_eyebrow' => ['Hero: mały nagłówek', 'text'],
        'hero_title' => ['Hero: główny nagłówek strony (SEO) i tekst przy braku produktów', 'text'],
        'hero_description' => ['Hero: opis', 'textarea'],
        'hero_details_button' => ['Hero: przycisk produktu', 'text'],
        'hero_demo_button' => ['Hero: przycisk demo', 'text'],
        'products_eyebrow' => ['Produkty: mały nagłówek', 'text'],
        'products_title' => ['Produkty: nagłówek', 'text'],
        'products_description' => ['Produkty: opis', 'textarea'],
        'products_button' => ['Produkty: przycisk katalogu', 'text'],
        'demo_eyebrow' => ['Demo: mały nagłówek', 'text'],
        'demo_title' => ['Demo: nagłówek', 'text'],
        'demo_description' => ['Demo: opis', 'textarea'],
        'demo_button' => ['Demo: przycisk rezerwacji', 'text'],
        'demo_frame_note' => ['Demo: opis ramki', 'textarea'],
        'demo_step_1' => ['Demo: krok 1', 'text'],
        'demo_step_2' => ['Demo: krok 2', 'text'],
        'demo_step_3' => ['Demo: krok 3', 'text'],
        'accessories_eyebrow' => ['Akcesoria: mały nagłówek', 'text'],
        'accessories_title' => ['Akcesoria: nagłówek', 'text'],
        'accessories_description' => ['Akcesoria: opis', 'textarea'],
        'footer_tagline' => ['Stopka: opis', 'textarea'],
    ];
    for ($number = 1; $number <= 4; $number++) {
        $fields['benefit_' . $number . '_title'] = ['Zaleta ' . $number . ': tytuł', 'text'];
        $fields['benefit_' . $number . '_text'] = ['Zaleta ' . $number . ': opis', 'textarea'];
    }
    foreach (['pl' => 'Polski', 'en' => 'Angielski', 'de' => 'Niemiecki', 'ru' => 'Rosyjski', 'uk' => 'Ukraiński'] as $language => $label) {
        $section = 'lasery_xtool_content_' . $language;
        $customizer->add_section($section, ['title' => $label, 'panel' => 'lasery_xtool_content', 'description' => 'Puste pole wyświetla domyślne tłumaczenie.']);
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
