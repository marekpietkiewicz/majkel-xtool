<?php
// Force the second conditional update to miss, exercising transaction rollback.
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    throw new RuntimeException( 'This test requires an explicitly enabled disposable WordPress install.' );
}
add_filter( 'query', function ( $query ) {
    global $wpdb;
    static $updates = 0;
    if ( strpos( $query, "UPDATE {$wpdb->posts} SET post_content = " ) === 0 && ++$updates === 2 ) {
        return $query . ' AND 0=1';
    }
    return $query;
} );
require '/safety-scripts/apply-safety-corrections.php';
