<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('article'); ?>>
        <h1><?php echo esc_html(get_post_type() === 'lasery_product' ? lasery_xtool_display_product_title(get_the_ID()) : get_the_title()); ?></h1>
        <div class="product-layout">
        <?php if (get_post_type() === 'lasery_product') : ?>
            <?php
            $gallery_items = [];
            $thumbnail_id = get_post_thumbnail_id();
            if ($thumbnail_id) {
                $gallery_items[] = ['id' => $thumbnail_id, 'url' => ''];
            }
            if (function_exists('lasery_xtool_product_gallery_items')) {
                foreach (lasery_xtool_product_gallery_items(get_the_ID()) as $item) {
                    if (!$item['id']) {
                        continue;
                    }
                    if ($item['id'] && $item['id'] === $thumbnail_id) {
                        continue;
                    }
                    $gallery_items[] = $item;
                }
            }
            $images = [];
            foreach ($gallery_items as $item) {
                $full = $item['id'] ? wp_get_attachment_image_url($item['id'], 'full') : $item['url'];
                $large = $item['id'] ? wp_get_attachment_image_url($item['id'], 'large') : $item['url'];
                if ($full && $large) {
                    $images[] = ['id' => $item['id'], 'full' => $full, 'large' => $large];
                }
            }
            ?>
            <?php if ($images) : ?>
                <div class="product-gallery" data-product-gallery aria-label="<?php esc_attr_e('Zdjęcia produktu', 'lasery-xtool'); ?>">
                    <a class="product-gallery-main" data-gallery-main href="<?php echo esc_url($images[0]['full']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Zdjęcie produktu %d', 'lasery-xtool'), 1)); ?>">
                        <img src="<?php echo esc_url($images[0]['large']); ?>" alt="<?php echo esc_attr(lasery_xtool_display_product_title(get_the_ID())); ?>" decoding="async">
                    </a>
                    <?php if (count($images) > 1) : ?>
                        <div class="gallery-controls" data-gallery-controls hidden>
                            <button type="button" data-gallery-prev aria-label="<?php esc_attr_e('Poprzednie zdjęcie', 'lasery-xtool'); ?>">←</button>
                            <span data-gallery-count aria-live="polite">1 / <?php echo esc_html((string) count($images)); ?></span>
                            <button type="button" data-gallery-next aria-label="<?php esc_attr_e('Następne zdjęcie', 'lasery-xtool'); ?>">→</button>
                            <button type="button" class="gallery-play" data-gallery-pause data-label-pause="<?php esc_attr_e('Wstrzymaj', 'lasery-xtool'); ?>" data-label-play="<?php esc_attr_e('Odtwórz', 'lasery-xtool'); ?>"><?php esc_html_e('Odtwórz', 'lasery-xtool'); ?></button>
                        </div>
                        <div class="product-gallery-thumbnails" data-gallery-thumbnails>
                            <?php foreach ($images as $index => $image) : ?>
                                <a href="<?php echo esc_url($image['full']); ?>" data-gallery-item data-large="<?php echo esc_url($image['large']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Zdjęcie produktu %d', 'lasery-xtool'), $index + 1)); ?>">
                                    <?php if ($image['id']) : ?>
                                        <?php echo wp_get_attachment_image($image['id'], 'thumbnail', false, ['alt' => '', 'loading' => 'lazy']); ?>
                                    <?php else : ?>
                                        <img src="<?php echo esc_url($image['large'], ['https']); ?>" alt="" loading="lazy" decoding="async">
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('large'); ?>
        <?php endif; ?>
        <div class="article-content">
            <?php if (get_post_type() === 'lasery_product' && function_exists('lasery_xtool_product_description')) : ?>
                <?php echo apply_filters('the_content', wp_kses_post(lasery_xtool_product_description(get_the_ID(), lasery_xtool_interface_language()))); ?>
            <?php else : ?>
                <?php the_content(); ?>
            <?php endif; ?>
        </div>
        </div>
        <?php if (get_post_type() === 'lasery_product' && function_exists('lasery_xtool_render_order_form')) : ?>
            <?php lasery_xtool_render_order_form(get_the_ID()); ?>
        <?php endif; ?>
        <?php wp_link_pages(); ?>
    </article>
<?php endwhile; ?>
<?php get_footer(); ?>
