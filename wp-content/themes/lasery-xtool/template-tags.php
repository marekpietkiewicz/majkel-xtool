<?php
/** Shared markup helpers for templates. */
if (!defined('ABSPATH')) {
    exit;
}

function lasery_xtool_languages() {
    return ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'uk' => 'Українська'];
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

/** Published products of one kind in the order set in the admin panel. */
function lasery_xtool_products($kind, $limit = -1, $exclude = []) {
    $args = [
        'post_type' => 'lasery_product',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'],
        'post__not_in' => $exclude,
        'no_found_rows' => true,
    ];
    if (function_exists('lasery_xtool_kind_tax_query')) {
        $args['tax_query'] = lasery_xtool_kind_tax_query($kind);
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

/** "from €…" when several variants are offered, otherwise the single price. Escaped HTML. */
function lasery_xtool_price_html($post_id) {
    if (!function_exists('lasery_xtool_lowest_price')) {
        return '';
    }
    $price = lasery_xtool_lowest_price($post_id);
    if (!$price) {
        return '';
    }
    $amount = '<strong>' . esc_html(lasery_xtool_format_price($price[0], $price[1], lasery_xtool_interface_language())) . '</strong>';
    return count(lasery_xtool_available_variants($post_id)) > 1
        /* translators: %s: the lowest product price. */
        ? sprintf(esc_html__('od %s', 'lasery-xtool'), $amount)
        : $amount;
}

/** Local product photos: the featured image first, then the gallery. */
function lasery_xtool_product_images($post_id) {
    $ids = array_filter([get_post_thumbnail_id($post_id)]);
    if (function_exists('lasery_xtool_product_gallery')) {
        $ids = array_merge($ids, lasery_xtool_product_gallery($post_id));
    }
    return array_values(array_unique(array_map('absint', $ids)));
}

function lasery_xtool_requested_kind() {
    $kind = isset($_GET['kind']) && is_string($_GET['kind']) ? sanitize_key(wp_unslash($_GET['kind'])) : '';
    return in_array($kind, ['device', 'accessory'], true) ? $kind : '';
}
