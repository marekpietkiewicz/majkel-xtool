<?php
/** Run with LASERY_XTOOL_INTEGRATION_TEST=1 and wp eval-file on disposable WordPress. */
if ('1' !== getenv('LASERY_XTOOL_INTEGRATION_TEST') || !defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('This test requires disposable WordPress.');
}
if (!defined('SAVEQUERIES')) {
    define('SAVEQUERIES', true);
}
global $wpdb;
$check = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    WP_CLI::log('PASS ' . $message);
};
$created = [];
$insert = static function ($args) use (&$created) {
    $id = wp_insert_post($args + ['post_status' => 'publish', 'post_date' => '2025-01-01 12:00:00'], true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    $created[] = $id;
    return $id;
};

try {
    $original = $insert(['post_title' => 'Older article with no language metadata']);
    $polish = $insert(['post_title' => 'Polish article', 'meta_input' => ['_lasery_language' => 'pl']]);
    add_post_meta($polish, '_lasery_language', 'pl');
    $english = $insert(['post_title' => 'English translation', 'meta_input' => [
        '_lasery_language' => 'en', '_lasery_translation_of' => $original,
    ]]);
    $protected = $insert(['post_title' => 'Protected translation', 'post_password' => 'fixture', 'meta_input' => [
        '_lasery_language' => 'de', '_lasery_translation_of' => $original,
    ]]);
    $draft = $insert(['post_title' => 'Unpublished translation', 'post_status' => 'draft', 'meta_input' => [
        '_lasery_language' => 'uk', '_lasery_translation_of' => $original,
    ]]);
    $product = $insert(['post_title' => 'Sitemap product', 'post_type' => 'lasery_product']);
    $hidden_product = $insert(['post_title' => 'Protected product', 'post_type' => 'lasery_product', 'post_password' => 'fixture']);
    foreach ([$original, $english, $protected, $draft] as $article) {
        add_post_meta($article, LASERY_XTOOL_ARTICLE_PRODUCTS, '1234');
    }

    $_GET['lang'] = 'pl';
    $args = ['post__in' => [$original, $polish, $english, $protected, $draft], 'posts_per_page' => 1];
    $first = lasery_xtool_blog_query($args);
    $second = lasery_xtool_blog_query($args + ['paged' => 2]);
    $check([$polish] === wp_list_pluck($first->posts, 'ID') && [$original] === wp_list_pluck($second->posts, 'ID')
        && 2 === (int) $first->found_posts && 2 === $first->max_num_pages,
        'Polish pagination includes legacy posts and orders tied dates consistently');
    $check(!str_contains($first->request, 'JOIN') && str_contains($first->request, 'NOT EXISTS'),
        'Polish listing avoids multiplying postmeta rows with joins');

    $related = $args + ['meta_query' => [['key' => LASERY_XTOOL_ARTICLE_PRODUCTS, 'value' => '1234']]];
    $check([$original] === wp_list_pluck(lasery_xtool_blog_query($related)->posts, 'ID'),
        'a Polish product relation still filters public posts');
    $_GET['lang'] = 'en';
    $check([$english] === wp_list_pluck(lasery_xtool_blog_query($related)->posts, 'ID'),
        'the product relation works in another language');

    wp_cache_flush_runtime();
    $wpdb->queries = [];
    $urls = array_map('get_permalink', lasery_xtool_post_translations($original));
    $check($urls === ['pl' => get_permalink($original), 'en' => get_permalink($english)]
        && str_ends_with($urls['en'], 'lang=en'), 'alternates include only published and unprotected translations');

    $registry = wp_sitemaps_get_server()->registry;
    wp_cache_flush_runtime();
    $wpdb->queries = [];
    $sitemap = $registry->get_provider('posts')->get_url_list(1, 'post');
    $metadata_reads = array_filter($wpdb->queries, static fn($entry) => str_contains($entry[0], "FROM {$wpdb->postmeta}"));
    $check(1 === count($metadata_reads), 'the post sitemap reads post metadata in one batch');
    $locations = array_column($sitemap, 'loc');
    $check(in_array($urls['pl'], $locations, true) && in_array($urls['en'], $locations, true)
        && !in_array(get_permalink($protected), $locations, true) && !in_array(get_permalink($draft), $locations, true),
        'post sitemap preserves language URLs and excludes private content');

    wp_cache_flush_runtime();
    $wpdb->queries = [];
    $languages = $registry->get_provider('languages')->get_url_list(1);
    $metadata_reads = array_filter($wpdb->queries, static fn($entry) => str_contains($entry[0], "FROM {$wpdb->postmeta}"));
    $lastmod_reads = array_filter($wpdb->queries, static fn($entry) => str_contains($entry[0], 'MAX(post_modified_gmt)'));
    $check(!$metadata_reads && 2 === count($lastmod_reads),
        'language sitemap skips unused metadata and reads each last-modified date once');
    $locations = array_column($languages, 'loc');
    $check(in_array(add_query_arg('lang', 'en', get_permalink($product)), $locations, true)
        && !in_array(add_query_arg('lang', 'en', get_permalink($hidden_product)), $locations, true),
        'language sitemap includes public product translations only');
} finally {
    foreach (array_reverse($created) as $id) {
        wp_delete_post($id, true);
    }
}
