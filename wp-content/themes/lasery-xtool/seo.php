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
// visitor's language. Articles have one language each and redirect themselves. The answer depends on the
// visitor, so it is never cached. After WordPress's own redirects (e.g. old addresses), so there is one hop.
add_action('template_redirect', function () {
    $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if (isset($_GET['lang']) || !in_array($method, ['GET', 'HEAD'], true) || is_singular('post') || is_feed() || is_robots() || is_favicon() || is_embed() || is_preview() || is_trackback() || is_customize_preview() || get_query_var('sitemap') !== '' || get_query_var('sitemap-stylesheet') !== '') {
        return;
    }
    nocache_headers();
    header('Vary: Accept-Language, Cookie');
    do_action('litespeed_control_set_nocache', 'lasery-xtool language redirect');
    wp_safe_redirect(add_query_arg('lang', lasery_xtool_preferred_language()), 302);
    exit;
}, 11);

/**
 * The page's address without its language (for alternates in every language), its canonical
 * address in the current language and its description; empty strings where they do not apply.
 */
function lasery_xtool_seo_page() {
    $language = lasery_xtool_interface_language();
    $neutral = '';
    $canonical = '';
    $description = '';
    if (is_front_page()) {
        $neutral = home_url('/');
        $description = lasery_xtool_text('hero_description');
    } elseif (is_singular('lasery_product')) {
        $canonical = get_permalink(get_queried_object_id());
        $neutral = remove_query_arg('lang', $canonical);
        $description = lasery_xtool_display_summary(get_queried_object_id());
    } elseif (is_singular('post')) {
        $canonical = get_permalink(get_queried_object_id());
        $description = get_the_excerpt(get_queried_object_id());
    } elseif (is_page('blog')) {
        $canonical = wp_get_canonical_url(get_queried_object_id());
        // Further pages list other articles in each language, so only the first one has alternates.
        $neutral = lasery_xtool_blog_listing()[0] > 1 ? '' : remove_query_arg('lang', $canonical);
        $description = lasery_xtool_text('blog_description');
    } elseif (is_privacy_policy()) {
        $canonical = wp_get_canonical_url(get_queried_object_id());
        $neutral = remove_query_arg('lang', $canonical);
    } elseif (is_post_type_archive('lasery_product')) {
        // The same products in the same order in every language, so each page of the catalogue has alternates.
        $kind = lasery_xtool_requested_kind();
        $neutral = remove_query_arg('lang', get_post_type_archive_link('lasery_product'));
        $page = max(1, (int) get_query_var('paged'));
        if ($page > 1) {
            $neutral = user_trailingslashit(trailingslashit($neutral) . 'page/' . $page, 'paged');
        }
        $neutral = $kind !== '' ? add_query_arg('kind', $kind, $neutral) : $neutral;
        $description = lasery_xtool_text($kind === 'accessory' ? 'accessories_description' : 'products_description');
    }
    if ($canonical === '' && $neutral !== '') {
        $canonical = add_query_arg('lang', $language, $neutral);
    }
    return [$neutral, $canonical, $description];
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
    [$neutral, $canonical, $description] = lasery_xtool_seo_page();
    $description = trim(wp_strip_all_tags($description));
    // Descriptions are plain text, so they are shortened without stripping tags again (which would cut "<5 mm").
    $description = $description !== '' ? mb_strimwidth($description, 0, 160, '…') : '';
    if ($description !== '') {
        printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
    }
    // Singular pages get their canonical address from WordPress (with the language, see get_canonical_url).
    if ($canonical !== '' && !is_singular()) {
        printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
    }
    // Open Graph writes every locale as language_COUNTRY (WordPress's Ukrainian is just "uk").
    $locales = ['uk' => 'uk_UA'] + lasery_xtool_locales();
    $alternates = [];
    if ($neutral !== '') {
        foreach (array_keys(lasery_xtool_languages()) as $language) {
            $alternates[$language] = add_query_arg('lang', $language, $neutral);
        }
        // The address without a language opens in the visitor's language.
        $alternates['x-default'] = $neutral;
    } elseif (is_singular('post') && function_exists('lasery_xtool_post_translations')) {
        // Articles link their translations, which have their own addresses.
        $translations = lasery_xtool_post_translations(get_queried_object_id());
        if (count($translations) > 1) {
            foreach ($translations as $language => $id) {
                $alternates[$language] = get_permalink($id);
            }
            if (isset($translations['pl'])) {
                $alternates['x-default'] = get_permalink($translations['pl']);
            }
        }
    }
    foreach ($alternates as $language => $url) {
        printf('<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr($language), esc_url($url));
    }
    if ($canonical === '') {
        return;
    }
    // Open Graph and Twitter cards for links shared in messengers and social networks.
    $language = lasery_xtool_interface_language();
    $image = lasery_xtool_seo_image();
    $meta = [
        'og:site_name' => get_bloginfo('name'),
        'og:locale' => $locales[$language],
        'og:type' => is_singular('post') ? 'article' : (is_singular('lasery_product') ? 'product' : 'website'),
        'og:title' => wp_get_document_title(),
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

/** WordPress locales of the site languages (also used for og:locale). */
function lasery_xtool_locales() {
    return ['pl' => 'pl_PL', 'en' => 'en_US', 'de' => 'de_DE', 'ru' => 'ru_RU', 'uk' => 'uk'];
}

/** schema.org data of the page: the shop, the site with its search, products, articles and breadcrumbs. */
function lasery_xtool_structured_data($canonical, $description, $image) {
    $language = lasery_xtool_interface_language();
    $home = add_query_arg('lang', $language, home_url('/'));
    $shop = [
        '@type' => 'Organization',
        '@id' => home_url('/#organization'),
        'name' => get_bloginfo('name'),
        'url' => $home,
        'email' => lasery_xtool_contact_email(),
        'logo' => get_template_directory_uri() . '/assets/apple-touch-icon.png',
    ];
    $data = [];
    if (is_front_page()) {
        $data[] = $shop;
        $data[] = [
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'name' => get_bloginfo('name'),
            'url' => $home,
            'inLanguage' => $language,
            'publisher' => ['@id' => $shop['@id']],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => add_query_arg('lang', $language, home_url('/')) . '&s={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        return $data;
    }
    $crumbs = [[get_bloginfo('name'), $home]];
    if (is_singular('lasery_product')) {
        $id = get_queried_object_id();
        $title = wp_strip_all_tags(lasery_xtool_display_product_title($id));
        $accessory = function_exists('lasery_xtool_is_accessory') && lasery_xtool_is_accessory($id);
        $crumbs[] = [$accessory ? __('Akcesoria', 'lasery-xtool') : __('Urządzenia', 'lasery-xtool'), add_query_arg('kind', $accessory ? 'accessory' : 'device', get_post_type_archive_link('lasery_product'))];
        $crumbs[] = [$title, $canonical];
        $product = [
            '@type' => 'Product',
            '@id' => $canonical . '#product',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'brand' => ['@type' => 'Brand', 'name' => 'xTool'],
        ];
        if ($image) {
            $product['image'] = $image['url'];
        }
        $offer = lasery_xtool_product_offer($id, $canonical, $shop['@id']);
        if ($offer) {
            $product['offers'] = $offer;
        }
        $data[] = $shop;
        $data[] = $product;
    } elseif (is_singular('post')) {
        $id = get_queried_object_id();
        $blog = lasery_xtool_blog_url($language);
        if ($blog !== '') {
            $crumbs[] = [__('Blog', 'lasery-xtool'), $blog];
        }
        $crumbs[] = [get_the_title($id), $canonical];
        $article = [
            '@type' => 'Article',
            '@id' => $canonical . '#article',
            'headline' => get_the_title($id),
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
        $data[] = $shop;
        $data[] = $article;
    } else {
        return $data;
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
function lasery_xtool_product_offer($post_id, $url, $seller) {
    if (!function_exists('lasery_xtool_lowest_price')) {
        return null;
    }
    $lowest = lasery_xtool_lowest_price($post_id);
    if (!$lowest) {
        return null;
    }
    $offer = ['url' => $url, 'priceCurrency' => $lowest[1], 'availability' => 'https://schema.org/InStock', 'seller' => ['@id' => $seller]];
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

// The other language versions of pages and products, and the catalogue in every language.
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

add_action('template_redirect', function () {
    $key = get_option('lasery_xtool_indexnow_key');
    $path = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if (is_string($key) && $key !== '' && $path === '/' . $key . '.txt') {
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo esc_html($key);
        exit;
    }
}, 0);

/** Every language version of a page or product; an article has its own address per language. */
function lasery_xtool_indexnow_urls($post) {
    if ($post->post_type === 'post') {
        return [get_permalink($post)];
    }
    $url = remove_query_arg('lang', get_permalink($post));
    return array_map(function ($language) use ($url) {
        return add_query_arg('lang', $language, $url);
    }, array_keys(lasery_xtool_languages()));
}

/** Queue addresses for IndexNow; they are sent together a minute later. */
function lasery_xtool_indexnow_queue($urls) {
    $queue = get_option('lasery_xtool_indexnow_queue', []);
    update_option('lasery_xtool_indexnow_queue', array_values(array_unique(array_merge(is_array($queue) ? $queue : [], $urls))), false);
    if (!wp_next_scheduled('lasery_xtool_indexnow_send')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'lasery_xtool_indexnow_send');
    }
}

// A page, product or article that is published (also by scripts), or changes its title, text, excerpt or
// address. Price changes from the hourly sync do not count.
add_action('wp_after_insert_post', function ($id, $post, $update, $before) {
    $changed = !$before || $before->post_status !== 'publish' || $post->post_title !== $before->post_title || $post->post_content !== $before->post_content || $post->post_excerpt !== $before->post_excerpt || $post->post_name !== $before->post_name;
    // The cart is not indexed.
    if ($changed && $post->post_status === 'publish' && in_array($post->post_type, ['page', 'post', 'lasery_product'], true) && !($post->post_type === 'page' && $post->post_name === 'koszyk')) {
        lasery_xtool_indexnow_queue(lasery_xtool_indexnow_urls($post));
    }
}, 10, 4);

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
    update_option('lasery_xtool_indexnow_last', ['time' => time(), 'urls' => count($urls), 'status' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)], false);
}
