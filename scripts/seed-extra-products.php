<?php
/** Publish selected machines from verified private Shopify snapshots; existing products are skipped. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'lasery_xtool_merge_handle_documents' ) ) {
    return;
}
if ( ! isset( $args[0], $args[1] ) || ! is_dir( $args[0] ) || ! ctype_digit( $args[1] ) ) {
    WP_CLI::error( 'Supply the private snapshot directory and its Unix fetch timestamp.' );
}
$timestamp = (int) $args[1];
if ( $timestamp > time() || $timestamp < time() - HOUR_IN_SECONDS ) {
    WP_CLI::error( 'Snapshots must be less than one hour old.' );
}
$products = [
    '9163846877397' => 'xtool-f2-5w-ir-15w-diode-portable-dual-laser-engraver',
    '9089720189141' => 'xtool-p3-the-flagship-80w-co2-laser-cutters',
    '8169201631445' => 'xtool-s1-laser-cutter',
    '9187692970197' => 'xtool-f2-ultra-uv-5w-uv-laser-engraver',
    '9009695293653' => 'xtool-f2-ultra-60w-mopa-40w-diode-dual-laser-engraver',
    '8974447804629' => 'xtool-metalfab-laser-welder-and-cnc-cutter',
];
$prepared = [];
$exists = static function ( $id ) {
    return (bool) get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ], 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => (string) $id, 'fields' => 'ids', 'posts_per_page' => 1 ] );
};
foreach ( $products as $id => $handle ) {
    if ( $exists( $id ) ) {
        WP_CLI::log( 'Product ' . $id . ' already exists; skipped.' );
        continue;
    }
    $json_path = $args[0] . '/' . $handle . '.json';
    $js_path = $args[0] . '/' . $handle . '.js';
    if ( ! is_readable( $json_path ) || ! is_readable( $js_path ) ) {
        WP_CLI::error( 'Missing snapshots for ' . $handle );
    }
    $json = json_decode( file_get_contents( $json_path ), false, 512, JSON_BIGINT_AS_STRING );
    $js = json_decode( file_get_contents( $js_path ), false, 512, JSON_BIGINT_AS_STRING );
    if ( is_object( $json ) && isset( $json->products ) && is_array( $json->products ) && count( $json->products ) === 1 ) {
        $json = (object) [ 'product' => $json->products[0] ];
    }
    $item = lasery_xtool_merge_handle_documents( (string) $id, $handle, 'EUR', $timestamp, $json, $js );
    if ( is_wp_error( $item ) ) {
        WP_CLI::error( 'Invalid snapshots for ' . $handle . ': ' . $item->get_error_code() );
    }
    $prepared[ (string) $id ] = $item;
}
if ( ! lasery_xtool_lock_import() ) {
    WP_CLI::error( 'Another import is running.' );
}
foreach ( $prepared as $id => $product ) {
    if ( ! lasery_xtool_refresh_import_lock() ) {
        WP_CLI::error( 'Lost the import lock.' );
    }
    if ( $exists( $id ) ) {
        WP_CLI::warning( 'Product ' . $id . ' already exists; skipped.' );
        continue;
    }
    $post_id = wp_insert_post( wp_slash( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'draft', 'post_title' => $product['title'], 'post_content' => $product['content'] ] ), true );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        WP_CLI::error( 'Could not create product ' . $id );
    }
    if ( ! add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, (string) $id, true ) || ! lasery_xtool_save_source_data( $post_id, $product ) ) {
        wp_delete_post( $post_id, true );
        WP_CLI::error( 'Could not save source data for ' . $id );
    }
    $published = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ], true );
    if ( is_wp_error( $published ) || ! $published || get_post_status( $post_id ) !== 'publish' ) {
        wp_delete_post( $post_id, true );
        WP_CLI::error( 'Could not publish product ' . $id );
    }
    WP_CLI::success( 'Published product ' . $id );
}
lasery_xtool_release_import_lock();
