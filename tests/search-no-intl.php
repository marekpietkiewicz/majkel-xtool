<?php
// Run against a disposable local WordPress with PHP's intl extension disabled.
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) ) {
	throw new RuntimeException( 'This test requires a disposable WordPress installation.' );
}
require '/var/www/html/wp-load.php';
if ( class_exists( 'Normalizer' ) ) {
	throw new RuntimeException( 'This test requires PHP without intl.' );
}
$marker = 'SearchNoIntl' . time();
$ids = array();
try {
	foreach ( array( 'Straße', 'Strasse' ) as $word ) {
		$ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $marker . ' ' . $word ) );
	}
	foreach ( array( 'straße' => 2, 'strasse' => 2, 'strase' => 1, 'strassen' => 0 ) as $query => $expected ) {
		$result = lasery_xtool_search_results( $marker . ' ' . $query, 0, 0, true );
		if ( $result['count'] !== $expected ) {
			throw new RuntimeException( 'Unexpected result count for ' . $query );
		}
	}
	if ( 'и е' !== lasery_xtool_search_normalize( 'й ё' ) ) {
		throw new RuntimeException( 'Cyrillic folding without intl failed.' );
	}
	if ( 'a' !== lasery_xtool_search_normalize( "a\u{0301}" ) || '' !== lasery_xtool_search_normalize( "\u{0301}" ) ) {
		throw new RuntimeException( 'Combining-mark folding without intl failed.' );
	}
	echo "Search without intl: ß/ss and Cyrillic folding passed.\n";
} finally {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}
