<?php
/** Privacy information for the WordPress privacy-policy page. */
get_header();
$language = lasery_xtool_interface_language();
$text = lasery_xtool_privacy_texts($language);
$override = function_exists('lasery_xtool_privacy_override') ? lasery_xtool_privacy_override($language) : null;
$content = $override === null ? lasery_xtool_default_privacy_html($language) : $override;
?>
<div class="wrap page-wrap">
    <article class="prose privacy-policy">
        <h1><?php echo esc_html($text['title']); ?></h1>
        <?php echo apply_filters('the_content', wp_kses_post($content)); ?>
    </article>
</div>
<?php get_footer(); ?>
