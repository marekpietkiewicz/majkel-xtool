<?php
/** WordPress's GD editor, but palette images (many PNGs) become true colour, which GD needs to write WebP. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lasery_XTool_Image_Editor extends WP_Image_Editor_GD {
	public function load() {
		$loaded = parent::load();
		if ( true === $loaded && ! imageistruecolor( $this->image ) ) {
			imagepalettetotruecolor( $this->image );
		}
		return $loaded;
	}
}
