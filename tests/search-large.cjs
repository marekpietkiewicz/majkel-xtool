// Run only against a disposable WordPress container with PLAYWRIGHT_CORE, WP_TEST_CONTAINER and WP_TEST_URL set.
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local container and URL are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8' }).trim();
const marker = `LargeSearch${Date.now()}`;
const rankMarker = `RankSearch${Date.now()}`;
const germanMarker = `GermanSearch${Date.now()}`;
const ids = JSON.parse(php(`
  $ids = [];
  for ($i = 0; $i < 106; ++$i) {
    $ids[] = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${marker} Zażółć ' . ($i ? 'new' . $i : 'old'), 'post_excerpt'=>'Search example', 'post_date'=>'2024-01-01 12:00:00', 'post_date_gmt'=>'2024-01-01 12:00:00']);
  }
  $ids[] = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${marker} Zażółć hidden', 'post_password'=>'secret']);
  $ids[] = wp_insert_post(['post_type'=>'lasery_product', 'post_status'=>'publish', 'post_title'=>'${marker} Zażółć device']);
  $ids[] = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${rankMarker} omega', 'post_date'=>'2025-01-01 12:00:00', 'post_date_gmt'=>'2025-01-01 12:00:00']);
  $ids[] = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'omega ${rankMarker}', 'post_date'=>'2025-01-02 12:00:00', 'post_date_gmt'=>'2025-01-02 12:00:00']);
  foreach (['Straße', 'Strasse'] as $word) {
    $german = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${germanMarker} ' . $word]);
    update_post_meta($german, '_lasery_language', 'de');
    $ids[] = $german;
  }
  echo wp_json_encode($ids);
`));
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const endpoint = `${base}/?rest_route=/lasery/v1/search&lang=pl`;
    const api = async (query) => page.request.get(`${endpoint}&q=${encodeURIComponent(query)}`);
    const old = await api(`${marker} zazolc old`);
    assert.match(old.headers()['cache-control'], /no-store/);
    const oldData = await old.json();
    assert.equal(oldData.count, 1);
    assert.match(oldData.items[0][1], /old/);
    const result = await api(`${marker} zazolc`);
    const body = await result.text();
    const data = JSON.parse(body);
    assert.equal(data.count, 107);
    assert.equal(data.items.filter((item) => item[0] === 'p').length, 1);
    assert.equal(data.items.filter((item) => item[0] === 'a').length, 4);
    assert.equal(data.items[0][0], 'p');
    assert.ok(Buffer.byteLength(body) < 10000);
    assert.doesNotMatch(body, /hidden/);
    const legacy = await (await page.request.get(endpoint)).json();
    assert.ok(Array.isArray(legacy));
    assert.ok(legacy.filter((item) => item[0] === 'a').length <= 100);
    assert.ok(legacy.length <= 2100);
    assert.ok(legacy.some((item) => item[0] === 'p' && item[1].includes(marker)));
    assert.deepEqual(await (await api('')).json(), {items: [], count: 0});
    assert.equal((await (await api(`${rankMarker} omega`)).json()).items[0][1], `omega ${rankMarker}`);
    assert.equal((await (await api(`${rankMarker} ${rankMarker} omega`)).json()).items[0][1], `${rankMarker} omega`);
    const apiDe = async (query) => (await page.request.get(`${base}/?rest_route=/lasery/v1/search&lang=de&q=${encodeURIComponent(query)}`)).json();
    assert.equal((await apiDe(`${germanMarker} strasse`)).count, 2);
    assert.equal((await apiDe(`${germanMarker} straße`)).count, 2);
    assert.equal((await apiDe(`${germanMarker} strase`)).count, 1);
    assert.equal((await apiDe(`${germanMarker} strassen`)).count, 0);
    assert.equal((await apiDe('\u0301')).count, 0);
    php(`wp_update_post(['ID'=>${ids[105]}, 'post_password'=>'temporary']);`);
    assert.equal((await (await api(`${marker} zazolc`)).json()).count, 106);
    assert.equal((await (await api(`${marker} zazolc new105`)).json()).count, 0);
    assert.equal((await (await api(`${marker} zazolc old`)).json()).count, 1);
    php(`wp_update_post(['ID'=>${ids[105]}, 'post_password'=>'']);`);
    assert.equal((await (await api(`${marker} zazolc`)).json()).count, 107);
    assert.equal((await (await api(`${marker} zazolc new105`)).json()).count, 1);

    const nojs = await browser.newContext({ javaScriptEnabled: false });
    const fallback = await nojs.newPage();
    await fallback.goto(`${base}/?lang=pl&s=${encodeURIComponent(`${marker} zazolc old`)}`);
    assert.match(await fallback.locator('main').innerText(), new RegExp(`${marker}.*old`));
    await fallback.goto(`${base}/?lang=pl&s=${encodeURIComponent(`${marker} zazolc`)}`);
    assert.ok(await fallback.locator('.post-card, .product-card').count() <= 24);
    const link = fallback.locator('.pagination a').filter({ hasText: '5' }).first();
    assert.equal(new URL(await link.getAttribute('href')).searchParams.get('lang'), 'pl');
    await link.click();
    assert.match(await fallback.locator('main').innerText(), new RegExp(`${marker}.*old`));
    assert.ok(await fallback.locator('.post-card, .product-card').count() <= 24);
    await fallback.goto(`${base}/?lang=pl&s=${encodeURIComponent(`${marker} zazolc`)}&strona=999`);
    assert.match(await fallback.locator('main').innerText(), new RegExp(`${marker}.*old`));
    assert.ok(await fallback.locator('.pagination a').count() > 0);
    await fallback.goto(`${base}/?lang=de&s=${encodeURIComponent(`${germanMarker} straße`)}`);
    assert.equal(await fallback.locator('.post-card').count(), 2);
    await nojs.close();

    await page.goto(`${base}/?lang=pl`);
    await page.locator('[data-search-open]').click();
    await page.locator('[data-search] input[type=search]').fill(`${marker} zazolc old`);
    await page.locator('.search-result').waitFor();
    assert.match(await page.locator('.search-result').innerText(), /old/);
    console.log('Search over 100 articles: bounded REST, legacy client, accents, pagination and no-JS fallback passed.');
  } finally {
    await browser.close();
    php(`foreach (${JSON.stringify(ids)} as $id) wp_delete_post($id, true);`);
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
