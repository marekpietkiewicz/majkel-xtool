<?php
/**
 * Multilingual blog: every language version is a regular post with its own slug.
 * Translations point to the Polish original; posts without a language are Polish.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lasery_xtool_post_language( $post_id ) {
	$language = get_post_meta( $post_id, '_lasery_language', true );
	return is_string( $language ) && isset( lasery_xtool_description_languages()[ $language ] ) ? $language : 'pl';
}

/** The Polish original of a post, or the post itself. */
function lasery_xtool_post_original( $post_id ) {
	$original = absint( get_post_meta( $post_id, '_lasery_translation_of', true ) );
	return $original && 'post' === get_post_type( $original ) ? $original : (int) $post_id;
}

/** Language => post ID for every version of the article, including the given post. */
function lasery_xtool_post_translations( $post_id, $published_only = true ) {
	$original = lasery_xtool_post_original( $post_id );
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => $published_only ? 'publish' : array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'meta_key'       => '_lasery_translation_of',
			'meta_value'     => $original,
			'fields'         => 'ids',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
		)
	);
	$translations = array();
	if ( ! $published_only || 'publish' === get_post_status( $original ) ) {
		$translations[ lasery_xtool_post_language( $original ) ] = $original;
	}
	foreach ( $ids as $id ) {
		$translations[ lasery_xtool_post_language( $id ) ] = $id;
	}
	return $translations;
}

/** Limit a post query to one language. */
function lasery_xtool_language_meta_query( $language ) {
	$query = array( array( 'key' => '_lasery_language', 'value' => $language ) );
	if ( 'pl' === $language ) {
		$query = array( 'relation' => 'OR', $query[0], array( 'key' => '_lasery_language', 'compare' => 'NOT EXISTS' ) );
	}
	return $query;
}

// Post URLs carry their language, so the interface always matches the article.
add_filter( 'post_link', 'lasery_xtool_post_language_link', 10, 2 );
function lasery_xtool_post_language_link( $url, $post ) {
	return add_query_arg( 'lang', lasery_xtool_post_language( $post->ID ), $url );
}

add_action( 'template_redirect', 'lasery_xtool_redirect_post_language' );
function lasery_xtool_redirect_post_language() {
	if ( ! is_singular( 'post' ) || is_preview() ) {
		return;
	}
	$requested = isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
	if ( lasery_xtool_post_language( get_queried_object_id() ) !== $requested ) {
		wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
		exit;
	}
}

/** [lasery_produkty xtool="source IDs" ids="post IDs"] shows product cards inside an article. */
add_shortcode( 'lasery_produkty', 'lasery_xtool_products_shortcode' );
function lasery_xtool_products_shortcode( $attributes ) {
	$attributes = shortcode_atts( array( 'ids' => '', 'xtool' => '' ), $attributes, 'lasery_produkty' );
	$ids = array_filter( array_map( 'absint', explode( ',', $attributes['ids'] ) ) );
	foreach ( array_filter( array_map( 'trim', explode( ',', $attributes['xtool'] ) ), 'ctype_digit' ) as $source_id ) {
		$ids = array_merge( $ids, get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'meta_key' => LASERY_XTOOL_SOURCE_ID, 'meta_value' => $source_id, 'fields' => 'ids', 'posts_per_page' => 1 ) ) );
	}
	$products = $ids ? get_posts( array( 'post_type' => LASERY_XTOOL_PRODUCT_TYPE, 'post_status' => 'publish', 'post__in' => array_unique( $ids ), 'orderby' => 'post__in', 'posts_per_page' => 6 ) ) : array();
	if ( ! $products ) {
		return '';
	}
	global $post;
	$article = $post;
	ob_start();
	echo '<div class="inline-products">';
	foreach ( $products as $post ) {
		setup_postdata( $post );
		get_template_part( 'template-parts/product-card' );
	}
	echo '</div>';
	$post = $article;
	setup_postdata( $post );
	return ob_get_clean();
}

add_action( 'add_meta_boxes_post', 'lasery_xtool_language_box_register' );
function lasery_xtool_language_box_register() {
	add_meta_box( 'lasery-language', 'Język i tłumaczenia', 'lasery_xtool_language_box', 'post', 'side', 'high' );
}

