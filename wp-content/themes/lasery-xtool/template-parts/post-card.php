<?php
/** Article tile. Args: heading (h2|h3), featured (bool). */
$post_id = get_the_ID();
if (get_post($post_id)->post_password !== '') {
    return;
}
$heading = isset($args['heading']) && $args['heading'] === 'h2' ? 'h2' : 'h3';
$featured = !empty($args['featured']);
?>
<article class="post-card<?php echo $featured ? ' post-card--featured' : ''; ?>">
    <div class="post-card-media">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('large', ['alt' => '', 'loading' => $featured ? 'eager' : 'lazy', 'sizes' => $featured ? '(min-width: 960px) 720px, 100vw' : '(min-width: 960px) 400px, (min-width: 700px) 50vw, 100vw']); ?>
        <?php endif; ?>
    </div>
    <div class="post-card-body">
        <p class="post-card-meta"><?php echo esc_html(lasery_xtool_post_date($post_id)); ?> · <?php echo esc_html(lasery_xtool_reading_time($post_id)); ?></p>
        <<?php echo $heading; ?> class="post-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo $heading; ?>>
        <?php if (has_excerpt()) : ?><p class="post-card-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
        <span class="post-card-more" aria-hidden="true"><?php esc_html_e('Czytaj artykuł', 'lasery-xtool'); ?><?php echo lasery_xtool_icon('arrow-right'); ?></span>
    </div>
</article>
