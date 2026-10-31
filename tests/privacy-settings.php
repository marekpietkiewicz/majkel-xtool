<?php
/** Run with php tests/privacy-settings.php. */
define( 'ABSPATH', __DIR__ );
define( 'LASERY_XTOOL_PRODUCT_TYPE', 'lasery_product' );

$can_manage = true;
$privacy_options = array();
function add_action( ...$args ) {}
function current_user_can( $capability ) { global $can_manage; return $can_manage; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message ); }
function get_option( $key, $default = false ) { global $privacy_options; return $privacy_options[ $key ] ?? $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '&' . $key . '=' . $value; }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function wp_nonce_field( $name ) {}
function wp_editor( $content, $id, $args ) { echo '<textarea>' . esc_html( $content ) . '</textarea>'; }
function lasery_xtool_default_privacy_html( $language ) { return 'Default ' . $language; }

require __DIR__ . '/../wp-content/plugins/lasery-xtool/privacy-settings.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

foreach ( array( 'pl', 'en', 'de', 'ru', 'uk' ) as $language ) {
	$_GET = array( 'language' => $language );
	$privacy_options['lasery_xtool_privacy_texts'] = 'en' === $language ? array( 'en' => 'Custom policy' ) : array();
	ob_start();
	lasery_xtool_privacy_settings_page();
	$html = ob_get_clean();
	check( false !== strpos( $html, 'notice notice-warning' ), 'Missing administrator warning for ' . $language );
	check( false !== strpos( $html, 'nie usuwa automatycznie wiadomości ze skrzynki' ), 'Missing manual deletion explanation for ' . $language );
	check( false !== strpos( $html, 'en' === $language ? 'Custom policy' : 'Default ' . $language ), 'Incorrect editor content for ' . $language );
}

$can_manage = false;
try {
	lasery_xtool_privacy_settings_page();
	throw new RuntimeException( 'Unauthorized visitor saw privacy settings' );
} catch ( RuntimeException $error ) {
	check( 'Brak uprawnień.' === $error->getMessage(), 'Unexpected access control error' );
}

echo "Privacy settings warning and access control: passed.\n";
