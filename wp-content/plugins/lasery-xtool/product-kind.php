<?php
/** Product groups. Products without the accessory term are shown as devices. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_KIND_TAXONOMY  = 'lasery_kind';
const LASERY_XTOOL_ACCESSORY_TERM = 'akcesoria';

add_action( 'init', 'lasery_xtool_register_kind_taxonomy' );
function lasery_xtool_register_kind_taxonomy() {
	register_taxonomy(
		LASERY_XTOOL_KIND_TAXONOMY,
		LASERY_XTOOL_PRODUCT_TYPE,
		array(
			'labels'            => array(
				'name'          => 'Typy produktów',
				'singular_name' => 'Typ produktu',
			),
			'description'       => 'Zaznacz „Akcesoria” dla dodatków. Produkty bez tego typu są pokazywane jako urządzenia.',
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}

function lasery_xtool_is_accessory( $post_id ) {
	return has_term( LASERY_XTOOL_ACCESSORY_TERM, LASERY_XTOOL_KIND_TAXONOMY, $post_id );
}

/** Limit a product query to devices or accessories. */
function lasery_xtool_kind_tax_query( $kind ) {
	return array(
		array(
			'taxonomy' => LASERY_XTOOL_KIND_TAXONOMY,
			'field'    => 'slug',
			'terms'    => LASERY_XTOOL_ACCESSORY_TERM,
			'operator' => 'accessory' === $kind ? 'IN' : 'NOT IN',
		),
	);
}
