<?php
/**
 * Search engines and AI answers: the visitor's language for addresses without one, canonical
 * addresses, language alternates, descriptions, Open Graph, structured data (JSON-LD), every
 * language version in the sitemap and IndexNow notifications about new and changed pages.
 */
if (!defined('ABSPATH')) {
    exit;
}

const LASERY_XTOOL_LANGUAGE_COOKIE = 'lasery_lang';

/** The language for a page opened without one: the language chosen in the menu, the browser's languages, then English. */
function lasery_xtool_preferred_language() {
    $languages = lasery_xtool_languages();
    $chosen = isset($_COOKIE[LASERY_XTOOL_LANGUAGE_COOKIE]) && is_string($_COOKIE[LASERY_XTOOL_LANGUAGE_COOKIE]) ? sanitize_key(wp_unslash($_COOKIE[LASERY_XTOOL_LANGUAGE_COOKIE])) : '';
    if (isset($languages[$chosen])) {
        return $chosen;
    }
    $best = [isset($languages['en']) ? 'en' : 'pl', 0.0];
    $header = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 500) : '';
    // "pl-PL,pl;q=0.9,en;q=0.8": the highest weight wins, the first one on a tie.
    foreach (explode(',', $header) as $range) {
        if (preg_match('/^\s*([a-z]{2,3})(?:-[a-z0-9]{1,8})*\s*(?:;\s*q\s*=\s*([01](?:\.[0-9]{0,3})?))?\s*$/i', $range, $match)) {
            $language = strtolower($match[1]);
            $weight = isset($match[2]) && $match[2] !== '' ? (float) $match[2] : 1.0;
            if (isset($languages[$language]) && $weight > $best[1]) {
                $best = [$language, $weight];
            }
        }
    }
    return $best[0];
}

// Pages opened without a language (typed or shared addresses, x-default in search results) switch to the
// visitor's language. Articles have one language each and redirect themselves; missing pages stay 404s.
// The answer depends on the visitor, so it is never cached. It runs after WordPress's own redirects,
// which keep such addresses without a language, so the visitor still gets their language.
add_action('template_redirect', function () {
    $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if (isset($_GET['lang']) || !in_array($method, ['GET', 'HEAD'], true) || is_404() || is_singular('post') || is_feed() || is_robots() || is_favicon() || is_embed() || is_preview() || is_trackback() || is_customize_preview() || get_query_var('sitemap') !== '' || get_query_var('sitemap-stylesheet') !== '') {
        return;
    }
    nocache_headers();
    header('Vary: Accept-Language, Cookie');
    do_action('litespeed_control_set_nocache', 'lasery-xtool language redirect');
    wp_safe_redirect(add_query_arg('lang', lasery_xtool_preferred_language()), 302);
    exit;
}, 11);

