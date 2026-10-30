<?php
/** Product badges managed in the admin panel. The "demo" badge also offers demo booking. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_BADGE_TAXONOMY = 'lasery_badge';
const LASERY_XTOOL_DEMO_BADGE     = 'demo';

add_action( 'init', 'lasery_xtool_register_badge_taxonomy' );
function lasery_xtool_register_badge_taxonomy() {
	register_taxonomy(
		LASERY_XTOOL_BADGE_TAXONOMY,
		LASERY_XTOOL_PRODUCT_TYPE,
		array(
			'labels'            => array(
				'name'          => 'Oznaczenia',
				'singular_name' => 'Oznaczenie',
				'add_new_item'  => 'Dodaj oznaczenie',
			),
			'description'       => 'Plakietki na kartach i stronach produktów, np. „Nowość”. Oznaczenie z uproszczoną nazwą (slug) „demo” dodaje też ramkę z umawianiem demo.',
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}

function lasery_xtool_has_demo( $post_id ) {
	return has_term( LASERY_XTOOL_DEMO_BADGE, LASERY_XTOOL_BADGE_TAXONOMY, $post_id );
}

/**
 * The query var lasery_demo_first lists products with a demo before the rest, each group in
 * its usual order (get_posts() needs suppress_filters => false).
 */
add_filter( 'posts_clauses', 'lasery_xtool_demo_first_clauses', 10, 2 );
function lasery_xtool_demo_first_clauses( $clauses, $query ) {
	$term = $query->get( 'lasery_demo_first' ) ? get_term_by( 'slug', LASERY_XTOOL_DEMO_BADGE, LASERY_XTOOL_BADGE_TAXONOMY ) : false;
	if ( ! $term ) {
		return $clauses;
	}
	global $wpdb;
	$clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->term_relationships} AS lasery_demo ON lasery_demo.object_id = {$wpdb->posts}.ID AND lasery_demo.term_taxonomy_id = %d", $term->term_taxonomy_id );
	$clauses['orderby'] = rtrim( 'lasery_demo.object_id IS NULL, ' . $clauses['orderby'], ', ' );
	return $clauses;
}

function lasery_xtool_demo_tax_query() {
	return array(
		array(
			'taxonomy' => LASERY_XTOOL_BADGE_TAXONOMY,
			'field'    => 'slug',
			'terms'    => LASERY_XTOOL_DEMO_BADGE,
		),
	);
}

/** Badges of a product as [slug, label in the given language]. */
function lasery_xtool_product_badges( $post_id, $language ) {
	$terms = get_the_terms( $post_id, LASERY_XTOOL_BADGE_TAXONOMY );
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$badges = array();
	foreach ( $terms as $term ) {
		$label = 'pl' === $language ? '' : get_term_meta( $term->term_id, 'lasery_name_' . $language, true );
		$badges[] = array( $term->slug, is_string( $label ) && '' !== $label ? $label : $term->name );
	}
	return $badges;
}

/** The term name is the Polish label; other languages are stored as term meta. */
function lasery_xtool_badge_languages() {
	return array_diff_key( lasery_xtool_description_languages(), array( 'pl' => true ) );
}

add_action( LASERY_XTOOL_BADGE_TAXONOMY . '_add_form_fields', 'lasery_xtool_badge_add_fields' );
function lasery_xtool_badge_add_fields() {
	foreach ( lasery_xtool_badge_languages() as $language => $label ) {
		?>
		<div class="form-field"><label for="lasery-badge-<?php echo esc_attr( $language ); ?>">Nazwa — <?php echo esc_html( $label ); ?></label><input id="lasery-badge-<?php echo esc_attr( $language ); ?>" name="lasery_name_<?php echo esc_attr( $language ); ?>" type="text" maxlength="40"></div>
		<?php
	}
}

add_action( LASERY_XTOOL_BADGE_TAXONOMY . '_edit_form_fields', 'lasery_xtool_badge_edit_fields' );
function lasery_xtool_badge_edit_fields( $term ) {
	foreach ( lasery_xtool_badge_languages() as $language => $label ) {
		?>
		<tr class="form-field"><th scope="row"><label for="lasery-badge-<?php echo esc_attr( $language ); ?>">Nazwa — <?php echo esc_html( $label ); ?></label></th><td><input id="lasery-badge-<?php echo esc_attr( $language ); ?>" name="lasery_name_<?php echo esc_attr( $language ); ?>" type="text" maxlength="40" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'lasery_name_' . $language, true ) ); ?>"><p class="description">Puste pole pokazuje nazwę polską.</p></td></tr>
		<?php
	}
}

// Core verifies the nonce of the term forms before these hooks run.
add_action( 'created_' . LASERY_XTOOL_BADGE_TAXONOMY, 'lasery_xtool_save_badge_names' );
add_action( 'edited_' . LASERY_XTOOL_BADGE_TAXONOMY, 'lasery_xtool_save_badge_names' );
function lasery_xtool_save_badge_names( $term_id ) {
	if ( ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	foreach ( array_keys( lasery_xtool_badge_languages() ) as $language ) {
		$key = 'lasery_name_' . $language;
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			continue;
		}
		$name = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		if ( '' === $name ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $name );
		}
	}
}
