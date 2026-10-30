<?php
/** Shared markup helpers for templates. */
if (!defined('ABSPATH')) {
    exit;
}

/** Site languages come from the catalogue plugin; without it only Polish is offered. */
function lasery_xtool_languages() {
    return function_exists('lasery_xtool_description_languages') ? lasery_xtool_description_languages() : ['pl' => 'Polski'];
}

/** Inline stroke icons; names are fixed in templates. */
function lasery_xtool_icon($name) {
    $paths = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'pause' => '<path d="M9 6v12M15 6v12"/>',
        'play' => '<path d="M8 5.5v13l11-6.5z"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
        'bag' => '<path d="M6 8h12l-1 12H7z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
        'chat' => '<path d="M20 12a8 8 0 0 1-11.6 7.1L4 20l1-4.2A8 8 0 1 1 20 12z"/>',
        'shield' => '<path d="M12 3 5 6v6c0 4.2 3 7.6 7 9 4-1.4 7-4.8 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'zoom' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6M8 11h6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="icon icon-' . $name . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

function lasery_xtool_brand() {
    ?>
    <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="32" height="32"><rect width="32" height="32" rx="9" fill="currentColor"/><path d="M8 24 24 8" stroke="#ff5a1f" stroke-width="2.6" stroke-linecap="round"/><circle cx="24" cy="8" r="3.2" fill="#ff5a1f"/></svg></span>
    <span class="brand-name"><?php bloginfo('name'); ?></span>
    <?php
}

/** Published products of one kind ('' for all): machines with a demo first, then the order set in the panel. */
function lasery_xtool_products($kind, $limit = -1, $extra = []) {
    $args = $extra + [
        'post_type' => 'lasery_product',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'],
        'no_found_rows' => true,
        'lasery_demo_first' => true,
        'suppress_filters' => false,
    ];
    if ($kind !== '' && function_exists('lasery_xtool_kind_tax_query')) {
        $args['tax_query'] = array_merge($args['tax_query'] ?? [], lasery_xtool_kind_tax_query($kind));
    }
    return get_posts($args);
}

function lasery_xtool_kind_label($post_id) {
    return function_exists('lasery_xtool_is_accessory') && lasery_xtool_is_accessory($post_id)
        ? __('Akcesorium', 'lasery-xtool')
        : __('Urządzenie', 'lasery-xtool');
}

function lasery_xtool_display_summary($post_id) {
    return function_exists('lasery_xtool_product_summary') ? lasery_xtool_product_summary($post_id, lasery_xtool_interface_language()) : '';
}

/** "from €…" when variants differ in price, otherwise the single price. Escaped HTML. */
function lasery_xtool_price_html($post_id) {
    if (!function_exists('lasery_xtool_lowest_price')) {
        return '';
    }
    $price = lasery_xtool_lowest_price($post_id);
    if (!$price) {
        return '';
    }
    $amount = '<strong>' . esc_html(lasery_xtool_format_price($price[0], $price[1], lasery_xtool_interface_language())) . '</strong>';
    /* translators: %s: the lowest product price. */
    return $price[2] ? sprintf(esc_html__('od %s', 'lasery-xtool'), $amount) : $amount;
}

/** Local product photos as [id, full URL, width, height]: the featured image first, then the gallery. */
function lasery_xtool_product_images($post_id) {
    $ids = array_filter([get_post_thumbnail_id($post_id)]);
    if (function_exists('lasery_xtool_product_gallery')) {
        $ids = array_merge($ids, lasery_xtool_product_gallery($post_id));
    }
    $images = [];
    foreach (array_unique(array_map('absint', $ids)) as $id) {
        $full = wp_get_attachment_image_src($id, 'full');
        if ($full) {
            $images[] = [$id, $full[0], $full[1], $full[2]];
        }
    }
    return $images;
}

/** Badges set in the admin panel (Produkty xTool → Oznaczenia). Escaped HTML. */
function lasery_xtool_badges_html($post_id) {
    if (!function_exists('lasery_xtool_product_badges')) {
        return '';
    }
    $html = '';
    foreach (lasery_xtool_product_badges($post_id, lasery_xtool_interface_language()) as [$slug, $label]) {
        $icon = $slug === LASERY_XTOOL_DEMO_BADGE ? lasery_xtool_icon('video') : '';
        $html .= '<span class="badge badge--' . esc_attr(sanitize_html_class($slug)) . '">' . $icon . esc_html($label) . '</span>';
    }
    return $html === '' ? '' : '<span class="badges">' . $html . '</span>';
}

function lasery_xtool_requested_kind() {
    $kind = isset($_GET['kind']) && is_string($_GET['kind']) ? sanitize_key(wp_unslash($_GET['kind'])) : '';
    return in_array($kind, ['device', 'accessory'], true) ? $kind : '';
}

/** The published blog page in the given (default: current) language, or ''. */
function lasery_xtool_blog_url($language = '') {
    $page = get_page_by_path('blog');
    if (!$page || $page->post_status !== 'publish') {
        return '';
    }
    return add_query_arg('lang', $language !== '' ? $language : lasery_xtool_interface_language(), get_permalink($page));
}

/** Blog posts written in the current language (and matching an optional meta_query). */
function lasery_xtool_blog_query($args = []) {
    $args += ['post_type' => 'post', 'post_status' => 'publish', 'ignore_sticky_posts' => true];
    if (function_exists('lasery_xtool_language_meta_query')) {
        $language = lasery_xtool_language_meta_query(lasery_xtool_interface_language());
        $args['meta_query'] = isset($args['meta_query']) ? ['relation' => 'AND', $args['meta_query'], $language] : $language;
    }
    return new WP_Query($args);
}

/** The blog listing page number (?strona) and its posts, shared by the redirect check, canonical URL and template. */
function lasery_xtool_blog_listing() {
    static $listing = null;
    if ($listing === null) {
        $page = isset($_GET['strona']) && is_string($_GET['strona']) ? absint($_GET['strona']) : 1;
        $listing = [$page, lasery_xtool_blog_query(['posts_per_page' => 9, 'paged' => max(1, $page)])];
    }
    return $listing;
}

function lasery_xtool_reading_time($post_id) {
    $words = count(preg_split('/\s+/u', trim(wp_strip_all_tags(get_post_field('post_content', $post_id))), -1, PREG_SPLIT_NO_EMPTY));
    /* translators: %d: minutes of reading. */
    return sprintf(__('%d min czytania', 'lasery-xtool'), max(1, (int) round($words / 200)));
}

/** Publication date in the interface language (core month names follow the site locale). */
function lasery_xtool_post_date($post_id) {
    $locale = function_exists('lasery_xtool_icu_locale') ? lasery_xtool_icu_locale(lasery_xtool_interface_language()) : '';
    if ($locale !== '' && class_exists('IntlDateFormatter')) {
        $formatted = (new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE, wp_timezone()))->format(get_post_timestamp($post_id));
        if (is_string($formatted)) {
            return $formatted;
        }
    }
    return get_the_date('', $post_id);
}

