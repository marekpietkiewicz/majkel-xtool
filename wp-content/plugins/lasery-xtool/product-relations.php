<?php
/**
 * Relations shown on product pages: the machines an accessory fits (edited on the accessory,
 * "Pasuje do urządzeń"), the articles that show a product (read from the [lasery_produkty]
 * shortcodes whenever a post is saved), both stored as xTool source IDs, and the contents of
 * set variants (read from their SKUs).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_FITS             = '_lasery_fits';
const LASERY_XTOOL_ARTICLE_PRODUCTS = '_lasery_products';

/** Meta query for the related products: an accessory's machines, or a machine's accessories. */
function lasery_xtool_related_meta_query( $post_id ) {
	$source = get_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, true );
	if ( lasery_xtool_is_accessory( $post_id ) ) {
		$fits = get_post_meta( $post_id, LASERY_XTOOL_FITS );
		return $fits ? array( array( 'key' => LASERY_XTOOL_SOURCE_ID, 'value' => $fits, 'compare' => 'IN' ) ) : null;
	}
	return is_string( $source ) && '' !== $source ? array( array( 'key' => LASERY_XTOOL_FITS, 'value' => $source ) ) : null;
}

/** Published variants by SKU: [post ID, variant] pairs, read once per request. */
function lasery_xtool_sku_index() {
	static $index = null;
	if ( null === $index ) {
		global $wpdb;
		$index = array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_lasery_source_variants' AND p.post_type = %s AND p.post_status = 'publish' ORDER BY m.post_id", LASERY_XTOOL_PRODUCT_TYPE ) );
		foreach ( $rows as $row ) {
			$variants = maybe_unserialize( $row->meta_value );
			foreach ( is_array( $variants ) ? $variants : array() as $variant ) {
				if ( is_array( $variant ) && isset( $variant['id'], $variant['title'], $variant['sku'] ) && is_string( $variant['sku'] ) && '' !== $variant['sku'] ) {
					$index[ $variant['sku'] ][] = array( (int) $row->post_id, $variant );
				}
			}
		}
	}
	return $index;
}

/**
 * What a set contains, as a list of [product, variant, quantity]. xTool's set variants join the
 * SKUs of their parts ("P0101000661-P3010010-P5010391"). The parts are matched with the SKUs of
 * published variants, split into as many items as possible: a run of parts is taken together
 * only when it is sold only as a whole (LM1 + RF1), and the whole set never matches, since xTool
 * also sells it under copies of the product. Returns [] for a single product or when a part is
 * not on the site, so a list is never incomplete.
 */
function lasery_xtool_set_contents( $post_id, $variant ) {
	$parts = isset( $variant['sku'] ) && is_string( $variant['sku'] ) ? explode( '-', $variant['sku'] ) : array();
	$count = count( $parts );
	// A set has at least two parts; xTool's largest have seven.
	if ( $count < 2 || $count > 12 ) {
		return array();
	}
	$index = lasery_xtool_sku_index();
	// $items[ $i ]: the most items that parts $i.. split into, -1 when they cannot be matched;
	// $next[ $i ]: where the first of those items ends.
	$items = array_fill( 0, $count + 1, -1 );
	$items[ $count ] = 0;
	$next = array();
	for ( $i = $count - 1; $i >= 0; --$i ) {
		for ( $j = $i + 1; $j <= $count; ++$j ) {
			if ( ( 0 === $i && $count === $j ) || $items[ $j ] < 0 || $items[ $j ] + 1 <= $items[ $i ] || ! isset( $index[ implode( '-', array_slice( $parts, $i, $j - $i ) ) ] ) ) {
				continue;
			}
			$items[ $i ] = $items[ $j ] + 1;
			$next[ $i ] = $j;
		}
	}
	if ( $items[0] < 2 ) {
		return array();
	}
	$contents = array();
	$option = explode( ' / ', $variant['title'] )[0];
	for ( $i = 0; $i < $count; $i = $next[ $i ] ) {
		// Variants sharing a SKU: the product's own (the machine alone) first, then the one with
		// the set's first option, e.g. the cutter of the 1200W set rather than of the 800W one.
		$match = null;
		$best = -1;
		foreach ( $index[ implode( '-', array_slice( $parts, $i, $next[ $i ] - $i ) ) ] as $candidate ) {
			$score = ( $candidate[0] === $post_id ? 2 : 0 ) + ( explode( ' / ', $candidate[1]['title'] )[0] === $option ? 1 : 0 );
			if ( $score > $best ) {
				$best = $score;
				$match = $candidate;
			}
		}
		$key = $match[0] . ':' . $match[1]['id'];
		if ( isset( $contents[ $key ] ) ) {
			++$contents[ $key ]['quantity'];
		} else {
			$contents[ $key ] = array( 'product' => $match[0], 'variant' => $match[1], 'quantity' => 1 );
		}
	}
	return array_values( $contents );
}

