// Run only against a disposable local WordPress container, never against a live site.
// PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://localhost:8091 WP_TEST_COMPOSE=/path/to/compose.yml WP_TEST_PROJECT=... node tests/product-privacy.cjs
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');

const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
const compose = process.env.WP_TEST_COMPOSE;
const project = process.env.WP_TEST_PROJECT;
if (!container || !base || !compose || !project || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local container, URL and Compose project are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8' }).trim();
const cli = (script) => execFileSync('docker', ['compose', '-p', project, '-f', compose, 'run', '--rm', '--no-deps', 'cli', 'wp', 'eval', script], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const marker = `ReviewPrivacyFixture${Date.now()}`;
const source = `${Date.now()}`;
const fixture = JSON.parse(php(`
  update_option('permalink_structure', '/%postname%/');
  flush_rewrite_rules();
  $id = wp_insert_post(['post_type'=>'lasery_product','post_status'=>'publish','post_title'=>'${marker}','post_name'=>'review-privacy-fixture','post_content'=>'<p>${marker} secret description</p>']);
  update_post_meta($id, '_lasery_shopify_id', '${source}');
  update_post_meta($id, '_lasery_source_variants', [['id'=>'123', 'title'=>'Fixture', 'sku'=>'${marker}', 'available'=>true]]);
  $article = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Review fixture article','post_name'=>'review-fixture-article','post_content'=>'[lasery_produkty ids="'.$id.'"]']);
  echo wp_json_encode(['id'=>$id,'articleId'=>$article,'article'=>get_permalink($article),'product'=>get_permalink($id)]);
`));

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const one = await browser.newContext();
    const two = await browser.newContext();
    const a = await one.newPage();
    const b = await two.newPage();
    const indexUrl = `${base}/wp-json/lasery/v1/search?lang=pl&q=${encodeURIComponent(marker)}`;
    const sitemaps = [`${base}/wp-sitemap-posts-lasery_product-1.xml`, `${base}/wp-sitemap-languages-1.xml`];
    const slug = new URL(fixture.product).pathname.split('/').filter(Boolean).pop();
    const search = async (page) => {
      const response = await page.request.get(indexUrl);
      assert.match(response.headers()['cache-control'], /no-store/);
      return response.text();
    };
    assert.match(await search(a), new RegExp(marker));
    assert.match(cli(`echo lasery_xtool_products_shortcode(['ids'=>'${fixture.id}', 'xtool'=>'${source}']);`), new RegExp(marker));
    assert.equal(php(`echo isset(lasery_xtool_sku_index()['${marker}']) ? 'yes' : 'no';`), 'yes');
    assert.match(await (await a.request.get(fixture.article)).text(), new RegExp(marker));
    assert.match(await (await a.request.get(`${base}/produkty/?lang=pl`)).text(), new RegExp(marker));
    for (const url of sitemaps) {
      const response = await a.request.get(url);
      assert.ok(response.ok(), url);
      assert.ok((await response.text()).includes(slug), url);
    }
    assert.equal(php(`wp_update_post(['ID'=>${fixture.id}, 'post_password'=>'fixture-password']); echo did_action('litespeed_purge_all');`), '1');
    assert.equal(php(`echo isset(lasery_xtool_sku_index()['${marker}']) ? 'yes' : 'no';`), 'no');
    // Build the shared transient using actual WP-CLI, then read it anonymously over HTTP.
    const built = cli(`delete_transient('lasery_xtool_search_pl'); $index = lasery_xtool_search_index(); $registry = wp_sitemaps_get_server()->registry; echo wp_json_encode(['cli'=>defined('WP_CLI') && WP_CLI, 'index'=>$index, 'shortcode'=>lasery_xtool_products_shortcode(['ids'=>'${fixture.id}', 'xtool'=>'${source}']), 'sitemap'=>$registry->get_provider('posts')->get_url_list(1, 'lasery_product'), 'languages'=>$registry->get_provider('languages')->get_url_list(1)]);`);
    const cliResult = JSON.parse(built);
    assert.equal(cliResult.cli, true);
    assert.doesNotMatch(built, new RegExp(marker));
    assert.ok(!JSON.stringify(cliResult.sitemap).includes(slug));
    assert.ok(!JSON.stringify(cliResult.languages).includes(slug));
    assert.doesNotMatch(await search(b), new RegExp(marker));

    for (const page of [a, b]) {
      assert.doesNotMatch(await search(page), new RegExp(marker));
      for (const url of [`${base}/?lang=pl`, `${base}/produkty/?lang=pl`, fixture.article]) {
        const response = await page.request.get(url);
        assert.ok(response.ok(), url);
        assert.doesNotMatch(await response.text(), new RegExp(marker), url);
      }
      for (const url of sitemaps) {
        const response = await page.request.get(url);
        assert.ok(response.ok(), url);
        assert.ok(!(await response.text()).includes(slug), url);
      }
      const locked = await page.goto(fixture.product);
      assert.match(locked.headers()['cache-control'], /no-cache|no-store/);
      assert.doesNotMatch(await page.content(), /secret description/);
      assert.equal(await page.locator('form.post-password-form').count(), 1);
      assert.equal(await page.locator('[data-order-form]').count(), 0);
    }
    await a.locator('form.post-password-form input[type=password]').fill('fixture-password');
    await Promise.all([a.waitForURL(/review-privacy-fixture/), a.locator('form.post-password-form input[type=submit]').click()]);
    const unlocked = await a.goto(fixture.product);
    assert.match(unlocked.headers()['cache-control'], /no-cache|no-store/);
    assert.match(await a.content(), /secret description/);
    assert.equal(await a.locator('[data-order-form]').count(), 0);
    const head = await a.locator('head').innerHTML();
    assert.doesNotMatch(head, /secret description|application\/ld\+json|rel="canonical"/);
    assert.match(head, /noindex/);
    await b.goto(fixture.product);
    assert.doesNotMatch(await b.content(), /secret description/);
    assert.doesNotMatch(await search(a), new RegExp(marker));
    assert.equal(php(`wp_update_post(['ID'=>${fixture.id}, 'post_password'=>'']); echo did_action('litespeed_purge_all');`), '1');
    assert.equal(php(`echo isset(lasery_xtool_sku_index()['${marker}']) ? 'yes' : 'no';`), 'yes');
    assert.match(await search(b), new RegExp(marker));
    console.log('Product privacy: public → protected → public, independent visitors, search, listings, shortcode and sitemaps passed.');
  } finally {
    await browser.close();
    php(`wp_delete_post(${fixture.id}, true); wp_delete_post(${fixture.articleId}, true);`);
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
