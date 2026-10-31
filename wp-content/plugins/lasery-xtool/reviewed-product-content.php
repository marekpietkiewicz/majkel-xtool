<?php
/** Reviewed source descriptions used for new products with inaccurate supplier text. */
function lasery_xtool_reviewed_english_features( $source_id ) {
	if ( '8309744009429' === (string) $source_id ) {
		return array(
			'The RA2 Pro supports roller, chuck, sphere and ring setups.',
			'Compatible with xTool S1, F2, F2 Ultra, F2 Ultra UV and F1 Ultra. Check the F2 Ultra connection cable: some sets need a compatible cable purchased separately.',
			'The RA2 Pro Plus includes the RA2 Pro and accessory kit for additional setups and focus alignment.',
		);
	}
	if ( '7954661376213' === (string) $source_id ) {
		return array(
			'Connects to the laser and can automatically trigger fire suppression.',
			'Manual activation is available with a button.',
			'Indicators show gas level, motor stall and operating status.',
			'After activation, check and replace CO₂ cylinders according to your version’s manual. The set works in a closed processing space, cannot extinguish every fire and does not replace supervision or a fire extinguisher. For the original Fire Safety Set with M2 and xTool Inline Duct Fan or xTool Smoke Purifier, a separate smart switch is required.',
			'Fire Safety Set 2 fits X1 and M2; Fire Safety Set fits M2, S1, F2 Ultra, F2 Ultra UV and F1 Ultra.',
		);
	}
	return null;
}

/** Keep imported content unless the supplier text for this product needs a reviewed fallback. */
function lasery_xtool_initial_product_content( $product ) {
	$items = lasery_xtool_reviewed_english_features( $product['id'] );
	if ( null === $items ) {
		return $product['content'];
	}
	return wp_kses_post( '<ul>' . implode( '', array_map( static function ( $item ) { return '<li>' . esc_html( $item ) . '</li>'; }, $items ) ) . '</ul>' );
}
