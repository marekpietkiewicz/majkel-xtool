<?php
/** Run with wp eval-file in a disposable WordPress install, never production. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
	WP_CLI::log( 'PASS ' . $message );
};

$variant = array( 'id' => '1101', 'title' => 'Default', 'price' => '42.00', 'available' => true );
$old_variant = array( 'id' => '1101', 'title' => 'Default', 'sku' => '', 'price' => '10.00', 'compare_at_price' => null, 'available' => true );
$products = array(
	array( 'id' => '101', 'title' => 'Missing variants' ),
	array( 'id' => '102', 'title' => 'Valid variants', 'variants' => array( $variant ) ),
	array( 'id' => '103', 'title' => 'Empty variants', 'variants' => array() ),
	array( 'id' => '104', 'title' => 'Malformed variants', 'variants' => array( array( 'id' => '1104' ) ) ),
);
$ids = array();
foreach ( $products as $product ) {
	$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => $product['title'] ), true );
	$check( ! is_wp_error( $id ) && $id > 0, 'create ' . $product['title'] );
	$ids[ $product['id'] ] = $id;
	update_post_meta( $id, LASERY_XTOOL_SOURCE_ID, $product['id'] );
	update_post_meta( $id, '_lasery_source_variants', array( $old_variant ) );
	update_post_meta( $id, '_lasery_source_currency', 'EUR' );
	update_post_meta( $id, '_lasery_source_updated_at', (string) time() );
}

$manual = lasery_xtool_parse_products( wp_json_encode( array( 'products' => array( $products[0] ) ) ) );
$check( is_array( $manual ) && 1 === count( $manual ) && ! array_key_exists( 'variants', $manual[0] ), 'manual JSON still accepts absent variants' );

$request = static function ( $preempt, $args, $url ) use ( $products ) {
	if ( str_starts_with( $url, 'https://www.xtool.eu/meta.json' ) ) {
		$body = wp_json_encode( array( 'currency' => 'EUR' ) );
	} elseif ( str_starts_with( $url, 'https://www.xtool.eu/products.json' ) ) {
		$body = wp_json_encode( array( 'products' => $products ) );
	} else {
		return new WP_Error( 'unexpected_request', 'External requests are forbidden in this test.' );
	}
	return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $request, 10, 3 );
lasery_xtool_sync_existing();
remove_filter( 'pre_http_request', $request, 10 );

$sync = get_option( 'lasery_xtool_last_sync' );
$check( 'incomplete' === $sync['result'] && 2 === $sync['updated'] && 2 === $sync['failed'], 'sync reports valid and invalid products separately' );
$check( ! lasery_xtool_source_is_fresh( $ids['101'] ) && ! lasery_xtool_source_is_fresh( $ids['104'] ), 'missing and malformed variants invalidate old offers' );
$check( array( $old_variant ) === get_post_meta( $ids['101'], '_lasery_source_variants', true ), 'invalid product retains prior snapshot for recovery' );
$check( lasery_xtool_source_is_fresh( $ids['102'] ) && '42.00' === get_post_meta( $ids['102'], '_lasery_source_variants', true )[0]['price'], 'valid variant refreshes offer' );
$check( lasery_xtool_source_is_fresh( $ids['103'] ) && array() === get_post_meta( $ids['103'], '_lasery_source_variants', true ), 'empty variant array replaces the old offer' );
$check( array() === lasery_xtool_available_variants( $ids['103'] ), 'empty variant array is unavailable' );

$prior_time = get_post_meta( $ids['102'], '_lasery_source_updated_at', true );
$offline = static function () { return new WP_Error( 'offline', 'Test network failure' ); };
add_filter( 'pre_http_request', $offline );
lasery_xtool_sync_existing();
remove_filter( 'pre_http_request', $offline );
$check( 'network' === get_option( 'lasery_xtool_last_sync' )['result'], 'network failure is reported' );
$check( $prior_time === get_post_meta( $ids['102'], '_lasery_source_updated_at', true ) && lasery_xtool_source_is_fresh( $ids['102'] ), 'network failure keeps the last verified offer within its freshness limit' );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
WP_CLI::success( 'Source sync integration test completed.' );
