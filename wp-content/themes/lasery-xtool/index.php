<?php get_header(); ?>
<header class="page-heading">
    <?php if (is_post_type_archive('lasery_product')) : ?>
        <h1><?php esc_html_e('Produkty xTool', 'lasery-xtool'); ?></h1>
    <?php elseif (is_archive()) : ?>
        <h1><?php the_archive_title(); ?></h1>
        <?php the_archive_description('<div class="intro">', '</div>'); ?>
    <?php elseif (is_search()) : ?>
        <h1><?php printf(esc_html__('Wyniki wyszukiwania: %s', 'lasery-xtool'), esc_html(get_search_query())); ?></h1>
    <?php else : ?>
        <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <?php if (get_bloginfo('description')) : ?><p class="intro"><?php bloginfo('description'); ?></p><?php endif; ?>
    <?php endif; ?>
</header>
<?php if (have_posts()) : ?>
    <div class="grid">
        <?php while (have_posts()) : the_post(); ?>
            <?php get_template_part('template-parts/product-card'); ?>
        <?php endwhile; ?>
    </div>
    <div class="pagination"><?php the_posts_pagination(); ?></div>
<?php else : ?>
    <p><?php esc_html_e('Brak treści do wyświetlenia.', 'lasery-xtool'); ?></p>
<?php endif; ?>
<?php get_footer(); ?>
