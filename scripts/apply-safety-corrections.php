<?php
/** Review by default; set LASERY_APPLY_SAFETY_CORRECTIONS=1 to update the identified posts. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}
$manifest = json_decode( file_get_contents( __DIR__ . '/blog/safety-corrections.json' ), true );
if ( ! is_array( $manifest ) || ! $manifest ) {
    WP_CLI::error( 'Safety correction manifest is missing or invalid.' );
}
$changes = [];
foreach ( $manifest as $seed => $pairs ) {
    if ( ! is_string( $seed ) || ! is_array( $pairs ) || ! $pairs ) {
        WP_CLI::error( 'Invalid correction entry.' );
    }
    $posts = get_posts( [
        'post_type' => 'post', 'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
        'meta_key' => '_lasery_seed', 'meta_value' => $seed, 'posts_per_page' => -1,
    ] );
    if ( count( $posts ) !== 1 ) {
        WP_CLI::error( "Expected exactly one post for {$seed}; found " . count( $posts ) . '.' );
    }
    $post = $posts[0];
    $original = $post->post_content;
    $content = $original;
    $replaced = 0;
    foreach ( $pairs as $pair ) {
        if ( ! is_array( $pair ) || count( $pair ) !== 2 || ! is_string( $pair[0] ) || ! is_string( $pair[1] ) || $pair[0] === '' || $pair[1] === '' ) {
            WP_CLI::error( "Invalid correction pair for {$seed}." );
        }
        [ $old, $new ] = array_map( 'esc_html', $pair );
        $old_count = substr_count( $content, $old );
        $new_count = substr_count( $content, $new );
        if ( $old_count === 1 && $new_count === 0 ) {
            $content = str_replace( $old, $new, $content );
            ++$replaced;
        } elseif ( $old_count !== 0 || $new_count !== 1 ) {
            WP_CLI::error( "Cannot safely identify a correction in {$seed} (post {$post->ID}); no posts were updated." );
        }
    }
    $changes[] = [ 'id' => $post->ID, 'seed' => $seed, 'before' => $original, 'after' => $content, 'count' => $replaced ];
    WP_CLI::log( "{$seed} (post {$post->ID}): {$replaced} correction(s)." );
}
if ( getenv( 'LASERY_APPLY_SAFETY_CORRECTIONS' ) !== '1' ) {
    WP_CLI::success( 'Preview only. Set LASERY_APPLY_SAFETY_CORRECTIONS=1 to apply.' );
    return;
}

global $wpdb;
$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->posts ) );
if ( strtoupper( (string) $engine ) !== 'INNODB' ) {
    WP_CLI::error( 'Atomic updates require an InnoDB posts table; no posts were updated.' );
}
if ( $wpdb->query( 'START TRANSACTION' ) === false ) {
    WP_CLI::error( 'Could not start the transaction; no posts were updated.' );
}
$updated = [];
foreach ( $changes as $change ) {
    if ( $change['count'] === 0 ) {
        continue;
    }
    // Compare the entire stored content in the update itself to avoid overwriting concurrent edits.
    $query = $wpdb->prepare(
        "UPDATE {$wpdb->posts} SET post_content = %s, post_modified = %s, post_modified_gmt = %s WHERE ID = %d AND BINARY post_content = BINARY %s",
        $change['after'], current_time( 'mysql' ), current_time( 'mysql', true ), $change['id'], $change['before']
    );
    if ( $wpdb->query( $query ) !== 1 ) {
        $wpdb->query( 'ROLLBACK' );
        WP_CLI::error( "Post {$change['id']} changed during the update; all corrections were rolled back." );
    }
    $updated[] = $change['id'];
}
if ( $wpdb->query( 'COMMIT' ) === false ) {
    $wpdb->query( 'ROLLBACK' );
    WP_CLI::error( 'Could not commit the transaction.' );
}
foreach ( $updated as $id ) {
    clean_post_cache( $id );
}
if ( $updated ) {
    if ( function_exists( 'lasery_xtool_search_refresh' ) ) {
        lasery_xtool_search_refresh();
    }
    do_action( 'litespeed_purge', '*' );
}
WP_CLI::success( 'Updated ' . count( $updated ) . ' posts.' );
