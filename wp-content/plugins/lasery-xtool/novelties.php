<?php
/**
 * New machines of xTool's main catalogue. The collection "machine" is the manufacturer's list of the
 * machines it sells and recommends. A machine in it that the site does not know yet is published at
 * once, and every machine of it that xTool published within the last 60 days carries the "Nowość"
 * badge, which lists it right after the products with a demo. The scheduler checks it after the offer sync.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_NEW_BADGE      = 'nowosc';
const LASERY_XTOOL_NEW_DAYS       = 60;
const LASERY_XTOOL_NOVELTY_STATE  = 'lasery_xtool_novelty_state';
const LASERY_XTOOL_NOVELTY_LIMIT  = 3;
// Not machines for sale: used units, kiosks, gift cards, deposits and the education robotics line.
const LASERY_XTOOL_NOVELTY_SKIP   = '/Refurbished|Kiosk|Gift|Payment|mBot|mBuild|CyberPi|xToolCare/i';

/** Mail the site's administrator (the address in Settings → General). */
function lasery_xtool_notify( $subject, $body ) {
	add_action( 'phpmailer_init', 'lasery_xtool_mail_sender' );
	try {
		return wp_mail( get_option( 'admin_email' ), $subject, $body );
	} finally {
		remove_action( 'phpmailer_init', 'lasery_xtool_mail_sender' );
	}
}

/**
 * Drop the list items and paragraphs of an unreviewed supplier description that make safety, class or
 * certification claims. Descriptions of new models have not been checked against the manual: an
 * earlier supplier text called a Class 4 laser eye-safe.
 */
function lasery_xtool_strip_unreviewed_claims( $html ) {
	// A failed match (backtrack limit) yields '': better no description than an unfiltered one.
	$html = (string) preg_replace( '~<(li|p)\b[^>]*>(?:(?!</\1>).)*?(?:eye[- ]?safe|class\s*(?:1|i\b)|safe\b|safety|harmless|goggles|eyewear|certif|\bCE\b|\bFDA\b|non-?toxic|food[- ]safe)(?:(?!</\1>).)*</\1>\s*~isu', '', $html );
	return (string) preg_replace( '~<(ul|ol)\b[^>]*>\s*</\1>\s*~i', '', $html );
}

/** Source IDs of every product record of the site, in any status including the trash. */
function lasery_xtool_known_source_ids() {
	global $wpdb;
	return array_flip( $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", LASERY_XTOOL_SOURCE_ID ) ) );
}

/** Publish a machine that the site does not know yet. Returns the post ID or 0. */
function lasery_xtool_import_novelty( $product, $currency ) {
	$product = $product + array( 'currency' => $currency, 'fetched_at' => time() );
	$content = lasery_xtool_strip_unreviewed_claims( lasery_xtool_initial_product_content( $product ) );
	$post_id = wp_insert_post( wp_slash( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'draft', 'post_title' => $product['title'], 'post_content' => $content ) ), true );
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}
	if ( ! add_post_meta( $post_id, LASERY_XTOOL_SOURCE_ID, $product['id'], true ) || ! lasery_xtool_save_source_data( $post_id, $product ) ) {
		wp_delete_post( $post_id, true );
		return 0;
	}
	// The marker keeps a generic safety notice on the page until someone has reviewed the model.
	add_post_meta( $post_id, '_lasery_auto_imported', (string) time(), true );
	wp_set_object_terms( $post_id, LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY );
	$published = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $published ) || 'publish' !== get_post_status( $post_id ) ) {
		wp_delete_post( $post_id, true );
		return 0;
	}
	lasery_xtool_schedule_cache_purge();
	return $post_id;
}

/**
 * One conditional request for the collection, the import of new machines and the badge of the
 * recent ones. A failed request schedules a separate, bounded retry.
 */
