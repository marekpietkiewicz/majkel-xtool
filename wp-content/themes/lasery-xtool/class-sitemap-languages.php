<?php
/**
 * Sitemap of the language versions WordPress does not list: the home page, pages and products in
 * the languages other than Polish (the Polish ones are in its own sitemaps) and the catalogue in
 * every language. Articles have their own address per language and are all in the posts sitemap.
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
        $max = wp_sitemaps_get_max_urls($this->object_type);
        return array_slice($this->urls(), ($page_num - 1) * $max, $max);
    }

    public function get_max_num_pages($object_subtype = '') {
        return (int) ceil(count($this->urls()) / wp_sitemaps_get_max_urls($this->object_type));
    }

    /** Every entry, built once per request. */
    private function urls() {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $list = [];
        // The catalogue changes with its products, the home page with its products and articles.
        $products = lasery_xtool_newest_change('lasery_product');
        $articles = lasery_xtool_newest_change('post');
        $home = max($products, $articles);
        $front = get_option('show_on_front') === 'page' ? get_post(get_option('page_on_front')) : null;
        foreach (array_keys(lasery_xtool_languages()) as $language) {
            foreach (['', 'device', 'accessory'] as $kind) {
                $url = lasery_xtool_catalogue_url($language, $kind);
                if ($url !== '') {
                    $list[] = ['loc' => $url] + ($products !== '' ? ['lastmod' => $products] : []);
                }
            }
            if ($language !== 'pl' && (!$front || $front->post_password === '')) {
                $list[] = ['loc' => add_query_arg('lang', $language, home_url('/'))] + ($home !== '' ? ['lastmod' => $home] : []);
            }
        }
        foreach (['page', 'lasery_product'] as $post_type) {
            // The same pages as WordPress's sitemap (e.g. without the cart).
            $args = apply_filters('wp_sitemaps_posts_query_args', [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'orderby' => 'ID',
                'order' => 'ASC',
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ], $post_type);
            foreach (get_posts($args) as $post) {
                $lastmod = lasery_xtool_page_lastmod($post, $articles);
                foreach (lasery_xtool_post_language_urls($post) as $language => $url) {
                    if ($language !== 'pl') {
                        $list[] = ['loc' => $url, 'lastmod' => $lastmod];
                    }
                }
            }
        }
        return $list;
    }
}
