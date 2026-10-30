<?php
/** Blog listing for the page with the slug "blog", in the current language. */
get_header();
[$page_number, $blog_posts] = lasery_xtool_blog_listing();
?>
<div class="wrap">
    <header class="page-hero">
        <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('blog_eyebrow')); ?></p>
        <h1 class="page-title"><?php echo esc_html(lasery_xtool_text('blog_title')); ?></h1>
        <p class="section-lead"><?php echo esc_html(lasery_xtool_text('blog_description')); ?></p>
    </header>
    <?php if ($blog_posts->have_posts()) : ?>
        <div class="post-grid">
            <?php while ($blog_posts->have_posts()) : $blog_posts->the_post(); ?>
                <?php get_template_part('template-parts/post-card', null, ['heading' => 'h2', 'featured' => $page_number === 1 && $blog_posts->current_post === 0]); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php if ($blog_posts->max_num_pages > 1) : ?>
            <nav class="pagination" aria-label="<?php esc_attr_e('Strony bloga', 'lasery-xtool'); ?>">
                <div class="nav-links"><?php echo wp_kses_post(paginate_links(['base' => esc_url_raw(lasery_xtool_blog_url()) . '%_%', 'format' => '&strona=%#%', 'current' => $page_number, 'total' => $blog_posts->max_num_pages, 'prev_text' => '←', 'next_text' => '→'])); ?></div>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Pierwsze artykuły pojawią się wkrótce.', 'lasery-xtool'); ?></p>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