function lasery_xtool_sync_novelties( $currency, $deadline ) {
	if ( (int) get_option( 'lasery_xtool_novelty_retry_at' ) > time() ) {
		return;
	}
	$state    = get_option( LASERY_XTOOL_NOVELTY_STATE );
	$state    = is_array( $state ) ? $state : array();
	$members  = isset( $state['members'] ) && is_array( $state['members'] ) ? $state['members'] : array(); // source ID => published timestamp
	// Each tick sends at most one request for this optional collection.
	$response = lasery_xtool_source_get( LASERY_XTOOL_SOURCE_URL . '/collections/machine/products.json?limit=250', LASERY_XTOOL_MAX_BYTES, $deadline, isset( $state['etag'] ) ? $state['etag'] : '' );
	$success  = false;
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$raw = lasery_xtool_decode_products( wp_remote_retrieve_body( $response ), LASERY_XTOOL_PAGE_SIZE );
		if ( false !== $raw ) {
			$success    = true;
			$members    = array();
			$candidates = array();
			$known      = lasery_xtool_known_source_ids();
			foreach ( $raw as $entry ) {
				$id        = lasery_xtool_raw_product_id( $entry );
				$published = is_object( $entry ) && isset( $entry->published_at ) && is_string( $entry->published_at ) ? strtotime( $entry->published_at ) : false;
				if ( '' === $id || false === $published ) {
					continue;
				}
				$members[ $id ] = $published;
				if ( isset( $known[ $id ] ) ) {
					continue;
				}
				$product = lasery_xtool_parse_product( $entry );
				// A machine that can be ordered: valid data, an available variant and a picture.
				if ( $product && ! preg_match( LASERY_XTOOL_NOVELTY_SKIP, $product['title'] ) && ! empty( $product['image_urls'] ) && wp_list_filter( $product['variants'], array( 'available' => true ) ) ) {
					$candidates[ $id ] = $product;
				}
			}
			if ( count( $candidates ) > LASERY_XTOOL_NOVELTY_LIMIT ) {
				// Several at once looks like a change of the source, not a launch: let a person look.
				if ( ( isset( $state['held'] ) ? $state['held'] : '' ) !== implode( ',', array_keys( $candidates ) ) ) {
					lasery_xtool_notify( 'laseryxtool.pl: wiele nowych produktów naraz', "W głównym katalogu xTool pojawiło się naraz " . count( $candidates ) . " nieznanych maszyn, więc nie zostały dodane automatycznie:\n- " . implode( "\n- ", wp_list_pluck( $candidates, 'title' ) ) );
				}
				$state['held'] = implode( ',', array_keys( $candidates ) );
				$candidates    = array();
			} else {
				unset( $state['held'] );
			}
			foreach ( $candidates as $id => $product ) {
				$post_id = lasery_xtool_import_novelty( $product, $currency );
				if ( $post_id ) {
					lasery_xtool_notify(
						'laseryxtool.pl: nowy produkt ' . $product['title'],
						sprintf( "Nowa maszyna z głównego katalogu xTool została dodana do oferty i jest widoczna na stronie (z plakietką „Nowość”, zaraz za produktami z demo):\n%s\n\nOpis pochodzi od producenta bez zdań o bezpieczeństwie i certyfikatach, a strona ma ogólny komunikat o bezpieczeństwie. Sprawdź nazwy i opisy w językach oraz klasę lasera w instrukcji, dodaj komunikat bezpieczeństwa modelu, a potem usuń pole niestandardowe _lasery_auto_imported.\nEdycja: %s", get_permalink( $post_id ), admin_url( 'post.php?post=' . $post_id . '&action=edit' ) )
					);
				} else {
					$success = false;
				}
			}
			$state['etag'] = $success ? lasery_xtool_valid_etag( wp_remote_retrieve_header( $response, 'etag' ) ) : '';
		}
	} elseif ( ! is_wp_error( $response ) && 304 === wp_remote_retrieve_response_code( $response ) && ! empty( $state['etag'] ) && lasery_xtool_valid_etag( $state['etag'] ) ) {
		$success = true;
	}
	if ( $success ) {
		delete_option( 'lasery_xtool_novelty_retry_at' );
		delete_option( 'lasery_xtool_novelty_retry_failures' );
	} else {
		lasery_xtool_schedule_novelty_retry();
	}
	$state['members'] = $members;
	update_option( LASERY_XTOOL_NOVELTY_STATE, $state, false );
	lasery_xtool_apply_new_badges( $members );
}

/** The badge belongs to the machines of the collection that xTool published within the last 60 days. */
function lasery_xtool_apply_new_badges( $members ) {
	$term = get_term_by( 'slug', LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY );
	if ( ! $term ) {
		return;
	}
	$recent = array();
	foreach ( $members as $source => $published ) {
		if ( $published >= time() - LASERY_XTOOL_NEW_DAYS * DAY_IN_SECONDS ) {
			$recent[] = (string) $source;
		}
	}
	$wanted = $recent ? get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => LASERY_XTOOL_SOURCE_ID, 'value' => $recent, 'compare' => 'IN' ) ) ) ) : array();
	$have   = array_map( 'intval', get_objects_in_term( $term->term_id, LASERY_XTOOL_BADGE_TAXONOMY ) );
	foreach ( array_diff( $wanted, $have ) as $post_id ) {
		wp_set_object_terms( $post_id, LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, true );
		lasery_xtool_schedule_cache_purge();
	}
	foreach ( array_diff( $have, $wanted ) as $post_id ) {
		wp_remove_object_terms( $post_id, LASERY_XTOOL_NEW_BADGE, LASERY_XTOOL_BADGE_TAXONOMY );
		lasery_xtool_schedule_cache_purge();
	}
}
