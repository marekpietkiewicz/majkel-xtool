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
            <h1 class="page-title"><?php printf(esc_html__('Wyniki wyszukiwania: %s', 'lasery-xtool'), esc_html(get_search_query())); ?></h1>
        <?php else : ?>
            <h1 class="page-title"><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <?php endif; ?>
    </header>
    <?php if (have_posts()) : ?>
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
