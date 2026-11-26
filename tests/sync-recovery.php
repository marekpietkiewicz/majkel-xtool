<?php
/** Run only in an explicitly enabled disposable WordPress install. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}

foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( LASERY_XTOOL_STATE, LASERY_XTOOL_OFFERS_PAUSE, 'lasery_xtool_last_sync', 'lasery_xtool_last_ok', 'lasery_xtool_resume_page', 'lasery_xtool_retry_at', 'lasery_xtool_novelty_retry_at', 'lasery_xtool_novelty_retry_failures', 'lasery_xtool_import_lock', 'lasery_xtool_last_alert', 'lasery_xtool_last_recovery_alert', 'lasery_xtool_recovery_attempt', 'lasery_xtool_alert_attempt', 'lasery_xtool_last_rate_limit', 'lasery_xtool_paced_started_at', 'lasery_xtool_host_cron_at' ) as $option ) {
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
$mock = array( 'catalog' => $catalog, 'limited_page' => 0, 'network_page' => 0, 'requests' => array(), 'novelty_requests' => 0 );
add_filter( 'lasery_xtool_pre_request', static function ( $pre, $url, $etag ) use ( &$mock, $response ) {
	if ( false !== strpos( $url, '/collections/machine/' ) ) {
		++$mock['novelty_requests'];
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
$old_first_page_etag = get_option( LASERY_XTOOL_STATE )['pages'][1]['etag'];
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

// The last complete sweep had 10.00. Only the new partial page proof describes 70.00.
$partial_etag = get_option( LASERY_XTOOL_STATE )['pages'][1]['etag'];
$mock['catalog'][0]['variants'][0]['price'] = '10.00';
$run();
$check( '' !== $partial_etag && $old_first_page_etag !== $partial_etag && $partial_etag === $mock['requests'][0]['etag']
	&& '10.00' === get_post_meta( $post_ids[0], '_lasery_source_variants', true )[0]['price'],
	'the changed page is re-downloaded when the source price reverts and its new ETag no longer matches' );
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

$mock['catalog'][0]['variants'][0]['price'] = '57.00';
$mock['limited_page'] = 2;
add_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_final_state, 10, 2 );
$run();
remove_filter( 'pre_update_option_' . LASERY_XTOOL_STATE, $block_final_state, 10 );
$check( 'storage' === get_option( 'lasery_xtool_last_sync' )['result'] && '' === get_option( LASERY_XTOOL_STATE )['pages'][1]['etag']
	&& 2 === (int) get_option( 'lasery_xtool_resume_page' ) && lasery_xtool_fetching_paused(),
	'a failed partial proof keeps the failed-page cursor and the rate-limit cooldown' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;
$run();
$check( array( 2, 3 ) === $pages(), 'a proof write failure does not re-download a verified earlier page on resume' );
$run();

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

foreach ( $post_ids as $id ) {
	update_post_meta( $id, '_lasery_source_updated_at', (string) time() );
}
$mail_count = count( $mails );
delete_option( 'lasery_xtool_last_alert' );
delete_option( 'lasery_xtool_last_recovery_alert' );
delete_option( 'lasery_xtool_recovery_attempt' );
update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'complete' ), false );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a fresh catalogue without a preceding stale alert does not send a recovery mail' );

$incident = time() - 60;
update_option( 'lasery_xtool_last_alert', $incident, false );
update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'partial' ), false );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'partial catalogue progress does not send a recovery mail' );

update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'complete' ), false );
update_post_meta( $post_ids[250], '_lasery_source_updated_at', (string) ( time() - 7 * HOUR_IN_SECONDS ) );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a complete walk with any stale active offer does not send a recovery mail' );
update_post_meta( $post_ids[250], '_lasery_source_updated_at', (string) time() );

wp_update_post( array( 'ID' => $ghost, 'post_status' => 'publish' ) );
update_post_meta( $ghost, '_lasery_missing_runs', '1' );
update_post_meta( $ghost, '_lasery_source_updated_at', (string) ( time() - 7 * HOUR_IN_SECONDS ) );
$check( 0 === lasery_xtool_stale_offer_count() && 1 === lasery_xtool_stale_offer_count( true ), 'the normal stale-offer count excludes known absence while recovery checks every published product' );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a stale published product marked absent still blocks a recovery mail until it is unpublished' );
wp_update_post( array( 'ID' => $ghost, 'post_status' => 'draft' ) );

update_post_meta( $post_ids[250], '_lasery_source_updated_at', (string) ( time() + HOUR_IN_SECONDS ) );
$check( 1 === lasery_xtool_stale_offer_count( true ), 'a future offer timestamp is not treated as fresh' );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a future-dated offer blocks a recovery mail' );
update_post_meta( $post_ids[250], '_lasery_source_updated_at', 'not-a-timestamp' );
$check( 1 === lasery_xtool_stale_offer_count( true ), 'a malformed offer timestamp is not treated as fresh' );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a malformed offer timestamp blocks a recovery mail' );
update_post_meta( $post_ids[250], '_lasery_source_updated_at', (string) time() );

update_option( 'lasery_xtool_last_alert', time(), false );
update_option( 'lasery_xtool_last_sync', array( 'time' => time() - 1, 'result' => 'complete' ), false );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count === count( $mails ), 'a successful sync from before the stale alert does not send a recovery mail' );

$incident = time() - 60;
update_option( 'lasery_xtool_last_alert', $incident, false );
update_option( 'lasery_xtool_last_sync', array( 'time' => time(), 'result' => 'complete' ), false );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count + 1 === count( $mails ) && 'laseryxtool.pl: oferty znów aktualne' === $mails[ $mail_count ]['subject']
	&& (string) $incident === (string) get_option( 'lasery_xtool_last_recovery_alert' ), 'a full fresh catalogue sends one recovery mail and acknowledges the stale alert' );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count + 1 === count( $mails ), 'the recovery mail is not repeated on later cron ticks' );

$incident = max( time() - 60, (int) get_option( 'lasery_xtool_last_recovery_alert' ) + 1 );
update_option( 'lasery_xtool_last_alert', $incident, false );
update_option( 'lasery_xtool_last_sync', array( 'time' => $incident + 1, 'result' => 'paced' ), false );
do_action( 'lasery_xtool_cron_tick' );
$check( $mail_count + 2 === count( $mails ) && (string) $incident === (string) get_option( 'lasery_xtool_last_recovery_alert' ), 'a fully paced fresh sweep also sends one recovery mail' );

$incident = max( time() - 60, (int) get_option( 'lasery_xtool_last_recovery_alert' ) + 1 );
update_option( 'lasery_xtool_last_alert', $incident, false );
update_option( 'lasery_xtool_last_sync', array( 'time' => $incident + 1, 'result' => 'complete' ), false );
update_option( 'lasery_xtool_recovery_attempt', time() - 31 * MINUTE_IN_SECONDS, false );
$reject_recovery = static function ( $result, $mail ) {
	return isset( $mail['subject'] ) && 'laseryxtool.pl: oferty znów aktualne' === $mail['subject'] ? false : $result;
};
add_filter( 'pre_wp_mail', $reject_recovery, 30, 2 );
do_action( 'lasery_xtool_cron_tick' );
$failed_recovery_count = count( $mails );
$check( $mail_count + 3 === $failed_recovery_count && (string) $incident !== (string) get_option( 'lasery_xtool_last_recovery_alert' ), 'a failed recovery mail does not acknowledge the outage' );
do_action( 'lasery_xtool_cron_tick' );
$check( $failed_recovery_count === count( $mails ), 'a failed recovery mail is throttled between retry attempts' );
remove_filter( 'pre_wp_mail', $reject_recovery, 30 );
update_option( 'lasery_xtool_recovery_attempt', time() - 31 * MINUTE_IN_SECONDS, false );
do_action( 'lasery_xtool_cron_tick' );
$check( $failed_recovery_count + 1 === count( $mails ) && (string) $incident === (string) get_option( 'lasery_xtool_last_recovery_alert' ), 'a failed recovery mail is retried after the cooldown' );

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
delete_option( 'lasery_xtool_novelty_retry_at' );
$baseline_ok = time() - 2 * DAY_IN_SECONDS;
update_option( 'lasery_xtool_last_ok', $baseline_ok, false );
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
$check( 0 === $mock['novelty_requests'] && (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time(), 'the paced sweep queues novelties separately' );
update_option( 'lasery_xtool_novelty_retry_at', time() - 1, false );
do_action( 'lasery_xtool_cron_tick' );
$check( 1 === $mock['novelty_requests'] && ! get_option( 'lasery_xtool_novelty_retry_at' ), 'recent paced coverage also allows a novelty check when the last full walk is old' );

// A single repeatedly throttled page must not starve the rest of the catalog after cooldown.
$mock['limited_page'] = 3;
$now = time();
update_option( 'lasery_xtool_last_sync', array( 'time' => $now, 'result' => 'paused', 'page' => 3 ), false );
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 3, 'retry_after' => 60, 'attempts' => 3 ), false );
update_option( 'lasery_xtool_resume_page', 3, false );
update_option( LASERY_XTOOL_OFFERS_PAUSE, $now + 20 * MINUTE_IN_SECONDS, false );
$due_tick();
$check( array() === $pages() && 3 === (int) get_option( 'lasery_xtool_resume_page' ), 'repeated 429 blocks all catalog traffic for the entire cooldown' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$due_tick();
$check( array( 1 ) === $pages(), 'after cooldown a stuck later page gives way to the first page once' );
$due_tick();
$check( array( 2 ) === $pages(), 'the paced sweep confirms the next reachable page' );
$due_tick();
$check( array( 3 ) === $pages() && 'paused' === get_option( 'lasery_xtool_last_sync' )['result'] && (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + 39 * MINUTE_IN_SECONDS, 'the sweep returns to the blocked page and retains its escalating cooldown' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;

$now = time();
update_option( 'lasery_xtool_last_sync', array( 'time' => $now, 'result' => 'paused', 'page' => 1 ), false );
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 1, 'retry_after' => 60, 'attempts' => 3 ), false );
update_option( 'lasery_xtool_resume_page', 1, false );
$due_tick();
$check( array( 2 ) === $pages(), 'a repeatedly throttled first page gives way to the known tail after cooldown' );
$due_tick();
$check( array( 3 ) === $pages() && 1 === (int) get_option( 'lasery_xtool_resume_page' ) && 'partial' === get_option( 'lasery_xtool_last_sync' )['result'], 'the known tail returns to the first page without reporting full coverage' );

$now = time();
update_option( 'lasery_xtool_last_sync', array( 'time' => $now, 'result' => 'paused', 'page' => 2 ), false );
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 2, 'retry_after' => 60, 'attempts' => 3 ), false );
update_option( 'lasery_xtool_resume_page', 2, false );
$due_tick();
$check( array( 3 ) === $pages() && 1 === (int) get_option( 'lasery_xtool_resume_page' ), 'a blocked middle page gives later pages a turn before restarting the sweep' );

$now = time();
update_option( 'lasery_xtool_last_sync', array( 'time' => $now, 'result' => 'paused', 'page' => 3 ), false );
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 3, 'retry_after' => 60, 'attempts' => 3 ), false );
update_option( 'lasery_xtool_resume_page', 3, false );
$mock['limited_page'] = 1;
$due_tick();
$check( array( 1 ) === $pages() && (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + 39 * MINUTE_IN_SECONDS, '429 on the alternate page preserves the backoff for a likely source-wide limit' );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['limited_page'] = 0;

$url = add_query_arg( array( 'limit' => 250, 'page' => 1 ), LASERY_XTOOL_SOURCE_URL . '/products.json' );
$now = time();
update_option( 'lasery_xtool_last_sync', array( 'time' => $now - 3 * HOUR_IN_SECONDS, 'result' => 'paused', 'page' => 1 ), false );
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now - 3 * HOUR_IN_SECONDS, 'path' => '/products.json', 'page' => 1, 'retry_after' => 60, 'attempts' => 5 ), false );
$mock['limited_page'] = 1;
$limited = lasery_xtool_source_get( $url, LASERY_XTOOL_MAX_BYTES, time() + MINUTE_IN_SECONDS );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( is_wp_error( $limited ) && 6 === $rate_limit['attempts'] && (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + 159 * MINUTE_IN_SECONDS, 'a repeated 429 keeps its streak beyond two hours and increases the cooldown' );

$mock['limited_page'] = 0;
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$body = wp_json_encode( array( 'products' => array_slice( $mock['catalog'], 0, 250 ) ) );
$etag = '"' . md5( $body ) . '"';
$confirmed = lasery_xtool_source_get( $url, LASERY_XTOOL_MAX_BYTES, time() + MINUTE_IN_SECONDS, $etag );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( 304 === wp_remote_retrieve_response_code( $confirmed ) && 0 === $rate_limit['attempts'], 'a 304 from the same page clears its persistent 429 streak' );

$now = time();
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 1, 'retry_after' => 60, 'attempts' => 6 ), false );
$other_page_url = add_query_arg( array( 'limit' => 250, 'page' => 2 ), LASERY_XTOOL_SOURCE_URL . '/products.json' );
$other_page = lasery_xtool_source_get( $other_page_url, LASERY_XTOOL_MAX_BYTES, time() + MINUTE_IN_SECONDS );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( 200 === wp_remote_retrieve_response_code( $other_page ) && 6 === $rate_limit['attempts'] && 1 === $rate_limit['page'], 'a successful response from another page does not clear the blocked page streak' );

$now = time();
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now, 'path' => '/products.json', 'page' => 1, 'retry_after' => 60, 'attempts' => 6 ), false );
$mock['limited_page'] = 0;
$recovered = lasery_xtool_source_get( $url, LASERY_XTOOL_MAX_BYTES, time() + MINUTE_IN_SECONDS );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( 200 === wp_remote_retrieve_response_code( $recovered ) && 0 === $rate_limit['attempts'], 'a fresh response from the same page clears its persistent 429 streak' );

$now = time();
update_option( 'lasery_xtool_last_rate_limit', array( 'time' => $now - 3 * HOUR_IN_SECONDS, 'path' => '/products.json', 'page' => 1, 'retry_after' => 60, 'attempts' => 9 ), false );
$mock['limited_page'] = 1;
$limited = lasery_xtool_source_get( $url, LASERY_XTOOL_MAX_BYTES, time() + MINUTE_IN_SECONDS );
$rate_limit = get_option( 'lasery_xtool_last_rate_limit' );
$check( is_wp_error( $limited ) && 10 === $rate_limit['attempts'] && (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) >= time() + DAY_IN_SECONDS - 5, 'a persistent source outage backs off to at most one retry per day' );
$mock['limited_page'] = 0;
delete_option( LASERY_XTOOL_OFFERS_PAUSE );

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

global $wpdb;
$last_completed = get_option( 'lasery_xtool_last_sync' );
$replacement_lock = ( time() + 900 ) . ':replacement';
$replaced = false;
$replace_during_refresh = static function ( $sql ) use ( $wpdb, $replacement_lock, &$replaced ) {
	if ( ! $replaced && str_starts_with( $sql, "UPDATE {$wpdb->options} SET option_value" ) && str_contains( $sql, "'lasery_xtool_import_lock'" ) ) {
		$replaced = true;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $replacement_lock, 'lasery_xtool_import_lock' ) );
	}
	return $sql;
};
add_filter( 'query', $replace_during_refresh );
$run();
remove_filter( 'query', $replace_during_refresh );
$current_lock = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'lasery_xtool_import_lock' ) );
$check( $replaced && $replacement_lock === $current_lock && $last_completed === get_option( 'lasery_xtool_last_sync' ) && ! get_option( 'lasery_xtool_retry_at' ), 'a displaced worker leaves the replacement lease, result and retry schedule intact' );
$wpdb->delete( $wpdb->options, array( 'option_name' => 'lasery_xtool_import_lock' ) );

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

$invalid_304 = static function () use ( $response ) { return $response( 304 ); };
add_filter( 'lasery_xtool_pre_request', $invalid_304, 30 );
$mock['requests'] = array();
$invalid_proof = array( 1 => array( 'etag' => 'invalid cached tag', 'ids' => array( '1000001' ), 'count' => 1 ) );
$walk = lasery_xtool_walk_catalog( $invalid_proof, time() + 60, '__return_true' );
remove_filter( 'lasery_xtool_pre_request', $invalid_304, 30 );
$check( is_wp_error( $walk ) && 'network' === $walk->get_error_code() && array( '' ) === array_column( $mock['requests'], 'etag' ), 'an unconditional request cannot reuse a 304 from an invalid cached ETag' );

$missing_post = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Missing with failing marker write' ), true );
$check( ! is_wp_error( $missing_post ) && $missing_post > 0, 'create a target for the hide recovery test' );
update_post_meta( $missing_post, LASERY_XTOOL_SOURCE_ID, '9999998' );
update_post_meta( $missing_post, '_lasery_missing_runs', LASERY_XTOOL_MISSING_RUNS - 1 );
$block_marker = static function ( $allowed, $post_id, $key ) use ( $missing_post ) {
	return $missing_post === $post_id && '_lasery_auto_hidden' === $key ? false : $allowed;
};
add_filter( 'update_post_metadata', $block_marker, 10, 3 );
$run();
remove_filter( 'update_post_metadata', $block_marker, 10 );
$check( 'incomplete' === get_option( 'lasery_xtool_last_sync' )['result'] && 'publish' === get_post_status( $missing_post ), 'a failed marker write leaves the product published and reports an incomplete sync' );
$run();
$check( 'draft' === get_post_status( $missing_post ) && '1' === get_post_meta( $missing_post, '_lasery_auto_hidden', true ), 'the next successful write lets the sync hide the missing product safely' );
$restored = $catalog[0];
$restored['id'] = 9999998;
$mock['catalog'][] = $restored;
$run();
$check( 'publish' === get_post_status( $missing_post ) && ! metadata_exists( 'post', $missing_post, '_lasery_auto_hidden' ), 'a returning product is republished and its marker is cleared' );

$old_sync = array( 'time' => time() - 2 * HOUR_IN_SECONDS, 'result' => 'complete' );
$new_sync = array( 'time' => time(), 'result' => 'complete' );
update_option( 'lasery_xtool_last_sync', $old_sync, true );
$check( $old_sync === get_option( 'lasery_xtool_last_sync' ), 'preload the old autoloaded receipt before a competing cron finishes' );
$finish_before_lease = static function ( $sql ) use ( $wpdb, $new_sync ) {
	if ( str_starts_with( $sql, 'INSERT IGNORE INTO' ) && str_contains( $sql, "'lasery_xtool_import_lock'" ) ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $new_sync ), 'lasery_xtool_last_sync' ) );
	}
	return $sql;
};
add_filter( 'query', $finish_before_lease );
$mock['requests'] = array();
do_action( 'lasery_xtool_sync_existing' );
remove_filter( 'query', $finish_before_lease );
$check( array() === $pages() && $new_sync === get_option( 'lasery_xtool_last_sync' ), 'a racing cron reloads an autoloaded receipt under the lease and skips duplicate traffic' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( reset( $admins ) );
$paused_until = time() + HOUR_IN_SECONDS;
update_option( LASERY_XTOOL_OFFERS_PAUSE, $paused_until, false );
update_option( LASERY_XTOOL_IMAGES_PAUSE, $paused_until, false );
update_option( 'lasery_xtool_retry_at', $paused_until, false );
update_option( 'lasery_xtool_novelty_retry_at', $paused_until + MINUTE_IN_SECONDS, false );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'lasery_xtool_resume_fetch' );
$redirect = static function () { throw new RuntimeException( 'EXPECTED_RESUME_REDIRECT' ); };
add_filter( 'wp_redirect', $redirect, 1 );
try {
	lasery_xtool_resume_fetch();
} catch ( RuntimeException $error ) {
	if ( 'EXPECTED_RESUME_REDIRECT' !== $error->getMessage() ) {
		throw $error;
	}
}
remove_filter( 'wp_redirect', $redirect, 1 );
$check( lasery_xtool_retry_due() && ! lasery_xtool_fetching_paused( LASERY_XTOOL_IMAGES_PAUSE ) && $paused_until + MINUTE_IN_SECONDS === (int) get_option( 'lasery_xtool_novelty_retry_at' ), 'the admin resume action releases the old cooldown without moving a separate retry' );

WP_CLI::success( 'Sync recovery integration test completed.' );
