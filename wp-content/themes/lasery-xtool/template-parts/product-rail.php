<?php
/** Swipeable row of product tiles. Args: products (WP_Post[]). */
global $post;
?>
<div class="swiper product-rail" data-rail data-label-prev="<?php esc_attr_e('Poprzedni produkt', 'lasery-xtool'); ?>" data-label-next="<?php esc_attr_e('Następny produkt', 'lasery-xtool'); ?>">
    <div class="swiper-wrapper">
        <?php foreach ($args['products'] as $post) : setup_postdata($post); ?>
            <div class="swiper-slide"><?php get_template_part('template-parts/product-card', null, ['rail' => true]); ?></div>
        <?php endforeach; wp_reset_postdata(); ?>
    </div>
</div>
