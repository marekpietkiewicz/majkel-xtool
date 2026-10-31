<?php
/** Safety information, translated in place like the privacy policy. */
get_header();
$text = lasery_xtool_safety_page_texts();
$sources = [
    ['https://support.xtool.com/article/3424', 'xTool F2 — Safety First'],
    ['https://support.xtool.com/article/2818', 'xTool F2 — FAQ'],
    ['https://support.xtool.com/academy/lesson/2642?courseId=271', 'xTool F2 — LSO Training'],
    ['https://support.xtool.com/article/3378', 'xTool F2 Ultra — Safety First (MXF-K003-003 / MXF-K004-003)'],
    ['https://support.xtool.com/article/2246', 'xTool F2 Ultra — Safety First (MXF-K003-002 / MXF-K004-002)'],
    ['https://support.xtool.com/article/1443', 'xTool — Safety Goggles Selection'],
    ['https://support.xtool.com/article/2737', 'xTool F2 Ultra UV — Safety First (MXF-K007-001 / MXF-K007-002)'],
    ['https://support.xtool.com/article/2751', 'xTool F2 Ultra UV — Safety First (MXF-K007-003)'],
    ['https://support.xtool.com/article/2359', 'xTool F2 Ultra UV — User Guide (MXF-K007-001 / MXF-K007-002)'],
    ['https://support.xtool.com/article/1970', 'xTool F2 / F2 Ultra UV + RA3'],
    ['https://support.xtool.com/article/2579', 'xTool P3 Automatic Conveyor Feeder — Safety First'],
    ['https://support.xtool.com/article/253', 'xTool — Materials unsuitable for laser processing'],
];
?>
<div class="wrap page-wrap">
    <article class="prose">
        <h1><?php echo esc_html($text['title']); ?></h1>
        <p><?php echo esc_html($text['intro']); ?></p>
        <?php foreach (['general', 'materials', 'f2', 'ultra', 'uv', 'p3'] as $section) : ?>
            <section>
                <h2><?php echo esc_html($text[$section . '_heading']); ?></h2>
                <p><?php echo esc_html($text[$section]); ?></p>
                <?php if ($section === 'f2') : ?><p><?php echo esc_html($text['f2_package']); ?></p><?php endif; ?>
            </section>
        <?php endforeach; ?>
        <section>
            <h2><?php echo esc_html($text['sources']); ?></h2>
            <ul><?php foreach ($sources as [$url, $label]) : ?><li><a href="<?php echo esc_url($url); ?>" rel="noopener noreferrer"><?php echo esc_html($label); ?></a></li><?php endforeach; ?></ul>
        </section>
    </article>
</div>
<?php get_footer(); ?>
