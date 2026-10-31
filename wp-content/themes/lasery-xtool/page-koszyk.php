<?php
/** The cart page (slug "koszyk"): the plugin renders the cart and the order form. */
get_header();
?>
<div class="cart-band">
    <div class="wrap cart-page">
        <?php if (function_exists('lasery_xtool_render_cart')) : ?>
            <?php lasery_xtool_render_cart(); ?>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
