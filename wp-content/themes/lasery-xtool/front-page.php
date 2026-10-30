<?php get_header(); ?>
<?php
$selected_products = get_option('lasery_xtool_featured_products', []);
$selected_products = is_array($selected_products) ? array_slice(array_values(array_unique(array_filter(array_map('absint', array_filter($selected_products, 'is_scalar'))))), 0, 4) : [];
$featured_query = [
    'post_type' => 'lasery_product',
    'post_status' => 'publish',
    'posts_per_page' => $selected_products ? count($selected_products) : 4,
];
if ($selected_products) {
    $featured_query['post__in'] = $selected_products;
    $featured_query['orderby'] = 'post__in';
}
$featured = get_posts($featured_query);
$slides = [];
foreach ($featured as $featured_product) {
    $image_url = get_the_post_thumbnail_url($featured_product->ID, 'large');
    $slides[] = ['post' => $featured_product, 'image' => $image_url];
    if (count($slides) === 4) {
        break;
    }
}
?>
<section class="hero" aria-labelledby="hero-heading">
    <div class="wrap hero-shell">
        <h1 id="hero-heading" class="visually-hidden"><?php echo esc_html(lasery_xtool_text('hero_title', __('Twórz więcej. Z xTool.', 'lasery-xtool'))); ?></h1>
        <?php if ($slides) : ?>
            <div class="hero-track" data-carousel aria-label="<?php esc_attr_e('Wyróżnione produkty', 'lasery-xtool'); ?>" tabindex="0">
                <?php foreach ($slides as $index => $slide) : ?>
                    <article class="hero-slide">
                        <div class="hero-copy">
                            <p class="eyebrow hero-eyebrow"><span aria-hidden="true"></span><?php echo esc_html(lasery_xtool_text('hero_eyebrow', __('LASERY I URZĄDZENIA XTOOL', 'lasery-xtool'))); ?></p>
                            <h2><?php echo esc_html(lasery_xtool_display_product_title($slide['post']->ID)); ?></h2>
                            <p class="hero-description"><?php echo esc_html(lasery_xtool_text('hero_description', __('Poznaj urządzenia do grawerowania i cięcia. Wybierz model dla siebie lub zapytaj nas o demo.', 'lasery-xtool'))); ?></p>
                            <div class="actions">
                                <a class="button button-light" href="<?php echo esc_url(get_permalink($slide['post']->ID)); ?>"><?php echo esc_html(lasery_xtool_text('hero_details_button', __('Zobacz szczegóły', 'lasery-xtool'))); ?> <span aria-hidden="true">↗</span></a>
                                <a class="button button-outline" href="#demo"><?php echo esc_html(lasery_xtool_text('hero_demo_button', __('Zapytaj o demo', 'lasery-xtool'))); ?></a>
                            </div>
                        </div>
                        <div class="hero-product-image"><?php if ($slide['image']) : ?><img src="<?php echo esc_url($slide['image']); ?>" alt="" decoding="async" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"><?php else : ?><span class="card-placeholder" aria-hidden="true">xTool</span><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (count($slides) > 1) : ?>
                <div class="carousel-controls" data-carousel-controls hidden>
                    <button type="button" data-carousel-prev aria-label="<?php esc_attr_e('Poprzedni produkt', 'lasery-xtool'); ?>">←</button>
                    <span class="carousel-count" aria-live="polite" data-carousel-count>1 / <?php echo esc_html((string) count($slides)); ?></span>
                    <button type="button" data-carousel-next aria-label="<?php esc_attr_e('Następny produkt', 'lasery-xtool'); ?>">→</button>
                    <button type="button" class="carousel-pause" data-carousel-pause data-label-pause="<?php esc_attr_e('Wstrzymaj', 'lasery-xtool'); ?>" data-label-play="<?php esc_attr_e('Odtwórz', 'lasery-xtool'); ?>"><?php esc_html_e('Wstrzymaj', 'lasery-xtool'); ?></button>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="hero-fallback hero-slide">
                <div class="hero-copy">
                    <p class="eyebrow hero-eyebrow"><span aria-hidden="true"></span><?php echo esc_html(lasery_xtool_text('hero_eyebrow', __('LASERY I URZĄDZENIA XTOOL', 'lasery-xtool'))); ?></p>
                    <h2><?php echo esc_html(lasery_xtool_text('hero_title', __('Twórz więcej. Z xTool.', 'lasery-xtool'))); ?></h2>
                    <p class="hero-description"><?php echo esc_html(lasery_xtool_text('hero_description', __('Poznaj urządzenia do grawerowania i cięcia. Wybierz model dla siebie lub zapytaj nas o demo.', 'lasery-xtool'))); ?></p>
                    <div class="actions"><a class="button button-light" href="#produkty"><?php echo esc_html(lasery_xtool_text('hero_products_button', __('Zobacz produkty', 'lasery-xtool'))); ?> <span aria-hidden="true">↗</span></a><a class="button button-outline" href="#demo"><?php echo esc_html(lasery_xtool_text('hero_demo_button', __('Zapytaj o demo', 'lasery-xtool'))); ?></a></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<section id="produkty" class="products-section section" aria-labelledby="products-heading">
    <div class="wrap">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span><?php echo esc_html(lasery_xtool_text('products_eyebrow', __('KATALOG XTOOL', 'lasery-xtool'))); ?></p>
                <h2 id="products-heading"><?php echo esc_html(lasery_xtool_text('products_title', __('Znajdź urządzenie dla siebie.', 'lasery-xtool'))); ?></h2>
            </div>
            <p><?php echo esc_html(lasery_xtool_text('products_description', __('Poznaj dostępne urządzenia i wybierz rozwiązanie do swoich projektów.', 'lasery-xtool'))); ?></p>
        </div>
        <?php
        $products = new WP_Query([
            'post_type' => 'lasery_product',
            'posts_per_page' => 6,
            'post_status' => 'publish',
        ]);
        if ($products->have_posts()) : ?>
            <div class="grid">
                <?php while ($products->have_posts()) : $products->the_post(); ?>
                    <?php get_template_part('template-parts/product-card'); ?>
                <?php endwhile; ?>
            </div>
            <?php $archive = get_post_type_archive_link('lasery_product'); ?>
            <?php if ($archive) : ?><p class="archive-link"><a href="<?php echo esc_url($archive); ?>"><?php echo esc_html(lasery_xtool_text('products_button', __('Zobacz cały katalog', 'lasery-xtool'))); ?> <span aria-hidden="true">↗</span></a></p><?php endif; ?>
        <?php else : ?>
            <p class="empty-catalog"><?php esc_html_e('Katalog produktów jest w przygotowaniu. Skontaktuj się z nami, aby zapytać o urządzenia.', 'lasery-xtool'); ?></p>
        <?php endif;
        wp_reset_postdata(); ?>
    </div>
