<?php
/** Each scenario runs in a fresh wp eval-file process on disposable WordPress with LiteSpeed active. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'LITESPEED_CLI' ) ) {
	throw new RuntimeException( 'This test requires disposable WordPress with LiteSpeed enabled.' );
}
if ( ! function_exists( 'lasery_xtool_search_refresh' ) ) {
	function lasery_xtool_search_refresh() { update_option( 'lasery_xtool_search_generation', (int) get_option( 'lasery_xtool_search_generation', 0 ) + 1, false ); }
}

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
	WP_CLI::log( 'PASS ' . $message );
};
$search_refreshes = 0;
$scenario = getenv( 'LASERY_XTOOL_CACHE_SCENARIO' );
$expected_purge = 'unchanged' !== $scenario;
$second = 0;
$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Cache integration test' ), true );
$check( ! is_wp_error( $id ) && $id > 0, 'create test product' );
$variant = array( 'id' => '1101', 'title' => 'Default', 'sku' => '', 'price' => '10.00', 'compare_at_price' => null, 'available' => true );
update_post_meta( $id, LASERY_XTOOL_SOURCE_ID, '101' );
update_post_meta( $id, '_lasery_source_variants', array( $variant ) );
update_post_meta( $id, '_lasery_source_currency', 'EUR' );
update_post_meta( $id, '_lasery_source_updated_at', (string) ( time() - 120 ) );
add_option( 'lasery_xtool_search_generation', 0, '', false );
add_action( 'update_option_lasery_xtool_search_generation', static function () use ( &$search_refreshes ) { ++$search_refreshes; } );

// Ignore the core purge caused by creating a published post.
\LiteSpeed\Purge::update_option( \LiteSpeed\Purge::DB_QUEUE, '-1' );
\LiteSpeed\Purge::update_option( \LiteSpeed\Purge::DB_QUEUE2, '-1' );
$events = 0;
$requests = array();
$raster = imagecreatetruecolor( 2, 2 );
ob_start();
imagewebp( $raster );
$webp = ob_get_clean();
imagedestroy( $raster );
add_action( 'litespeed_purge', static function ( $tag ) use ( &$events ) { if ( '*' === $tag ) { ++$events; } } );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$requests, $webp, $scenario, $variant ) {
	$requests[] = $url;
	if ( str_starts_with( $url, 'https://www.xtool.eu/meta.json' ) ) {
		$body = wp_json_encode( array( 'currency' => 'EUR' ) );
	} elseif ( str_starts_with( $url, 'https://www.xtool.eu/products.json' ) ) {
		if ( 'locklost' === $scenario ) {
			$variant['price'] = '20.00';
			$body = wp_json_encode( array( 'products' => array( array( 'id' => '101', 'title' => 'Changed', 'variants' => array( $variant ) ), array( 'id' => '102', 'title' => 'Second', 'variants' => array( $variant ) ) ) ) );
		} else {
			$body = wp_json_encode( array( 'products' => array( array( 'id' => '101', 'title' => 'Missing variants' ) ) ) );
		}
	} elseif ( 'https://cdn.shopify.com/cache-test.webp' === $url ) {
		$body = $webp;
	} elseif ( $url === admin_url( 'admin-ajax.php' ) ) {
		if ( 'loopback_failure' === $scenario ) {
			return new WP_Error( 'offline', 'Test loopback failure' );
		}
		$body = '0';
	} else {
		return new WP_Error( 'unexpected_request' );
	}
	return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
}, 10, 3 );

$product = array( 'variants' => array( $variant ), 'currency' => 'EUR', 'fetched_at' => time() );
if ( 'unchanged' === $scenario ) {
	$blanked = false;
	$observe = static function ( $result, $object_id, $key, $value ) use ( $id, &$blanked ) {
		if ( $object_id === $id && '_lasery_source_updated_at' === $key && '' === $value ) { $blanked = true; }
		return $result;
	};
	add_filter( 'update_post_metadata', $observe, 10, 4 );
	$check( lasery_xtool_save_source_data( $id, $product ), 'unchanged data saved' );
	remove_filter( 'update_post_metadata', $observe, 10 );
	$check( ! $blanked, 'unchanged offer stays fresh throughout the write' );
} elseif ( 'changed' === $scenario || 'loopback_failure' === $scenario ) {
	$product['variants'][0]['price'] = '20.00';
	$check( lasery_xtool_save_source_data( $id, $product ), 'changed price saved' );
	$product['variants'][0]['price'] = '30.00';
	$check( lasery_xtool_save_source_data( $id, $product ), 'second change saved' );
} elseif ( 'stale' === $scenario ) {
	lasery_xtool_sync_existing();
	$check( ! lasery_xtool_source_is_fresh( $id ), 'missing variants invalidate offer' );
} elseif ( 'partial' === $scenario ) {
	$block_currency = static function ( $result, $object_id, $key ) use ( $id ) {
		return $object_id === $id && '_lasery_source_currency' === $key ? false : $result;
	};
	add_filter( 'update_post_metadata', $block_currency, 10, 3 );
	$product['currency'] = 'USD';
	$check( ! lasery_xtool_save_source_data( $id, $product ), 'failed metadata write is reported' );
	remove_filter( 'update_post_metadata', $block_currency, 10 );
	$check( ! lasery_xtool_source_is_fresh( $id ), 'partial write leaves offer stale' );
} elseif ( 'image' === $scenario ) {
	$url = 'https://cdn.shopify.com/cache-test.webp';
	update_post_meta( $id, '_lasery_source_image_url', $url );
	update_post_meta( $id, '_lasery_source_image_urls', array( $url ) );
	lasery_xtool_sync_images();
	$check( 1 === get_option( 'lasery_xtool_last_image_sync' )['imported'] && get_post_thumbnail_id( $id ), 'new image is imported and displayed' );
} elseif ( 'locklost' === $scenario ) {
	$second = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'draft', 'post_title' => 'Second product' ) );
	update_post_meta( $second, LASERY_XTOOL_SOURCE_ID, '102' );
	$search_refreshes = 0; // Ignore the normal save_post refresh for the fixture.
	$lose_lock = static function ( $meta_id, $object_id, $key, $value ) use ( $id ) {
		if ( $object_id === $id && '_lasery_source_updated_at' === $key && '' !== $value ) {
			$GLOBALS['lasery_xtool_lock_value'] = 'test-lock-lost';
		}
	};
	add_action( 'updated_post_meta', $lose_lock, 10, 4 );
	lasery_xtool_sync_existing();
	remove_action( 'updated_post_meta', $lose_lock, 10 );
	$check( 'locklost' === get_option( 'lasery_xtool_last_sync' )['result'], 'sync stops after lock is lost' );
	$check( '20.00' === get_post_meta( $id, '_lasery_source_variants', true )[0]['price'], 'earlier changed offer is retained' );
	delete_option( 'lasery_xtool_import_lock' );
} else {
	WP_CLI::error( 'Unknown cache scenario.' );
}

// The actual LiteSpeed handler runs at shutdown priority 0; verify after it.
add_action( 'shutdown', static function () use ( $id, $second, $check, $expected_purge, &$events, &$requests, &$search_refreshes ) {
	$check( ( $expected_purge ? 1 : 0 ) === $events, 'at most one page-cache purge per request' );
	$check( ( $expected_purge ? 1 : 0 ) === $search_refreshes, 'search index follows visible changes' );
	$check( $expected_purge === in_array( admin_url( 'admin-ajax.php' ), $requests, true ), 'LiteSpeed CLI loopback follows queued purge' );
	if ( $expected_purge ) {
		$check( false !== strpos( \LiteSpeed\Purge::get_option( \LiteSpeed\Purge::DB_QUEUE ), 'public,' . LSWCP_TAG_PREFIX . '_' ), 'LiteSpeed receives the site-wide page-cache tag' );
	}
	foreach ( lasery_xtool_product_gallery( $id ) as $attachment ) {
		wp_delete_attachment( $attachment, true );
	}
	if ( $second ) { wp_delete_post( $second, true ); }
	wp_delete_post( $id, true );
	\LiteSpeed\Purge::update_option( \LiteSpeed\Purge::DB_QUEUE, '-1' );
	\LiteSpeed\Purge::update_option( \LiteSpeed\Purge::DB_QUEUE2, '-1' );
}, 1 );
