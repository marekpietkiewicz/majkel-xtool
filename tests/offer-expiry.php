<?php
/** Run each scenario in a disposable WordPress installation with LiteSpeed active. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'LITESPEED_CLI' ) ) {
	throw new RuntimeException( 'This test requires disposable WordPress with LiteSpeed enabled.' );
}
$check = static function ( $condition, $message ) {
	if ( ! $condition ) { WP_CLI::error( $message ); }
	WP_CLI::log( 'PASS ' . $message );
};
$scenario = getenv( 'LASERY_XTOOL_EXPIRY_SCENARIO' );
$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Expiry test' ) );
$now = time();
update_post_meta( $id, '_lasery_source_updated_at', (string) ( $now - DAY_IN_SECONDS ) );
$expiry = lasery_xtool_offer_expires_at( $id, $now );
$check( $expiry === $now + 1, 'offer expires one second after the final valid second' );
$check( lasery_xtool_offer_expires_at( $id, $now + 1 ) === $expiry, 'expiry remains stable across the boundary' );
update_post_meta( $id, '_lasery_source_updated_at', (string) ( $now + 60 ) );
$check( 0 === lasery_xtool_offer_expires_at( $id, $now ), 'future timestamp is invalid' );

if ( 'header' === $scenario || 'expired' === $scenario ) {
	$deadline = 'header' === $scenario ? time() + 1 : time() - 1;
	$nocache = false;
	$ttl = 0;
	add_action( 'litespeed_control_set_nocache', static function () use ( &$nocache ) { $nocache = true; } );
	add_action( 'litespeed_control_set_ttl', static function ( $value ) use ( &$ttl ) { $ttl = $value; } );
	lasery_xtool_track_offer_expiry( time() + 3600 );
	lasery_xtool_track_offer_expiry( $deadline );
	add_action( 'shutdown', static function () use ( $check, $id, $deadline, &$nocache, &$ttl ) {
		if ( time() >= $deadline ) {
			$check( $nocache, 'offer expired while rendering: HTML is not cached' );
		} else {
			$check( ! $nocache && 1 === $ttl, 'one-second boundary caps the HTML TTL' );
		}
		wp_delete_post( $id, true );
	}, -1 );
} elseif ( 'search' === $scenario ) {
	update_post_meta( $id, LASERY_XTOOL_SOURCE_ID, '10001' );
	update_post_meta( $id, '_lasery_source_updated_at', (string) ( time() - DAY_IN_SECONDS + 20 ) );
	update_post_meta( $id, '_lasery_source_currency', 'EUR' );
	update_post_meta( $id, '_lasery_source_variants', array( array( 'id' => '101', 'title' => 'Default', 'sku' => '', 'price' => '42.00', 'compare_at_price' => null, 'available' => true ) ) );
	$language = lasery_xtool_interface_language();
	$key = 'lasery_xtool_search_' . $language;
	delete_transient( $key );
	$index = lasery_xtool_search_index();
	$cached = get_transient( $key );
	$check( is_array( $cached ) && $cached['expires_at'] === lasery_xtool_offer_expires_at( $id ), 'search cache records the first offer expiry' );
	$check( (int) get_option( '_transient_timeout_' . $key ) <= $cached['expires_at'], 'search transient expires no later than its offer' );
	$cached['expires_at'] = time();
	$cached['index'] = array( array( 'sentinel' ) );
	set_transient( $key, $cached, HOUR_IN_SECONDS );
	$check( array( array( 'sentinel' ) ) !== lasery_xtool_search_index(), 'search rejects cached prices at the exact expiry second' );
	wp_delete_post( $id, true );
	delete_transient( $key );
} else {
	WP_CLI::error( 'Unknown expiry scenario.' );
}
