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
$mock = array( 'catalog' => $catalog, 'machine' => array(), 'requests' => array(), 'mode' => 'ok', 'retry_after' => '600' );
add_filter( 'lasery_xtool_pre_request', function ( $pre, $url, $etag ) use ( &$mock ) {
	$mock['requests'][] = array( $url, $etag );
	if ( 'network' === $mock['mode'] ) { return new WP_Error( 'x', 'down' ); }
	if ( false !== strpos( $url, 'meta.json' ) ) {
		return array( 'headers' => array(), 'body' => '{"currency":"EUR"}', 'response' => array( 'code' => 200 ) );
	}
	if ( 'limited' === $mock['mode'] ) {
		return array( 'headers' => array( 'retry-after' => $mock['retry_after'] ), 'body' => 'local_rate_limited', 'response' => array( 'code' => 429 ) );
	}
	if ( false !== strpos( $url, '/collections/machine/' ) ) {
		$body = wp_json_encode( array( 'products' => $mock['machine'] ) );
		$tag  = '"col:' . md5( $body ) . '"';
		if ( $etag && trim( $etag, 'W/' ) === $tag ) {
			return array( 'headers' => array( 'etag' => $tag ), 'body' => '', 'response' => array( 'code' => 304 ) );
		}
		return array( 'headers' => array( 'etag' => $tag ), 'body' => $body, 'response' => array( 'code' => 200 ) );
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
	return count( array_filter( $mock['requests'], static function ( $r ) { return false !== strpos( $r[0], '/products.json' ) && false === strpos( $r[0], '/collections/' ); } ) );
};
$conditional_requests = static function () use ( &$mock ) {
	return count( array_filter( $mock['requests'], static function ( $r ) { return false !== strpos( $r[0], '/products.json' ) && false === strpos( $r[0], '/collections/' ) && $r[1]; } ) );
};
$mails = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$mails ) { $mails[] = $atts; return true; }, 10, 2 );

// ---- fixtures: 40 real products on the site, 3 of them absent from the source ----------------
foreach ( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
delete_option( LASERY_XTOOL_STATE ); delete_option( 'lasery_xtool_last_sync' ); delete_option( 'lasery_xtool_currency' ); delete_option( LASERY_XTOOL_OFFERS_PAUSE ); delete_option( 'lasery_xtool_last_ok' ); delete_option( 'lasery_xtool_last_alert' ); delete_option( LASERY_XTOOL_IMAGE_QUEUE ); delete_option( LASERY_XTOOL_NOVELTY_STATE ); delete_option( 'lasery_xtool_novelty_retry_at' ); delete_option( 'lasery_xtool_novelty_retry_failures' );
if ( ! term_exists( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY ) ) { lasery_xtool_install(); }
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
update_post_meta( reset( $by_source ), '_lasery_source_updated_at', (string) ( time() - 7 * HOUR_IN_SECONDS ) );
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


echo "S10 novelties: the manufacturer's machine collection\n";
$clone = static function ( $index, $changes ) use ( $catalog ) {
	$p = json_decode( wp_json_encode( $catalog[ $index ] ) );
	foreach ( $changes as $k => $v ) { $p->$k = $v; }
	return $p;
};
$days = static function ( $n ) { return gmdate( 'c', time() - $n * DAY_IN_SECONDS ); };
$recent_known = $catalog[3]; $old_known = $catalog[4];
$new_machine = $clone( 100, array( 'id' => 777000000001, 'title' => 'xTool Test Machine', 'handle' => 'xtool-test-machine', 'published_at' => $days( 1 ), 'body_html' => '<ul><li>Cuts wood and acrylic.</li><li>Eye-safe design for everyone.</li></ul><p>Class 1 enclosure with certified protection.</p><p>Works with the xTool app.</p>' ) );
foreach ( $new_machine->variants as $v ) { $v->available = true; }
$skip = array(
	$clone( 101, array( 'id' => 777000000002, 'title' => '[Refurbished] xTool Old Machine', 'published_at' => $days( 1 ) ) ),
	$clone( 102, array( 'id' => 777000000003, 'title' => 'Self-Service Kiosk Floor', 'published_at' => $days( 1 ) ) ),
	$clone( 103, array( 'id' => 777000000004, 'title' => 'xTool Sold Out Machine', 'published_at' => $days( 1 ) ) ),
);
foreach ( $skip[2]->variants as $v ) { $v->available = false; }
$mock['machine'] = array_merge( array( $clone( 3, array( 'published_at' => $days( 10 ) ) ), $clone( 4, array( 'published_at' => $days( 100 ) ) ), $new_machine ), $skip );
$mails = array();
$run();
$new_post = get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => '777000000001', 'fields' => 'ids' ) );
$check( 'the unknown available machine is published', 1 === count( $new_post ) && 'publish' === get_post_status( $new_post[0] ) );
$check( 'refurbished, kiosk and unavailable ones are not imported', 0 === count( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => array( '777000000002', '777000000003', '777000000004' ), 'meta_compare' => 'IN', 'fields' => 'ids' ) ) ) );
$new_id = $new_post ? $new_post[0] : 0;
$check( 'its offer is stored and fresh', $new_id && lasery_xtool_source_is_fresh( $new_id ) );
$check( 'it carries the new badge and the auto-import marker', $new_id && has_term( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, $new_id ) && metadata_exists( 'post', $new_id, '_lasery_auto_imported' ) );
$content = $new_id ? get_post_field( 'post_content', $new_id ) : '';
$check( 'claims about safety and certification are dropped, the rest stays', false !== strpos( $content, 'Cuts wood' ) && false !== strpos( $content, 'xTool app' ) && false === stripos( $content, 'eye-safe' ) && false === stripos( $content, 'certified' ), $content );
$check( 'the page gets a general safety notice', $new_id && '' !== lasery_xtool_product_safety_notice( $new_id, 'pl' ) );
$check( 'the administrator is told once', 1 === count( $mails ) && false !== strpos( $mails[0]['subject'], 'xTool Test Machine' ), count( $mails ) . ' mails' );
$check( 'a machine published 10 days ago has the badge, one from 100 days ago does not', has_term( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, $by_source[ (string) $recent_known->id ] ) && ! has_term( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, $by_source[ (string) $old_known->id ] ) );

