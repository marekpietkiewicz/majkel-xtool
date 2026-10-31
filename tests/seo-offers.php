<?php
/** Run with php tests/seo-offers.php. Uses actual pricing helpers with a small metadata fixture. */
define('ABSPATH', __DIR__ . '/');
define('LASERY_XTOOL_PRODUCT_TYPE', 'lasery_product');
define('LASERY_XTOOL_SOURCE_ID', '_lasery_source_id');
define('DAY_IN_SECONDS', 86400);
define('WP_CLI', true);

function add_action(...$args) {}
function add_filter(...$args) {}
function is_admin() { return false; }
function wp_doing_cron() { return false; }
function get_post_meta($id, $key, $single) { return $GLOBALS['metadata'][$id][$key] ?? ''; }
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['metadata'][$id] ?? []); }

require __DIR__ . '/../wp-content/plugins/lasery-xtool/order-form.php';
require __DIR__ . '/../wp-content/themes/lasery-xtool/seo.php';

$check = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
};
$url = 'https://example.test/product/';
$variant = ['id' => '101', 'title' => 'A', 'price' => '42.00', 'available' => true];
$GLOBALS['metadata'] = [
    1 => ['_lasery_order_price' => '12.34', '_lasery_order_currency' => 'EUR'],
    2 => [LASERY_XTOOL_SOURCE_ID => '123', '_lasery_source_updated_at' => (string) time(), '_lasery_source_currency' => 'EUR', '_lasery_source_variants' => [$variant]],
];
$manual = lasery_xtool_product_offer(1, $url);
$check($manual['price'] === '12.34' && !isset($manual['availability']), 'manual price does not assert stock');
$source = lasery_xtool_product_offer(2, $url);
$check($source['price'] === '42.00' && $source['availability'] === 'https://schema.org/InStock', 'fresh available source variant is in stock');
$second = ['id' => '102', 'title' => 'B', 'price' => '55.00', 'available' => true];
$GLOBALS['metadata'][2]['_lasery_source_variants'][] = $second;
$aggregate = lasery_xtool_product_offer(2, $url);
$check($aggregate['@type'] === 'AggregateOffer' && $aggregate['lowPrice'] === '42.00' && $aggregate['highPrice'] === '55.00' && $aggregate['availability'] === 'https://schema.org/InStock', 'multiple fresh source prices produce an in-stock range');
$GLOBALS['metadata'][2]['_lasery_source_variants'][0]['available'] = false;
$GLOBALS['metadata'][2]['_lasery_source_variants'][1]['available'] = false;
$check(lasery_xtool_product_offer(2, $url) === null, 'unavailable variants produce no offer');
$GLOBALS['metadata'][2]['_lasery_source_variants'][0]['available'] = true;
$GLOBALS['metadata'][2]['_lasery_source_updated_at'] = (string) (time() - DAY_IN_SECONDS - 1);
$check(lasery_xtool_product_offer(2, $url) === null, 'stale source data produces no offer');
