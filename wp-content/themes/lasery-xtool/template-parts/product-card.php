<?php
/**
 * Product tile. Args: heading (h2|h3), featured (bool), rail (bool), position (int, place in a grid:
 * the first row loads at once and the first image, usually the largest content, first of all).
 */
$post_id = get_the_ID();
$heading = isset($args['heading']) && $args['heading'] === 'h2' ? 'h2' : 'h3';
$featured = !empty($args['featured']);
$position = isset($args['position']) ? (int) $args['position'] : -1;
// Displayed image widths: featured card, rail card, grid card (two columns on phones, three from 760px).
$image = [
    'alt' => '',
    'sizes' => $featured ? '(min-width: 1280px) 385px, (min-width: 960px) 30vw, 81vw' : (!empty($args['rail']) ? '(min-width: 600px) 240px, 53vw' : '(min-width: 1280px) 320px, (min-width: 760px) 24vw, 36vw'),
    'loading' => $position >= 0 && $position < 3 ? 'eager' : 'lazy',
];
if ($position === 0) {
    $image['fetchpriority'] = 'high';
}
$permalink = get_permalink();
$price = lasery_xtool_price_html($post_id);
$summary = lasery_xtool_display_summary($post_id);
?>
<article class="product-card<?php echo $featured ? ' product-card--featured' : ''; ?>">
    <div class="product-card-media">
        <?php echo lasery_xtool_badges_html($post_id); ?>
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('large', $image); ?>
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
