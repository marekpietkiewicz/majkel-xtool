<?php get_header(); ?>
<div class="wrap">
    <header class="page-hero">
        <p class="eyebrow">404</p>
        <h1 class="page-title"><?php esc_html_e('Nie znaleźliśmy tej strony', 'lasery-xtool'); ?></h1>
        <p class="section-lead"><?php esc_html_e('Adres może być nieaktualny albo zawierać literówkę. Wyszukaj urządzenie, akcesorium lub artykuł albo przejdź do katalogu.', 'lasery-xtool'); ?></p>
        <?php get_template_part('template-parts/search-form'); ?>
        <p class="actions not-found-actions">
            <a class="btn btn-primary" href="<?php echo esc_url(get_post_type_archive_link('lasery_product')); ?>"><?php esc_html_e('Zobacz produkty', 'lasery-xtool'); ?></a>
            <?php $blog = lasery_xtool_blog_url(); ?>
            <?php if ($blog !== '') : ?><a class="btn btn-secondary" href="<?php echo esc_url($blog); ?>"><?php esc_html_e('Blog', 'lasery-xtool'); ?></a><?php endif; ?>
            <a class="btn btn-secondary" href="<?php echo esc_url(lasery_xtool_language_url(home_url('/'))); ?>"><?php esc_html_e('Strona główna', 'lasery-xtool'); ?></a>
        </p>
    </header>
</div>
<?php get_footer(); ?>
