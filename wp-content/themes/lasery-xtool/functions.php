<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/cookie-consent.php';

require_once __DIR__ . '/privacy-defaults.php';
require_once __DIR__ . '/customizer.php';

add_action('after_setup_theme', function () {
    load_theme_textdomain('lasery-xtool', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => __('Menu główne', 'lasery-xtool')]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('lasery-xtool', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    if (is_front_page()) {
        wp_enqueue_script('lasery-xtool-carousel', get_template_directory_uri() . '/assets/carousel.js', [], wp_get_theme()->get('Version'), true);
    }
    if (is_singular('lasery_product')) {
        wp_enqueue_script('lasery-xtool-gallery', get_template_directory_uri() . '/assets/gallery.js', [], wp_get_theme()->get('Version'), true);
    }
});

/** Use a URL parameter for interface language until translated products are available. */
function lasery_xtool_interface_language() {
    $language = isset($_GET['lang']) && is_string($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
    if ('' === $language && isset($_POST['action'], $_POST['language']) && $_POST['action'] === 'lasery_xtool_order' && is_string($_POST['language'])) {
        $language = sanitize_key(wp_unslash($_POST['language']));
    }
    return in_array($language, ['pl', 'en', 'de', 'ru', 'uk'], true) ? $language : 'pl';
}

function lasery_xtool_language_url($url) {
    return add_query_arg('lang', lasery_xtool_interface_language(), $url);
}

function lasery_xtool_display_product_title($post_id) {
    return function_exists('lasery_xtool_product_title')
        ? lasery_xtool_product_title($post_id, lasery_xtool_interface_language())
        : get_the_title($post_id);
}

add_filter('document_title_parts', function ($parts) {
    if (is_singular('lasery_product')) {
        $parts['title'] = lasery_xtool_display_product_title(get_queried_object_id());
    }
    return $parts;
});

add_filter('locale', function ($locale) {
    if (is_admin() && (!isset($_POST['action']) || !is_string($_POST['action']) || $_POST['action'] !== 'lasery_xtool_order')) {
        return $locale;
    }
    $locales = ['pl' => 'pl_PL', 'en' => 'en_US', 'de' => 'de_DE', 'ru' => 'ru_RU', 'uk' => 'uk'];
    return $locales[lasery_xtool_interface_language()];
});

add_filter('post_type_link', function ($url, $post) {
    return $post->post_type === 'lasery_product' ? lasery_xtool_language_url($url) : $url;
}, 10, 2);

add_filter('post_type_archive_link', function ($url, $post_type) {
    return $post_type === 'lasery_product' ? lasery_xtool_language_url($url) : $url;
}, 10, 2);