</section>
<section id="demo" class="demo-section section" aria-labelledby="demo-heading">
    <div class="wrap demo-inner">
        <div>
            <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span><?php echo esc_html(lasery_xtool_text('demo_eyebrow', __('POZNAJ XTOOL', 'lasery-xtool'))); ?></p>
            <h2 id="demo-heading"><?php echo esc_html(lasery_xtool_text('demo_title', __('Chcesz zobaczyć urządzenie w akcji?', 'lasery-xtool'))); ?></h2>
        </div>
        <div class="demo-copy">
            <p><?php echo esc_html(lasery_xtool_text('demo_description', __('Wybierz dogodny termin na stronie demonstracji xTool lub otwórz ją w nowej karcie.', 'lasery-xtool'))); ?></p>
            <a class="button button-dark" href="<?php echo esc_url(lasery_xtool_demo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(lasery_xtool_text('demo_button', __('Otwórz rezerwację demo', 'lasery-xtool'))); ?> <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <div class="wrap demo-frame-wrap">
        <p><?php echo esc_html(lasery_xtool_text('demo_frame_note', __('Formularz „Book a Service” znajduje się poniżej profilu na stronie xTool. Przewiń zawartość ramki, aby go zobaczyć.', 'lasery-xtool'))); ?></p>
        <?php $consent_text = lasery_xtool_consent_texts(); ?>
        <div data-lasery-consent-placeholder><p><?php echo esc_html($consent_text['frame_notice']); ?></p><button type="button" class="lasery-consent-settings" data-lasery-consent-open><?php echo esc_html($consent_text['settings']); ?></button></div>
        <iframe class="demo-frame" data-lasery-consent-src="<?php echo esc_url(lasery_xtool_demo_url()); ?>" title="<?php esc_attr_e('Rezerwacja demonstracji xTool u Michała', 'lasery-xtool'); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" hidden></iframe>
    </div>
</section>
<?php get_footer(); ?>
