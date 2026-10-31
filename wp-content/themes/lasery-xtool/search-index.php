<?php
/** Instant search over products and articles in the interface language. */
if (!defined('ABSPATH')) {
    exit;
}

/** Product entries: [type, title, searchable text, URL, image URL, price, label, post ID]. */
function lasery_xtool_search_index() {
    $language = lasery_xtool_interface_language();
    $sync = get_option('lasery_xtool_last_sync');
    // Bump when index safety rules change, so pre-existing transients are rebuilt after deployment.
    $version = '5:' . get_option('lasery_xtool_search_generation', 0) . ':' . (is_array($sync) && isset($sync['time']) ? (int) $sync['time'] : 0);
    $cached = get_transient('lasery_xtool_search_' . $language);
    if (is_array($cached) && isset($cached['version'], $cached['index'], $cached['expires_at']) && $cached['version'] === $version && (!$cached['expires_at'] || $cached['expires_at'] > time())) {
        if ($cached['expires_at'] && function_exists('lasery_xtool_track_offer_expiry') && !is_admin() && !wp_doing_cron() && !(defined('WP_CLI') && WP_CLI)) {
            lasery_xtool_track_offer_expiry($cached['expires_at']);
        }
        return $cached['index'];
    }
    // The dialog shows text, so entities from titles and prices are decoded.
    $plain = function_exists('lasery_xtool_plain_text') ? 'lasery_xtool_plain_text' : 'wp_strip_all_tags';
    $index = [];
    $expires_at = 0;
    foreach (array_merge(lasery_xtool_products('device'), lasery_xtool_products('accessory')) as $product) {
        // This index is shared across requests, including those made by CLI and cron.
        if ($product->post_password !== '') {
            continue;
        }
        $description = function_exists('lasery_xtool_product_description') ? $plain(lasery_xtool_product_description($product->ID, $language)) : '';
        // The original (English) name also finds the product, e.g. "fiber" in Polish.
        $text = $plain(get_the_title($product)) . ' ' . mb_substr($description, 0, 600);
        $price = lasery_xtool_price_html($product->ID);
        if ($price !== '' && function_exists('lasery_xtool_offer_expires_at')) {
            $expiry = lasery_xtool_offer_expires_at($product->ID);
            if ($expiry) {
                $expires_at = $expires_at ? min($expires_at, $expiry) : $expiry;
            }
        }
        $index[] = ['p', $plain(lasery_xtool_display_product_title($product->ID)), $text, get_permalink($product), (string) get_the_post_thumbnail_url($product, 'thumbnail'), $plain($price), lasery_xtool_kind_label($product->ID), $product->ID];
    }
    // Do not retain a displayed price after its source offer expires, even near the boundary.
    $ttl = $expires_at ? min(HOUR_IN_SECONDS, $expires_at - time()) : HOUR_IN_SECONDS;
    if ($ttl > 0) {
        set_transient('lasery_xtool_search_' . $language, ['version' => $version, 'index' => $index, 'expires_at' => $expires_at], $ttl);
    }
    return $index;
}

add_action('save_post', 'lasery_xtool_search_changed');
add_action('deleted_post', 'lasery_xtool_search_changed');
function lasery_xtool_search_changed($post_id) {
    if (in_array(get_post_type($post_id), ['lasery_product', 'post'], true)) {
        lasery_xtool_search_refresh();
    }
}

/** Build the search index again on its next request (products, articles or texts changed). */
function lasery_xtool_search_refresh() {
    update_option('lasery_xtool_search_generation', (int) get_option('lasery_xtool_search_generation', 0) + 1, false);
}

add_action('rest_api_init', function () {
    register_rest_route('lasery/v1', '/search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function ($request) {
            // Products and articles can become protected at any time; do not cache HTTP responses.
            do_action('litespeed_control_set_nocache', 'lasery-xtool search index');
            $query = $request->get_param('q');
            if ($query === null) {
                // Tabs opened before this change still search the old, bounded client index.
                $legacy = array_slice(lasery_xtool_search_index(), 0, 2000);
                $articles = 0;
                foreach (lasery_xtool_search_entries() as $entry) {
                    if ($entry[0] === 'a') {
                        $legacy[] = $entry;
                        if (++$articles === 100) {
                            break;
                        }
                    }
                }
                $response = rest_ensure_response($legacy);
            } else {
                $response = rest_ensure_response(lasery_xtool_search_results(is_string($query) ? $query : '', 0, 0, true));
            }
            $response->header('Cache-Control', 'no-store, private');
            return $response;
        },
    ]);
});

// Match the former dialog folding: lowercase, remove combining marks (ё → е, й → и,
// ä → a, so "gerat" finds "Gerät"), ł → l, ß → s.
function lasery_xtool_search_normalize($text) {
    $text = mb_strtolower((string) $text, 'UTF-8');
    if (!class_exists('Normalizer')) {
        return strtr(remove_accents($text, 'en_US'), ['й' => 'и', 'ё' => 'е', 'ї' => 'і', 'ѝ' => 'и', 'ѐ' => 'е', 'ѓ' => 'г', 'ќ' => 'к', 'ў' => 'у']);
    }
    $text = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
    return strtr($text, ['ł' => 'l', 'ß' => 's']);
}

