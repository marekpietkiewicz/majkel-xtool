<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('article'); ?>>
        <h1><?php the_title(); ?></h1>
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
                        <img src="<?php echo esc_url($images[0]['large']); ?>" alt="<?php the_title_attribute(); ?>" decoding="async">
                    </a>
                    <?php if (count($images) > 1) : ?>
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
        <div class="article-content"><?php the_content(); ?></div>
        <?php if (get_post_type() === 'lasery_product' && function_exists('lasery_xtool_render_order_form')) : ?>
            <?php lasery_xtool_render_order_form(get_the_ID()); ?>
        <?php endif; ?>
        <?php wp_link_pages(); ?>
    </article>
<?php endwhile; ?>
<?php get_footer(); ?>
