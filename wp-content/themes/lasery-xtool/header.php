<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#tresc"><?php esc_html_e('Przejdź do treści', 'lasery-xtool'); ?></a>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="site-name" href="<?php echo esc_url(lasery_xtool_language_url(home_url('/'))); ?>"><?php bloginfo('name'); ?><span class="brand-dot" aria-hidden="true">.</span></a>
        <nav class="site-nav" aria-label="<?php esc_attr_e('Menu główne', 'lasery-xtool'); ?>">
            <?php if (has_nav_menu('primary')) : ?>
                <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false]); ?>
            <?php else : ?>
                <ul>
                    <li><a href="<?php echo esc_url(lasery_xtool_language_url(home_url('/')) . '#produkty'); ?>"><?php esc_html_e('Produkty', 'lasery-xtool'); ?></a></li>
                    <li><a href="<?php echo esc_url(lasery_xtool_language_url(home_url('/')) . '#demo'); ?>"><?php esc_html_e('Demo', 'lasery-xtool'); ?></a></li>
                    <li><a href="<?php echo esc_url('mailto:' . lasery_xtool_contact_email()); ?>"><?php esc_html_e('Kontakt', 'lasery-xtool'); ?></a></li>
                </ul>
            <?php endif; ?>
        </nav>
        <nav class="language-switcher" aria-label="<?php esc_attr_e('Język strony', 'lasery-xtool'); ?>">
            <?php foreach (['pl', 'en', 'de', 'ru', 'uk'] as $language) : ?>
                <a href="<?php echo esc_url(add_query_arg('lang', $language)); ?>" lang="<?php echo esc_attr($language); ?>"<?php if (lasery_xtool_interface_language() === $language) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html(strtoupper($language)); ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="header-cta" href="<?php echo esc_url(lasery_xtool_language_url(home_url('/')) . '#demo'); ?>"><?php echo esc_html(lasery_xtool_text('hero_demo_button', __('Zapytaj o demo', 'lasery-xtool'))); ?> <span aria-hidden="true">↗</span></a>
    </div>
</header>
<main id="tresc" class="<?php echo is_front_page() ? 'home-content' : 'wrap content'; ?>">
