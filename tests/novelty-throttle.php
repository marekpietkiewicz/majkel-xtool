<?php
/** Run only in an explicitly enabled disposable WordPress install. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
delete_option( 'lasery_xtool_retry_at' );
delete_option( 'lasery_xtool_novelty_retry_at' );
delete_option( 'lasery_xtool_novelty_retry_failures' );
delete_option( 'lasery_xtool_import_lock' );
delete_option( LASERY_XTOOL_NOVELTY_STATE );
$source_id = '98888001';
foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => $source_id, 'fields' => 'ids', 'posts_per_page' => -1 ) ) as $id ) {
	wp_delete_post( $id, true );
}
$fixture = wp_json_encode( array( 'products' => array( array(
	'id' => $source_id,
	'title' => 'xTool Test Machine',
	'published_at' => gmdate( 'c' ),
	'body_html' => '<p>Test machine.</p>',
	'variants' => array( array( 'id' => '98888002', 'title' => 'Default', 'price' => '42.00', 'available' => true ) ),
	'images' => array( array( 'src' => 'https://cdn.shopify.com/s/files/1/test.webp' ) ),
) ) ) );
update_option( 'lasery_xtool_currency', array( 'code' => 'EUR', 'at' => time() ), false );
update_option( LASERY_XTOOL_STATE, array( 'pages' => array(), 'currency' => 'EUR' ), false );
update_option( 'lasery_xtool_last_ok', time(), false );
$last_sync = array( 'time' => time(), 'result' => 'complete', 'confirmed' => 1 );
update_option( 'lasery_xtool_last_sync', $last_sync, false );
$requests = 0;
$mode = 'novelties';
$limited = true;
add_filter( 'lasery_xtool_pre_request', static function ( $pre, $url, $etag ) use ( &$requests, &$mode, &$limited, $fixture ) {
	if ( ( 'novelties' === $mode && false === strpos( $url, '/collections/machine/' ) ) || ( 'offers' === $mode && false === strpos( $url, '/products.json' ) ) ) {
		return new WP_Error( 'unexpected_request' );
	}
	++$requests;
	if ( 'novelties' === $mode && ! $limited ) {
		$tag = '"novelty-test-v1"';
		return array( 'headers' => array( 'etag' => $tag ), 'body' => $etag === $tag ? '' : $fixture, 'response' => array( 'code' => $etag === $tag ? 304 : 200 ) );
	}
	if ( 'offers' === $mode && $limited ) {
		return array( 'headers' => array( 'retry-after' => '600' ), 'body' => '', 'response' => array( 'code' => 429 ) );
	}
	if ( 'offers' === $mode && $requests > 1 ) {
		return array( 'headers' => array(), 'body' => '{"products":[]}', 'response' => array( 'code' => 200 ) );
	}
	return array( 'headers' => array( 'retry-after' => '0' ), 'body' => '', 'response' => array( 'code' => 429 ) );
}, 20, 3 );
lasery_xtool_sync_novelties( 'EUR', time() + 8 );
if ( 1 !== $requests ) {
	WP_CLI::error( 'A novelty rate limit must stop after one request; observed ' . $requests . '.' );
}
$pause = (int) get_option( LASERY_XTOOL_OFFERS_PAUSE );
$rate = get_option( 'lasery_xtool_last_rate_limit' );
if ( $pause < time() + 5 * MINUTE_IN_SECONDS - 1 || ! is_array( $rate ) || '/collections/machine/products.json' !== $rate['path'] ) {
	WP_CLI::error( 'The source pause and rate-limit diagnostics must be retained.' );
}
if ( (int) get_option( 'lasery_xtool_novelty_retry_at' ) < time() + 10 * MINUTE_IN_SECONDS - 1 || 1 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) ) {
	WP_CLI::error( 'A failed novelty request must schedule its first bounded retry.' );
}
lasery_xtool_run_due_retries();
if ( 1 !== $requests ) {
	WP_CLI::error( 'The host watchdog must not retry during the source pause.' );
}
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 2 !== $requests || 2 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) || (int) get_option( 'lasery_xtool_novelty_retry_at' ) < time() + 20 * MINUTE_IN_SECONDS - 1 ) {
	WP_CLI::error( 'A repeated novelty limit must back off without fetching the catalog.' );
}
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
update_option( 'lasery_xtool_import_lock', ( time() + 900 ) . ':held-by-another-process', false );
lasery_xtool_run_due_retries();
if ( 2 !== $requests || 2 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) || (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time() ) {
	WP_CLI::error( 'Lock contention must defer the novelty retry without counting another source failure.' );
}
delete_option( 'lasery_xtool_import_lock' );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
delete_option( 'lasery_xtool_currency' );
lasery_xtool_run_due_retries();
if ( 2 !== $requests || 3 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) || (int) get_option( 'lasery_xtool_novelty_retry_at' ) < time() + 40 * MINUTE_IN_SECONDS - 1 ) {
	WP_CLI::error( 'Missing verified currency must defer novelties and continue bounded backoff.' );
}
update_option( 'lasery_xtool_currency', array( 'code' => 'USD', 'at' => time() ), false );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 2 !== $requests || 4 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) ) {
	WP_CLI::error( 'A currency mismatch must defer the novelty request.' );
}
update_option( 'lasery_xtool_currency', array( 'code' => 'EUR', 'at' => time() ), false );
update_option( 'lasery_xtool_last_ok', time() - 2 * DAY_IN_SECONDS, false );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 2 !== $requests || 4 !== (int) get_option( 'lasery_xtool_novelty_retry_failures' ) || (int) get_option( 'lasery_xtool_novelty_retry_at' ) < time() + HOUR_IN_SECONDS - 1 ) {
	WP_CLI::error( 'An outdated catalog must defer novelties and cap the backoff at one hour.' );
}
update_option( 'lasery_xtool_last_ok', time(), false );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
$limited = false;
$block_source_id = static function ( $check, $object_id, $key ) {
	return LASERY_XTOOL_SOURCE_ID === $key ? false : $check;
};
add_filter( 'add_post_metadata', $block_source_id, 10, 3 );
lasery_xtool_run_due_retries();
remove_filter( 'add_post_metadata', $block_source_id, 10 );
$state = get_option( LASERY_XTOOL_NOVELTY_STATE );
if ( 3 !== $requests || ! get_option( 'lasery_xtool_novelty_retry_at' ) || ! is_array( $state ) || '' !== $state['etag'] ) {
	WP_CLI::error( 'A failed novelty import must remain retryable without caching the page ETag.' );
}
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 4 !== $requests || get_option( 'lasery_xtool_novelty_retry_at' ) || get_option( 'lasery_xtool_novelty_retry_failures' ) || $last_sync !== get_option( 'lasery_xtool_last_sync' ) ) {
	WP_CLI::error( 'The host watchdog must recover novelties without changing the offer-sync result.' );
}
$new_products = get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => $source_id, 'fields' => 'ids', 'posts_per_page' => -1 ) );
if ( 1 !== count( $new_products ) || ! lasery_xtool_source_is_fresh( $new_products[0] ) ) {
	WP_CLI::error( 'A recovered novelty must be published once with a fresh offer.' );
}
$mode = 'offers';
$requests = 0;
$response = lasery_xtool_source_get( LASERY_XTOOL_SOURCE_URL . '/products.json', 1024, time() + 10 );
if ( 2 !== $requests || is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
	WP_CLI::error( 'A short catalog rate limit should retain its inline retry.' );
}
$requests = 0;
$limited = true;
update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'network' ), false );
update_option( 'lasery_xtool_retry_at', time() - 1, false );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 1 !== $requests || 'paused' !== get_option( 'lasery_xtool_last_sync' )['result'] || (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time() ) {
	WP_CLI::error( 'Offer retries must run first and a source pause must prevent the novelty request.' );
}
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
delete_option( 'lasery_xtool_retry_at' );
delete_option( 'lasery_xtool_novelty_retry_at' );
$requests = 0;
update_option( 'lasery_xtool_retry_at', time() + 30 * MINUTE_IN_SECONDS, false );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
lasery_xtool_run_due_retries();
if ( 0 !== $requests || (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time() ) {
	WP_CLI::error( 'A pending offer retry must defer the optional novelty retry.' );
}
delete_option( 'lasery_xtool_retry_at' );
delete_option( 'lasery_xtool_novelty_retry_at' );
$requests = 0;
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
$complete_during_lock = static function ( $sql ) {
	if ( false !== strpos( $sql, 'INSERT IGNORE INTO' ) && false !== strpos( $sql, 'lasery_xtool_import_lock' ) ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'lasery_xtool_novelty_retry_at' ) );
	}
	return $sql;
};
add_filter( 'query', $complete_during_lock );
lasery_xtool_run_due_retries();
remove_filter( 'query', $complete_during_lock );
global $wpdb;
$locks = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'lasery_xtool_import_lock' ) );
if ( 0 !== $requests || 0 !== $locks ) {
	WP_CLI::error( 'A retry completed by another process must not issue a duplicate request.' );
}
$requests = 0;
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
$offer_during_lock = static function ( $sql ) {
	if ( false !== strpos( $sql, 'INSERT IGNORE INTO' ) && false !== strpos( $sql, 'lasery_xtool_import_lock' ) ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", 'lasery_xtool_retry_at', (string) ( time() + 300 ) ) );
	}
	return $sql;
};
add_filter( 'query', $offer_during_lock );
lasery_xtool_run_due_retries();
remove_filter( 'query', $offer_during_lock );
if ( 0 !== $requests || (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time() ) {
	WP_CLI::error( 'An offer retry queued by another process must take priority after lease acquisition.' );
}
delete_option( 'lasery_xtool_retry_at' );
delete_option( 'lasery_xtool_novelty_retry_at' );
$requests = 0;
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
update_option( 'lasery_xtool_import_lock', ( time() + 900 ) . ':held-by-another-process', false );
add_filter( 'query', $complete_during_lock );
lasery_xtool_run_due_retries();
remove_filter( 'query', $complete_during_lock );
$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'lasery_xtool_novelty_retry_at' ) );
if ( 0 !== $requests || 0 !== $pending ) {
	WP_CLI::error( 'Lock contention must not recreate a retry completed by another process.' );
}
delete_option( 'lasery_xtool_import_lock' );
delete_option( 'lasery_xtool_novelty_retry_at' );
WP_CLI::success( 'Novelty retries back off independently; offer retries take priority.' );
