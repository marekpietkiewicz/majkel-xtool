<?php
/** Run only in an explicitly enabled disposable WordPress install. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}
$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	WP_CLI::log( 'PASS ' . $message );
};

require_once ABSPATH . 'wp-admin/includes/file.php';
$existing_path = wp_tempnam( 'lasery-sync-test.webp' );
$check( is_string( $existing_path ) && file_exists( $existing_path ), 'create a local media fixture' );
$products = $attachments = array();
$urls = array( 'https://cdn.shopify.com/s/files/existing.webp', 'https://cdn.shopify.com/s/files/missing.webp' );
$requests = array();
$dispatches = 0;
$http = static function ( $pre, $args, $url ) use ( &$requests ) {
	$requests[] = $url;
	return new WP_Error( 'test_download', 'Simulated CDN failure.' );
};
$observe = static function () use ( &$dispatches ) { ++$dispatches; };
try {
	foreach ( array( $existing_path, $existing_path . '.missing' ) as $i => $path ) {
		$id = wp_insert_post( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post_title' => 'Image sync fixture ' . $i ), true );
		$check( ! is_wp_error( $id ) && $id, 'create fixture product' );
		$products[] = $id;
		$attachment = wp_insert_attachment( array( 'post_title' => 'Image fixture ' . $i, 'post_mime_type' => 'image/webp' ), $path, $id, true );
		$check( ! is_wp_error( $attachment ) && $attachment, 'create fixture attachment' );
		$attachments[] = $attachment;
		update_post_meta( $id, '_lasery_source_image_url', $urls[ $i ] );
		// Exercise both old single-image and newer gallery records.
		if ( 0 === $i ) {
			update_post_meta( $id, '_lasery_local_image_source', $urls[ $i ] );
			update_post_meta( $id, '_lasery_local_image_attachment_id', $attachment );
		} else {
			update_post_meta( $id, '_lasery_local_gallery', array( $urls[ $i ] => $attachment ) );
		}
	}
	add_filter( 'pre_http_request', $http, 10, 3 );
	add_action( 'lasery_xtool_sync_images', $observe, 99 );
	update_option( LASERY_XTOOL_IMAGE_QUEUE, $products, false );
	update_option( 'lasery_xtool_image_audit', time(), false );
	update_option( 'lasery_xtool_last_image_sync', array( 'time' => time() - 21 * MINUTE_IN_SECONDS ), false );
	update_option( LASERY_XTOOL_OFFERS_PAUSE, time() + HOUR_IN_SECONDS, false );
	delete_option( LASERY_XTOOL_IMAGES_PAUSE );
	wp_cache_flush_runtime();

	do_action( 'lasery_xtool_cron_tick' );
	$check( 0 === $dispatches, 'the WordPress watchdog leaves media to its own fallback event' );
	lasery_xtool_run_due_retries( true );
	$receipt = get_option( 'lasery_xtool_last_image_sync' );
	$check( 1 === $dispatches && array( $urls[1] ) === $requests && 1 === $receipt['attempted'] && 1 === $receipt['failed'],
		'the Hostido watchdog processes media during offer cooldown and only requests the missing file' );
	$check( array( $products[1] ) === get_option( LASERY_XTOOL_IMAGE_QUEUE ),
		'a missing file remains queued while the legacy valid image needs no work' );
	lasery_xtool_run_due_retries( true );
	$check( 1 === $dispatches, 'the next Hostido tick respects the 20-minute image cadence' );

	global $wpdb;
	update_option( 'lasery_xtool_last_image_sync', array( 'time' => time() - 21 * MINUTE_IN_SECONDS ), false );
	$other_receipt = array( 'time' => time(), 'imported' => 1 );
	$finish_before_lease = static function ( $sql ) use ( $wpdb, $other_receipt ) {
		if ( str_starts_with( $sql, 'INSERT IGNORE INTO' ) && str_contains( $sql, "'lasery_xtool_import_lock'" ) ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $other_receipt ) ), array( 'option_name' => 'lasery_xtool_last_image_sync' ) );
		}
		return $sql;
	};
	add_filter( 'query', $finish_before_lease );
	try {
		lasery_xtool_run_due_retries( true );
	} finally {
		remove_filter( 'query', $finish_before_lease );
	}
	$check( 2 === $dispatches && $other_receipt === get_option( 'lasery_xtool_last_image_sync' ),
		'the media worker reloads a concurrent completion under the lease and skips its own batch' );
} finally {
	remove_filter( 'pre_http_request', $http, 10 );
	remove_action( 'lasery_xtool_sync_images', $observe, 99 );
	foreach ( $attachments as $id ) {
		wp_delete_attachment( $id, true );
	}
	foreach ( $products as $id ) {
		wp_delete_post( $id, true );
	}
	if ( file_exists( $existing_path ) ) {
		unlink( $existing_path );
	}
	foreach ( array( LASERY_XTOOL_IMAGE_QUEUE, LASERY_XTOOL_OFFERS_PAUSE, 'lasery_xtool_last_image_sync', 'lasery_xtool_image_audit' ) as $option ) {
		delete_option( $option );
	}
}
