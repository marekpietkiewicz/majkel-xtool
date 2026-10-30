<?php
/** One-time initial catalogue from a previously fetched Shopify snapshot. Run with wp eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'lasery_xtool_parse_products' ) ) {
    return;
}
if ( ! isset( $args[0], $args[1] ) || ! is_readable( $args[0] ) || ! ctype_digit( $args[1] ) ) {
    WP_CLI::error( 'Supply the private JSON snapshot path and its Unix fetch timestamp.' );
}
$fetched_at = (int) $args[1];
if ( $fetched_at > time() || $fetched_at < time() - HOUR_IN_SECONDS ) {
    WP_CLI::error( 'The snapshot must be less than one hour old.' );
}
$products = lasery_xtool_parse_products( file_get_contents( $args[0] ) );
if ( false === $products ) {
    WP_CLI::error( 'Invalid product snapshot.' );
}
$wanted = [ '15473430692053', '15452856942805' ];
$selected = [];
foreach ( $products as $product ) {
    if ( in_array( $product['id'], $wanted, true ) ) {
        $selected[ $product['id'] ] = $product;
    }
}
if ( count( $selected ) !== count( $wanted ) ) {
    WP_CLI::error( 'The snapshot does not contain every selected product.' );
}
foreach ( $wanted as $id ) {
    $product = $selected[ $id ];
    $existing = get_posts( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ], 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => $id, 'fields' => 'ids', 'posts_per_page' => 1 ] );
    if ( $existing ) {
        WP_CLI::warning( 'Product ' . $id . ' already exists; no changes made.' );
        continue;
    }
    $post_id = wp_insert_post( wp_slash( [ 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'draft', 'post_title' => $product['title'], 'post_content' => $product['content'] ] ), true );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        WP_CLI::error( 'Could not create product ' . $id );
    }
    $product['currency'] = 'EUR'; // Verified from this storefront's /meta.json at snapshot time.
    $product['fetched_at'] = $fetched_at;
    if ( ! add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, $id, true ) || ! lasery_xtool_save_source_data( $post_id, $product ) ) {
        wp_delete_post( $post_id, true );
        WP_CLI::error( 'Could not save source data for product ' . $id );
    }
    $published = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ], true );
    if ( is_wp_error( $published ) || ! $published ) {
        WP_CLI::error( 'Could not publish product ' . $id );
    }
    WP_CLI::success( 'Published product ' . $id );
}
