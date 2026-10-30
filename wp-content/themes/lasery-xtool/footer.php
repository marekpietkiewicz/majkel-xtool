</main>
<footer class="site-footer">
    <div class="wrap footer-inner">
        <div><a class="footer-brand" href="<?php echo esc_url(lasery_xtool_language_url(home_url('/'))); ?>"><?php bloginfo('name'); ?><span class="brand-dot" aria-hidden="true">.</span></a><p><?php esc_html_e('Produkty xTool i kontakt w sprawie demo.', 'lasery-xtool'); ?></p></div>
        <div class="footer-contact"><span><?php esc_html_e('MASZ PYTANIA?', 'lasery-xtool'); ?></span><a href="<?php echo esc_url('mailto:' . lasery_xtool_contact_email()); ?>"><?php echo esc_html(lasery_xtool_contact_email()); ?></a></div>
    </div>
    <div class="wrap footer-bottom">
        <span>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span>
        <?php $privacy_url = get_privacy_policy_url(); ?>
        <?php if ($privacy_url) : ?>
            <?php $privacy_labels = ['pl' => 'Polityka prywatności', 'en' => 'Privacy policy', 'de' => 'Datenschutzerklärung', 'ru' => 'Политика конфиденциальности', 'uk' => 'Політика конфіденційності']; ?>
            <span><a href="<?php echo esc_url(lasery_xtool_language_url($privacy_url)); ?>"><?php echo esc_html($privacy_labels[lasery_xtool_interface_language()]); ?></a></span>
        <?php endif; ?>
        <?php $consent_text = lasery_xtool_consent_texts(); ?>
        <span><button type="button" class="lasery-consent-settings" data-lasery-consent-open><?php echo esc_html($consent_text['settings']); ?></button></span>
        <span><?php esc_html_e('MAJKEL MICHAŁ MAGALSKI', 'lasery-xtool'); ?></span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
