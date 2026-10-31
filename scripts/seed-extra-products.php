<?php
/**
 * Publish the machines below and the accessories in scripts/accessories.php from a private
 * snapshot of https://www.xtool.eu (meta.json and the products.json pages saved as
 * products-<n>.json in one folder); existing products are skipped. Accessories get the
 * "akcesoria" type and the machines they fit, unless the fit was already set in the panel.
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
    '9341134569685', // F2 Ultra MOPA + UV Business Duo
    '8259513778389', // S1 + 1064nm IR Laser Kit
    '8863750488277', // S1 for Education
    '9078363816149', // MetalFab Laser Welder and CNC Cutter for Education
    '8919099048149', // F1 Ultra for Education
];
// Sets and education versions of a machine take the accessories of the machines they contain.
$families = [
    '9341134569685' => [ '9009695293653', '9187692970197' ],
    '8259513778389' => [ '8169201631445' ],
    '8863750488277' => [ '8169201631445' ],
    '9078363816149' => [ '8974447804629' ],
    '8919099048149' => [ '8523126931669' ],
];
$accessories = require __DIR__ . '/accessories.php';
$ids = array_merge( $ids, array_map( 'strval', array_keys( $accessories ) ) );
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
foreach ( $accessories as $id => $accessory ) {
    foreach ( get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => (string) $id, 'fields' => 'ids', 'posts_per_page' => -1 ] ) as $post_id ) {
        wp_set_object_terms( $post_id, LASERY_XTOOL_ACCESSORY_TERM, LASERY_XTOOL_KIND_TAXONOMY, true );
        if ( ! metadata_exists( 'post', $post_id, LASERY_XTOOL_FITS ) ) {
            foreach ( $accessory['fits'] as $machine ) {
                add_post_meta( $post_id, LASERY_XTOOL_FITS, (string) $machine );
            }
        }
    }
}
// Each set or education version joins the fits of its base machines' accessories once (marked on
// the machine), so fits edited in the panel later stay as they are, and a rerun completes a stopped one.
foreach ( $families as $machine => $bases ) {
    $machine_posts = get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => (string) $machine, 'fields' => 'ids', 'posts_per_page' => 1 ] );
    if ( ! $machine_posts || get_post_meta( $machine_posts[0], '_lasery_family_fits', true ) ) {
        continue;
    }
    $fitting = get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], 'meta_query' => [ [ 'key' => LASERY_XTOOL_FITS, 'value' => array_map( 'strval', $bases ), 'compare' => 'IN' ] ], 'fields' => 'ids', 'posts_per_page' => -1 ] );
    foreach ( $fitting as $post_id ) {
        if ( ! in_array( (string) $machine, get_post_meta( $post_id, LASERY_XTOOL_FITS ), true ) ) {
            add_post_meta( $post_id, LASERY_XTOOL_FITS, (string) $machine );
        }
    }
    update_post_meta( $machine_posts[0], '_lasery_family_fits', '1' );
    WP_CLI::log( 'Machine ' . $machine . ' fits ' . count( $fitting ) . ' accessories.' );
}
WP_CLI::success( 'Accessory types and fits set for ' . count( $accessories ) . ' accessories.' );
