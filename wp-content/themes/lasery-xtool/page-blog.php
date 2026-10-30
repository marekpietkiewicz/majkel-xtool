<?php
/** Blog listing for the page with the slug "blog", in the current language. */
get_header();
$paged = isset($_GET['strona']) && is_string($_GET['strona']) ? max(1, absint($_GET['strona'])) : 1;
$posts = lasery_xtool_blog_query(['posts_per_page' => 9, 'paged' => $paged]);
?>
<div class="wrap">
    <header class="page-hero">
        <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('blog_eyebrow')); ?></p>
        <h1 class="page-title"><?php echo esc_html(lasery_xtool_text('blog_title')); ?></h1>
        <p class="section-lead"><?php echo esc_html(lasery_xtool_text('blog_description')); ?></p>
    </header>
    <?php if ($posts->have_posts()) : ?>
        <div class="post-grid">
            <?php while ($posts->have_posts()) : $posts->the_post(); ?>
                <?php get_template_part('template-parts/post-card', null, ['heading' => 'h2', 'featured' => $paged === 1 && $posts->current_post === 0]); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php if ($posts->max_num_pages > 1) : ?>
            <nav class="pagination" aria-label="<?php esc_attr_e('Strony bloga', 'lasery-xtool'); ?>">
                <?php echo wp_kses_post(paginate_links(['base' => esc_url_raw(add_query_arg('strona', '%#%')), 'format' => '', 'current' => $paged, 'total' => $posts->max_num_pages, 'prev_text' => '←', 'next_text' => '→'])); ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Pierwsze artykuły pojawią się wkrótce.', 'lasery-xtool'); ?></p>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
