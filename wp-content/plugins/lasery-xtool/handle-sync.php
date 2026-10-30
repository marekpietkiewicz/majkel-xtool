<?php
/** Bounded verification of a known Shopify product by its source handle. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_valid_source_handle( $handle ) {
	return is_string( $handle ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,199}$/D', $handle );
}

/** Fetch only an already known product; JSON and JS must agree before use. */
function lasery_xtool_fetch_known_handle( $id, $handle, $currency, $fetched_at ) {
	if ( ! is_string( $id ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', $id ) || ! lasery_xtool_valid_source_handle( $handle ) ) {
		return new WP_Error( 'format' );
	}
	$documents = array();
	foreach ( array( 'json', 'js' ) as $extension ) {
		$url = 'https://www.xtool.eu/products/' . rawurlencode( $handle ) . '.' . $extension;
		$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => LASERY_XTOOL_MAX_BYTES + 1, 'decompress' => false, 'headers' => array( 'Accept-Encoding' => 'identity' ) ) );
		if ( ! is_wp_error( $response ) && 429 === wp_remote_retrieve_response_code( $response ) ) {
			update_option( 'lasery_xtool_fetch_paused', 1, false );
			return new WP_Error( 'paused' );
		}
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! lasery_xtool_identity_response( $response ) ) {
			return new WP_Error( 'network' );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > LASERY_XTOOL_MAX_BYTES ) {
			return new WP_Error( 'network' );
		}
		$documents[ $extension ] = json_decode( $body, false, 512, JSON_BIGINT_AS_STRING );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $documents[ $extension ] ) ) {
			return new WP_Error( 'format' );
		}
	}
	return lasery_xtool_merge_handle_documents( $id, $handle, $currency, $fetched_at, $documents['json'], $documents['js'] );
}

/** Validate already acquired JSON/JS documents without making network requests. */
function lasery_xtool_merge_handle_documents( $id, $handle, $currency, $fetched_at, $json, $js ) {
	if ( ! is_string( $id ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', $id ) || ! lasery_xtool_valid_source_handle( $handle ) || ! is_string( $currency ) || ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) || ! is_int( $fetched_at ) || $fetched_at < 1 || $fetched_at > time() || ! is_object( $json ) || ! is_object( $js ) ) {
		return new WP_Error( 'format' );
	}
	$product = isset( $json->product ) ? $json->product : null;
	$offer = $js;
	foreach ( array( $product, $offer ) as $document ) {
		if ( ! is_object( $document ) || ! isset( $document->id, $document->handle, $document->variants ) || ( ! is_int( $document->id ) && ! is_string( $document->id ) ) || (string) $document->id !== $id || $document->handle !== $handle || ! is_array( $document->variants ) ) {
			return new WP_Error( 'format' );
		}
	}
	$available = array();
	foreach ( $offer->variants as $variant ) {
		if ( ! is_object( $variant ) || ! isset( $variant->id, $variant->price, $variant->available ) || ( ! is_int( $variant->id ) && ! is_string( $variant->id ) ) || ! preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $variant->id ) || ( ! is_int( $variant->price ) && ! is_string( $variant->price ) ) || ! preg_match( '/^(?:0|[1-9][0-9]{0,10})$/D', (string) $variant->price ) || ! is_bool( $variant->available ) || isset( $available[ (string) $variant->id ] ) ) {
			return new WP_Error( 'format' );
		}
		$available[ (string) $variant->id ] = $variant;
	}
	foreach ( $product->variants as $variant ) {
		if ( ! is_object( $variant ) || ! isset( $variant->id, $variant->price ) || ( ! is_int( $variant->id ) && ! is_string( $variant->id ) ) || ! is_string( $variant->price ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $variant->price ) || ! isset( $available[ (string) $variant->id ] ) ) {
			return new WP_Error( 'format' );
		}
		foreach ( array( 'price_currency', 'compare_at_price_currency' ) as $currency_field ) {
			if ( 'compare_at_price_currency' === $currency_field && ! isset( $variant->compare_at_price ) ) {
				continue;
			}
			if ( isset( $variant->$currency_field ) && ( ! is_string( $variant->$currency_field ) || ! preg_match( '/^[A-Z]{3}$/D', $variant->$currency_field ) || ( '' !== $currency && $currency !== $variant->$currency_field ) ) ) {
				return new WP_Error( 'format' );
			}
		}
		$parts = explode( '.', $variant->price, 2 );
		$cents = ltrim( $parts[0] . str_pad( isset( $parts[1] ) ? $parts[1] : '', 2, '0' ), '0' );
		$cents = '' === $cents ? '0' : $cents;
		$offer_variant = $available[ (string) $variant->id ];
		if ( $cents !== (string) $offer_variant->price ) {
			return new WP_Error( 'format' );
		}
		$variant->available = $offer_variant->available;
		unset( $available[ (string) $variant->id ] );
	}
	if ( $available ) {
		return new WP_Error( 'format' );
	}
	$encoded = wp_json_encode( array( 'products' => array( $product ) ) );
	$parsed = is_string( $encoded ) ? lasery_xtool_parse_products( $encoded ) : false;
	if ( false === $parsed || 1 !== count( $parsed ) ) {
		return new WP_Error( 'format' );
	}
	$parsed[0]['currency'] = $currency;
	$parsed[0]['fetched_at'] = $fetched_at;
	return $parsed[0];
}
