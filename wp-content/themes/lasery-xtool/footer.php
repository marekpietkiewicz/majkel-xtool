</main>
<?php
$home = lasery_xtool_language_url(home_url('/'));
$archive = get_post_type_archive_link('lasery_product');
$privacy_url = get_privacy_policy_url();
$consent_text = lasery_xtool_consent_texts();
$email = lasery_xtool_contact_email();
?>
<footer class="site-footer" id="kontakt">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-about">
                <a class="brand" href="<?php echo esc_url($home); ?>"><?php lasery_xtool_brand(); ?></a>
                <p><?php echo esc_html(lasery_xtool_text('footer_tagline')); ?></p>
                <a class="btn btn-light" href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo lasery_xtool_icon('mail'); ?><?php echo esc_html($email); ?></a>
            </div>
            <nav class="footer-col" aria-labelledby="footer-devices">
                <h2 class="footer-title" id="footer-devices"><?php esc_html_e('Urządzenia', 'lasery-xtool'); ?></h2>
                <ul class="footer-links">
                    <?php foreach (lasery_xtool_products('device', 6) as $product) : ?>
                        <li><a href="<?php echo esc_url(get_permalink($product)); ?>"><?php echo esc_html(lasery_xtool_display_product_title($product->ID)); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <nav class="footer-col" aria-labelledby="footer-info">
                <h2 class="footer-title" id="footer-info"><?php esc_html_e('Informacje', 'lasery-xtool'); ?></h2>
                <ul class="footer-links">
                    <?php if ($archive) : ?>
                        <li><a href="<?php echo esc_url($archive); ?>"><?php esc_html_e('Wszystkie produkty', 'lasery-xtool'); ?></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('kind', 'accessory', $archive)); ?>"><?php esc_html_e('Akcesoria', 'lasery-xtool'); ?></a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo esc_url($home . '#demo'); ?>"><?php esc_html_e('Demo', 'lasery-xtool'); ?></a></li>
                    <?php $blog = lasery_xtool_blog_url(); ?>
                    <?php if ($blog !== '') : ?><li><a href="<?php echo esc_url($blog); ?>"><?php esc_html_e('Blog', 'lasery-xtool'); ?></a></li><?php endif; ?>
                    <?php if ($privacy_url) : ?>
                        <li><a href="<?php echo esc_url(lasery_xtool_language_url($privacy_url)); ?>"><?php echo esc_html($consent_text['privacy']); ?></a></li>
                    <?php endif; ?>
                    <li><button type="button" class="lasery-consent-settings" data-lasery-consent-open><?php echo esc_html($consent_text['settings']); ?></button></li>
                </ul>
            </nav>
            <div class="footer-col">
                <h2 class="footer-title"><?php esc_html_e('Kontakt', 'lasery-xtool'); ?></h2>
                <address class="footer-company"><?php echo nl2br(esc_html(lasery_xtool_company())); ?><br><a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></address>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></p>
            <p><?php esc_html_e('xTool i nazwy produktów są znakami towarowymi ich właścicieli.', 'lasery-xtool'); ?></p>
        </div>
    </div>
</footer>
<dialog class="search-dialog" data-search aria-label="<?php esc_attr_e('Szukaj', 'lasery-xtool'); ?>"
    data-endpoint="<?php echo esc_url(add_query_arg('lang', lasery_xtool_interface_language(), rest_url('lasery/v1/search'))); ?>"
    data-label-products="<?php esc_attr_e('Produkty', 'lasery-xtool'); ?>"
    data-label-articles="<?php esc_attr_e('Artykuły', 'lasery-xtool'); ?>"
    data-label-empty="<?php esc_attr_e('Brak wyników dla „%s”.', 'lasery-xtool'); ?>"
    data-label-count="<?php esc_attr_e('Wyniki: %d', 'lasery-xtool'); ?>"
    data-label-all="<?php esc_attr_e('Pokaż wszystkie wyniki (%d)', 'lasery-xtool'); ?>">
    <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <?php echo lasery_xtool_icon('search'); ?>
        <input type="search" name="s" maxlength="200" placeholder="<?php esc_attr_e('Szukaj urządzeń, akcesoriów i artykułów', 'lasery-xtool'); ?>" aria-label="<?php esc_attr_e('Szukaj', 'lasery-xtool'); ?>" aria-controls="search-results" autocomplete="off" enterkeyhint="search">
        <input type="hidden" name="lang" value="<?php echo esc_attr(lasery_xtool_interface_language()); ?>">
        <button type="button" class="search-close" data-search-close><span aria-hidden="true">Esc</span><span class="visually-hidden"><?php esc_html_e('Zamknij', 'lasery-xtool'); ?></span></button>
    </form>
    <p class="visually-hidden" role="status" data-search-status></p>
    <div class="search-dialog-results" id="search-results" data-search-results></div>
</dialog>
<?php wp_footer(); ?>
</body>
</html>
