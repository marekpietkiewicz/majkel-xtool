<?php
/**
 * Sitemap of the language versions WordPress does not list: pages and products in the languages
 * other than Polish (the Polish ones are in its own sitemaps) and the catalogue in every language.
 * Articles have their own address per language and are all in the posts sitemap.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class Lasery_XTool_Sitemap_Languages extends WP_Sitemaps_Provider {
    public function __construct() {
        $this->name = 'languages';
        $this->object_type = 'languages';
    }

    public function get_url_list($page_num, $object_subtype = '') {
        if ($page_num > 1) {
            return [];
        }
        $catalogue = remove_query_arg('lang', get_post_type_archive_link('lasery_product'));
        $translated = [home_url('/')];
        foreach (['page', 'lasery_product'] as $post_type) {
            // The same pages as WordPress's sitemap (e.g. without the cart).
            $args = apply_filters('wp_sitemaps_posts_query_args', ['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC'], $post_type);
            foreach (get_posts(['posts_per_page' => -1, 'fields' => 'ids'] + $args) as $id) {
                $translated[] = remove_query_arg('lang', get_permalink($id));
            }
        }
        $list = [];
        foreach (array_keys(lasery_xtool_languages()) as $language) {
            foreach ([$catalogue, add_query_arg('kind', 'device', $catalogue), add_query_arg('kind', 'accessory', $catalogue)] as $url) {
                $list[] = ['loc' => add_query_arg('lang', $language, $url)];
            }
            if ($language !== 'pl') {
                foreach (array_unique($translated) as $url) {
                    $list[] = ['loc' => add_query_arg('lang', $language, $url)];
                }
            }
        }
        return $list;
    }

    public function get_max_num_pages($object_subtype = '') {
        return 1;
    }
}