/** Meta query for the articles that show the product, or null. */
function lasery_xtool_product_articles_meta_query( $post_id ) {
	$source = get_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, true );
	return is_string( $source ) && '' !== $source ? array( array( 'key' => LASERY_XTOOL_ARTICLE_PRODUCTS, 'value' => $source ) ) : null;
}

/** Source IDs of the products in a post's [lasery_produkty] shortcodes. */
function lasery_xtool_article_source_ids( $content ) {
	$sources = array();
	if ( ! preg_match_all( '/' . get_shortcode_regex( array( 'lasery_produkty' ) ) . '/', (string) $content, $matches, PREG_SET_ORDER ) ) {
		return $sources;
	}
	foreach ( $matches as $match ) {
		// [[lasery_produkty]] is shown as text, not as products.
		if ( '[' === $match[1] && ']' === $match[6] ) {
			continue;
		}
		$attributes = shortcode_parse_atts( $match[3] );
		$attributes = is_array( $attributes ) ? $attributes : array();
		foreach ( explode( ',', $attributes['xtool'] ?? '' ) as $source ) {
			if ( ctype_digit( trim( $source ) ) ) {
				$sources[] = trim( $source );
			}
		}
		foreach ( array_filter( array_map( 'absint', explode( ',', $attributes['ids'] ?? '' ) ) ) as $id ) {
			$source = get_post_meta( $id, LASERY_XTOOL_SOURCE_ID, true );
			if ( is_string( $source ) && '' !== $source ) {
				$sources[] = $source;
			}
		}
	}
	return array_values( array_unique( $sources ) );
}

add_action( 'save_post_post', 'lasery_xtool_index_article_products' );
function lasery_xtool_index_article_products( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	delete_post_meta( $post_id, LASERY_XTOOL_ARTICLE_PRODUCTS );
	foreach ( lasery_xtool_article_source_ids( get_post_field( 'post_content', $post_id ) ) as $source ) {
		add_post_meta( $post_id, LASERY_XTOOL_ARTICLE_PRODUCTS, $source );
	}
}

add_action( 'add_meta_boxes_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_fits_box_register' );
function lasery_xtool_fits_box_register() {
	add_meta_box( 'lasery-fits', 'Pasuje do urządzeń', 'lasery_xtool_fits_box', LASERY_XTOOL_PRODUCT_TYPE, 'side' );
}

function lasery_xtool_fits_box( $post ) {
	wp_nonce_field( 'lasery_xtool_save_fits', 'lasery_xtool_fits_nonce' );
	$fits = get_post_meta( $post->ID, LASERY_XTOOL_FITS );
	// Every machine that is not in the trash, so saving keeps the fits of one being edited as a draft.
	$devices = get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => lasery_xtool_kind_tax_query( 'device' ), 'post__not_in' => array( $post->ID ) ) );
	echo '<p class="description">Dla akcesoriów: urządzenia, przy których akcesorium jest pokazywane w sekcji „Akcesoria do tego urządzenia”. Strona akcesorium pokazuje je w sekcji „Pasuje do urządzeń”.</p>';
	foreach ( $devices as $device ) {
		$source = get_post_meta( $device->ID, LASERY_XTOOL_SOURCE_ID, true );
		if ( ! is_string( $source ) || '' === $source ) {
			continue;
		}
		echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="lasery_fits[]" value="' . esc_attr( $source ) . '" ' . checked( in_array( $source, $fits, true ), true, false ) . '> ' . esc_html( get_the_title( $device ) ) . '</label>';
	}
}

add_action( 'save_post_' . LASERY_XTOOL_PRODUCT_TYPE, 'lasery_xtool_save_fits' );
function lasery_xtool_save_fits( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lasery_xtool_fits_nonce'] ) || ! is_string( $_POST['lasery_xtool_fits_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lasery_xtool_fits_nonce'] ) ), 'lasery_xtool_save_fits' ) ) {
		return;
	}
	$fits = isset( $_POST['lasery_fits'] ) && is_array( $_POST['lasery_fits'] ) ? array_unique( array_filter( wp_unslash( $_POST['lasery_fits'] ), function ( $source ) { return is_string( $source ) && ctype_digit( $source ); } ) ) : array();
	delete_post_meta( $post_id, LASERY_XTOOL_FITS );
	foreach ( $fits as $source ) {
		add_post_meta( $post_id, LASERY_XTOOL_FITS, $source );
	}
}
