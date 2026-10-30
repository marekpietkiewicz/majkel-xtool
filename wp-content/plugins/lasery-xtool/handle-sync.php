<?php
/** Validation of Shopify product handles and of saved per-product JSON/JS snapshots (scripts/seed-extra-products.php). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_valid_source_handle( $handle ) {
	return is_string( $handle ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,199}$/D', $handle );
}

/** Merge a product's JSON and JS documents; variant IDs and prices must agree. */
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
