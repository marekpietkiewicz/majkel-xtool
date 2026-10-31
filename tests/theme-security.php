<?php
// Run with WP-CLI against a disposable WordPress installation: wp eval-file tests/theme-security.php
if ( '1' !== getenv( 'LASERY_XTOOL_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This test requires disposable WordPress and WP-CLI.' );
}
function theme_test_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

foreach ( array(
	'<img src="http:external.example/pixel">',
	'<img src="https:/external.example/pixel">',
	'<img src="//external.example/pixel">',
	'<img srcset="http:external.example/pixel 1x">',
	'<img srcset="/local.png 1x, https://external.example/pixel 2x">',
	'<div style="background-image:u\\72l(https://external.example/pixel)"></div>',
	'<div style=\'background-image:image-set("https://external.example/pixel" 1x)\'></div>',
	'<style>@import "https://external.example/style.css";</style>',
	'<link rel="stylesheet" href="https://external.example/style.css">',
	'<iframe srcdoc="&lt;img src=https://external.example/pixel&gt;"></iframe>',
	'<iframe src="data:text/html,%3Cimg%20src%3Dhttps%3A%2F%2Fexternal.example%2Fpixel%3E"></iframe>',
) as $html ) {
	$result = lasery_xtool_gate_external_content( $html );
	theme_test_check( str_contains( $result, 'data-lasery-consent-' ), 'External URL was not gated: ' . $html );
}
$local = '<img src="/local.png" srcset="/local.png 1x, /local2.png 2x">';
theme_test_check( lasery_xtool_gate_external_content( $local ) === $local, 'Local media was unexpectedly gated' );
theme_test_check( lasery_xtool_gate_external_content( '<img src="data:image/png;base64,AAAA">' ) === '<img src="data:image/png;base64,AAAA">', 'A data image was unexpectedly gated' );
$local_style = '<style>.local { color: red; }</style><p style="color: red">Local</p>';
theme_test_check( lasery_xtool_gate_external_content( $local_style ) === $local_style, 'Safe styles were unexpectedly gated' );

$id = wp_insert_post( array( 'post_type' => 'lasery_product', 'post_status' => 'publish', 'post_title' => 'Offer fixture' ) );
try {
	update_post_meta( $id, '_lasery_order_price', '100.00' );
	update_post_meta( $id, '_lasery_order_currency', 'EUR' );
	$offer = lasery_xtool_product_offer( $id, get_permalink( $id ) );
	theme_test_check( isset( $offer['seller']['name'] ) && 'xTool' === $offer['seller']['name'], 'Offer seller must be xTool' );
	echo "Consent URL gating and offer seller passed.\n";
} finally {
	wp_delete_post( $id, true );
}
