<?php get_header(); ?>
<?php
$selected_products = get_option('lasery_xtool_featured_products', []);
$selected_products = is_array($selected_products) ? array_slice(array_values(array_unique(array_filter(array_map('absint', array_filter($selected_products, 'is_scalar'))))), 0, 4) : [];
$slides = $selected_products
    ? lasery_xtool_products('', 4, ['post__in' => $selected_products, 'orderby' => 'post__in'])
    : lasery_xtool_products('device', 4);
// Eight cards fill the featured grid evenly; the catalogue lists the rest.
$devices = lasery_xtool_products('device', 8);
$accessories = lasery_xtool_products('accessory', 12);
$demo_label = lasery_xtool_text('hero_demo_button');
$demo_products = function_exists('lasery_xtool_demo_tax_query') ? lasery_xtool_products('device', -1, ['tax_query' => lasery_xtool_demo_tax_query()]) : [];
$slider_labels = [
    'data-label-prev' => __('Poprzedni produkt', 'lasery-xtool'),
    'data-label-next' => __('Następny produkt', 'lasery-xtool'),
    'data-label-slide' => __('Przejdź do produktu {{index}}', 'lasery-xtool'),
];
$benefit_icons = [1 => 'video', 2 => 'bag', 3 => 'chat', 4 => 'shield'];
?>
<section class="hero" aria-labelledby="hero-heading">
    <h1 id="hero-heading" class="visually-hidden"><?php echo esc_html(lasery_xtool_text('hero_title')); ?></h1>
    <div class="wrap"><p class="commercial-disclosure"><?php echo esc_html(lasery_xtool_purchase_info_texts(lasery_xtool_interface_language())['commercial_disclosure']); ?></p></div>
    <?php if ($slides) : ?>
        <div class="swiper hero-swiper" data-hero-swiper role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e('Wyróżnione produkty', 'lasery-xtool'); ?>"<?php foreach ($slider_labels as $attribute => $label) : ?> <?php echo esc_attr($attribute); ?>="<?php echo esc_attr($label); ?>"<?php endforeach; ?>>
            <div class="swiper-wrapper">
                <?php foreach ($slides as $index => $slide) : ?>
                    <?php $summary = lasery_xtool_display_summary($slide->ID); $price = lasery_xtool_price_html($slide->ID); $link = get_permalink($slide); ?>
                    <article class="swiper-slide hero-slide">
                        <div class="wrap hero-grid">
                            <div class="hero-copy">
                                <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('hero_eyebrow')); ?></p>
                                <?php echo lasery_xtool_badges_html($slide->ID); ?>
                                <h2 class="hero-title"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html(lasery_xtool_display_product_title($slide->ID)); ?></a></h2>
                                <?php if ($summary !== '') : ?><p class="hero-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
                                <?php if ($price !== '') : ?><p class="hero-price price"><?php echo $price; ?></p><?php endif; ?>
                                <div class="actions">
                                    <a class="btn btn-primary" href="<?php echo esc_url($link . '#zamow'); ?>"><?php esc_html_e('Zamów', 'lasery-xtool'); ?><?php echo lasery_xtool_icon('arrow-right'); ?></a>
                                    <?php if (function_exists('lasery_xtool_has_demo') && lasery_xtool_has_demo($slide->ID)) : ?>
                                        <a class="btn btn-accent" href="<?php echo esc_url(lasery_xtool_demo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo lasery_xtool_icon('video'); ?><?php echo esc_html(lasery_xtool_text('demo_callout_button')); ?><?php echo lasery_xtool_new_tab_hint(); ?></a>
                                    <?php else : ?>
                                        <a class="btn btn-secondary" href="<?php echo esc_url($link); ?>"><?php echo esc_html(lasery_xtool_text('hero_details_button')); ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="hero-media">
                                <?php if (has_post_thumbnail($slide)) : ?>
                                    <?php echo get_the_post_thumbnail($slide, 'large', [
                                        'alt' => '',
                                        'sizes' => '(min-width: 960px) 640px, 100vw',
                                        'loading' => $index === 0 ? 'eager' : 'lazy',
                                        'fetchpriority' => $index === 0 ? 'high' : 'auto',
                                    ]); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (count($slides) > 1) : ?>
                <div class="hero-controls" data-slider-controls hidden>
                    <div class="wrap hero-controls-inner">
                        <div class="hero-pagination" data-slider-pagination></div>
                        <div class="slider-buttons">
                            <button type="button" class="icon-btn" data-autoplay-toggle data-label-pause="<?php esc_attr_e('Wstrzymaj', 'lasery-xtool'); ?>" data-label-play="<?php esc_attr_e('Odtwórz', 'lasery-xtool'); ?>"><?php echo lasery_xtool_icon('pause'); ?><?php echo lasery_xtool_icon('play'); ?></button>
                            <button type="button" class="icon-btn" data-slider-prev><?php echo lasery_xtool_icon('chevron-left'); ?></button>
                            <button type="button" class="icon-btn" data-slider-next><?php echo lasery_xtool_icon('chevron-right'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="wrap hero-grid hero-grid--empty">
            <div class="hero-copy">
                <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('hero_eyebrow')); ?></p>
                <p class="hero-title"><?php echo esc_html(lasery_xtool_text('hero_title')); ?></p>
                <p class="hero-lead"><?php echo esc_html(lasery_xtool_text('hero_description')); ?></p>
                <div class="actions"><a class="btn btn-primary" href="#demo"><?php echo esc_html($demo_label); ?></a></div>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="benefits" aria-label="<?php esc_attr_e('Dlaczego warto', 'lasery-xtool'); ?>">
    <ul class="wrap benefits-list">
        <?php foreach ($benefit_icons as $number => $icon) : ?>
            <li class="benefit">
                <span class="benefit-icon"><?php echo lasery_xtool_icon($icon); ?></span>
                <div>
                    <h2 class="benefit-title"><?php echo esc_html(lasery_xtool_text('benefit_' . $number . '_title')); ?></h2>
                    <p><?php echo esc_html(lasery_xtool_text('benefit_' . $number . '_text')); ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section id="urzadzenia" class="section" aria-labelledby="devices-heading">
    <div class="wrap">
        <header class="section-head">
            <div>
                <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('products_eyebrow')); ?></p>
                <h2 id="devices-heading" class="section-title"><?php echo esc_html(lasery_xtool_text('products_title')); ?></h2>
            </div>
            <p class="section-lead"><?php echo esc_html(lasery_xtool_text('products_description')); ?></p>
        </header>
        <?php if ($devices) : ?>
            <div class="product-grid product-grid--featured">
                <?php foreach ($devices as $index => $post) : setup_postdata($post); ?>
                    <?php get_template_part('template-parts/product-card', null, ['featured' => $index === 0]); ?>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
            <?php $archive = get_post_type_archive_link('lasery_product'); ?>
            <?php if ($archive) : ?>
                <p class="section-more"><a class="btn btn-secondary" href="<?php echo esc_url($archive); ?>"><?php echo esc_html(lasery_xtool_text('products_button')); ?><?php echo lasery_xtool_icon('arrow-right'); ?></a></p>
            <?php endif; ?>
        <?php else : ?>
            <p class="empty-state"><?php esc_html_e('Katalog produktów jest w przygotowaniu. Skontaktuj się z nami, aby zapytać o urządzenia.', 'lasery-xtool'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php if ($accessories) : ?>
    <section id="akcesoria" class="section section--muted" aria-labelledby="accessories-heading" data-rail-section>
        <div class="wrap">
            <header class="section-head">
                <div>
                    <p class="eyebrow"><?php echo esc_html(lasery_xtool_text('accessories_eyebrow')); ?></p>
                    <h2 id="accessories-heading" class="section-title"><?php echo esc_html(lasery_xtool_text('accessories_title')); ?></h2>
                </div>
                <div class="section-head-aside">
                    <p class="section-lead"><?php echo esc_html(lasery_xtool_text('accessories_description')); ?></p>
                    <div class="slider-buttons" data-slider-controls hidden>
                        <button type="button" class="icon-btn" data-slider-prev><?php echo lasery_xtool_icon('chevron-left'); ?></button>
                        <button type="button" class="icon-btn" data-slider-next><?php echo lasery_xtool_icon('chevron-right'); ?></button>
                    </div>
                </div>
            </header>
            <?php get_template_part('template-parts/product-rail', null, ['products' => $accessories]); ?>
        </div>
    </section>
<?php endif; ?>

<section id="demo" class="section demo-section" aria-labelledby="demo-heading">
    <div class="wrap">
        <div class="demo-card">
            <div class="demo-grid">
                <div>
                    <p class="eyebrow eyebrow--light"><?php echo esc_html(lasery_xtool_text('demo_eyebrow')); ?></p>
                    <h2 id="demo-heading" class="section-title"><?php echo esc_html(lasery_xtool_text('demo_title')); ?></h2>
                    <p class="demo-lead"><?php echo esc_html(lasery_xtool_text('demo_description')); ?></p>
                    <a class="btn btn-light" href="<?php echo esc_url(lasery_xtool_demo_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo lasery_xtool_icon('calendar'); ?><?php echo esc_html(lasery_xtool_text('demo_button')); ?><?php echo lasery_xtool_icon('arrow-up-right'); ?><?php echo lasery_xtool_new_tab_hint(); ?></a>
                </div>
                <ol class="demo-steps">
                    <li><?php echo esc_html(lasery_xtool_text('demo_step_1')); ?></li>
                    <li><?php echo esc_html(lasery_xtool_text('demo_step_2')); ?></li>
                    <li><?php echo esc_html(lasery_xtool_text('demo_step_3')); ?></li>
                </ol>
            </div>
            <?php if ($demo_products) : ?>
                <div class="demo-models">
                    <p class="demo-models-title"><?php echo esc_html(lasery_xtool_text('demo_models_title')); ?></p>
                    <ul>
                        <?php foreach ($demo_products as $demo_product) : ?>
                            <li><a href="<?php echo esc_url(get_permalink($demo_product)); ?>"><?php echo get_the_post_thumbnail($demo_product, 'thumbnail', ['alt' => '', 'loading' => 'lazy']); ?><?php echo esc_html(lasery_xtool_display_product_title($demo_product->ID)); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="demo-frame-wrap">
                <p class="demo-frame-note"><?php echo esc_html(lasery_xtool_text('demo_frame_note')); ?></p>
                <?php $consent_text = lasery_xtool_consent_texts(); ?>
                <div class="consent-placeholder" data-lasery-consent-placeholder>
                    <?php echo lasery_xtool_icon('shield'); ?>
                    <p><?php echo esc_html($consent_text['frame_notice']); ?></p>
                    <button type="button" class="btn btn-light btn-sm" data-lasery-consent-open><?php echo esc_html($consent_text['settings']); ?></button>
                </div>
                <iframe class="demo-frame" data-lasery-consent-src="<?php echo esc_url(lasery_xtool_demo_url()); ?>" title="<?php esc_attr_e('Rezerwacja demonstracji xTool u Michała', 'lasery-xtool'); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" hidden></iframe>
            </div>
        </div>
    </div>
</section>
<?php get_footer(); ?>
