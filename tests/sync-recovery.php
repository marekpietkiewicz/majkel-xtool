<?php
/** Run only in an explicitly enabled disposable WordPress install. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}

foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( LASERY_XTOOL_STATE, LASERY_XTOOL_OFFERS_PAUSE, 'lasery_xtool_last_sync', 'lasery_xtool_last_ok', 'lasery_xtool_resume_page', 'lasery_xtool_retry_at', 'lasery_xtool_novelty_retry_at', 'lasery_xtool_novelty_retry_failures', 'lasery_xtool_import_lock', 'lasery_xtool_last_alert', 'lasery_xtool_alert_attempt', 'lasery_xtool_last_rate_limit', 'lasery_xtool_paced_started_at', 'lasery_xtool_host_cron_at' ) as $option ) {
	delete_option( $option );
}

$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
	WP_CLI::log( 'PASS ' . $message );
};
$response = static function ( $code, $body = '', $headers = array() ) {
	return array( 'headers' => $headers, 'body' => $body, 'response' => array( 'code' => $code, 'message' => '' ) );
};
$catalog = array();
for ( $i = 0; $i < 501; ++$i ) {
	$catalog[] = array(
		'id'       => 1000001 + $i,
		'title'    => 'Product ' . $i,
		'variants' => array( array( 'id' => 2000001 + $i, 'title' => 'Default', 'price' => '10.00', 'available' => true ) ),
	);
}
$mock = array( 'catalog' => $catalog, 'limited_page' => 0, 'network_page' => 0, 'requests' => array() );
add_filter( 'lasery_xtool_pre_request', static function ( $pre, $url, $etag ) use ( &$mock, $response ) {
	if ( false !== strpos( $url, '/collections/machine/' ) ) {
		return $response( 200, '{"products":[]}' );
	}
	if ( false !== strpos( $url, '/meta.json' ) ) {
		return $response( 200, '{"currency":"EUR"}' );
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$page = isset( $query['page'] ) ? (int) $query['page'] : 0;
	$mock['requests'][] = array( 'page' => $page, 'etag' => $etag );
	if ( $page === $mock['network_page'] ) {
		return new WP_Error( 'offline', 'Simulated network failure.' );
	}
	if ( $page === $mock['limited_page'] ) {
		return $response( 429, '', array( 'retry-after' => '600' ) );
	}
	$body = wp_json_encode( array( 'products' => array_slice( $mock['catalog'], ( $page - 1 ) * 250, 250 ) ) );
	$tag  = '"' . md5( $body ) . '"';
	return $etag === $tag ? $response( 304, '', array( 'etag' => $tag ) ) : $response( 200, $body, array( 'etag' => $tag ) );
}, 20, 3 );

$post_ids = array();
foreach ( array( 0, 250, 500 ) as $index ) {
	$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Target ' . $index ), true );
	$check( ! is_wp_error( $id ) && $id > 0, 'create target on page ' . (int) ( $index / 250 + 1 ) );
	update_post_meta( $id, LASERY_XTOOL_SOURCE_ID, (string) $catalog[ $index ]['id'] );
	$post_ids[ $index ] = $id;
}
$ghost = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Missing target' ), true );
update_post_meta( $ghost, LASERY_XTOOL_SOURCE_ID, '9999999' );
update_option( 'lasery_xtool_currency', array( 'code' => 'EUR', 'at' => time() ), false );
$run = static function () use ( &$mock ) {
	$mock['requests'] = array();
	lasery_xtool_sync_existing( true );
};
$pages = static function () use ( &$mock ) { return array_column( $mock['requests'], 'page' ); };

$run();
$check( 'complete' === get_option( 'lasery_xtool_last_sync' )['result'] && array( 1, 2, 3 ) === $pages(), 'baseline completes and stores all three pages' );
foreach ( $post_ids as $id ) {
	update_post_meta( $id, '_lasery_source_updated_at', (string) ( time() - HOUR_IN_SECONDS ) );
}
$baseline_ok = (int) get_option( 'lasery_xtool_last_ok' );
$missing_runs = get_post_meta( $ghost, '_lasery_missing_runs', true );
$mock['catalog'][0]['variants'][0]['price'] = '70.00';
$mock['limited_page'] = 2;
$block_state_write = static function ( $value, $old ) { return $old; };
add_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state_write, 10, 2 );
$run();
remove_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state_write, 10 );
$check( 'storage' === get_option( 'lasery_xtool_last_sync' )['result'] && '10.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'], 'a failed ETag invalidation cannot save a price under an older cached page' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$run();
$first = get_option( 'lasery_xtool_last_sync' );
$check( 'paused' === $first['result'] && array( 1, 2 ) === $pages(), '429 stops the run at page two' );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( '/products.json' === $rate_limit['path'] && 2 === $rate_limit['page'] && 600 === $rate_limit['retry_after'], 'the last rate limit records its endpoint, page and requested delay' );
$check( '70.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'] && (int) get_post_meta( $post_ids[0], '_lasery_source_updated_at', true ) > time() - 60, 'a verified earlier page updates its offer despite the later 429' );
$check( (int) get_post_meta( $post_ids[250], '_lasery_source_updated_at', true ) < time() - 300, 'an unvisited page is not falsely confirmed' );
$check( 2 === (int) get_option( 'lasery_xtool_resume_page' ) && (int) get_option( 'lasery_xtool_retry_at' ) >= time() + 590, 'the interrupted page and bounded retry time are saved' );
$check( $missing_runs === get_post_meta( $ghost, '_lasery_missing_runs', true ) && 'publish' === get_post_status( $ghost ), 'an interrupted walk never counts an absent product' );
$check( $baseline_ok === (int) get_option( 'lasery_xtool_last_ok' ), 'an interrupted walk is not reported as complete' );
$run();
$check( array() === $pages() && 2 === (int) get_option( 'lasery_xtool_resume_page' ), 'no requests are sent while the source pause is active' );

delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 3;
$run();
$check( array( 2, 3 ) === $pages() && 3 === (int) get_option( 'lasery_xtool_resume_page' ), 'the next retry starts at page two and advances to page three' );
$check( (int) get_post_meta( $post_ids[250], '_lasery_source_updated_at', true ) > time() - 60, 'a conditional response on the resumed page refreshes its offer' );

delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;
$run();
$check( array( 3 ) === $pages() && (int) get_post_meta( $post_ids[500], '_lasery_source_updated_at', true ) > time() - 60, 'the last page is refreshed without rereading earlier pages' );
$check( 'partial' === get_option( 'lasery_xtool_last_sync' )['result'] && 1 === (int) get_option( 'lasery_xtool_resume_page' ) && (int) get_option( 'lasery_xtool_retry_at' ) > time(), 'the next retry starts a full walk after partial recovery' );
$check( $missing_runs === get_post_meta( $ghost, '_lasery_missing_runs', true ) && 'publish' === get_post_status( $ghost ), 'a walk spanning retries cannot hide an absent product' );

// An old 304 must not confirm data saved from a newer partial page.
$mock['catalog'][0]['variants'][0]['price'] = '10.00';
$run();
$check( '' === $mock['requests'][0]['etag'] && '10.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'], 'a changed partial page is fetched again before its old ETag can confirm a different price' );
$check( 'complete' === get_option( 'lasery_xtool_last_sync' )['result'] && ! get_option( 'lasery_xtool_retry_at' ), 'a complete walk clears the retry' );
$check( (int) get_post_meta( $ghost, '_lasery_missing_runs', true ) === (int) $missing_runs + 1, 'only a complete walk counts absence' );

update_option( 'lasery_xtool_currency', array( 'code' => 'USD', 'at' => time() ), false );
add_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state_write, 10, 2 );
$run();
remove_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_state_write, 10 );
$check( 'storage' === get_option( 'lasery_xtool_last_sync' )['result'] && array() === $pages() && ! lasery_xtool_source_is_fresh( $post_ids[0] ) && (int) get_option( 'lasery_xtool_retry_at' ) > time(), 'a failed state write keeps old-currency offers stale and schedules recovery' );
$mock['limited_page'] = 2;
$run();
$check( 'USD' === get_post_meta( $post_ids[0], '_lasery_source_currency', true ) && lasery_xtool_source_is_fresh( $post_ids[0] ) && ! lasery_xtool_source_is_fresh( $post_ids[250] ) && ! lasery_xtool_source_is_fresh( $post_ids[500] ), 'a currency change makes unvisited old-currency offers stale immediately' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;
$run();
$check( array( 2, 3 ) === $pages() && array( '', '' ) === array_column( $mock['requests'], 'etag' ) && lasery_xtool_source_is_fresh( $post_ids[0] ) && 'USD' === get_post_meta( $post_ids[500], '_lasery_source_currency', true ), 'currency recovery re-fetches remaining pages without invalidating the new-currency page again' );
$run();
update_option( 'lasery_xtool_currency', array( 'code' => 'EUR', 'at' => time() ), false );
$run();

$mock['catalog'][0]['variants'][0]['price'] = '55.00';
$mock['network_page'] = 2;
$run();
$check( 'network' === get_option( 'lasery_xtool_last_sync' )['result'] && 2 === (int) get_option( 'lasery_xtool_resume_page' ) && '55.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'], 'a network failure also preserves earlier verified pages and the resume point' );
$mock['network_page'] = 0;
$run();
$check( array( 2, 3 ) === $pages() && 'partial' === get_option( 'lasery_xtool_last_sync' )['result'], 'network recovery resumes at the failed page' );
$run();

$mock['catalog'][0]['variants'][0]['price'] = '56.00';
$block_final_state = static function ( $value, $old ) {
	return ! empty( $value['pages'][1]['etag'] ) ? $old : $value;
};
add_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_final_state, 10, 2 );
$run();
remove_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_final_state, 10 );
$check( 'storage' === get_option( 'lasery_xtool_last_sync' )['result'] && '' === get_option( LASERY_XTOOL_STATE )['pages'][1]['etag'] && (int) get_option( 'lasery_xtool_retry_at' ) > time(), 'a failed final state write retains the invalidated ETag and schedules a retry' );
$run();
$check( '' === $mock['requests'][0]['etag'] && '56.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'], 'recovery after a state-write failure re-fetches the changed page' );

// A 304 after malformed source data must not resurrect the old offer.
unset( $mock['catalog'][250]['variants'] );
$run();
$check( ! lasery_xtool_source_is_fresh( $post_ids[250] ), 'malformed source variants invalidate the offer' );
$run();
$check( '' === $mock['requests'][1]['etag'] && ! lasery_xtool_source_is_fresh( $post_ids[250] ), 'the next run revalidates malformed data instead of confirming it with 304' );

$mails = array();
add_filter( 'pre_wp_mail', static function ( $result, $mail ) use ( &$mails ) { $mails[] = $mail; return true; }, 20, 2 );
update_option( 'lasery_xtool_last_ok', time() - 7 * HOUR_IN_SECONDS, false );
delete_option( 'lasery_xtool_last_alert' );
delete_option( 'lasery_xtool_alert_attempt' );
foreach ( $post_ids as $id ) {
	update_post_meta( $id, '_lasery_source_updated_at', (string) time() );
}
lasery_xtool_alert_when_stale();
$check( array() === $mails, 'fresh offers from partial progress do not cause a misleading stale alert' );
update_post_meta( $post_ids[250], '_lasery_source_updated_at', (string) ( time() - 7 * HOUR_IN_SECONDS ) );
lasery_xtool_alert_when_stale();
lasery_xtool_alert_when_stale();
$check( 1 === count( $mails ) && false !== strpos( $mails[0]['message'], '6 godzin: 1.' ), 'a genuinely stale offer triggers one accurate alert' );

$header = gmdate( 'D, d M Y H:i:s', time() + 2 * HOUR_IN_SECONDS ) . ' GMT';
lasery_xtool_pause_fetching( $response( 429, '', array( 'retry-after' => $header ) ) );
$check( abs( (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) - ( time() + 2 * HOUR_IN_SECONDS ) ) < 5, 'Retry-After HTTP dates are respected' );
lasery_xtool_pause_fetching( $response( 429, '', array( 'retry-after' => (string) ( 2 * DAY_IN_SECONDS ) ) ) );
$check( (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + 2 * DAY_IN_SECONDS, 'a long Retry-After is never shortened to a day' );
lasery_xtool_pause_fetching( $response( 429 ) );
$check( (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + 5 * MINUTE_IN_SECONDS, 'a missing Retry-After uses a safe minimum pause' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );

update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'network' ), false );
update_option( 'lasery_xtool_resume_page', 1, false );
update_option( 'lasery_xtool_retry_at', time() - 1, false );
$mock['requests'] = array();
$mock['limited_page'] = 1;
if ( lasery_xtool_retry_due() ) {
	do_action( 'lasery_xtool_sync_existing' );
}
$check( array( 1 ) === $pages() && 'paused' === get_option( 'lasery_xtool_last_sync' )['result'] && (int) get_option( 'lasery_xtool_retry_at' ) > time(), 'the hosting watchdog runs a due retry and schedules the next one on 429' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );

update_option( 'lasery_xtool_import_lock', ( time() + 900 ) . ':held-by-another-process', false );
$due = time() - 1;
update_option( 'lasery_xtool_retry_at', $due, false );
$run();
$check( $due === (int) get_option( 'lasery_xtool_retry_at' ) && array() === $pages(), 'lock contention leaves the due retry untouched' );
$check( lasery_xtool_retry_due(), 'the hosting watchdog sees a due retry' );
update_option( LASERY_XTOOL_OFFERS_PAUSE, time() + 300, false );
$check( ! lasery_xtool_retry_due(), 'the hosting watchdog respects the source pause' );
delete_option( 'lasery_xtool_import_lock' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );

// Drive each scheduled batch through the same action used by WordPress and the hosting cron.
$mock['limited_page'] = 0;
$mock['catalog'][250]['variants'] = $catalog[250]['variants'];
$baseline_ok = (int) get_option( 'lasery_xtool_last_ok' );
$missing_runs = get_post_meta( $ghost, '_lasery_missing_runs', true );
$due_tick = static function ( $host = false ) use ( &$mock ) {
	$mock['requests'] = array();
	update_option( 'lasery_xtool_retry_at', time() - 1, false );
	if ( $host ) {
		lasery_xtool_run_due_retries( true );
	} else {
		do_action( 'lasery_xtool_cron_tick' );
	}
};
$due_tick( true );
$check( array( 1 ) === $pages() && 2 === (int) get_option( 'lasery_xtool_resume_page' ) && (int) get_option( 'lasery_xtool_host_cron_at' ) >= time() - 5, 'the host watchdog records its heartbeat and paces page one after a recent 429' );
$not_before = (int) get_option( 'lasery_xtool_retry_at' );
$mock['requests'] = array();
do_action( 'lasery_xtool_sync_existing' );
do_action( 'lasery_xtool_cron_tick' );
$check( array() === $pages() && $not_before === (int) get_option( 'lasery_xtool_retry_at' ), 'overlapping cron triggers honor the queued retry without moving it' );
$due_tick();
$check( array( 2 ) === $pages() && 3 === (int) get_option( 'lasery_xtool_resume_page' ), 'WP-Cron shares the durable cursor and fetches only page two' );
$due_tick();
$check( array( 3 ) === $pages() && 'paced' === get_option( 'lasery_xtool_last_sync' )['result'] && ! get_option( 'lasery_xtool_retry_at' ), 'the last page finishes a paced sweep without another immediate sweep' );
$check( $baseline_ok === (int) get_option( 'lasery_xtool_last_ok' ) && $missing_runs === get_post_meta( $ghost, '_lasery_missing_runs', true ), 'split sweeps never count as full absence evidence' );
$check( wp_get_schedule( 'lasery_xtool_cron_tick' ) === 'lasery_xtool_five_minutes', 'the WordPress fallback has an independent recurring hook' );

$mock['network_page'] = 1;
delete_option( 'lasery_xtool_last_rate_limit' );
$due_tick();
$check( array( 1 ) === $pages() && 1 === (int) get_option( 'lasery_xtool_resume_page' ), 'a network error first retries the same page' );
$due_tick();
$check( array( 1 ) === $pages() && 2 === (int) get_option( 'lasery_xtool_resume_page' ), 'the same network error twice makes room for known later pages' );
$due_tick();
$check( array( 2, 3 ) === $pages() && 1 === (int) get_option( 'lasery_xtool_resume_page' ), 'after confirming the known tail the first page is queued again' );
$mock['network_page'] = 0;
update_option( 'lasery_xtool_import_lock', ( time() - 60 ) . ':abandoned', false );
$due_tick();
$check( array( 1, 2, 3 ) === $pages() && 'complete' === get_option( 'lasery_xtool_last_sync' )['result'], 'a worker reclaims an abandoned lease and completes the catalog' );

$saved_catalog = $mock['catalog'];
$mock['catalog'] = array();
$run();
$check( 'anomaly' === get_option( 'lasery_xtool_last_sync' )['result'] && lasery_xtool_source_is_fresh( $post_ids[0] ), 'an empty successful response cannot mark the whole catalogue absent' );
$mock['catalog'] = $saved_catalog;

delete_option( 'lasery_xtool_last_alert' );
delete_option( 'lasery_xtool_alert_attempt' );
update_option( 'lasery_xtool_last_ok', time(), false );
update_post_meta( $post_ids[0], '_lasery_source_updated_at', (string) ( time() - 7 * HOUR_IN_SECONDS ) );
update_option( LASERY_XTOOL_OFFERS_PAUSE, time() + DAY_IN_SECONDS, false );
$reject_mail = static function () { return false; };
add_filter( 'pre_wp_mail', $reject_mail, 30 );
$mock['requests'] = array();
$prior_mails = count( $mails );
do_action( 'lasery_xtool_cron_tick' );
do_action( 'lasery_xtool_cron_tick' );
$check( array() === $pages() && count( $mails ) === $prior_mails + 1 && ! get_option( 'lasery_xtool_last_alert' ), 'stale offers are reported during cooldown, with failed mail throttled separately' );
remove_filter( 'pre_wp_mail', $reject_mail, 30 );
update_option( 'lasery_xtool_alert_attempt', time() - 31 * MINUTE_IN_SECONDS, false );
do_action( 'lasery_xtool_cron_tick' );
$check( count( $mails ) === $prior_mails + 2 && (int) get_option( 'lasery_xtool_last_alert' ) > time() - 30, 'the next mail attempt can recover and is then deduplicated for a day' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );

WP_CLI::success( 'Sync recovery integration test completed.' );
