<?php
/** Download bounded source images into the media library as WebP. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_is_source_image_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || strlen( $url ) > 2048 ) {
		return false;
	}
	$parts = wp_parse_url( $url );
	return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === $parts['scheme']
		&& in_array( strtolower( $parts['host'] ), array( 'cdn.shopify.com', 'www.xtool.eu' ), true )
		&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['port'] );
}

/** The feed order determines the gallery order; old single-image records remain usable. */
function lasery_xtool_source_image_urls( $post_id ) {
	$urls = get_post_meta( $post_id, '_lasery_source_image_urls', true );
	if ( ! is_array( $urls ) ) {
		$first = get_post_meta( $post_id, '_lasery_source_image_url', true );
		$urls = $first ? array( $first ) : array();
	}
	return array_values( array_unique( array_filter( $urls, 'lasery_xtool_is_source_image_url' ) ) );
}

/** Return validated source URLs and any corresponding local images, in source order. */
function lasery_xtool_product_gallery_items( $post_id ) {
	$manual = function_exists( 'lasery_xtool_get_manual_gallery' ) ? lasery_xtool_get_manual_gallery( $post_id ) : null;
	if ( null !== $manual ) {
		$items = array();
		foreach ( $manual as $id ) {
			$items[] = array( 'id' => $id, 'url' => '' );
		}
		return $items;
	}
	$urls = lasery_xtool_source_image_urls( $post_id );
	$images = get_post_meta( $post_id, '_lasery_local_gallery', true );
	$images = is_array( $images ) ? $images : array();
	$old_source = get_post_meta( $post_id, '_lasery_local_image_source', true );
	$old_id = absint( get_post_meta( $post_id, '_lasery_local_image_attachment_id', true ) );
	if ( is_string( $old_source ) && $old_source && $old_id && ! isset( $images[ $old_source ] ) ) {
		$images[ $old_source ] = $old_id;
	}
	$gallery = array();
	foreach ( $urls as $url ) {
		$id = isset( $images[ $url ] ) ? absint( $images[ $url ] ) : 0;
		if ( ! $id || 'attachment' !== get_post_type( $id ) || 'image/webp' !== get_post_mime_type( $id ) || ! get_attached_file( $id ) || ! file_exists( get_attached_file( $id ) ) ) {
			$id = 0;
		}
		$gallery[] = array( 'id' => $id, 'url' => $url );
	}
	return $gallery;
}

/** Return only locally stored images, in the current source order. */
function lasery_xtool_product_gallery( $post_id ) {
	$ids = array();
	foreach ( lasery_xtool_product_gallery_items( $post_id ) as $item ) {
		if ( $item['id'] && ! in_array( $item['id'], $ids, true ) ) {
			$ids[] = $item['id'];
		}
	}
	return $ids;
}

/** Keep a manually selected or removed thumbnail untouched. */
function lasery_xtool_set_source_thumbnail( $post_id, $url, $attachment ) {
	$previous = absint( get_post_meta( $post_id, '_lasery_local_image_attachment_id', true ) );
	$thumbnail = get_post_thumbnail_id( $post_id );
	if ( ( ! $previous && ! $thumbnail ) || ( $previous && $thumbnail === $previous ) ) {
		set_post_thumbnail( $post_id, $attachment );
	}
	update_post_meta( $post_id, '_lasery_local_image_source', wp_slash( $url ) );
	update_post_meta( $post_id, '_lasery_local_image_attachment_id', $attachment );
}