echo "S11 listing order: demo, then new (newest first), then the rest\n";
$demo_id = $by_source[ (string) $catalog[10]->id ];
wp_set_object_terms( $demo_id, LASERY_XTOOL_DEMO_BADGE, LASERY_XTOOL_BADGE_TAXONOMY );
wp_update_post( array( 'ID' => $by_source[ (string) $recent_known->id ], 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ) ) );
$listing = get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'posts_per_page' => 5, 'fields' => 'ids', 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC' ), 'lasery_demo_first' => true, 'suppress_filters' => false ) );
$check( 'order: demo, newest new, older new, then the rest', array_slice( $listing, 0, 3 ) === array( $demo_id, $new_id, $by_source[ (string) $recent_known->id ] ), json_encode( $listing ) );

echo "S12 the next run changes nothing, and a badge expires\n";
$mails = array();
$run();
$check( 'collection answered 304 and no second import', 1 === count( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => '777000000001', 'fields' => 'ids' ) ) ) && 0 === count( $mails ) );
$state = get_option( LASERY_XTOOL_NOVELTY_STATE );
$state['members'][ (string) $recent_known->id ] = time() - 70 * DAY_IN_SECONDS;
update_option( LASERY_XTOOL_NOVELTY_STATE, $state, false );
$run();
$check( 'after 60 days the badge is removed by the next sync', ! has_term( LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, $by_source[ (string) $recent_known->id ] ) );

echo "S13 several unknown machines at once are held back\n";
$many = array();
foreach ( array( 110, 111, 112, 113 ) as $i => $index ) {
	$m = $clone( $index, array( 'id' => 777000000100 + $i, 'title' => 'xTool Bulk ' . $i, 'published_at' => $days( 1 ) ) );
	foreach ( $m->variants as $v ) { $v->available = true; }
	$many[] = $m;
}
$mock['machine'] = array_merge( $mock['machine'], $many );
$mails = array();
$run();
$run();
$check( 'none imported', 0 === count( get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'any', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => '777000000100', 'fields' => 'ids' ) ) ) );
$check( 'one mail for the same set, not one per run', 1 === count( $mails ), count( $mails ) . ' mails' );

WP_CLI::log( "\n$pass passed, $fail failed" );
if ( $fail ) {
	WP_CLI::halt( 1 );
}
