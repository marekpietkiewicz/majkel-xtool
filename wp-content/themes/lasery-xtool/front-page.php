<?php get_header(); ?>
<section class="hero" aria-labelledby="hero-heading">
    <div class="wrap hero-inner">
        <div class="hero-copy">
            <p class="eyebrow hero-eyebrow"><span aria-hidden="true"></span><?php esc_html_e('LASERY I URZĄDZENIA XTOOL', 'lasery-xtool'); ?></p>
            <h1 id="hero-heading"><?php esc_html_e('Twórz więcej. Z xTool.', 'lasery-xtool'); ?></h1>
            <p class="hero-description"><?php esc_html_e('Poznaj urządzenia do grawerowania i cięcia. Wybierz model dla siebie lub zapytaj nas o demo.', 'lasery-xtool'); ?></p>
            <div class="actions">
                <a class="button button-light" href="#produkty"><?php esc_html_e('Zobacz produkty', 'lasery-xtool'); ?> <span aria-hidden="true">↗</span></a>
                <a class="button button-outline" href="#demo"><?php esc_html_e('Zapytaj o demo', 'lasery-xtool'); ?></a>
            </div>
        </div>
        <span class="hero-caption" aria-hidden="true"><?php esc_html_e('OD POMYSŁU DO REALIZACJI', 'lasery-xtool'); ?></span>
    </div>
</section>
<section id="produkty" class="products-section section" aria-labelledby="products-heading">
    <div class="wrap">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span><?php esc_html_e('KATALOG XTOOL', 'lasery-xtool'); ?></p>
                <h2 id="products-heading"><?php esc_html_e('Znajdź urządzenie dla siebie.', 'lasery-xtool'); ?></h2>
            </div>
            <p><?php esc_html_e('Poznaj dostępne urządzenia i wybierz rozwiązanie do swoich projektów.', 'lasery-xtool'); ?></p>
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
            <?php if ($archive) : ?><p class="archive-link"><a href="<?php echo esc_url($archive); ?>"><?php esc_html_e('Zobacz cały katalog', 'lasery-xtool'); ?> <span aria-hidden="true">↗</span></a></p><?php endif; ?>
        <?php else : ?>
            <p class="empty-catalog"><?php esc_html_e('Katalog produktów jest w przygotowaniu. Skontaktuj się z nami, aby zapytać o urządzenia.', 'lasery-xtool'); ?></p>
        <?php endif;
        wp_reset_postdata(); ?>
    </div>
</section>
<section id="demo" class="demo-section section" aria-labelledby="demo-heading">
    <div class="wrap demo-inner">
        <div>
            <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span><?php esc_html_e('POZNAJ XTOOL', 'lasery-xtool'); ?></p>
            <h2 id="demo-heading"><?php esc_html_e('Chcesz zobaczyć urządzenie w akcji?', 'lasery-xtool'); ?></h2>
        </div>
        <div class="demo-copy">
            <p><?php esc_html_e('Wybierz dogodny termin na stronie demonstracji xTool lub otwórz ją w nowej karcie.', 'lasery-xtool'); ?></p>
            <a class="button button-dark" href="https://support.xtool.com/demo-room/host-instruction?id=1252&amp;type=demoPreSale" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Otwórz rezerwację demo', 'lasery-xtool'); ?> <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <div class="wrap demo-frame-wrap">
        <p><?php esc_html_e('Formularz „Book a Service” znajduje się poniżej profilu na stronie xTool. Przewiń zawartość ramki, aby go zobaczyć.', 'lasery-xtool'); ?></p>
        <iframe class="demo-frame" src="https://support.xtool.com/demo-room/host-instruction?id=1252&amp;type=demoPreSale" title="<?php esc_attr_e('Rezerwacja demonstracji xTool u Michała', 'lasery-xtool'); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
</section>
<?php get_footer(); ?>
