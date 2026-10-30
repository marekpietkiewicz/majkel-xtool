<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('article'); ?>>
        <h1><?php the_title(); ?></h1>
        <?php if (has_post_thumbnail()) : ?><?php the_post_thumbnail('large'); ?><?php endif; ?>
        <?php if (get_post_type() === 'lasery_product' && !has_post_thumbnail()) : ?>
            <?php $source_image = get_post_meta(get_the_ID(), '_lasery_source_image_url', true); ?>
            <?php if (is_string($source_image) && $source_image !== '') : ?><img src="<?php echo esc_url($source_image, ['https']); ?>" alt="" decoding="async"><?php endif; ?>
        <?php endif; ?>
        <div class="article-content"><?php the_content(); ?></div>
        <?php if (get_post_type() === 'lasery_product' && function_exists('lasery_xtool_render_order_form')) : ?>
            <?php lasery_xtool_render_order_form(get_the_ID()); ?>
        <?php endif; ?>
        <?php wp_link_pages(); ?>
    </article>
<?php endwhile; ?>
<?php get_footer(); ?>