/** Called by a scheduler or manually via WP-CLI; never schedules itself. */
add_action( 'lasery_xtool_sync_images', 'lasery_xtool_sync_images' );
function lasery_xtool_sync_images() {
	if ( lasery_xtool_fetching_paused() ) {
		update_option( 'lasery_xtool_last_image_sync', array( 'time' => time(), 'result' => 'paused' ), false );
		return;
	}
	if ( ! lasery_xtool_lock_import() ) {
		update_option( 'lasery_xtool_last_image_sync', array( 'time' => time(), 'result' => 'locked' ), false );
		return;
	}
	$posts = get_posts( array(
		'post_type' => LASERY_XTOOL_PRODUCT_TYPE,
		'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'meta_key' => '_lasery_source_image_url',
		'fields' => 'ids',
		'posts_per_page' => -1,
		'no_found_rows' => true,
	) );
	$attempted = 0;
	$imported = 0;
	$failed = 0;
	$result = 'complete';
	$primary = array();
	$other = array();
	foreach ( $posts as $post_id ) {
		$urls = lasery_xtool_source_image_urls( $post_id );
		$images = get_post_meta( $post_id, '_lasery_local_gallery', true );
		$images = is_array( $images ) ? $images : array();
		$old_url = get_post_meta( $post_id, '_lasery_local_image_source', true );
		$old_id = absint( get_post_meta( $post_id, '_lasery_local_image_attachment_id', true ) );
		if ( is_string( $old_url ) && $old_url && $old_id && ! isset( $images[ $old_url ] ) ) {
			$images[ $old_url ] = $old_id;
		}
		$retries = get_post_meta( $post_id, '_lasery_image_retries', true );
		$retries = is_array( $retries ) ? $retries : array();
		foreach ( $urls as $index => $url ) {
			$id = isset( $images[ $url ] ) ? absint( $images[ $url ] ) : 0;
			if ( $id && 'attachment' === get_post_type( $id ) && 'image/webp' === get_post_mime_type( $id ) && get_attached_file( $id ) && file_exists( get_attached_file( $id ) ) ) {
				if ( 0 === $index && ( $old_url !== $url || $old_id !== $id ) ) {
					lasery_xtool_set_source_thumbnail( $post_id, $url, $id );
				}
				continue;
			}
			if ( isset( $retries[ $url ] ) && (int) $retries[ $url ] > time() ) {
				continue;
			}
			$job = array( 'post_id' => $post_id, 'url' => $url );
			if ( 0 === $index ) {
				$primary[] = $job;
			} else {
				$other[] = $job;
			}
		}
	}
	// Fill each product's main image before spending the batch on galleries.
	foreach ( array_slice( array_merge( $primary, $other ), 0, 3 ) as $job ) {
		if ( ! lasery_xtool_refresh_import_lock() ) {
			$result = 'locklost';
			break;
		}
		$post_id = $job['post_id'];
		$url = $job['url'];
		$retries = get_post_meta( $post_id, '_lasery_image_retries', true );
		$retries = is_array( $retries ) ? array_intersect_key( $retries, array_fill_keys( lasery_xtool_source_image_urls( $post_id ), true ) ) : array();
		++$attempted;
		$image = lasery_xtool_import_image( $post_id, $url );
		if ( is_wp_error( $image ) ) {
			++$failed;
			$retries[ $url ] = time() + 6 * HOUR_IN_SECONDS;
			update_post_meta( $post_id, '_lasery_image_retries', wp_slash( $retries ) );
			if ( 'paused' === $image->get_error_code() ) {
				$result = 'paused';
				break;
			}
		} else {
			++$imported;
			unset( $retries[ $url ] );
			update_post_meta( $post_id, '_lasery_image_retries', wp_slash( $retries ) );
		}
	}
	if ( 'complete' === $result && count( $primary ) + count( $other ) > $attempted ) {
		$result = 'batch';
	}
	update_option( 'lasery_xtool_last_image_sync', compact( 'result', 'attempted', 'imported', 'failed' ) + array( 'time' => time() ), false );
	lasery_xtool_release_import_lock();
}

/** The image editor class: WordPress's GD editor with palette images converted (class-image-editor.php). */
function lasery_xtool_image_editor_class() {
	require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
	require_once __DIR__ . '/class-image-editor.php';
	return 'Lasery_XTool_Image_Editor';
}

