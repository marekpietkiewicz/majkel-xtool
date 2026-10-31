<?php
/** Exercise a failed page insert and retry only in a disposable WordPress. */
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress installation.' );
}
$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	WP_CLI::log( 'PASS ' . $message );
};
$slugs = array( 'bezpieczenstwo', 'informacje-o-zakupach' );
$original = array();
$created = array();
$old_schema = get_option( 'lasery_xtool_schema' );
$failed_insert = false;
global $wpdb;
$old_suppress = $wpdb->suppress_errors( true );
$failure = static function ( $query ) use ( $wpdb, &$failed_insert ) {
	if ( str_starts_with( $query, 'INSERT INTO `' . $wpdb->posts . '`' ) && str_contains( $query, "'informacje-o-zakupach'" ) ) {
		$failed_insert = true;
		return 'INSERT INTO `' . $wpdb->prefix . 'missing_safety_fixture` (`id`) VALUES (1)';
	}
	return $query;
};
try {
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$original[ $slug ] = $page->ID;
			$renamed = wp_update_post( array( 'ID' => $page->ID, 'post_name' => $slug . '-test-' . $page->ID ), true );
			$check( ! is_wp_error( $renamed ), 'temporarily move fixture page ' . $slug );
		}
	}
	update_option( 'lasery_xtool_schema', '6' );
	add_filter( 'query', $failure );
	$check( false === lasery_xtool_install(), 'a failed page insertion fails the upgrade' );
	$check( $failed_insert, 'the simulated database error affected the intended insert' );
	$check( '6' === get_option( 'lasery_xtool_schema' ), 'schema is not advanced after failure' );
	$safety = get_page_by_path( 'bezpieczenstwo' );
	$check( $safety && ! get_page_by_path( 'informacje-o-zakupach' ), 'already created page is retained on partial failure' );
	$created[] = $safety->ID;
	remove_filter( 'query', $failure );
	lasery_xtool_install();
	$purchase = get_page_by_path( 'informacje-o-zakupach' );
	$check( $purchase && $safety->ID === get_page_by_path( 'bezpieczenstwo' )->ID, 'retry completes without duplicating the first page' );
	$created[] = $purchase->ID;
	$check( LASERY_XTOOL_SCHEMA === get_option( 'lasery_xtool_schema' ), 'schema advances after all pages exist' );
} finally {
	remove_filter( 'query', $failure );
	$wpdb->suppress_errors( $old_suppress );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $original as $slug => $id ) {
		wp_update_post( array( 'ID' => $id, 'post_name' => $slug ) );
	}
	update_option( 'lasery_xtool_schema', $old_schema );
}
