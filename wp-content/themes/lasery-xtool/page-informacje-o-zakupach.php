<?php
/** Purchase process and buyer rights. */
get_header();
$text = lasery_xtool_purchase_info_texts(lasery_xtool_interface_language());
$email = lasery_xtool_contact_email();
?>
<div class="wrap page-wrap">
    <article class="prose">
        <h1><?php echo esc_html($text['title']); ?></h1>
        <?php foreach (['operator', 'process', 'rights'] as $section) : ?>
            <section>
                <h2><?php echo esc_html($text[$section . '_heading']); ?></h2>
                <p><?php echo esc_html($text[$section]); ?></p>
                <?php if ($section === 'operator') : ?>
                    <address><?php echo nl2br(esc_html(lasery_xtool_company())); ?></address>
                    <h3><?php echo esc_html($text['xtool_heading']); ?></h3>
                    <p><?php echo esc_html($text['xtool_intro']); ?></p>
                    <address>
                        <?php echo esc_html($text['xtool_entity']); ?><br>
                        <?php echo esc_html($text['xtool_address']); ?><br>
                        <?php if (is_email($text['xtool_email'])) : ?><a href="<?php echo esc_url('mailto:' . $text['xtool_email']); ?>"><?php echo esc_html($text['xtool_email']); ?></a><?php else : ?><?php echo esc_html($text['xtool_email']); ?><?php endif; ?><br>
                        <a href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $text['xtool_phone'])); ?>"><?php echo esc_html($text['xtool_phone']); ?></a>
                    </address>
                    <p><a href="https://www.xtool.eu/pl-pl/pages/imprint-1" rel="noopener noreferrer"><?php echo esc_html($text['xtool_imprint']); ?></a> · <a href="https://www.xtool.eu/pl-pl/pages/contact-us" rel="noopener noreferrer"><?php echo esc_html($text['xtool_contact_source']); ?></a></p>
                <?php endif; ?>
                <?php if ($section === 'rights') : ?>
                    <p><?php echo esc_html($text['contact']); ?> <a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></p>
                    <p><?php echo esc_html($text['seller_contact']); ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <section id="regulamin">
            <h2><?php echo esc_html($text['service_title']); ?></h2>
            <?php foreach (['service_scope', 'service_requirements', 'service_duration', 'service_complaints'] as $key) : ?>
                <p><?php echo esc_html($text[$key]); ?></p>
            <?php endforeach; ?>
            <p><a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></p>
        </section>
        <section>
            <h2><?php echo esc_html($text['links_heading']); ?></h2>
            <ul>
                <li><a href="https://www.xtool.eu/policies/terms-of-service" rel="noopener noreferrer"><?php echo esc_html($text['terms']); ?></a></li>
                <li><a href="https://www.xtool.eu/policies/refund-policy" rel="noopener noreferrer"><?php echo esc_html($text['returns']); ?></a></li>
                <li><a href="https://www.xtool.eu/pages/aftersale-policies-v3" rel="noopener noreferrer"><?php echo esc_html($text['aftersales']); ?></a></li>
                <li><a href="https://support.xtool.com/" rel="noopener noreferrer"><?php echo esc_html($text['support']); ?></a></li>
            </ul>
        </section>
    </article>
</div>
<?php get_footer(); ?>
