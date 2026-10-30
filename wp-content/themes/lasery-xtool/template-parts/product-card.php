<?php
/** Product tile. Args: heading (h2|h3), featured (bool). */
$post_id = get_the_ID();
$heading = isset($args['heading']) && $args['heading'] === 'h2' ? 'h2' : 'h3';
$featured = !empty($args['featured']);
$permalink = get_permalink();
$price = lasery_xtool_price_html($post_id);
$summary = lasery_xtool_display_summary($post_id);
?>
<article class="product-card<?php echo $featured ? ' product-card--featured' : ''; ?>">
    <div class="product-card-media">
        <?php echo lasery_xtool_badges_html($post_id); ?>
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('large', [
                'alt' => '',
                'sizes' => $featured ? '(min-width: 960px) 560px, 100vw' : '(min-width: 960px) 380px, (min-width: 760px) 33vw, 50vw',
                'loading' => 'lazy',
            ]); ?>
        <?php else : ?>
            <span class="product-card-placeholder" aria-hidden="true">xTool</span>
        <?php endif; ?>
    </div>
    <div class="product-card-body">
        <p class="product-card-kind"><?php echo esc_html(lasery_xtool_kind_label($post_id)); ?></p>
        <<?php echo $heading; ?> class="product-card-title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html(lasery_xtool_display_product_title($post_id)); ?></a></<?php echo $heading; ?>>
        <?php if ($summary !== '') : ?><p class="product-card-summary"><?php echo esc_html($summary); ?></p><?php endif; ?>
        <div class="product-card-footer">
            <p class="price"><?php echo $price !== '' ? $price : esc_html__('Cena na zapytanie', 'lasery-xtool'); ?></p>
            <span class="product-card-arrow" aria-hidden="true"><?php echo lasery_xtool_icon('arrow-right'); ?></span>
        </div>
    </div>
</article>