/** Validate the response before decoding; save only a resized WebP in WordPress media. */
function lasery_xtool_import_image( $post_id, $url ) {
	if ( ! lasery_xtool_is_source_image_url( $url ) ) {
		return new WP_Error( 'image_url' );
	}
	$max_bytes = 8 * 1024 * 1024;
	$response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => $max_bytes + 1, 'decompress' => false, 'headers' => array( 'Accept-Encoding' => 'identity' ) ) );
	if ( ! is_wp_error( $response ) && 429 === wp_remote_retrieve_response_code( $response ) ) {
		lasery_xtool_pause_fetching( $response );
		return new WP_Error( 'paused' );
	}
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'image_download' );
	}
	$encoding = wp_remote_retrieve_header( $response, 'content-encoding' );
	if ( ! is_string( $encoding ) || ( '' !== $encoding && 'identity' !== strtolower( trim( $encoding ) ) ) ) {
		return new WP_Error( 'image_encoding' );
	}
	$body = wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) > $max_bytes ) {
		return new WP_Error( 'image_size' );
	}
	$info = @getimagesizefromstring( $body );
	if ( ! is_array( $info ) || ! isset( $info[0], $info[1], $info['mime'] ) || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) || $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 16000000 ) {
		return new WP_Error( 'image_format' );
	}
	// Refuse animated formats before decoding: frame counts are not bounded by
	// the dimensions reported for the first frame. Conservative byte matching
	// can also reject a static image containing these markers in other data.
	if ( ( 'image/png' === $info['mime'] && false !== strpos( $body, 'acTL' ) ) || ( 'image/webp' === $info['mime'] && false !== strpos( $body, 'ANIM' ) ) ) {
		return new WP_Error( 'image_format' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$source = wp_tempnam( 'xtool-source' );
	$output = wp_tempnam( 'xtool.webp' );
	if ( ! $source || ! $output ) {
		if ( $source ) { @unlink( $source ); }
		if ( $output ) { @unlink( $output ); }
		return new WP_Error( 'image_temp' );
	}
	$saved_path = '';
	try {
		if ( false === file_put_contents( $source, $body ) ) {
			return new WP_Error( 'image_write' );
		}
		unset( $body );
		// GD decodes a single raster; Imagick may load every frame of a crafted
		// multi-image file before WordPress selects the first one.
		$single_frame_editor = static function () { return array( lasery_xtool_image_editor_class() ); };
		add_filter( 'wp_image_editors', $single_frame_editor );
		try {
			$editor = wp_get_image_editor( $source );
		} finally {
			remove_filter( 'wp_image_editors', $single_frame_editor );
		}
		if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
			return new WP_Error( 'image_editor' );
		}
		$resized = ( $info[0] > 1800 || $info[1] > 1800 ) ? $editor->resize( 1800, 1800, false ) : true;
		if ( is_wp_error( $resized ) || is_wp_error( $editor->set_quality( 82 ) ) ) {
			return new WP_Error( 'image_resize' );
		}
		$saved = $editor->save( $output, 'image/webp' );
		if ( ! is_wp_error( $saved ) && isset( $saved['path'] ) && is_string( $saved['path'] ) ) {
			$saved_path = $saved['path'];
		}
		if ( is_wp_error( $saved ) || ! isset( $saved['path'], $saved['mime-type'] ) || 'image/webp' !== $saved['mime-type'] ) {
			return new WP_Error( 'image_encode' );
		}
		if ( ! in_array( $url, lasery_xtool_source_image_urls( $post_id ), true ) ) {
			return new WP_Error( 'image_changed' );
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment = media_handle_sideload( array( 'name' => 'xtool-' . $post_id . '.webp', 'tmp_name' => $saved['path'] ), $post_id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		if ( ! in_array( $url, lasery_xtool_source_image_urls( $post_id ), true ) ) {
			wp_delete_attachment( $attachment, true );
			return new WP_Error( 'image_changed' );
		}
		$images = get_post_meta( $post_id, '_lasery_local_gallery', true );
		$images = is_array( $images ) ? $images : array();
		$images = array_intersect_key( $images, array_fill_keys( lasery_xtool_source_image_urls( $post_id ), true ) );
		$images[ $url ] = $attachment;
		update_post_meta( $post_id, '_lasery_local_gallery', wp_slash( $images ) );
		if ( get_post_meta( $post_id, '_lasery_local_gallery', true ) !== $images ) {
			wp_delete_attachment( $attachment, true );
			return new WP_Error( 'image_meta' );
		}
		$urls = lasery_xtool_source_image_urls( $post_id );
		if ( $urls && $url === $urls[0] ) {
			lasery_xtool_set_source_thumbnail( $post_id, $url, $attachment );
		}
		return $attachment;
	} finally {
		if ( $saved_path && file_exists( $saved_path ) ) { @unlink( $saved_path ); }
		if ( file_exists( $source ) ) { @unlink( $source ); }
		if ( file_exists( $output ) ) { @unlink( $output ); }
	}
}
