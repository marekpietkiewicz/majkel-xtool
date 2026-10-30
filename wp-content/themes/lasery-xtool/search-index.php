<?php
/** Instant search over products and articles in the interface language. */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search entries: [type (p|a), title, searchable text, URL, image URL, price, label, post ID].
 * Cached per language until a product or article is saved or the hourly offer sync runs.
 */
function lasery_xtool_search_index() {
    $language = lasery_xtool_interface_language();
    $sync = get_option('lasery_xtool_last_sync');
    $version = get_option('lasery_xtool_search_generation', 0) . ':' . (is_array($sync) && isset($sync['time']) ? (int) $sync['time'] : 0);
    $cached = get_transient('lasery_xtool_search_' . $language);
    if (is_array($cached) && isset($cached['version'], $cached['index']) && $cached['version'] === $version) {
        return $cached['index'];
    }
    // The dialog shows text, so entities from titles and prices are decoded.
    $plain = function_exists('lasery_xtool_plain_text') ? 'lasery_xtool_plain_text' : 'wp_strip_all_tags';
    $index = [];
    foreach (array_merge(lasery_xtool_products('device'), lasery_xtool_products('accessory')) as $product) {
        $description = function_exists('lasery_xtool_product_description') ? $plain(lasery_xtool_product_description($product->ID, $language)) : '';
        // The original (English) name also finds the product, e.g. "fiber" in Polish.
        $text = $plain(get_the_title($product)) . ' ' . mb_substr($description, 0, 600);
        $index[] = ['p', $plain(lasery_xtool_display_product_title($product->ID)), $text, get_permalink($product), (string) get_the_post_thumbnail_url($product, 'thumbnail'), $plain(lasery_xtool_price_html($product->ID)), lasery_xtool_kind_label($product->ID), $product->ID];
    }
    foreach (lasery_xtool_blog_query(['posts_per_page' => 100, 'no_found_rows' => true])->posts as $post) {
        $index[] = ['a', $plain(get_the_title($post)), $plain(get_the_excerpt($post)), get_permalink($post), (string) get_the_post_thumbnail_url($post, 'medium'), '', lasery_xtool_post_date($post->ID), $post->ID];
    }
    // Shown prices also expire with their 24-hour freshness, so the cache stays short.
    set_transient('lasery_xtool_search_' . $language, ['version' => $version, 'index' => $index], HOUR_IN_SECONDS);
    return $index;
}

add_action('save_post', 'lasery_xtool_search_changed');
add_action('deleted_post', 'lasery_xtool_search_changed');
function lasery_xtool_search_changed($post_id) {
    if (in_array(get_post_type($post_id), ['lasery_product', 'post'], true)) {
        update_option('lasery_xtool_search_generation', (int) get_option('lasery_xtool_search_generation', 0) + 1, false);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('lasery/v1', '/search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            // LiteSpeed does not cache REST responses unless told to.
            do_action('litespeed_control_set_cacheable', 'lasery-xtool search index');
            do_action('litespeed_control_set_ttl', 600);
            $response = rest_ensure_response(lasery_xtool_search_index());
            $response->header('Cache-Control', 'public, max-age=600');
            return $response;
        },
    ]);
});

// The same folding as the dialog's script: lower case, combining marks removed (ё → е, й → и,
// ä → a, so "gerat" finds "Gerät"), ł → l, ß → s.
function lasery_xtool_search_normalize($text) {
    $text = mb_strtolower((string) $text, 'UTF-8');
    if (!class_exists('Normalizer')) {
        return remove_accents($text, 'en_US');
    }
    $text = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
    return strtr($text, ['ł' => 'l', 'ß' => 's']);
}

/** Entries containing every word of the query: the results page for visitors without JavaScript. */
function lasery_xtool_search_matches($query) {
    $terms = preg_split('/\s+/u', lasery_xtool_search_normalize($query), -1, PREG_SPLIT_NO_EMPTY);
    if (!$terms) {
        return [];
    }
    return array_values(array_filter(lasery_xtool_search_index(), function ($entry) use ($terms) {
        $text = lasery_xtool_search_normalize($entry[1] . ' ' . $entry[2] . ' ' . $entry[6]);
        foreach ($terms as $term) {
            if (strpos($text, $term) === false) {
                return false;
            }
        }
        return true;
    }));
}
