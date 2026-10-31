<?php /** Search form for the results page and the 404 page; the results come from the instant search index. */ ?>
<form class="search-page-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="search" name="s" maxlength="200" value="<?php echo esc_attr(get_search_query(false)); ?>" aria-label="<?php esc_attr_e('Szukaj', 'lasery-xtool'); ?>" placeholder="<?php esc_attr_e('Szukaj urządzeń, akcesoriów i artykułów', 'lasery-xtool'); ?>">
    <input type="hidden" name="lang" value="<?php echo esc_attr(lasery_xtool_interface_language()); ?>">
    <button class="btn btn-primary" type="submit"><?php esc_html_e('Szukaj', 'lasery-xtool'); ?></button>
</form>
