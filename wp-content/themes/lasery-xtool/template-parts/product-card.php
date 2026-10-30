<?php
$is_product = get_post_type() === 'lasery_product';
?>
<article <?php post_class('card'); ?>>
    <a class="card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('medium_large'); ?>
        <?php else : ?>
            <span class="card-placeholder"><?php echo $is_product ? esc_html__('xTool', 'lasery-xtool') : esc_html(get_bloginfo('name')); ?></span>
        <?php endif; ?>
    </a>
    <div class="card-body">
        <?php if ($is_product) : ?><p class="card-label"><?php esc_html_e('PRODUKT XTOOL', 'lasery-xtool'); ?></p><?php endif; ?>
        <?php if (is_front_page()) : ?><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><?php else : ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php endif; ?>
        <p class="card-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 20)); ?></p>
        <div class="card-actions">
            <a class="details-link" href="<?php the_permalink(); ?>"><?php esc_html_e('Zobacz szczegóły', 'lasery-xtool'); ?> <span aria-hidden="true">↗</span></a>
            <?php if ($is_product && function_exists('lasery_xtool_render_order_form')) : ?><a class="purchase-link" href="<?php echo esc_url(get_permalink() . '#zamow'); ?>"><?php esc_html_e('Zamów', 'lasery-xtool'); ?> <span aria-hidden="true">↗</span></a><?php endif; ?>
        </div>
    </div>
</article>