function lasery_xtool_language_box( $post ) {
	wp_nonce_field( 'lasery_xtool_save_language', 'lasery_xtool_language_nonce' );
	$language = lasery_xtool_post_language( $post->ID );
	$languages = lasery_xtool_description_languages();
	?>
	<p><label for="lasery-post-language"><strong>Język artykułu</strong></label></p>
	<select id="lasery-post-language" name="lasery_language" class="widefat">
		<?php foreach ( $languages as $code => $label ) : ?>
			<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
	$original = lasery_xtool_post_original( $post->ID );
	if ( $original !== $post->ID ) {
		echo '<p>Tłumaczenie artykułu: <a href="' . esc_url( get_edit_post_link( $original ) ) . '">' . esc_html( get_the_title( $original ) ) . '</a></p>';
		return;
	}
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>Po zapisaniu można utworzyć tłumaczenia.</p>';
		return;
	}
	$translations = lasery_xtool_post_translations( $post->ID, false );
	echo '<p><strong>Tłumaczenia</strong></p><ul>';
	foreach ( $languages as $code => $label ) {
		if ( $code === $language ) {
			continue;
		}
		echo '<li>' . esc_html( $label ) . ': ';
		if ( isset( $translations[ $code ] ) ) {
			echo '<a href="' . esc_url( get_edit_post_link( $translations[ $code ] ) ) . '">' . esc_html( get_the_title( $translations[ $code ] ) ) . '</a> (' . esc_html( get_post_status( $translations[ $code ] ) ) . ')';
		} else {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=lasery_xtool_translate_post&post=' . $post->ID . '&language=' . $code ), 'lasery_xtool_translate_' . $post->ID );
			echo '<a class="button button-small" href="' . esc_url( $url ) . '">Utwórz tłumaczenie</a>';
		}
		echo '</li>';
	}
	echo '</ul><p class="description">Tłumaczenie powstaje jako szkic z kopią treści, zdjęcia i zajawki do przetłumaczenia.</p>';
}

add_action( 'save_post_post', 'lasery_xtool_save_post_language' );
function lasery_xtool_save_post_language( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lasery_xtool_language_nonce'], $_POST['lasery_language'] ) || ! is_string( $_POST['lasery_xtool_language_nonce'] ) || ! is_string( $_POST['lasery_language'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lasery_xtool_language_nonce'] ) ), 'lasery_xtool_save_language' ) ) {
		return;
	}
	$language = sanitize_key( wp_unslash( $_POST['lasery_language'] ) );
	if ( isset( lasery_xtool_description_languages()[ $language ] ) ) {
		update_post_meta( $post_id, '_lasery_language', $language );
	}
}

/** Create a draft copy in another language, linked to the Polish original. */
add_action( 'admin_post_lasery_xtool_translate_post', 'lasery_xtool_translate_post' );
function lasery_xtool_translate_post() {
	$source = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$language = isset( $_GET['language'] ) && is_string( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : '';
	check_admin_referer( 'lasery_xtool_translate_' . $source );
	if ( ! $source || 'post' !== get_post_type( $source ) || ! current_user_can( 'edit_post', $source ) || ! current_user_can( 'edit_posts' ) || ! isset( lasery_xtool_description_languages()[ $language ] ) ) {
		wp_die( 'Nie można utworzyć tłumaczenia.', '', array( 'response' => 403 ) );
	}
	$original = lasery_xtool_post_original( $source );
	$existing = lasery_xtool_post_translations( $original, false );
	if ( isset( $existing[ $language ] ) ) {
		wp_safe_redirect( get_edit_post_link( $existing[ $language ], 'raw' ) );
		exit;
	}
	$post = get_post( $original );
	$copy = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => 'post',
				'post_status'    => 'draft',
				'post_title'     => $post->post_title . ' (' . strtoupper( $language ) . ')',
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_category'  => wp_get_post_categories( $original ),
				'comment_status' => $post->comment_status,
				'meta_input'     => array( '_lasery_language' => $language, '_lasery_translation_of' => $original ),
			)
		),
		true
	);
	if ( is_wp_error( $copy ) ) {
		wp_die( 'Nie można utworzyć tłumaczenia.', '', array( 'response' => 500 ) );
	}
	$thumbnail = get_post_thumbnail_id( $original );
	if ( $thumbnail ) {
		set_post_thumbnail( $copy, $thumbnail );
	}
	wp_safe_redirect( get_edit_post_link( $copy, 'raw' ) );
	exit;
}

add_filter( 'manage_post_posts_columns', 'lasery_xtool_language_column' );
function lasery_xtool_language_column( $columns ) {
	return array_slice( $columns, 0, 2, true ) + array( 'lasery_language' => 'Język' ) + $columns;
}

add_action( 'manage_post_posts_custom_column', 'lasery_xtool_language_column_value', 10, 2 );
function lasery_xtool_language_column_value( $column, $post_id ) {
	if ( 'lasery_language' === $column ) {
		echo esc_html( strtoupper( lasery_xtool_post_language( $post_id ) ) );
	}
}
