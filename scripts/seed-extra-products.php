<?php
/**
 * Publish selected machines from a private snapshot of https://www.xtool.eu (meta.json and the
 * products.json pages saved as products-<n>.json in one folder); existing products are skipped.
 * Usage: wp eval-file scripts/seed-extra-products.php <snapshot folder> <Unix fetch time>
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}
if ( ! function_exists( 'lasery_xtool_parse_product' ) ) {
    WP_CLI::error( 'Activate the lasery-xtool plugin first.' );
}
if ( ! isset( $args[0], $args[1] ) || ! is_dir( $args[0] ) || ! ctype_digit( $args[1] ) ) {
    WP_CLI::error( 'Supply the private snapshot directory and its Unix fetch timestamp.' );
}
$timestamp = (int) $args[1];
if ( $timestamp > time() || $timestamp < time() - HOUR_IN_SECONDS ) {
    WP_CLI::error( 'Snapshots must be less than one hour old.' );
}
$ids = [
    '9163846877397', // F2
    '9089720189141', // P3
    '8169201631445', // S1
    '9187692970197', // F2 Ultra UV
    '9009695293653', // F2 Ultra
    '8974447804629', // MetalFab Laser Welder and CNC Cutter
    '9432562729173', // MetalFab Laser Welder
    '8523126931669', // F1 Ultra
    '9475369337045', // O1 Omni Printer
    '9573828133077', // Apparel Printer Plus
    '9420620202197', // WonderPress 3D
    '8941086245077', // OS1
];
$folder = rtrim( $args[0], '/' );
// The store's currency, as the sync reads it.
$meta = is_readable( $folder . '/meta.json' ) ? json_decode( (string) file_get_contents( $folder . '/meta.json' ) ) : null;
if ( ! is_object( $meta ) || ! isset( $meta->currency ) || ! is_string( $meta->currency ) || ! preg_match( '/^[A-Z]{3}$/D', $meta->currency ) ) {
    WP_CLI::error( 'The snapshot needs meta.json with the store currency.' );
}
$snapshot = [];
foreach ( glob( $folder . '/products-*.json' ) as $file ) {
    $raw = lasery_xtool_decode_products( (string) file_get_contents( $file ), LASERY_XTOOL_MAX_PRODUCTS );
    if ( false === $raw ) {
        WP_CLI::error( 'Invalid snapshot ' . basename( $file ) );
    }
    foreach ( $raw as $entry ) {
        $id = lasery_xtool_raw_product_id( $entry );
        if ( in_array( $id, $ids, true ) && ! isset( $snapshot[ $id ] ) ) {
            $product = lasery_xtool_parse_product( $entry );
            if ( false === $product ) {
                WP_CLI::error( 'Invalid data for product ' . $id );
            }
            $snapshot[ $id ] = $product + [ 'currency' => $meta->currency, 'fetched_at' => $timestamp ];
        }
    }
}
$prepared = [];
$exists = static function ( $id ) {
    return (bool) get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ], 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => (string) $id, 'fields' => 'ids', 'posts_per_page' => 1 ] );
};
foreach ( $ids as $id ) {
    if ( $exists( $id ) ) {
        WP_CLI::log( 'Product ' . $id . ' already exists; skipped.' );
        continue;
    }
    if ( ! isset( $snapshot[ $id ] ) ) {
        WP_CLI::error( 'Product ' . $id . ' is missing from the snapshot.' );
    }
    $prepared[ $id ] = $snapshot[ $id ];
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
