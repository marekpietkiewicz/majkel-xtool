<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/cookie-consent.php';
require_once __DIR__ . '/privacy-defaults.php';
require_once __DIR__ . '/customizer.php';
require_once __DIR__ . '/template-tags.php';

add_action('after_setup_theme', function () {
    load_theme_textdomain('lasery-xtool', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => __('Menu główne', 'lasery-xtool')]);
});

add_action('wp_enqueue_scripts', function () {
    $version = wp_get_theme()->get('Version');
    $assets = get_template_directory_uri() . '/assets/';
    $script = ['in_footer' => true, 'strategy' => 'defer'];
    wp_enqueue_style('lasery-xtool', get_stylesheet_uri(), [], $version);
    $sliders = is_front_page() || is_singular('lasery_product');
    if ($sliders) {
        wp_enqueue_script('lasery-xtool-swiper', $assets . 'lib/swiper/swiper-bundle.min.js', [], '14.3.0', $script);
    }
    wp_enqueue_script('lasery-xtool-site', $assets . 'site.js', $sliders ? ['lasery-xtool-swiper'] : [], $version, $script);
    if (is_singular('lasery_product')) {
        wp_enqueue_script_module('lasery-xtool-lightbox', $assets . 'lightbox.js', [], $version);
    }
    // Only blog articles render block content edited in the block editor.
    if (!is_singular('post')) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('classic-theme-styles');
    }
}, 100);
add_action('wp', function () {
    if (!is_singular('post')) {
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
    }
});
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');

// The theme shows no comments, so none can be posted (this also stops comment spam and trackbacks).
add_filter('comments_open', '__return_false');
add_filter('pings_open', '__return_false');
// Category, tag and archive feed links would point to the Polish feed.
remove_action('wp_head', 'feed_links_extra', 3);

add_action('wp_head', function () {
    $fonts = get_template_directory_uri() . '/assets/fonts/';
    foreach (['geologica-latin-wght-normal.woff2', 'manrope-latin-wght-normal.woff2'] as $file) {
        printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url($fonts . $file));
    }
}, 1);

/** Use a URL parameter for interface language until translated products are available. */
function lasery_xtool_interface_language() {
    $language = isset($_GET['lang']) && is_string($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
    if ('' === $language && isset($_POST['action'], $_POST['language']) && $_POST['action'] === 'lasery_xtool_order' && is_string($_POST['language'])) {
        $language = sanitize_key(wp_unslash($_POST['language']));
    }
    return isset(lasery_xtool_languages()[$language]) ? $language : 'pl';
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

// Pages are translated in place, so each language version is its own canonical URL.
add_filter('get_canonical_url', function ($url, $post) {
    if ($post->post_type !== 'page') {
        return $url;
    }
    $url = lasery_xtool_language_url($url);
    $page = $post->ID === get_queried_object_id() && is_page('blog') ? lasery_xtool_blog_listing()[0] : 1;
    return $page > 1 ? add_query_arg('strona', $page, $url) : $url;
}, 10, 2);

// One URL per blog listing page: ?strona=1 and pages past the end lead to the first page.
add_action('template_redirect', function () {
    if (!is_page('blog') || !isset($_GET['strona'])) {
        return;
    }
    [$page, $posts] = lasery_xtool_blog_listing();
    if ($page < 2 || $page > $posts->max_num_pages) {
        wp_safe_redirect(remove_query_arg('strona'), $page < 2 ? 301 : 302);
        exit;
    }
});

/** Description and language alternates for the pages visitors land on. */
add_action('wp_head', function () {
    $description = '';
    $url = '';
    if (is_front_page()) {
        $description = lasery_xtool_text('hero_description');
        $url = home_url('/');
    } elseif (is_singular('lasery_product')) {
        $description = lasery_xtool_display_summary(get_queried_object_id());
        $url = get_permalink(get_queried_object_id());
    } elseif (is_singular('post')) {
        $description = get_the_excerpt(get_queried_object_id());
    } elseif (is_page('blog')) {
        $description = lasery_xtool_text('blog_description');
        // Further pages differ per language, so only the first one has alternates.
        $url = lasery_xtool_blog_listing()[0] > 1 ? '' : get_permalink(get_queried_object_id());
    } elseif (is_post_type_archive('lasery_product')) {
        $kind = lasery_xtool_requested_kind();
        $url = $kind === '' ? get_post_type_archive_link('lasery_product') : add_query_arg('kind', $kind, get_post_type_archive_link('lasery_product'));
    }
    // Articles link their translations, which have their own URLs.
    if (is_singular('post') && function_exists('lasery_xtool_post_translations')) {
        $translations = lasery_xtool_post_translations(get_queried_object_id());
        if (count($translations) > 1) {
            foreach ($translations as $language => $id) {
                printf('<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr($language), esc_url(get_permalink($id)));
            }
            if (isset($translations['pl'])) {
                printf('<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url(get_permalink($translations['pl'])));
            }
        }
    }
    if ($description !== '') {
        printf('<meta name="description" content="%s">' . "\n", esc_attr(wp_html_excerpt($description, 160, '…')));
    }
    if (is_string($url) && $url !== '') {
        $url = remove_query_arg('lang', $url);
        foreach (array_keys(lasery_xtool_languages()) as $language) {
            printf('<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr($language), esc_url(add_query_arg('lang', $language, $url)));
        }
        printf('<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url($url));
    }
});

// Pages with source prices (also product cards inside articles) and the article list stay in the page cache for at most an hour.
add_action('template_redirect', function () {
    if (is_front_page() || is_post_type_archive('lasery_product') || is_singular(['lasery_product', 'post']) || is_page('blog')) {
        do_action('litespeed_control_set_ttl', HOUR_IN_SECONDS);
    }
});

// Post archives and feeds list the articles of the interface language only.
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || $query->is_singular() || $query->is_search() || $query->is_comment_feed() || $query->is_post_type_archive() || !function_exists('lasery_xtool_language_meta_query')) {
        return;
    }
    if ($query->is_home() || $query->is_archive() || $query->is_feed()) {
        $query->set('meta_query', lasery_xtool_language_meta_query(lasery_xtool_interface_language()));
    }
});

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('lasery_product')) {
        return;
    }
    $query->set('posts_per_page', 48);
    $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC']);
    $kind = lasery_xtool_requested_kind();
    if ($kind !== '' && function_exists('lasery_xtool_kind_tax_query')) {
        $query->set('tax_query', lasery_xtool_kind_tax_query($kind));
    }
});
