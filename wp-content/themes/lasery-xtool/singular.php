<?php get_header(); ?>
<div class="wrap page-wrap">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('prose'); ?>>
            <h1><?php the_title(); ?></h1>
            <?php if (has_post_thumbnail()) : ?><?php the_post_thumbnail('large'); ?><?php endif; ?>
            <?php the_content(); ?>
            <?php wp_link_pages(); ?>
        </article>
    <?php endwhile; ?>
</div>
<?php get_footer(); ?>