/** Plain text from WordPress output that may hold tags and entities (titles, excerpts, the site name). */
function lasery_xtool_seo_text($html) {
    return trim(html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** The catalogue (one kind, one page) in a language, or without one (''), with its parameters in the site's order. */
function lasery_xtool_catalogue_url($language, $kind = '', $page = 1) {
    $url = get_post_type_archive_link('lasery_product');
    if (!$url) {
        return '';
    }
    $url = remove_query_arg('lang', $url);
    if ($page > 1) {
        $url = user_trailingslashit(trailingslashit($url) . 'page/' . $page, 'paged');
    }
    return add_query_arg(array_filter(['lang' => $language, 'kind' => $kind]), $url);
}

/**
 * Language => address of a published post: products, the blog and the privacy policy in every
 * language (translated in place), an article in its own language, other pages in Polish only;
 * nothing for the cart, which is not indexed.
 */
function lasery_xtool_post_language_urls($post) {
    if ($post->post_password !== '') {
        return [];
    }
    if ($post->post_type === 'page' && $post->post_name === (defined('LASERY_XTOOL_CART_PAGE') ? LASERY_XTOOL_CART_PAGE : 'koszyk')) {
        return [];
    }
    if ($post->post_type === 'post') {
        return [function_exists('lasery_xtool_post_language') ? lasery_xtool_post_language($post->ID) : 'pl' => get_permalink($post)];
    }
    $translated = $post->post_type === 'lasery_product' || in_array($post->post_name, ['blog', 'bezpieczenstwo', 'informacje-o-zakupach'], true) || (int) $post->ID === (int) get_option('wp_page_for_privacy_policy');
    $url = remove_query_arg('lang', get_permalink($post));
    $urls = [];
    foreach ($translated ? array_keys(lasery_xtool_languages()) : ['pl'] as $language) {
        $urls[$language] = add_query_arg('lang', $language, $url);
    }
    return $urls;
}

/**
 * The page's language alternates (language => address, with x-default), its canonical address in
 * the current language and its description; empty where they do not apply.
 */
function lasery_xtool_seo_page() {
    if (is_singular() && get_queried_object()->post_password !== '') {
        return [[], '', ''];
    }
    $language = lasery_xtool_interface_language();
    $alternates = [];
    $canonical = '';
    $description = '';
    if (is_front_page()) {
        foreach (array_keys(lasery_xtool_languages()) as $other) {
            $alternates[$other] = add_query_arg('lang', $other, home_url('/'));
        }
        $alternates['x-default'] = home_url('/');
        $description = lasery_xtool_text('hero_description');
    } elseif (is_post_type_archive('lasery_product')) {
        // The same products in the same order in every language, so each page of the catalogue has alternates.
        $kind = lasery_xtool_requested_kind();
        $page = max(1, (int) get_query_var('paged'));
        foreach (array_keys(lasery_xtool_languages()) as $other) {
            $alternates[$other] = lasery_xtool_catalogue_url($other, $kind, $page);
        }
        $alternates['x-default'] = lasery_xtool_catalogue_url('', $kind, $page);
        $description = lasery_xtool_text($kind === 'accessory' ? 'accessories_description' : 'products_description');
    } elseif (is_singular('post')) {
        $canonical = get_permalink(get_queried_object_id());
        $description = lasery_xtool_seo_text(get_the_excerpt(get_queried_object_id()));
        // Articles link their translations, which have their own addresses.
        $translations = function_exists('lasery_xtool_post_translations') ? lasery_xtool_post_translations(get_queried_object_id()) : [];
        if (count($translations) > 1) {
            $alternates = array_map('get_permalink', $translations);
            if (isset($translations['pl'])) {
                $alternates['x-default'] = get_permalink($translations['pl']);
            }
        }
    } elseif (is_singular(['lasery_product', 'page'])) {
        $post = get_queried_object();
        $urls = lasery_xtool_post_language_urls($post);
        if (!$urls) {
            return [[], '', ''];
        }
        $canonical = wp_get_canonical_url($post);
        // Further blog pages list other articles in each language, so only the first one has alternates.
        if (count($urls) > 1 && !(is_page('blog') && lasery_xtool_blog_listing()[0] > 1)) {
            $alternates = $urls + ['x-default' => remove_query_arg('lang', reset($urls))];
        }
        if (is_singular('lasery_product')) {
            // Product summaries are plain text already, so a "<5 mm" in them stays.
            $description = lasery_xtool_display_summary($post->ID);
        } elseif (is_page('blog')) {
            $description = lasery_xtool_text('blog_description');
        } elseif (is_privacy_policy()) {
            // The first sentence on how the data is used.
            $description = preg_replace('/(?<=[.!?])\s.*$/su', '', lasery_xtool_seo_text(lasery_xtool_privacy_texts($language)['purposes_text']));
        } elseif (is_page('bezpieczenstwo')) {
            $description = lasery_xtool_safety_page_texts($language)['intro'];
        } elseif (is_page('informacje-o-zakupach')) {
            $description = lasery_xtool_purchase_info_texts($language)['operator'];
        }
    }
    if ($canonical === '' && isset($alternates[$language])) {
        $canonical = $alternates[$language];
    }
    return [$alternates, $canonical, $description];
}

/** The page's main image: the product or article photo, on the home page the first machine's. */
function lasery_xtool_seo_image() {
    if (is_singular(['lasery_product', 'post'])) {
        $id = get_queried_object_id();
    } elseif (is_front_page()) {
        $first = lasery_xtool_products('device', 1);
        $id = $first ? $first[0]->ID : 0;
    } else {
        return null;
    }
    $image = $id ? wp_get_attachment_image_src(get_post_thumbnail_id($id), 'large') : false;
    return $image ? ['url' => $image[0], 'width' => $image[1], 'height' => $image[2]] : null;
}

add_action('wp_head', function () {
    [$alternates, $canonical, $description] = lasery_xtool_seo_page();
    $description = $description !== '' ? mb_strimwidth($description, 0, 160, '…') : '';
    if ($description !== '') {
        printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
    }
    // Singular pages get their canonical address from WordPress (with the language, see get_canonical_url).
    if ($canonical !== '' && !is_singular()) {
        printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
    }
    foreach ($alternates as $language => $url) {
        printf('<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr($language), esc_url($url));
    }
    if ($canonical === '') {
        return;
    }
    // Open Graph and Twitter cards for links shared in messengers and social networks. Open Graph
    // writes every locale as language_COUNTRY (WordPress's Ukrainian is just "uk").
    $locales = ['uk' => 'uk_UA'] + lasery_xtool_locales();
    $language = lasery_xtool_interface_language();
    $image = lasery_xtool_seo_image();
    $meta = [
        'og:site_name' => lasery_xtool_seo_text(get_bloginfo('name')),
        'og:locale' => $locales[$language],
        'og:type' => is_singular('post') ? 'article' : (is_singular('lasery_product') ? 'product' : 'website'),
        'og:title' => lasery_xtool_seo_text(wp_get_document_title()),
        'og:description' => $description,
        'og:url' => $canonical,
        'og:image' => $image ? $image['url'] : '',
        'og:image:width' => $image ? (string) $image['width'] : '',
        'og:image:height' => $image ? (string) $image['height'] : '',
        'twitter:card' => $image ? 'summary_large_image' : 'summary',
    ];
    foreach ($meta as $property => $content) {
        if ($content !== '') {
            printf('<meta %s="%s" content="%s">' . "\n", strpos($property, 'twitter:') === 0 ? 'name' : 'property', esc_attr($property), esc_attr($content));
        }
    }
    foreach (array_keys($alternates) as $other) {
        if (isset($locales[$other]) && $other !== $language) {
            printf('<meta property="og:locale:alternate" content="%s">' . "\n", esc_attr($locales[$other]));
        }
    }
    $data = lasery_xtool_structured_data($canonical, $description, $image);
    if ($data) {
        // Hex-escaped <, > and &, so no text can close the script element.
        printf('<script type="application/ld+json">%s</script>' . "\n", wp_json_encode(['@context' => 'https://schema.org', '@graph' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
    }
});

/** schema.org data of the page: the shop, the site, products with their offer, articles and breadcrumbs. */
function lasery_xtool_structured_data($canonical, $description, $image) {
    $language = lasery_xtool_interface_language();
    $home = add_query_arg('lang', $language, home_url('/'));
    $name = lasery_xtool_seo_text(get_bloginfo('name'));
    $shop = [
        '@type' => 'Organization',
        '@id' => home_url('/#organization'),
        'name' => $name,
        'url' => $home,
        'email' => lasery_xtool_contact_email(),
        'logo' => get_template_directory_uri() . '/assets/apple-touch-icon.png',
    ];
    if (is_front_page()) {
        return [$shop, ['@type' => 'WebSite', '@id' => home_url('/#website'), 'name' => $name, 'url' => $home, 'inLanguage' => $language, 'publisher' => ['@id' => $shop['@id']]]];
    }
    $crumbs = [[$name, $home]];
    $data = [$shop];
    if (is_singular('lasery_product')) {
        $id = get_queried_object_id();
        $title = lasery_xtool_seo_text(lasery_xtool_display_product_title($id));
        $kind = function_exists('lasery_xtool_is_accessory') && lasery_xtool_is_accessory($id) ? 'accessory' : 'device';
        $crumbs[] = [$kind === 'accessory' ? __('Akcesoria', 'lasery-xtool') : __('Urządzenia', 'lasery-xtool'), lasery_xtool_catalogue_url($language, $kind)];
        $crumbs[] = [$title, $canonical];
        // Google expects an offer with every product, so products without a current price are left out.
        $offer = lasery_xtool_product_offer($id, $canonical);
        if ($offer) {
            $product = ['@type' => 'Product', '@id' => $canonical . '#product', 'name' => $title, 'description' => $description, 'url' => $canonical, 'brand' => ['@type' => 'Brand', 'name' => 'xTool'], 'offers' => $offer];
            if ($image) {
                $product['image'] = $image['url'];
            }
            $data[] = $product;
        }
    } elseif (is_singular('post')) {
        $id = get_queried_object_id();
        $title = lasery_xtool_seo_text(get_the_title($id));
        $blog = lasery_xtool_blog_url($language);
        if ($blog !== '') {
            $crumbs[] = [__('Blog', 'lasery-xtool'), $blog];
        }
        $crumbs[] = [$title, $canonical];
        $article = [
            '@type' => 'Article',
            '@id' => $canonical . '#article',
            'headline' => $title,
            'description' => $description,
            'mainEntityOfPage' => $canonical,
            'inLanguage' => $language,
            'datePublished' => get_post_time('c', true, $id),
            'dateModified' => get_post_modified_time('c', true, $id),
            'author' => ['@id' => $shop['@id']],
            'publisher' => ['@id' => $shop['@id']],
        ];
        if ($image) {
            $article['image'] = $image['url'];
        }
        $data[] = $article;
    } else {
        return [];
    }
    $data[] = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(function ($crumb, $index) {
            return ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb[0], 'item' => $crumb[1]];
        }, $crumbs, array_keys($crumbs)),
    ];
    return $data;
}

/** The offer of a product with current prices: one price, or the range of its available variants. */
function lasery_xtool_product_offer($post_id, $url) {
    if (!function_exists('lasery_xtool_lowest_price')) {
        return null;
    }
    $lowest = lasery_xtool_lowest_price($post_id);
    if (!$lowest) {
        return null;
    }
    $offer = ['url' => $url, 'priceCurrency' => $lowest[1], 'seller' => ['@type' => 'Organization', 'name' => 'xTool']];
    // A manual estimate does not establish availability. Source prices only reach here
    // when at least one source variant is both available and fresh.
    if (lasery_xtool_uses_source_variants($post_id)) {
        $offer['availability'] = 'https://schema.org/InStock';
    }
    if (!$lowest[2]) {
        return ['@type' => 'Offer', 'price' => $lowest[0]] + $offer;
    }
    $prices = [];
    foreach (lasery_xtool_available_variants($post_id) as $variant) {
        $amount = lasery_xtool_variant_amount($post_id, $variant);
        if ($amount && $amount[1] === $lowest[1]) {
            $prices[] = (float) $amount[0];
        }
    }
    return ['@type' => 'AggregateOffer', 'lowPrice' => $lowest[0], 'highPrice' => number_format(max($prices), 2, '.', ''), 'offerCount' => count($prices)] + $offer;
}

// Pages in the sitemap carry their language, like products and articles (a page without one redirects).
add_filter('wp_sitemaps_posts_entry', function ($entry, $post) {
    if ($post->post_type === 'page') {
        $entry['loc'] = add_query_arg('lang', 'pl', remove_query_arg('lang', $entry['loc']));
    }
    return $entry;
}, 10, 2);
add_filter('wp_sitemaps_posts_show_on_front_entry', function ($entry) {
    $entry['loc'] = add_query_arg('lang', 'pl', $entry['loc']);
    return $entry;
});

// The other language versions of the home page, pages and products, and the catalogue in every language.
add_action('init', function () {
    if (function_exists('wp_register_sitemap_provider') && class_exists('WP_Sitemaps_Provider')) {
        require_once __DIR__ . '/class-sitemap-languages.php';
        wp_register_sitemap_provider('languages', new Lasery_XTool_Sitemap_Languages());
    }
});

/** The IndexNow key, created on first use; search engines check it at /<key>.txt. */
function lasery_xtool_indexnow_key() {
    $key = get_option('lasery_xtool_indexnow_key');
    if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/D', $key)) {
        $key = bin2hex(random_bytes(16));
        update_option('lasery_xtool_indexnow_key', $key, false);
    }
    return $key;
}

// The key file. The option is read only for addresses that look like one.
add_action('template_redirect', function () {
    $path = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? (string) wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if (!preg_match('~/([a-f0-9]{32})\.txt$~D', $path, $match)) {
        return;
    }
    $key = get_option('lasery_xtool_indexnow_key');
    if (is_string($key) && $key === $match[1] && $path === wp_parse_url(home_url('/' . $key . '.txt'), PHP_URL_PATH)) {
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo esc_html($key);
        exit;
    }
}, 0);

/** Queue addresses for IndexNow; they are sent together a minute later. Only a public production site sends any. */
function lasery_xtool_indexnow_queue($urls, $delay = MINUTE_IN_SECONDS) {
    if (!$urls || !get_option('blog_public') || wp_get_environment_type() !== 'production') {
        return;
    }
    $queue = get_option('lasery_xtool_indexnow_queue', []);
    update_option('lasery_xtool_indexnow_queue', array_values(array_unique(array_merge(is_array($queue) ? $queue : [], $urls))), false);
    if (!wp_next_scheduled('lasery_xtool_indexnow_send')) {
        wp_schedule_single_event(time() + $delay, 'lasery_xtool_indexnow_send');
    }
}

// A page, product or article that is published (also by scripts), or changes its title, text, excerpt or
// address. Price changes from the hourly sync do not count.
add_action('wp_after_insert_post', function ($id, $post, $update, $before) {
    $changed = !$before || $before->post_status !== 'publish' || $post->post_title !== $before->post_title || $post->post_content !== $before->post_content || $post->post_excerpt !== $before->post_excerpt || $post->post_name !== $before->post_name;
    if ($changed && $post->post_status === 'publish' && in_array($post->post_type, ['page', 'post', 'lasery_product'], true)) {
        lasery_xtool_indexnow_queue(array_values(lasery_xtool_post_language_urls($post)));
    }
}, 10, 4);

// Addresses that could not be delivered (network error, rate limit, server error) are tried again an hour later.
add_action('lasery_xtool_indexnow_send', 'lasery_xtool_indexnow_send');
function lasery_xtool_indexnow_send() {
    $urls = get_option('lasery_xtool_indexnow_queue', []);
    delete_option('lasery_xtool_indexnow_queue');
    if (!is_array($urls) || !$urls) {
        return;
    }
    $key = lasery_xtool_indexnow_key();
    $response = wp_remote_post('https://api.indexnow.org/indexnow', [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
        'body' => wp_json_encode(['host' => wp_parse_url(home_url(), PHP_URL_HOST), 'key' => $key, 'keyLocation' => home_url('/' . $key . '.txt'), 'urlList' => array_slice($urls, 0, 10000)]),
    ]);
    $status = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
    update_option('lasery_xtool_indexnow_last', ['time' => time(), 'urls' => count($urls), 'status' => $status], false);
    if (is_wp_error($response) || $status === 429 || $status >= 500) {
        lasery_xtool_indexnow_queue($urls, HOUR_IN_SECONDS);
    }
}
