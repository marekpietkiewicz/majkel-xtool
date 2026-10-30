<?php get_header(); ?>
<?php $is_catalogue = is_post_type_archive('lasery_product'); ?>
<div class="wrap">
    <header class="page-hero">
        <?php if ($is_catalogue) : ?>
            <?php
            $kind = lasery_xtool_requested_kind();
            $archive = get_post_type_archive_link('lasery_product');
            $filters = ['' => __('Wszystkie', 'lasery-xtool'), 'device' => __('Urządzenia', 'lasery-xtool'), 'accessory' => __('Akcesoria', 'lasery-xtool')];
            ?>
            <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('products_eyebrow')); ?></p>
            <h1 class="page-title"><?php esc_html_e('Produkty xTool', 'lasery-xtool'); ?></h1>
            <nav class="filter-chips" aria-label="<?php esc_attr_e('Rodzaj produktów', 'lasery-xtool'); ?>">
                <?php foreach ($filters as $value => $label) : ?>
                    <a class="chip" href="<?php echo esc_url($value === '' ? $archive : add_query_arg('kind', $value, $archive)); ?>"<?php if ($kind === $value) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
        <?php elseif (is_archive()) : ?>
            <h1 class="page-title"><?php the_archive_title(); ?></h1>
            <?php the_archive_description('<div class="section-lead">', '</div>'); ?>
        <?php elseif (is_search()) : ?>
            <h1 class="page-title"><?php echo get_search_query(false) === '' ? esc_html__('Szukaj', 'lasery-xtool') : sprintf(esc_html__('Wyniki wyszukiwania: %s', 'lasery-xtool'), esc_html(get_search_query(false))); ?></h1>
            <form class="search-page-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="search" name="s" value="<?php echo esc_attr(get_search_query(false)); ?>" aria-label="<?php esc_attr_e('Szukaj', 'lasery-xtool'); ?>" placeholder="<?php esc_attr_e('Szukaj urządzeń, akcesoriów i artykułów', 'lasery-xtool'); ?>">
                <input type="hidden" name="lang" value="<?php echo esc_attr(lasery_xtool_interface_language()); ?>">
                <button class="btn btn-primary" type="submit"><?php esc_html_e('Szukaj', 'lasery-xtool'); ?></button>
            </form>
        <?php else : ?>
            <h1 class="page-title"><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <?php endif; ?>
    </header>
    <?php if (is_search()) : ?>
        <?php
        // The same index as the instant search, so both find the same products and articles.
        $matches = lasery_xtool_search_matches(get_search_query(false));
        $groups = [
            'p' => ['lasery_product', __('Produkty', 'lasery-xtool'), 'product-grid', 'product-card'],
            'a' => ['post', __('Artykuły', 'lasery-xtool'), 'post-grid', 'post-card'],
        ];
        ?>
        <div class="search-page-results">
            <?php foreach ($groups as $type => [$post_type, $heading, $grid, $card]) : ?>
                <?php $ids = array_column(array_filter($matches, fn($entry) => $entry[0] === $type), 7); ?>
                <?php if ($ids) : ?>
                    <section class="search-page-section">
                        <h2 class="section-title section-title--sm"><?php echo esc_html($heading); ?></h2>
                        <div class="<?php echo esc_attr($grid); ?>">
                            <?php foreach (get_posts(['post_type' => $post_type, 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => -1]) as $post) : setup_postdata($post); ?>
                                <?php get_template_part('template-parts/' . $card); ?>
                            <?php endforeach; wp_reset_postdata(); ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$matches && get_search_query(false) !== '') : ?>
                <p class="empty-state"><?php printf(esc_html__('Brak wyników dla „%s”.', 'lasery-xtool'), esc_html(get_search_query(false))); ?></p>
            <?php endif; ?>
        </div>
    <?php elseif (have_posts()) : ?>
        <div class="product-grid product-grid--catalogue">
            <?php while (have_posts()) : the_post(); ?>
                <?php if (get_post_type() === 'lasery_product') : ?>
                    <?php get_template_part('template-parts/product-card', null, ['heading' => 'h2']); ?>
                <?php else : ?>
                    <?php get_template_part('template-parts/post-card', null, ['heading' => 'h2']); ?>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
        <div class="pagination"><?php the_posts_pagination(); ?></div>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Brak treści do wyświetlenia.', 'lasery-xtool'); ?></p>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
