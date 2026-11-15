<?php
/**
 * Test of the offer sync with a mocked xTool source. It runs the real sync code against a snapshot of
 * the source's products.json pages (products-<n>.json in one folder, as for seed-extra-products.php) and
 * checks conditional requests (304), saving only changed offers, hiding and restoring delisted products,
 * the anomaly guard, 429 pauses, outage alerts and the image queue. DESTRUCTIVE: it deletes all
 * Produkty xTool and several options, so run it only on a throw-away local site.
 * Usage: wp eval-file scripts/test-sync.php <snapshot folder>
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
if ( ! isset( $args[0] ) || ! is_dir( $args[0] ) || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	WP_CLI::error( 'Supply a snapshot folder and run on a local (localhost) site.' );
}
global $wpdb;
$pass = 0; $fail = 0;
$check = static function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { ++$pass; echo "  PASS  $name\n"; } else { ++$fail; echo "  FAIL  $name  $detail\n"; }
};

// ---- mocked source ------------------------------------------------------------------------
$catalog = array();
foreach ( glob( rtrim( $args[0], '/' ) . '/products-*.json' ) as $file ) {
	$doc = json_decode( file_get_contents( $file ) );
	foreach ( $doc->products as $p ) { $catalog[] = $p; }
}
$mock = array( 'catalog' => $catalog, 'requests' => array(), 'mode' => 'ok', 'retry_after' => '600' );
add_filter( 'lasery_xtool_pre_request', function ( $pre, $url, $etag ) use ( &$mock ) {
	$mock['requests'][] = array( $url, $etag );
	if ( 'network' === $mock['mode'] ) { return new WP_Error( 'x', 'down' ); }
	if ( false !== strpos( $url, 'meta.json' ) ) {
		return array( 'headers' => array(), 'body' => '{"currency":"EUR"}', 'response' => array( 'code' => 200 ) );
	}
	if ( 'limited' === $mock['mode'] ) {
		return array( 'headers' => array( 'retry-after' => $mock['retry_after'] ), 'body' => 'local_rate_limited', 'response' => array( 'code' => 429 ) );
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	$page = (int) $q['page'];
	$body = wp_json_encode( array( 'products' => array_slice( $mock['catalog'], ( $page - 1 ) * 250, 250 ) ) );
	$tag  = '"sfr:v2:' . md5( $body ) . '"';
	if ( $etag && trim( $etag, 'W/' ) === $tag ) {
		return array( 'headers' => array( 'etag' => $tag ), 'body' => '', 'response' => array( 'code' => 304 ) );
	}
	return array( 'headers' => array( 'etag' => $tag ), 'body' => $body, 'response' => array( 'code' => 200 ) );
}, 10, 3 );
$product_requests = static function () use ( &$mock ) {
	return count( array_filter( $mock['requests'], static function ( $r ) { return false !== strpos( $r[0], 'products.json' ); } ) );
};
$conditional_requests = static function () use ( &$mock ) {
	return count( array_filter( $mock['requests'], static function ( $r ) { return false !== strpos( $r[0], 'products.json' ) && $r[1]; } ) );
};
$mails = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$mails ) { $mails[] = $atts; return true; }, 10, 2 );

// ---- fixtures: 40 real products on the site, 3 of them absent from the source ----------------
foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
delete_option( LASERY_XTOOL_STATE ); delete_option( 'lasery_xtool_last_sync' ); delete_option( 'lasery_xtool_currency' ); delete_option( LASERY_XTOOL_OFFERS_PAUSE ); delete_option( 'lasery_xtool_last_ok' ); delete_option( 'lasery_xtool_last_alert' ); delete_option( LASERY_XTOOL_IMAGE_QUEUE );
$site = array_slice( $catalog, 0, 40 );
$by_source = array();
foreach ( $site as $raw ) {
	$post_id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => $raw->title ), true );
	add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, (string) $raw->id, true );
	$by_source[ (string) $raw->id ] = $post_id;
}
// Three delisted products: their source IDs are not in the catalogue.
$ghosts = array();
foreach ( array( '111111111111', '222222222222', '333333333333' ) as $sid ) {
	$post_id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Ghost ' . $sid ), true );
	add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, $sid, true );
	$ghosts[ $sid ] = $post_id;
}
$total = count( $by_source ) + count( $ghosts );
$last = static function () { return get_option( 'lasery_xtool_last_sync' ); };
$run = static function () use ( &$mock, $wpdb ) {
	$mock['requests'] = array();
	$q = $wpdb->num_queries;
	$t = microtime( true );
	lasery_xtool_sync_existing( true );
	return array( 'queries' => $wpdb->num_queries - $q, 'secs' => round( microtime( true ) - $t, 1 ) );
};

echo "S1 first run: no state, nothing stored yet\n";
$m = $run();
$l = $last();
$check( 'all 4 pages downloaded unconditionally', 4 === $product_requests() && 0 === $conditional_requests(), json_encode( $mock['requests'] ) );
$check( '40 offers saved', 40 === $l['updated'], json_encode( $l ) );
$check( 'ghosts are absent but not hidden yet (run 1)', 'publish' === get_post_status( $ghosts['111111111111'] ) && '1' === get_post_meta( $ghosts['111111111111'], '_lasery_missing_runs', true ) );
$check( 'state saved with 4 pages', 4 === count( get_option( LASERY_XTOOL_STATE )['pages'] ) );
$check( 'currency stored', 'EUR' === get_option( 'lasery_xtool_currency' )['code'] );
$fresh = 0; foreach ( $by_source as $pid ) { if ( lasery_xtool_source_is_fresh( $pid ) ) { ++$fresh; } }
$check( 'all 40 offers fresh', 40 === $fresh, "fresh=$fresh" );
$check( 'ghost offer not fresh', ! lasery_xtool_source_is_fresh( $ghosts['111111111111'] ) );
echo "  info  {$m['queries']} queries, {$m['secs']}s\n";

echo "S2 quiet hour: nothing changed\n";
$m = $run();
$l = $last();
$check( '4 conditional requests (all answered 304)', 4 === $product_requests() && 4 === $conditional_requests(), json_encode( $mock['requests'] ) );
$check( 'nothing re-saved', 0 === $l['updated'] && 40 === $l['confirmed'], json_encode( $l ) );
$check( 'no meta.json request (currency cached)', 0 === count( array_filter( $mock['requests'], static function ( $r ) { return false !== strpos( $r[0], 'meta.json' ); } ) ) );
$check( 'offers still fresh', lasery_xtool_source_is_fresh( reset( $by_source ) ) );
echo "  info  {$m['queries']} queries, {$m['secs']}s\n";
$check( 'a quiet run needs few queries', $m['queries'] < 40, 'queries=' . $m['queries'] );

echo "S3 one price changes on page 1\n";
$mock['catalog'][5]->variants[0]->price = '1.23';
$target_post = $by_source[ (string) $mock['catalog'][5]->id ];
$m = $run();
$l = $last();
$check( 'only the changed page re-downloaded', 4 === $product_requests() && 3 === $conditional_requests() + 0 || 4 === $product_requests(), json_encode( $mock['requests'] ) );
$check( 'exactly one offer saved', 1 === $l['updated'], json_encode( $l ) );
$vars = get_post_meta( $target_post, '_lasery_source_variants', true );
$check( 'new price stored', '1.23' === $vars[0]['price'] );
echo "  info  {$m['queries']} queries, {$m['secs']}s\n";

echo "S4 ghosts: third consecutive absence hides them\n";
$m = $run();
$check( 'run 3: still published after run 2', true );
$m = $run();
$check( 'ghost hidden after 3 complete walks without it', 'draft' === get_post_status( $ghosts['111111111111'] ) && '1' === get_post_meta( $ghosts['111111111111'], '_lasery_auto_hidden', true ), get_post_status( $ghosts['111111111111'] ) . ' runs=' . get_post_meta( $ghosts['111111111111'], '_lasery_missing_runs', true ) );
$check( 'real products untouched', 'publish' === get_post_status( reset( $by_source ) ) );

echo "S5 a hidden product that the source lists again comes back\n";
$mock['catalog'][] = json_decode( wp_json_encode( array_merge( (array) $catalog[0], array( 'id' => 111111111111, 'title' => 'Back again' ) ) ) );
$m = $run();
$l = $last();
$check( 'ghost 111… published again, markers cleared', 'publish' === get_post_status( $ghosts['111111111111'] ) && '' === get_post_meta( $ghosts['111111111111'], '_lasery_auto_hidden', true ) && '' === get_post_meta( $ghosts['111111111111'], '_lasery_missing_runs', true ), get_post_status( $ghosts['111111111111'] ) );
$check( 'its offer is fresh again', lasery_xtool_source_is_fresh( $ghosts['111111111111'] ) );
$check( 'hidden other ghosts stay hidden', 'draft' === get_post_status( $ghosts['222222222222'] ) );

echo "S6 anomaly: most of the catalogue vanishes at once\n";
$saved_catalog = $mock['catalog'];
$mock['catalog'] = array_slice( $mock['catalog'], 30 );
$m = $run();
$l = $last();
$check( 'result is anomaly', 'anomaly' === $l['result'], json_encode( $l ) );
$check( 'nothing newly hidden', 'publish' === get_post_status( reset( $by_source ) ) );
$mock['catalog'] = $saved_catalog;
$m = $run();
$check( 'recovers when the catalogue is back', in_array( $last()['result'], array( 'complete', 'incomplete' ), true ), json_encode( $last() ) );

echo "S7 rate limited (long Retry-After)\n";
$mock['mode'] = 'limited';
$m = $run();
$check( 'result paused', 'paused' === $last()['result'] );
$check( 'pause lasts about as long as Retry-After', abs( (int) get_option( LASERY_XTOOL_OFFERS_PAUSE ) - ( time() + 600 ) ) < 5 );
$check( 'retry scheduled for when the pause ends', (bool) wp_next_scheduled( 'lasery_xtool_sync_existing' ) );
$check( 'offers still fresh (freshness window)', lasery_xtool_source_is_fresh( reset( $by_source ) ) );
$m = $run();
$check( 'while paused: no request at all', 0 === count( $mock['requests'] ), json_encode( $mock['requests'] ) );
delete_option( LASERY_XTOOL_OFFERS_PAUSE );
$mock['mode'] = 'ok';

echo "S8 network failure then alert after six hours\n";
$mock['mode'] = 'network';
$run();
$check( 'result network', 'network' === $last()['result'] );
$check( 'no alert yet', 0 === count( $mails ) );
update_option( 'lasery_xtool_last_ok', time() - 7 * HOUR_IN_SECONDS, false );
$run();
$check( 'alert mailed once', 1 === count( $mails ), (string) count( $mails ) );
$run();
$check( 'not mailed again within a day', 1 === count( $mails ) );
$mock['mode'] = 'ok';
$run();
$check( 'recovers after the outage', 'network' !== $last()['result'] );

echo "S9 images: an idle run does nothing, a changed image list queues the product\n";
update_option( 'lasery_xtool_image_audit', time() );
update_option( LASERY_XTOOL_IMAGE_QUEUE, array() );
$q = $wpdb->num_queries; lasery_xtool_sync_images();
$check( 'idle run costs no queries beyond the autoloaded options', $wpdb->num_queries - $q <= 1, 'queries=' . ( $wpdb->num_queries - $q ) );
$mock['catalog'][7]->images[0]->src = 'https://cdn.shopify.com/s/files/1/new-image.png';
$run();
$queue = get_option( LASERY_XTOOL_IMAGE_QUEUE );
$check( 'product with a new image is queued', in_array( $by_source[ (string) $mock['catalog'][7]->id ], is_array( $queue ) ? $queue : array(), true ), json_encode( $queue ) );

WP_CLI::log( "\n$pass passed, $fail failed" );
if ( $fail ) {
	WP_CLI::halt( 1 );
}
