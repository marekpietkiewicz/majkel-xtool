// Run only against disposable local WordPress: PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://127.0.0.1:PORT node tests/purchase-info.cjs
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local WordPress container and URL are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], {input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8'}).trim();
const fixture = JSON.parse(php(`
 $page = get_page_by_path('informacje-o-zakupach');
 if (!$page || $page->post_status !== 'publish') throw new RuntimeException('Purchase information page was not installed');
 $id = wp_insert_post(['post_type'=>'lasery_product','post_status'=>'publish','post_title'=>'Purchase information test']);
 update_post_meta($id, '_lasery_order_price', '12.34'); update_post_meta($id, '_lasery_order_currency', 'EUR');
 $f2 = wp_insert_post(['post_type'=>'lasery_product','post_status'=>'publish','post_title'=>'Safety cart test']);
 update_post_meta($f2, LASERY_XTOOL_SOURCE_ID, '9163846877397');
 update_post_meta($f2, '_lasery_source_variants', [
   ['id'=>'101','title'=>'Option A','available'=>true,'price'=>'12.34'],
   ['id'=>'102','title'=>'Option B','available'=>true,'price'=>'23.45'],
 ]);
 update_post_meta($f2, '_lasery_source_currency', 'EUR');
 update_post_meta($f2, '_lasery_source_updated_at', (string) time());
 echo wp_json_encode(['id'=>$id, 'f2'=>$f2]);
`));
const expected = {
 pl: ['Informacje o zakupach', 'Reklamacje i zwroty', 'Sprzedawcą jest xTool'],
 en: ['Purchase information', 'Complaints and returns', 'xTool is the seller'],
 de: ['Informationen zum Kauf', 'Reklamation und Rückgabe', 'Verkäufer ist xTool'],
 ru: ['Информация о покупке', 'Претензии и возврат', 'Продавцом является xTool'],
 uk: ['Інформація про покупку', 'Рекламації та повернення', 'Продавцем є xTool'],
};
(async () => {
 let browser;
 try {
  browser = await chromium.launch({headless:true,...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
  for (const [language, [title, rights, seller]] of Object.entries(expected)) {
   const context = await browser.newContext();
   await context.addCookies([{name:'lasery_cart', value:encodeURIComponent(`${fixture.id}::1`), url:base}]);
   const page = await context.newPage();
   await page.goto(`${base}/?pagename=koszyk&lang=${language}`);
   const link = page.locator('[data-cart-form] .order-summary a').filter({hasText:title});
   assert.equal(await link.count(), 1, `${language}: cart information link`);
   assert(await link.evaluate(node => Boolean(node.compareDocumentPosition(document.querySelector('[data-cart-send]:not(.visually-hidden)')) & Node.DOCUMENT_POSITION_FOLLOWING)), `${language}: link appears before submit`);
   const url = await link.getAttribute('href');
   assert.equal(new URL(url).searchParams.get('lang'), language);
   const footer = page.locator('footer nav[aria-labelledby="footer-info"] a').filter({hasText:title});
   assert.equal(await footer.count(), 1, `${language}: footer information link`);
   assert.equal(await footer.getAttribute('href'), url);
   const response = await page.goto(url);
   assert(response.ok(), `${language}: document returned ${response.status()}`);
   assert.equal(await page.locator('main h1').textContent(), title);
   assert((await page.title()).includes(title));
   assert((await page.locator('main').innerText()).includes(rights));
   assert((await page.locator('main').innerText()).includes(seller));
   assert((await page.locator('main').innerText()).includes('Makeblock Europe B.V.'));
   assert((await page.locator('main').innerText()).includes('Oostergracht 48, 3763 LZ Soest, Netherlands'));
   for (const href of ['mailto:service.eu@xtool.com', 'tel:+4921186089128', 'https://www.xtool.eu/pl-pl/pages/imprint-1', 'https://www.xtool.eu/pl-pl/pages/contact-us', 'https://www.xtool.eu/policies/terms-of-service', 'https://www.xtool.eu/policies/refund-policy', 'https://support.xtool.com/']) assert.equal(await page.locator(`main a[href="${href}"]`).count(), 1, `${language}: ${href}`);
   await context.close();
  }
  console.log('Purchase information: five languages, footer and pre-submit links, seller, rights and official xTool links passed.');
  {
   const context = await browser.newContext();
   await context.addCookies([{name:'lasery_cart', value:encodeURIComponent(`${fixture.f2}:101:1|${fixture.f2}:102:1|${fixture.id}::1`), url:base}]);
   const page = await context.newPage();
   await page.goto(`${base}/?pagename=koszyk&lang=pl`);
   await page.locator('#order-first-name').fill('Alicja');
   const first = page.locator(`[data-cart-line="${fixture.f2}:101"]`);
   const second = page.locator(`[data-cart-line="${fixture.f2}:102"]`);
   assert.equal(await page.locator('[data-cart-safety]').count(), 1);
   assert.match(await first.locator('[data-cart-safety]').innerText(), /okularów ochronnych/);
   assert.equal(await second.locator('[data-cart-safety]').count(), 0);
   await first.locator('.cart-line-remove').click();
   await first.waitFor({state:'detached'});
   assert.equal(await page.locator('[data-cart-safety]').count(), 1);
   assert.match(await second.locator('[data-cart-safety]').innerText(), /okularów ochronnych/);
   assert.equal(await page.locator('#order-first-name').inputValue(), 'Alicja');
   await second.locator('.cart-line-remove').click();
   await second.waitFor({state:'detached'});
   assert.equal(await page.locator('[data-cart-safety]').count(), 0);
   assert.equal(await page.locator('[data-cart-line]').count(), 1);
   assert.equal(await page.locator('#order-first-name').inputValue(), 'Alicja');
   await context.close();
   console.log('Cart safety: warning follows remaining F2 variant and disappears when F2 is removed, preserving customer fields.');
  }
 } finally {
  if (browser) await browser.close();
  php(`wp_delete_post(${fixture.id}, true); wp_delete_post(${fixture.f2}, true);`);
 }
})().catch(error => { console.error(error); process.exitCode=1; });
