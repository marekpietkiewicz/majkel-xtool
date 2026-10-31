<?php
/**
 * Create the initial blog articles in five languages (wp eval-file scripts/seed-blog.php).
 * Existing articles are left untouched, so edits made in the panel are kept.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}
if ( ! function_exists( 'lasery_xtool_post_language' ) ) {
    WP_CLI::error( 'Activate the lasery-xtool plugin first.' );
}
// Articles and covers belong to the first administrator unless --user is given.
if ( ! get_current_user_id() ) {
    $admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ] );
    wp_set_current_user( $admins ? (int) $admins[0] : 0 );
}
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$articles = require __DIR__ . '/blog/articles.php';
// Later articles have a file each: scripts/blog/articles/<key>.php.
foreach ( glob( __DIR__ . '/blog/articles/*.php' ) as $file ) {
    $articles[ basename( $file, '.php' ) ] = require $file;
}

/** Convert the compact article format into block editor markup. */
$to_blocks = static function ( $blocks ) {
    $html = [];
    $list = static function ( $tag, $items ) {
        $markup = '';
        foreach ( $items as $item ) {
            $markup .= "<!-- wp:list-item -->\n<li>" . esc_html( $item ) . "</li>\n<!-- /wp:list-item -->";
        }
        return '<' . $tag . ' class="wp-block-list">' . $markup . '</' . $tag . '>';
    };
    foreach ( $blocks as $block ) {
        switch ( $block[0] ) {
            case 'p':
                $html[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $block[1] ) . "</p>\n<!-- /wp:paragraph -->";
                break;
            case 'h2':
                $html[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $block[1] ) . "</h2>\n<!-- /wp:heading -->";
                break;
            case 'h3':
                $html[] = "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $block[1] ) . "</h3>\n<!-- /wp:heading -->";
                break;
            case 'ul':
                $html[] = "<!-- wp:list -->\n" . $list( 'ul', $block[1] ) . "\n<!-- /wp:list -->";
                break;
            case 'ol':
                $html[] = "<!-- wp:list {\"ordered\":true} -->\n" . $list( 'ol', $block[1] ) . "\n<!-- /wp:list -->";
                break;
            case 'table':
                $head = '<thead><tr>' . implode( '', array_map( static function ( $cell ) { return '<th>' . esc_html( $cell ) . '</th>'; }, $block[1] ) ) . '</tr></thead>';
                $rows = '';
                foreach ( $block[2] as $row ) {
                    $rows .= '<tr>' . implode( '', array_map( static function ( $cell ) { return '<td>' . esc_html( $cell ) . '</td>'; }, $row ) ) . '</tr>';
                }
                $html[] = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">" . $head . '<tbody>' . $rows . "</tbody></table></figure>\n<!-- /wp:table -->";
                break;
            case 'products':
                $html[] = "<!-- wp:shortcode -->\n[lasery_produkty xtool=\"" . preg_replace( '/[^0-9,]/', '', $block[1] ) . "\"]\n<!-- /wp:shortcode -->";
                break;
            default:
                WP_CLI::error( 'Unknown block type ' . $block[0] );
        }
    }
    return implode( "\n\n", $html );
};

$find = static function ( $key, $language ) {
    return get_posts( [ 'post_type' => 'post', 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ], 'meta_query' => [ [ 'key' => '_lasery_seed', 'value' => $key . ':' . $language ] ], 'fields' => 'ids', 'posts_per_page' => 1 ] );
};

foreach ( $articles as $key => $article ) {
    // The F2 event article needs a safety rewrite before it can be published again.
    if ( 'event-engraving' === $key ) {
        WP_CLI::log( 'event-engraving requires a safety rewrite; skipped.' );
        continue;
    }
    $cover = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_lasery_seed', 'meta_value' => 'cover:' . $key, 'fields' => 'ids', 'posts_per_page' => 1 ] );
    $cover = $cover ? $cover[0] : 0;
    if ( ! $cover ) {
        $file = wp_tempnam( $article['cover'] );
        copy( __DIR__ . '/blog/covers/' . $article['cover'], $file );
        $cover = media_handle_sideload( [ 'name' => $article['cover'], 'tmp_name' => $file ], 0, $article['languages']['pl']['title'] );
        if ( is_wp_error( $cover ) ) {
            wp_delete_file( $file );
            WP_CLI::error( 'Could not import the cover of ' . $key . ': ' . $cover->get_error_message() );
        }
        update_post_meta( $cover, '_lasery_seed', 'cover:' . $key );
        update_post_meta( $cover, '_wp_attachment_image_alt', $article['languages']['pl']['title'] );
    }
    $date = gmdate( 'Y-m-d H:i:s', time() - $article['days_ago'] * DAY_IN_SECONDS );
    $original = 0;
    foreach ( $article['languages'] as $language => $text ) {
        $existing = $find( $key, $language );
        if ( $existing ) {
            if ( 'pl' === $language ) {
                $original = $existing[0];
            } elseif ( $original ) {
                // Re-link a translation whose original was deleted and created again.
                update_post_meta( $existing[0], '_lasery_translation_of', $original );
            }
            WP_CLI::log( $key . ' / ' . $language . ' already exists; skipped.' );
            continue;
        }
        $meta = [ '_lasery_seed' => $key . ':' . $language, '_lasery_language' => $language ];
        if ( 'pl' !== $language ) {
            if ( ! $original ) {
                WP_CLI::error( 'The Polish original of ' . $key . ' is missing.' );
            }
            $meta['_lasery_translation_of'] = $original;
        }
        $post_id = wp_insert_post( wp_slash( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'post_title'     => $text['title'],
            'post_name'      => $text['slug'],
            'post_excerpt'   => $text['excerpt'],
            'post_content'   => $to_blocks( $text['blocks'] ),
            'post_date_gmt'  => $date,
            'post_date'      => get_date_from_gmt( $date ),
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'meta_input'     => $meta,
        ] ), true );
        if ( is_wp_error( $post_id ) ) {
            WP_CLI::error( 'Could not create ' . $key . ' / ' . $language . ': ' . $post_id->get_error_message() );
        }
        set_post_thumbnail( $post_id, $cover );
        $original = 'pl' === $language ? $post_id : $original;
        WP_CLI::success( 'Published ' . $key . ' / ' . $language . ': ' . get_permalink( $post_id ) );
    }
}
