<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/cookie-consent.php';
require_once __DIR__ . '/privacy-defaults.php';
require_once __DIR__ . '/customizer.php';
require_once __DIR__ . '/template-tags.php';
require_once __DIR__ . '/search-index.php';
require_once __DIR__ . '/seo.php';

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
// No version, editing-client or shortlink tags in the page head.
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

// Every page: no MIME sniffing, no framing by other sites (the order form) and a modest referrer.
add_action('send_headers', function () {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
});

// User names stay private: no user list for visitors (the editor still lists authors), no user or category
// sitemaps, no author in embeds.
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return in_array($name, ['users', 'taxonomies'], true) ? false : $provider;
}, 10, 2);
add_filter('oembed_response_data', function ($data) {
    unset($data['author_name'], $data['author_url']);
    return $data;
});
// Author, date, category and tag archives would list articles of every language under a user name;
// the blog page lists them instead. Before redirect_canonical, which would reveal the author's slug.
add_action('template_redirect', function () {
    if (is_author() || is_date() || is_category() || is_tag()) {
        $blog = lasery_xtool_blog_url();
        $target = $blog !== '' ? $blog : lasery_xtool_language_url(home_url('/'));
        // Without a language the next page picks the visitor's (see seo.php).
        wp_safe_redirect(isset($_GET['lang']) ? $target : remove_query_arg('lang', $target), 301);
        exit;
    }
}, 9);

// The brand mark as the site icon, unless one is chosen in the Customizer.
add_action('wp_head', function () {
    if (has_site_icon()) {
        return;
    }
    $assets = get_template_directory_uri() . '/assets/';
    printf('<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url($assets . 'favicon.svg'));
    printf('<link rel="apple-touch-icon" href="%s">' . "\n", esc_url($assets . 'apple-touch-icon.png'));
});
add_action('do_faviconico', function () {
    if (!has_site_icon()) {
        wp_redirect(get_template_directory_uri() . '/assets/favicon-32.png', 301);
        exit;
    }
});

// The theme shows no comments, so none can be posted (this also stops comment spam and trackbacks).
add_filter('comments_open', '__return_false');
add_filter('pings_open', '__return_false');
// Category, tag and archive feed links would point to the Polish feed.
remove_action('wp_head', 'feed_links_extra', 3);

// The fonts of the first screen: Latin, plus Polish letters (ą, ł…) or Cyrillic.
add_action('wp_head', function () {
    $fonts = get_template_directory_uri() . '/assets/fonts/';
    $subsets = ['latin'];
    $extra = ['pl' => 'latin-ext', 'ru' => 'cyrillic', 'uk' => 'cyrillic'][lasery_xtool_interface_language()] ?? '';
    if ($extra !== '') {
        $subsets[] = $extra;
    }
    foreach ($subsets as $subset) {
        foreach (['geologica', 'manrope'] as $family) {
            printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url($fonts . $family . '-' . $subset . '-wght-normal.woff2'));
        }
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
    return lasery_xtool_keep_together(function_exists('lasery_xtool_product_title')
        ? lasery_xtool_product_title($post_id, lasery_xtool_interface_language())
        : get_the_title($post_id));
}

// Titles in the interface language (WordPress would use the Polish site tagline, post type label and page titles).
add_filter('document_title_parts', function ($parts) {
    $language = lasery_xtool_interface_language();
    if (is_front_page()) {
        $parts['tagline'] = __('Produkty xTool i demonstracje', 'lasery-xtool');
    } elseif (is_singular('lasery_product')) {
        $parts['title'] = lasery_xtool_display_product_title(get_queried_object_id());
    } elseif (is_post_type_archive('lasery_product')) {
        $kinds = ['device' => __('Urządzenia', 'lasery-xtool'), 'accessory' => __('Akcesoria', 'lasery-xtool')];
        $kind = lasery_xtool_requested_kind();
        $parts['title'] = $kind !== '' ? $kinds[$kind] . ' – ' . __('Produkty xTool', 'lasery-xtool') : __('Produkty xTool', 'lasery-xtool');
        if (isset($parts['page'])) {
            $parts['page'] = sprintf(__('Strona %d', 'lasery-xtool'), max(2, (int) get_query_var('paged')));
        }
    } elseif (is_page('koszyk') && function_exists('lasery_xtool_order_texts')) {
        $parts['title'] = lasery_xtool_order_texts($language)['cart_title'];
    } elseif (is_privacy_policy()) {
        $parts['title'] = lasery_xtool_privacy_texts($language)['title'];
    } elseif (is_search()) {
        $parts['title'] = get_search_query(false) === '' ? __('Szukaj', 'lasery-xtool') : sprintf(__('Wyniki wyszukiwania: %s', 'lasery-xtool'), get_search_query(false));
    } elseif (is_404()) {
        $parts['title'] = __('Nie znaleźliśmy tej strony', 'lasery-xtool');
    }
    return $parts;
});

add_filter('locale', function ($locale) {
    if (is_admin() && (!isset($_POST['action']) || !is_string($_POST['action']) || $_POST['action'] !== 'lasery_xtool_order')) {
        return $locale;
    }
    return lasery_xtool_locales()[lasery_xtool_interface_language()];
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
        // Not cached: a page past the end becomes valid when more articles are published.
        do_action('litespeed_control_set_nocache', 'lasery-xtool blog page redirect');
        wp_safe_redirect(remove_query_arg('strona'), $page < 2 ? 301 : 302);
        exit;
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
    $query->set('lasery_demo_first', true);
    $kind = lasery_xtool_requested_kind();
    if ($kind !== '' && function_exists('lasery_xtool_kind_tax_query')) {
        $query->set('tax_query', lasery_xtool_kind_tax_query($kind));
    }
});
