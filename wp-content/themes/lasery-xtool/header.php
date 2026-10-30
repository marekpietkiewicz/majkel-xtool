<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js');</script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#tresc"><?php esc_html_e('Przejdź do treści', 'lasery-xtool'); ?></a>
<?php
$home = lasery_xtool_language_url(home_url('/'));
$current_language = lasery_xtool_interface_language();
$languages = lasery_xtool_languages();
$demo_label = lasery_xtool_text('hero_demo_button');
$blog = lasery_xtool_blog_url();
?>
<header class="site-header" data-site-header>
    <div class="wrap header-bar">
        <a class="brand" href="<?php echo esc_url($home); ?>"><?php lasery_xtool_brand(); ?></a>
        <nav class="primary-nav" id="menu-glowne" aria-label="<?php esc_attr_e('Menu główne', 'lasery-xtool'); ?>" data-menu>
            <?php if (has_nav_menu('primary')) : ?>
                <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false]); ?>
            <?php else : ?>
                <ul>
                    <li><a href="<?php echo esc_url($home . '#urzadzenia'); ?>"><?php esc_html_e('Urządzenia', 'lasery-xtool'); ?></a></li>
                    <li><a href="<?php echo esc_url($home . '#akcesoria'); ?>"><?php esc_html_e('Akcesoria', 'lasery-xtool'); ?></a></li>
                    <li><a href="<?php echo esc_url($home . '#demo'); ?>"><?php esc_html_e('Demo', 'lasery-xtool'); ?></a></li>
                    <?php if ($blog !== '') : ?><li><a href="<?php echo esc_url($blog); ?>"<?php if (is_page('blog') || is_singular('post')) : ?> aria-current="page"<?php endif; ?>><?php esc_html_e('Blog', 'lasery-xtool'); ?></a></li><?php endif; ?>
                    <li><a href="#kontakt"><?php esc_html_e('Kontakt', 'lasery-xtool'); ?></a></li>
                </ul>
            <?php endif; ?>
            <a class="btn btn-primary primary-nav-cta" href="<?php echo esc_url($home . '#demo'); ?>"><?php echo lasery_xtool_icon('video'); ?><?php echo esc_html($demo_label); ?></a>
        </nav>
        <div class="header-actions">
            <a class="header-icon" href="<?php echo esc_url(add_query_arg(['s' => '', 'lang' => $current_language], home_url('/'))); ?>" data-search-open aria-keyshortcuts="Control+K Meta+K /">
                <span class="visually-hidden"><?php esc_html_e('Szukaj', 'lasery-xtool'); ?></span><?php echo lasery_xtool_icon('search'); ?>
            </a>
            <?php $cart = function_exists('lasery_xtool_cart_url') ? lasery_xtool_cart_url($current_language) : ''; ?>
            <?php if ($cart !== '') : ?>
                <a class="header-icon cart-link" href="<?php echo esc_url($cart); ?>"<?php if (is_page('koszyk')) : ?> aria-current="page"<?php endif; ?>>
                    <span class="visually-hidden"><?php echo esc_html(lasery_xtool_order_texts($current_language)['cart_title']); ?></span><?php echo lasery_xtool_icon('bag'); ?><span class="cart-count" data-cart-count hidden></span>
                </a>
            <?php endif; ?>
            <details class="lang-menu" data-lang-menu>
                <?php // The name includes the visible code, so voice control finds the menu by what it shows. ?>
                <summary>
                    <?php echo lasery_xtool_icon('globe'); ?><span class="visually-hidden"><?php echo esc_html(sprintf(__('Język strony: %s', 'lasery-xtool'), $languages[$current_language])); ?>, </span><span><?php echo esc_html(strtoupper($current_language)); ?></span><?php echo lasery_xtool_icon('chevron-down'); ?>
                </summary>
                <ul class="lang-menu-list">
                    <?php foreach ($languages as $language => $name) : ?>
                        <li><a href="<?php echo esc_url(lasery_xtool_language_switch_url($language)); ?>" lang="<?php echo esc_attr($language); ?>" hreflang="<?php echo esc_attr($language); ?>"<?php if ($current_language === $language) : ?> aria-current="true"<?php endif; ?>><?php echo esc_html($name); ?><?php if ($current_language === $language) : ?><?php echo lasery_xtool_icon('check'); ?><?php endif; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <a class="btn btn-primary btn-sm header-cta" href="<?php echo esc_url($home . '#demo'); ?>"><?php echo esc_html($demo_label); ?></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-glowne" data-menu-toggle>
                <span class="visually-hidden"><?php esc_html_e('Menu', 'lasery-xtool'); ?></span><?php echo lasery_xtool_icon('menu'); ?><?php echo lasery_xtool_icon('close'); ?>
            </button>
        </div>
    </div>
</header>
<main id="tresc" class="site-main">
