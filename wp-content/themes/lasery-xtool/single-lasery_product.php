<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $post_id = get_the_ID();
    $title = lasery_xtool_display_product_title($post_id);
    $images = lasery_xtool_product_images($post_id);
    $price = lasery_xtool_price_html($post_id);
    $is_accessory = function_exists('lasery_xtool_is_accessory') && lasery_xtool_is_accessory($post_id);
    $has_demo = function_exists('lasery_xtool_has_demo') && lasery_xtool_has_demo($post_id);
    $home = lasery_xtool_language_url(home_url('/'));
    $archive = get_post_type_archive_link('lasery_product');
    $description = function_exists('lasery_xtool_product_description') ? lasery_xtool_product_description($post_id, lasery_xtool_interface_language()) : get_the_content();
    // A machine lists its accessories, an accessory the machines it fits (set in the panel).
    $related_query = function_exists('lasery_xtool_related_meta_query') ? lasery_xtool_related_meta_query($post_id) : null;
    $related = $related_query ? lasery_xtool_products($is_accessory ? 'device' : 'accessory', -1, ['post__not_in' => [$post_id], 'meta_query' => $related_query]) : [];
    $articles_query = function_exists('lasery_xtool_product_articles_meta_query') ? lasery_xtool_product_articles_meta_query($post_id) : null;
    $articles = $articles_query ? lasery_xtool_blog_query(['posts_per_page' => 3, 'no_found_rows' => true, 'meta_query' => $articles_query]) : null;
    ?>
    <div class="wrap">
        <nav class="breadcrumbs" aria-label="<?php esc_attr_e('Ścieżka nawigacji', 'lasery-xtool'); ?>">
            <ol>
                <li><a href="<?php echo esc_url($home); ?>"><?php esc_html_e('Strona główna', 'lasery-xtool'); ?></a></li>
                <?php if ($archive) : ?><li><a href="<?php echo esc_url(add_query_arg('kind', $is_accessory ? 'accessory' : 'device', $archive)); ?>"><?php echo esc_html($is_accessory ? __('Akcesoria', 'lasery-xtool') : __('Urządzenia', 'lasery-xtool')); ?></a></li><?php endif; ?>
                <li aria-current="page"><?php echo esc_html($title); ?></li>
            </ol>
        </nav>

        <article <?php post_class('product'); ?>>
            <div class="product-hero">
                <div class="product-gallery">
                    <?php if ($images) : ?>
                        <div class="swiper gallery-main" data-gallery-main data-label-prev="<?php esc_attr_e('Poprzednie zdjęcie', 'lasery-xtool'); ?>" data-label-next="<?php esc_attr_e('Następne zdjęcie', 'lasery-xtool'); ?>">
                            <div class="swiper-wrapper">
                                <?php foreach ($images as $index => $image) : ?>
                                    <div class="swiper-slide">
                                        <a class="gallery-slide" href="<?php echo esc_url($image[1]); ?>" data-pswp-width="<?php echo esc_attr($image[2]); ?>" data-pswp-height="<?php echo esc_attr($image[3]); ?>" aria-label="<?php echo esc_attr(sprintf(__('Powiększ zdjęcie %d', 'lasery-xtool'), $index + 1)); ?>">
                                            <?php echo wp_get_attachment_image($image[0], 'large', false, [
                                                'alt' => sprintf(__('%1$s – zdjęcie %2$d', 'lasery-xtool'), $title, $index + 1),
                                                'sizes' => '(min-width: 1280px) 585px, (min-width: 960px) 45vw, 81vw',
                                                'loading' => $index === 0 ? 'eager' : 'lazy',
                                                'fetchpriority' => $index === 0 ? 'high' : 'auto',
                                            ]); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php echo lasery_xtool_badges_html($post_id); ?>
                            <span class="gallery-zoom-hint" aria-hidden="true"><?php echo lasery_xtool_icon('zoom'); ?></span>
                            <?php if (count($images) > 1) : ?>
                                <div class="gallery-controls" data-slider-controls hidden>
                                    <button type="button" class="icon-btn" data-slider-prev><?php echo lasery_xtool_icon('chevron-left'); ?></button>
                                    <span class="gallery-count" data-slider-pagination></span>
                                    <button type="button" class="icon-btn" data-slider-next><?php echo lasery_xtool_icon('chevron-right'); ?></button>
                                    <button type="button" class="icon-btn" data-autoplay-toggle data-label-pause="<?php esc_attr_e('Wstrzymaj', 'lasery-xtool'); ?>" data-label-play="<?php esc_attr_e('Odtwórz', 'lasery-xtool'); ?>"><?php echo lasery_xtool_icon('pause'); ?><?php echo lasery_xtool_icon('play'); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($images) > 1) : ?>
                            <div class="swiper gallery-thumbs" data-gallery-thumbs>
                                <div class="swiper-wrapper">
                                    <?php foreach ($images as $index => $image) : ?>
                                        <div class="swiper-slide">
                                            <button type="button" class="gallery-thumb" aria-label="<?php echo esc_attr(sprintf(__('Zdjęcie produktu %d', 'lasery-xtool'), $index + 1)); ?>"<?php if ($index === 0) : ?> aria-current="true"<?php endif; ?>>
                                                <?php echo wp_get_attachment_image($image[0], 'thumbnail', false, ['alt' => '', 'loading' => 'lazy']); ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="gallery-main gallery-main--empty"><span class="product-card-placeholder" aria-hidden="true">xTool</span></div>
                    <?php endif; ?>
                </div>

                <div class="product-summary">
                    <p class="eyebrow"><?php echo esc_html(lasery_xtool_kind_label($post_id)); ?></p>
                    <h1 class="product-title"><?php echo esc_html($title); ?></h1>
                    <div class="price-box">
                        <p class="price price--large"><?php echo $price !== '' ? $price : esc_html__('Cena na zapytanie', 'lasery-xtool'); ?></p>
                        <p class="price-box-note"><?php esc_html_e('Cena orientacyjna z oficjalnego sklepu xTool. Przed płatnością potwierdzimy ostateczną kwotę i koszt dostawy.', 'lasery-xtool'); ?></p>
                    </div>
                    <div class="product-actions<?php echo $has_demo ? ' product-actions--single' : ''; ?>">
                        <a class="btn btn-primary btn-lg" href="#zamow"><?php esc_html_e('Zamów', 'lasery-xtool'); ?><?php echo lasery_xtool_icon('arrow-right'); ?></a>
                        <?php if (!$has_demo) : ?>
                            <a class="btn btn-secondary btn-lg" href="<?php echo esc_url($home . '#demo'); ?>"><?php echo lasery_xtool_icon('video'); ?><?php echo esc_html(lasery_xtool_text('hero_demo_button')); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($has_demo) : ?>
                        <div class="demo-callout">
                            <span class="demo-callout-icon" aria-hidden="true"><?php echo lasery_xtool_icon('video'); ?></span>
                            <div>
                                <h2 class="demo-callout-title"><?php echo esc_html(lasery_xtool_text('demo_callout_title')); ?></h2>
                                <p><?php echo esc_html(lasery_xtool_text('demo_callout_text')); ?></p>
                                <div class="demo-callout-actions">
                                    <a class="btn btn-accent" href="<?php echo esc_url(lasery_xtool_demo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo lasery_xtool_icon('calendar'); ?><?php echo esc_html(lasery_xtool_text('demo_callout_button')); ?><?php echo lasery_xtool_new_tab_hint(); ?></a>
                                    <a class="demo-callout-link" href="<?php echo esc_url($home . '#demo'); ?>"><?php esc_html_e('Jak wygląda demo?', 'lasery-xtool'); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <ul class="assurances">
                        <li><?php echo lasery_xtool_icon('check'); ?><?php esc_html_e('Zamówienie bez zakładania konta', 'lasery-xtool'); ?></li>
                        <li><?php echo lasery_xtool_icon('check'); ?><?php esc_html_e('Płacisz dopiero po potwierdzeniu ceny i dostawy', 'lasery-xtool'); ?></li>
                        <li><?php echo lasery_xtool_icon('check'); ?><?php esc_html_e('Oryginalne urządzenia z oficjalnego sklepu xTool', 'lasery-xtool'); ?></li>
                    </ul>
                </div>
            </div>

            <?php // A description of empty paragraphs (e.g. "&nbsp;") gets no section. ?>
            <?php if ((function_exists('lasery_xtool_plain_text') ? lasery_xtool_plain_text($description) : trim(wp_strip_all_tags($description))) !== '') : ?>
                <section class="product-section" aria-labelledby="product-features-heading">
                    <h2 id="product-features-heading" class="section-title section-title--sm"><?php esc_html_e('Najważniejsze cechy', 'lasery-xtool'); ?></h2>
                    <div class="prose product-description"><?php echo lasery_xtool_keep_together_html(apply_filters('the_content', wp_kses_post($description))); ?></div>
                </section>
            <?php endif; ?>
        </article>
    </div>

    <?php if (function_exists('lasery_xtool_render_order_form')) : ?>
        <div class="order-band">
            <div class="wrap"><?php lasery_xtool_render_order_form($post_id); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($related) : ?>
        <section class="section" aria-labelledby="related-heading" data-rail-section>
            <div class="wrap">
                <header class="section-head">
                    <h2 id="related-heading" class="section-title section-title--sm"><?php echo esc_html($is_accessory ? __('Pasuje do urządzeń', 'lasery-xtool') : __('Akcesoria do tego urządzenia', 'lasery-xtool')); ?></h2>
                    <div class="slider-buttons" data-slider-controls hidden>
                        <button type="button" class="icon-btn" data-slider-prev><?php echo lasery_xtool_icon('chevron-left'); ?></button>
                        <button type="button" class="icon-btn" data-slider-next><?php echo lasery_xtool_icon('chevron-right'); ?></button>
                    </div>
                </header>
                <?php get_template_part('template-parts/product-rail', null, ['products' => $related]); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($articles && $articles->have_posts()) : ?>
        <section class="section section--muted" aria-labelledby="product-articles-heading">
            <div class="wrap">
                <header class="section-head"><h2 id="product-articles-heading" class="section-title section-title--sm"><?php esc_html_e('Artykuły o tym produkcie', 'lasery-xtool'); ?></h2></header>
                <div class="post-grid post-grid--compact">
                    <?php while ($articles->have_posts()) : $articles->the_post(); ?>
                        <?php get_template_part('template-parts/post-card'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endwhile; ?>
<?php get_footer(); ?>