/** Add unique anchors to H2 headings and list them for the table of contents. */
function lasery_xtool_article_toc($html) {
    $toc = [];
    // Ids of the article page layout, which headings must not take over (e.g. "Kontakt").
    $used = array_fill_keys(['tresc', 'menu-glowne', 'article-toc-title', 'related-posts-heading', 'kontakt', 'footer-devices', 'footer-info', 'lasery-consent', 'lasery-consent-title'], true);
    $html = preg_replace_callback('~<h2([^>]*)>(.*?)</h2>~is', function ($match) use (&$toc, &$used) {
        $text = trim(wp_strip_all_tags($match[2]));
        if ($text === '') {
            return $match[0];
        }
        if (preg_match('~\sid=(["\'])(.*?)\1~', $match[1], $id)) {
            $base = wp_specialchars_decode($id[2], ENT_QUOTES);
            $attributes = str_replace($id[0], '', $match[1]);
        } else {
            // sanitize_title() percent-encodes Cyrillic and ₂; decoding keeps the ids readable.
            $base = wp_check_invalid_utf8(rawurldecode(sanitize_title($text)));
            $attributes = $match[1];
        }
        $base = $base !== '' ? $base : 'sekcja';
        $anchor = $base;
        for ($number = 2; isset($used[$anchor]); ++$number) {
            $anchor = $base . '-' . $number;
        }
        $used[$anchor] = true;
        $attributes .= ' id="' . esc_attr($anchor) . '"';
        $toc[] = [$anchor, $text];
        return '<h2' . $attributes . '>' . $match[2] . '</h2>';
    }, $html);
    return [$html, $toc];
}

/** Where a language link points: an article's translation, the blog, or the current page. */
function lasery_xtool_language_switch_url($language) {
    if (is_singular('post') && function_exists('lasery_xtool_post_translations')) {
        $translations = lasery_xtool_post_translations(get_queried_object_id());
        if (isset($translations[$language])) {
            return get_permalink($translations[$language]);
        }
        $blog = lasery_xtool_blog_url($language);
        return $blog !== '' ? $blog : add_query_arg('lang', $language, home_url('/'));
    }
    return add_query_arg('lang', $language, remove_query_arg(['cart', 'strona']));
}