/** Visit the product index and cached article batches without sending the index to the browser. */
function lasery_xtool_search_entries() {
    yield from lasery_xtool_search_index();
    $language = lasery_xtool_interface_language();
    $generation = (int) get_option('lasery_xtool_search_generation', 0);
    $plain = function_exists('lasery_xtool_plain_text') ? 'lasery_xtool_plain_text' : 'wp_strip_all_tags';
    for ($page = 1; ; ++$page) {
        $key = 'lasery_xtool_search_articles_' . $language . '_' . $page;
        $cached = get_transient($key);
        if (!is_array($cached) || !isset($cached['generation'], $cached['entries']) || $cached['generation'] !== $generation || !is_array($cached['entries'])) {
            $entries = [];
            foreach (lasery_xtool_blog_query(['posts_per_page' => 100, 'paged' => $page, 'no_found_rows' => true, 'orderby' => ['date' => 'DESC', 'ID' => 'DESC']])->posts as $post) {
                if ($post->post_password === '') {
                    $entries[] = ['a', $plain(get_the_title($post)), $plain(get_the_excerpt($post)), get_permalink($post), (string) get_the_post_thumbnail_url($post, 'medium'), '', lasery_xtool_post_date($post->ID), $post->ID];
                }
            }
            $cached = ['generation' => $generation, 'entries' => $entries];
            set_transient($key, $cached, HOUR_IN_SECONDS);
        }
        yield from $cached['entries'];
        if (count($cached['entries']) < 100) {
            break;
        }
    }
}

/** Search every entry. The dialog receives only its best 8 products and 4 articles; HTML gets a page. */
function lasery_xtool_search_results($query, $offset = 0, $limit = 24, $ranked = false) {
    $query = mb_substr((string) $query, 0, 200);
    $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
    $result = ['items' => [], 'count' => 0];
    if (!$words) {
        return $result;
    }
    // Count repeated words once per entry while retaining their previous weight in the ranking.
    $terms = [];
    foreach ($words as $word) {
        $term = lasery_xtool_search_normalize($word);
        $alternate = lasery_xtool_search_normalize(str_replace(['ß', 'ẞ'], ['ss', 'SS'], $word));
        if ($term === '') {
            continue;
        }
        $key = wp_json_encode([$term, $alternate]);
        if (isset($terms[$key])) {
            ++$terms[$key]['frequency'];
        } else {
            $terms[$key] = ['term' => $term, 'alternate' => $alternate, 'frequency' => 1];
        }
    }
    if (!$terms) {
        return $result;
    }
    $best = ['p' => [], 'a' => []];
    $position = 0;
    foreach (lasery_xtool_search_entries() as $entry) {
        $raw = $entry[1] . ' ' . $entry[2] . ' ' . $entry[6];
        $title = lasery_xtool_search_normalize($entry[1]);
        $text = lasery_xtool_search_normalize($raw);
        $alternate_title = $title;
        $alternate_text = $text;
        if (strpos($raw, 'ß') !== false || strpos($raw, 'ẞ') !== false) {
            $alternate_title = lasery_xtool_search_normalize(str_replace(['ß', 'ẞ'], ['ss', 'SS'], $entry[1]));
            $alternate_text = lasery_xtool_search_normalize(str_replace(['ß', 'ẞ'], ['ss', 'SS'], $raw));
        }
        $score = 0;
        foreach ($terms as $word) {
            $term = $word['term'];
            $match_title = $title;
            if (strpos($text, $term) === false) {
                // Prefer an original match so existing ranking stays unchanged.
                if (strpos($text, $word['alternate']) !== false) {
                    $term = $word['alternate'];
                } elseif (strpos($alternate_text, $term) !== false) {
                    $match_title = $alternate_title;
                } elseif (strpos($alternate_text, $word['alternate']) !== false) {
                    $term = $word['alternate'];
                    $match_title = $alternate_title;
                } else {
                    $score = 0;
                    break;
                }
            }
            $score += $word['frequency'] * (strpos($match_title, $term) === 0 ? 8 : (strpos($match_title, ' ' . $term) !== false ? 5 : (strpos($match_title, $term) !== false ? 3 : 1)));
        }
        if (!$score) {
            continue;
        }
        ++$result['count'];
        if ($ranked) {
            // Keep only a bounded set, preserving the original order on equal scores.
            $type = $entry[0];
            $entry[2] = '';
            $best[$type][] = ['entry' => $entry, 'score' => $score, 'position' => $position++];
            usort($best[$type], fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['position'] <=> $b['position']));
            $best[$type] = array_slice($best[$type], 0, $type === 'p' ? 8 : 4);
        } elseif ($result['count'] > $offset && count($result['items']) < $limit) {
            $result['items'][] = $entry;
        }
    }
    if ($ranked) {
        $items = array_merge($best['p'], $best['a']);
        usort($items, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['position'] <=> $b['position']));
        $result['items'] = array_column($items, 'entry');
    }
    return $result;
}
