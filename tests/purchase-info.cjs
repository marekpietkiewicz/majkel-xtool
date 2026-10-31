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
 $articles = [];
 foreach (['pl','en','de','ru','uk'] as $lang) {
   $article = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Article test '.$lang,'post_content'=>'[lasery_produkty ids="'.$id.'"]']);
   update_post_meta($article, '_lasery_language', $lang);
   $articles[$lang] = $article;
 }
 lasery_xtool_search_refresh();
 echo wp_json_encode(['id'=>$id, 'f2'=>$f2, 'articles'=>$articles]);
`));
const expected = {
 pl: ['Informacje o zakupach', 'Reklamacje i zwroty', 'Sprzedawcą jest xTool', 'Regulamin korzystania z serwisu', '14 dni', 'Reklama produktów xTool.', 'Jesteśmy autoryzowanym partnerem xTool.'],
 en: ['Purchase information', 'Complaints and returns', 'xTool is the seller', 'Website terms of use', '14 days', 'xTool product advertising.', 'We are an authorized xTool partner.'],
 de: ['Informationen zum Kauf', 'Reklamation und Rückgabe', 'Verkäufer ist xTool', 'Nutzungsbedingungen der Website', '14 Tagen', 'Werbung für xTool-Produkte.', 'Wir sind autorisierter xTool-Partner.'],
 ru: ['Информация о покупке', 'Претензии и возврат', 'Продавцом является xTool', 'Правила пользования сайтом', '14 дней', 'Реклама продукции xTool.', 'Мы являемся авторизованным партнёром xTool.'],
 uk: ['Інформація про покупку', 'Рекламації та повернення', 'Продавцем є xTool', 'Правила користування сайтом', '14 днів', 'Реклама продукції xTool.', 'Ми є авторизованим партнером xTool.'],
};
(async () => {
 let browser;
 try {
  browser = await chromium.launch({headless:true,...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
  for (const [language, [title, rights, seller, serviceTitle, complaintPeriod, advertising, partnership]] of Object.entries(expected)) {
   const context = await browser.newContext();
   await context.addCookies([{name:'lasery_cart', value:encodeURIComponent(`${fixture.id}::1`), url:base}]);
   const page = await context.newPage();
   await page.goto(`${base}/?pagename=koszyk&lang=${language}`);
   assert((await page.locator('.order-summary .commercial-disclosure').innerText()).includes(advertising));
   assert((await page.locator('.order-summary .commercial-disclosure').innerText()).includes(partnership));
   const link = page.locator('[data-cart-form] .order-summary a').filter({hasText:title});
   assert.equal(await link.count(), 1, `${language}: cart information link`);
   assert(await link.evaluate(node => Boolean(node.compareDocumentPosition(document.querySelector('[data-cart-send]:not(.visually-hidden)')) & Node.DOCUMENT_POSITION_FOLLOWING)), `${language}: link appears before submit`);
   const url = await link.getAttribute('href');
   assert.equal(new URL(url).searchParams.get('lang'), language);
   const footer = page.locator('footer nav[aria-labelledby="footer-info"] a').filter({hasText:title});
   assert.equal(await footer.count(), 1, `${language}: footer information link`);
   assert.equal(await footer.getAttribute('href'), url);
   const serviceLink = page.locator('footer nav[aria-labelledby="footer-info"] a').filter({hasText:serviceTitle});
   assert.equal(await serviceLink.count(), 1, `${language}: site terms link`);
   assert.equal(await serviceLink.getAttribute('href'), `${url}#regulamin`);
   const response = await page.goto(url);
   assert(response.ok(), `${language}: document returned ${response.status()}`);
   assert.equal(await page.locator('main h1').textContent(), title);
   assert((await page.title()).includes(title));
   assert((await page.locator('main').innerText()).includes(rights));
   assert((await page.locator('main').innerText()).includes(seller));
   assert((await page.locator('main').innerText()).includes('Makeblock Europe B.V.'));
   assert((await page.locator('main').innerText()).includes('Oostergracht 48, 3763 LZ Soest, Netherlands'));
   assert.equal(await page.locator('#regulamin h2').textContent(), serviceTitle);
   assert((await page.locator('#regulamin').innerText()).includes(complaintPeriod));
   for (const href of ['mailto:service.eu@xtool.com', 'tel:+4921186089128', 'https://www.xtool.eu/pl-pl/pages/imprint-1', 'https://www.xtool.eu/pl-pl/pages/contact-us', 'https://www.xtool.eu/policies/terms-of-service', 'https://www.xtool.eu/policies/refund-policy', 'https://www.xtool.eu/pages/aftersale-policies-v3', 'https://support.xtool.com/']) assert.equal(await page.locator(`main a[href="${href}"]`).count(), 1, `${language}: ${href}`);
   await page.goto(`${base}/?post_type=lasery_product&p=${fixture.id}&lang=${language}`);
   assert((await page.locator('.product-summary .commercial-disclosure').innerText()).includes(advertising));
   assert((await page.locator('.product-summary .commercial-disclosure').innerText()).includes(partnership));
   await page.goto(`${base}/?p=${fixture.articles[language]}`);
   assert((await page.locator('.article-header .commercial-disclosure').innerText()).includes(advertising));
   assert((await page.locator('.article-header .commercial-disclosure').innerText()).includes(partnership));
   await page.goto(`${base}/?lang=${language}`);
   assert((await page.locator('.hero > .wrap .commercial-disclosure').innerText()).includes(partnership));
   await page.locator('[data-search-open]').first().click();
   assert(await page.locator('[data-search] .commercial-disclosure').isVisible());
   assert((await page.locator('[data-search] .commercial-disclosure').innerText()).includes(advertising));
   await page.goto(`${base}/?post_type=lasery_product&lang=${language}`);
   assert((await page.locator('.page-hero .commercial-disclosure').innerText()).includes(partnership));
   await page.goto(`${base}/?s=Purchase+information+test&lang=${language}`);
   assert((await page.locator('.search-page-section .commercial-disclosure').innerText()).includes(partnership));
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
  php(`wp_delete_post(${fixture.id}, true); wp_delete_post(${fixture.f2}, true); foreach (${JSON.stringify(Object.values(fixture.articles))} as $id) wp_delete_post($id, true); lasery_xtool_search_refresh();`);
 }
})().catch(error => { console.error(error); process.exitCode=1; });
