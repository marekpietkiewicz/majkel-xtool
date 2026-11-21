<?php
/** Run only in an explicitly enabled disposable WordPress install. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}
foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( LASERY_XTOOL_STATE, LASERY_XTOOL_OFFERS_PAUSE, 'lasery_xtool_last_sync', 'lasery_xtool_resume_page', 'lasery_xtool_retry_at', 'lasery_xtool_novelty_retry_at', 'lasery_xtool_novelty_retry_failures', 'lasery_xtool_import_lock' ) as $option ) {
	delete_option( $option );
}
$check = static function ( $condition, $message ) {
	if ( ! $condition ) { WP_CLI::error( $message ); }
	WP_CLI::log( 'PASS ' . $message );
};
$catalog = array();
for ( $i = 0; $i < 501; ++$i ) {
	$catalog[] = array(
		'id' => 3000001 + $i,
		'title' => 'Product ' . $i,
		'variants' => array( array( 'id' => 4000001 + $i, 'title' => 'Default', 'price' => '10.00', 'available' => true ) ),
	);
}
$mock = array( 'catalog' => $catalog, 'limited_page' => 0, 'requests' => array() );
add_filter( 'lasery_xtool_pre_request', static function ( $pre, $url, $etag ) use ( &$mock ) {
	if ( false !== strpos( $url, '/collections/machine/' ) ) {
		return array( 'headers' => array(), 'body' => '{"products":[]}', 'response' => array( 'code' => 200 ) );
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$page = isset( $query['page'] ) ? (int) $query['page'] : 0;
	$mock['requests'][ $page ] = $etag;
	if ( $page === $mock['limited_page'] ) {
		return array( 'headers' => array( 'retry-after' => '600' ), 'body' => '', 'response' => array( 'code' => 429 ) );
	}
	$body = wp_json_encode( array( 'products' => array_slice( $mock['catalog'], ( $page - 1 ) * 250, 250 ) ) );
	$tag = '"' . md5( $body ) . '"';
	return array( 'headers' => array( 'etag' => $tag ), 'body' => $etag === $tag ? '' : $body, 'response' => array( 'code' => $etag === $tag ? 304 : 200 ) );
}, 20, 3 );
$posts = array();
foreach ( array( 0, 250, 500 ) as $index ) {
	$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Target ' . $index ), true );
	$check( ! is_wp_error( $id ) && $id > 0, 'create target on page ' . (int) ( $index / 250 + 1 ) );
	update_post_meta( $id, LASERY_XTOOL_SOURCE_ID, (string) $catalog[ $index ]['id'] );
	$posts[ $index ] = $id;
}
update_option( 'lasery_xtool_currency', array( 'code' => 'EUR', 'at' => time() ), false );
$run = static function () use ( &$mock ) { $mock['requests'] = array(); lasery_xtool_sync_existing( true ); };
$price = static function ( $id ) { return get_post_meta( $id, '_lasery_source_variants', true )[0]['price']; };
$run();
$check( 'complete' === get_option( 'lasery_xtool_last_sync' )['result'], 'baseline captures the catalog and page ETags' );

$manual = $catalog[0];
$manual['variants'][0]['price'] = '80.00';
$manual_product = lasery_xtool_parse_product( json_decode( wp_json_encode( $manual ) ), false ) + array( 'currency' => 'EUR', 'fetched_at' => time() );
$check( lasery_xtool_save_source_data( $posts[0], $manual_product ), 'an independent source import saves its offer' );
$run();
$check( '' === $mock['requests'][1] && '10.00' === $price( $posts[0] ), 'an independent import cannot let an old 304 confirm a different price' );

$block_state = static function ( $value, $old ) { return $old; };
add_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state, 10, 2 );
$check( ! lasery_xtool_save_source_data( $posts[0], $manual_product ) && '10.00' === $price( $posts[0] ), 'a failed cache invalidation prevents an independent offer write' );
remove_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state, 10 );

$filler = $mock['catalog'][1];
$moved = $mock['catalog'][500];
$moved['variants'][0]['price'] = '77.00';
$mock['catalog'][1] = $moved;
$mock['limited_page'] = 2;
$run();
$check( 'paused' === get_option( 'lasery_xtool_last_sync' )['result'] && '77.00' === $price( $posts[500] ), 'a verified moved product is saved before a later 429' );
$check( '' === get_option( LASERY_XTOOL_STATE )['pages'][3]['etag'], 'a partial walk invalidates the old page of a moved product' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;
$mock['catalog'][1] = $filler;
$run();
$check( '' === $mock['requests'][3] && '10.00' === $price( $posts[500] ), 'the resumed old page is fetched instead of incorrectly confirming the moved price' );
$run();

$duplicate = $mock['catalog'][250];
$duplicate['variants'][0]['price'] = '88.00';
$mock['catalog'][1] = $duplicate;
$run();
$check( '88.00' === $price( $posts[250] ) && '' === get_option( LASERY_XTOOL_STATE )['pages'][2]['etag'], 'a duplicate between a changed page and a 304 page invalidates the 304 proof' );
$mock['catalog'][1] = $filler;
$run();
$check( '' === $mock['requests'][2] && '10.00' === $price( $posts[250] ), 'the next run re-fetches the unchanged page after the duplicate disappears' );

$duplicate['variants'][0]['price'] = '88.00';
$mock['catalog'][1] = $duplicate;
$mock['catalog'][250]['variants'][0]['price'] = '99.00';
$run();
$state = get_option( LASERY_XTOOL_STATE );
$check( '99.00' === $price( $posts[250] ) && '' === $state['pages'][1]['etag'] && '' === $state['pages'][2]['etag'], 'conflicting duplicates on two downloaded pages invalidate both ETags' );
delete_option( LASERY_XTOOL_STATE );
$run();
$state = get_option( LASERY_XTOOL_STATE );
$check( '' === $state['pages'][1]['etag'] && '' === $state['pages'][2]['etag'], 'duplicates are safe even on the first catalog walk without a prior cache' );

$mock['catalog'][1] = $filler;
$run();
$last_full = get_option( 'lasery_xtool_last_ok' );
$mock['catalog'][0]['variants'][0]['price'] = '42.00';
$mock['limited_page'] = 2;
$run();
$proof = get_option( LASERY_XTOOL_STATE )['pages'][1]['etag'];
$check( '' !== $proof && $proof !== $mock['requests'][1] && '42.00' === $price( $posts[0] ),
	'a saved page retains its new ETag when a later page is throttled' );
$check( $last_full === get_option( 'lasery_xtool_last_ok' ), 'a saved partial proof does not advance full coverage' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;
$run();
$check( ! isset( $mock['requests'][1] ) && $last_full === get_option( 'lasery_xtool_last_ok' ),
	'the resumed tail keeps the earlier page proof and remains partial' );
$run();
$check( $proof === $mock['requests'][1] && '42.00' === $price( $posts[0] ),
	'the next sweep can reuse the saved proof without downloading page one again' );

$block_new_hash = static function ( $allowed, $post_id, $key, $value ) use ( $posts ) {
	return $posts[0] === $post_id && '_lasery_source_hash' === $key && '' !== $value ? false : $allowed;
};
add_filter( 'update_post_metadata', $block_new_hash, 10, 4 );
$mock['catalog'][0]['variants'][0]['price'] = '43.00';
$run();
remove_filter( 'update_post_metadata', $block_new_hash, 10 );
$check( '' === get_option( LASERY_XTOOL_STATE )['pages'][1]['etag'] && '43.00' === $price( $posts[0] )
	&& 'incomplete' === get_option( 'lasery_xtool_last_sync' )['result'], 'a failed offer hash write cannot produce a page proof' );
$mock['catalog'][0]['variants'][0]['price'] = '42.00';
$run();
$check( '' === $mock['requests'][1] && '42.00' === $price( $posts[0] ),
	'a reverting source price cannot match an obsolete hash after an interrupted write' );
delete_post_meta( $posts[0], '_lasery_source_updated_at' );
$run();
$check( '' === $mock['requests'][1] && lasery_xtool_source_is_fresh( $posts[0] ),
	'a revalidated legacy offer gets a missing timestamp back' );

WP_CLI::success( 'Sync cache provenance integration test completed.' );
