<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/cookie-consent.php';
require_once __DIR__ . '/privacy-defaults.php';
require_once __DIR__ . '/safety-info.php';
require_once __DIR__ . '/purchase-info.php';
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

/**
 * A theme file's version in its URL: the time it last changed. Browsers keep the theme's files for a year,
 * so a new release must change their URLs.
 */
function lasery_xtool_asset_version($file) {
    return (string) filemtime(get_template_directory() . '/' . $file);
}

add_action('wp_enqueue_scripts', function () {
    $assets = get_template_directory_uri() . '/assets/';
    $script = ['in_footer' => true, 'strategy' => 'defer'];
    wp_enqueue_style('lasery-xtool', get_stylesheet_uri(), [], lasery_xtool_asset_version('style.css'));
    $sliders = is_front_page() || is_singular('lasery_product');
    if ($sliders) {
        wp_enqueue_script('lasery-xtool-swiper', $assets . 'lib/swiper/swiper-bundle.min.js', [], '14.3.0', $script);
    }
    wp_enqueue_script('lasery-xtool-site', $assets . 'site.js', $sliders ? ['lasery-xtool-swiper'] : [], lasery_xtool_asset_version('assets/site.js'), $script);
    if (is_singular('lasery_product')) {
        wp_enqueue_script_module('lasery-xtool-lightbox', $assets . 'lightbox.js', [], lasery_xtool_asset_version('assets/lightbox.js'));
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

/** Protected posts this user cannot edit; null means every protected post for an anonymous visitor. */
function lasery_xtool_rest_hidden_posts() {
    static $by_user = [];
    $user_id = get_current_user_id();
    if (!$user_id) {
        return null;
    }
    if (!isset($by_user[$user_id])) {
        $hidden = [];
        $posts = get_posts(['post_type' => get_post_types(['public' => true]), 'post_status' => ['publish', 'future', 'draft', 'pending', 'private', 'trash'], 'has_password' => true, 'posts_per_page' => -1, 'update_post_meta_cache' => false, 'update_post_term_cache' => false]);
        foreach ($posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) {
                $hidden[] = $post->ID;
            }
        }
        $by_user[$user_id] = $hidden;
    }
    return $by_user[$user_id];
}

// Collections and search enforce the same per-post permissions as the single REST endpoint.
function lasery_xtool_rest_public_query($args) {
    $hidden = lasery_xtool_rest_hidden_posts();
    unset($args['post_password']);
    if ($hidden === null) {
        $args['has_password'] = false;
    } elseif ($hidden) {
        // WP_Query ignores post__not_in when a caller also specifies post__in.
        $args['lasery_hidden_posts'] = $hidden;
    }
    return $args;
}
foreach (['post', 'page', 'lasery_product'] as $type) {
    add_filter('rest_' . $type . '_query', 'lasery_xtool_rest_public_query');
}
add_filter('rest_post_search_query', function ($args) {
    $args = lasery_xtool_rest_public_query($args);
    $args['lasery_hidden_media_parents'] = lasery_xtool_rest_hidden_posts() ?? true;
    return $args;
});
add_filter('rest_attachment_query', function ($args) {
    $args['lasery_hidden_media_parents'] = lasery_xtool_rest_hidden_posts() ?? true;
    return $args;
});
add_filter('posts_where', function ($where, $query) {
    $posts = $query->get('lasery_hidden_posts');
    if (is_array($posts) && $posts) {
        global $wpdb;
        $where .= " AND {$wpdb->posts}.ID NOT IN (" . implode(',', array_map('absint', $posts)) . ')';
    }
    $hidden = $query->get('lasery_hidden_media_parents');
    if ($hidden === true) {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_type <> 'attachment' OR NOT EXISTS (SELECT 1 FROM {$wpdb->posts} lasery_parent WHERE lasery_parent.ID = {$wpdb->posts}.post_parent AND lasery_parent.post_password <> ''))";
    } elseif (is_array($hidden) && $hidden) {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_type <> 'attachment' OR {$wpdb->posts}.post_parent NOT IN (" . implode(',', array_map('absint', $hidden)) . '))';
    }
    return $where;
}, 10, 2);
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ($result !== null || !in_array($request->get_method(), ['GET', 'HEAD'], true) || !preg_match('~^/wp/v2/(posts|pages|media|lasery_product)/(\d+)$~', $request->get_route(), $match)) {
        return $result;
    }
    $post = get_post((int) $match[2]);
    $types = ['posts' => 'post', 'pages' => 'page', 'media' => 'attachment', 'lasery_product' => 'lasery_product'];
    $protected = $post && $post->post_type === $types[$match[1]] ? $post : null;
    if ($protected && $protected->post_type === 'attachment') {
        $protected = $protected->post_parent ? get_post($protected->post_parent) : null;
    }
    if ($protected && $protected->post_password !== '' && !current_user_can('edit_post', $protected->ID)) {
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
    }
    return $result;
}, 10, 3);
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return in_array($name, ['users', 'taxonomies'], true) ? false : $provider;
}, 10, 2);
add_filter('oembed_response_data', function ($data, $post) {
    if ($post->post_password !== '' && !current_user_can('edit_post', $post->ID)) {
        return false;
    }
    unset($data['author_name'], $data['author_url']);
    return $data;
}, 10, 2);
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
    if (is_singular() && get_queried_object()->post_password !== '') {
        $parts['title'] = __('Protected');
        return $parts;
    }
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
    } elseif (is_page('blog')) {
        // The page title is Polish; later list pages are numbered like the catalogue's.
        $parts['title'] = __('Blog', 'lasery-xtool');
        $page = lasery_xtool_blog_listing()[0];
        if ($page > 1) {
            $parts['page'] = sprintf(__('Strona %d', 'lasery-xtool'), $page);
        }
    } elseif (is_privacy_policy()) {
        $parts['title'] = lasery_xtool_privacy_texts($language)['title'];
    } elseif (is_page('bezpieczenstwo')) {
        $parts['title'] = lasery_xtool_safety_texts($language)['title'];
    } elseif (is_page('informacje-o-zakupach')) {
        $parts['title'] = lasery_xtool_purchase_info_title();
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
    if ($post->post_password !== '') {
        return false;
    }
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

// A password cookie must never affect a shared response, even after the page is unlocked.
add_action('template_redirect', function () {
    if (is_singular() && get_queried_object()->post_password !== '') {
        nocache_headers();
        do_action('litespeed_control_set_nocache', 'password-protected content');
    }
}, 0);

// Custom page templates do not necessarily call the_content(), which normally checks the password.
add_filter('template_include', function ($template) {
    return is_singular() && post_password_required() ? get_template_directory() . '/protected.php' : $template;
}, 99);

// Pages with source prices (also product cards inside articles) and the article list stay in the page cache for at most an hour.
add_action('template_redirect', function () {
    if (is_singular() && get_queried_object()->post_password !== '') {
        return;
    }
    if (is_front_page() || is_post_type_archive('lasery_product') || is_singular(['lasery_product', 'post']) || is_page('blog')) {
        do_action('litespeed_control_set_ttl', HOUR_IN_SECONDS);
    }
});

// Protected posts and products can be opened directly, but never belong in a shared listing.
add_action('pre_get_posts', function ($query) {
    if (!is_admin() && !(defined('REST_REQUEST') && REST_REQUEST) && !wp_doing_cron() && !(defined('WP_CLI') && WP_CLI) && !$query->is_singular() && in_array($query->get('post_type'), ['post', 'lasery_product'], true)) {
        $query->set('has_password', false);
    }
});

// Core and language sitemap providers can also be rendered outside a normal front-end request.
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    if (in_array($post_type, ['post', 'page', 'lasery_product'], true)) {
        $args['has_password'] = false;
    }
    return $args;
}, 10, 2);

add_filter('wp_robots', function ($robots) {
    if (is_singular() && get_queried_object()->post_password !== '') {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
});

// Cards can appear on any page, including cached articles. Invalidate all shared pages on a visibility change.
add_action('wp_after_insert_post', function ($id, $post, $update, $before) {
    if ($update && $before && in_array($post->post_type, ['post', 'page', 'lasery_product'], true) && $post->post_password !== $before->post_password) {
        do_action('litespeed_purge_all', 'content password changed');
    }
}, 10, 4);

// Post archives and feeds list the articles of the interface language only.
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || $query->is_singular() || $query->is_search() || $query->is_comment_feed() || $query->is_post_type_archive() || !function_exists('lasery_xtool_language_meta_query')) {
        return;
    }
    if ($query->is_home() || $query->is_archive() || $query->is_feed()) {
        $query->set('has_password', false);
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
