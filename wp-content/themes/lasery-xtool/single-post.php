<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $post_id = get_the_ID();
    [$content, $toc] = lasery_xtool_article_toc(apply_filters('the_content', get_the_content()));
    $blog = lasery_xtool_blog_url();
    $home = lasery_xtool_language_url(home_url('/'));
    $related = lasery_xtool_blog_query(['posts_per_page' => 3, 'post__not_in' => [$post_id], 'no_found_rows' => true]);
    ?>
    <div class="reading-progress" aria-hidden="true"><span data-reading-progress></span></div>
    <article <?php post_class('article'); ?>>
        <div class="wrap">
            <nav class="breadcrumbs" aria-label="<?php esc_attr_e('Ścieżka nawigacji', 'lasery-xtool'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url($home); ?>"><?php esc_html_e('Strona główna', 'lasery-xtool'); ?></a></li>
                    <?php if ($blog !== '') : ?><li><a href="<?php echo esc_url($blog); ?>"><?php esc_html_e('Blog', 'lasery-xtool'); ?></a></li><?php endif; ?>
                    <li aria-current="page"><?php the_title(); ?></li>
                </ol>
            </nav>
            <header class="article-header">
                <p class="article-meta"><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(lasery_xtool_post_date($post_id)); ?></time> · <?php echo esc_html(lasery_xtool_reading_time($post_id)); ?></p>
                <h1 class="article-title"><?php the_title(); ?></h1>
                <p class="commercial-disclosure"><?php echo esc_html(lasery_xtool_purchase_info_texts(lasery_xtool_interface_language())['commercial_disclosure']); ?></p>
                <?php if (has_excerpt()) : ?><p class="article-lead"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
            </header>
            <?php if (has_post_thumbnail()) : ?>
                <figure class="article-cover"><?php the_post_thumbnail('full', ['alt' => '', 'sizes' => '(min-width: 1300px) 1240px, 100vw', 'loading' => 'eager', 'fetchpriority' => 'high']); ?></figure>
            <?php endif; ?>
            <div class="article-layout<?php echo count($toc) > 1 ? ' article-layout--toc' : ''; ?>">
                <?php if (count($toc) > 1) : ?>
                    <nav class="article-toc" aria-labelledby="article-toc-title">
                        <p class="article-toc-title" id="article-toc-title"><?php esc_html_e('Spis treści', 'lasery-xtool'); ?></p>
                        <ol>
                            <?php foreach ($toc as $item) : ?>
                                <li><a href="#<?php echo esc_attr($item[0]); ?>"><?php echo esc_html($item[1]); ?></a></li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                <div class="prose article-content"><?php echo $content; ?></div>
            </div>
            <aside class="article-cta">
                <div>
                    <p class="eyebrow eyebrow--light"><?php echo esc_html(lasery_xtool_text('demo_eyebrow')); ?></p>
                    <h2><?php echo esc_html(lasery_xtool_text('blog_cta_title')); ?></h2>
                    <p><?php echo esc_html(lasery_xtool_text('blog_cta_text')); ?></p>
                </div>
                <div class="article-cta-actions">
                    <a class="btn btn-accent btn-lg" href="<?php echo esc_url(lasery_xtool_demo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo lasery_xtool_icon('calendar'); ?><?php echo esc_html(lasery_xtool_text('demo_callout_button')); ?><?php echo lasery_xtool_new_tab_hint(); ?></a>
                    <a class="btn btn-light btn-lg" href="<?php echo esc_url($home . '#urzadzenia'); ?>"><?php echo esc_html(lasery_xtool_text('blog_products_button')); ?><?php echo lasery_xtool_icon('arrow-right'); ?></a>
                </div>
            </aside>
        </div>
    </article>
    <?php if ($related->have_posts()) : ?>
        <section class="section section--muted" aria-labelledby="related-posts-heading">
            <div class="wrap">
                <header class="section-head"><h2 id="related-posts-heading" class="section-title section-title--sm"><?php esc_html_e('Czytaj także', 'lasery-xtool'); ?></h2></header>
                <div class="post-grid post-grid--compact">
                    <?php while ($related->have_posts()) : $related->the_post(); ?>
                        <?php get_template_part('template-parts/post-card'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endwhile; ?>
<?php get_footer(); ?>
