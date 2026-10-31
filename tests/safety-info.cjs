// Run only against a disposable local WordPress; this test creates and removes products.
// PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://localhost:8093 node tests/safety-info.cjs
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local WordPress container and URL are required');
const php = script => execFileSync('docker', ['exec', '-i', container, 'php'], {input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8'}).trim();
const fixture = JSON.parse(php(`
 $ids=[];
 foreach (['9163846877397','9009695293653','9187692970197','9089720189141','9089525088469','9089336017109','9341134569685','1111111111111'] as $source) {
   $id=wp_insert_post(['post_type'=>LASERY_XTOOL_PRODUCT_TYPE,'post_status'=>'publish','post_title'=>'Safety fixture '.$source,'post_content'=>'Editable description']);
   update_post_meta($id,LASERY_XTOOL_SOURCE_ID,$source); $ids[$source]=['id'=>$id,'url'=>get_permalink($id)];
 }
 $page=get_page_by_path('bezpieczenstwo');
 echo wp_json_encode(['products'=>$ids,'safety'=>$page ? get_permalink($page) : null]);
`));
(async()=>{
 let browser;
 try {
  assert(fixture.safety, 'The published safety page must exist after installation');
  browser=await chromium.launch({headless:true,...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
  const context=await browser.newContext({viewport:{width:390,height:844}});
  const page=await context.newPage();
  const localize=(url,language)=>{const value=new URL(url);value.searchParams.set('lang',language);return value.href};
  const titles={pl:'Bezpieczeństwo pracy z laserem',en:'Laser safety',de:'Sicherheit beim Lasern',ru:'Безопасная работа с лазером',uk:'Безпечна робота з лазером'};
  for(const [language,title] of Object.entries(titles)) {
   const response=await page.goto(localize(fixture.safety,language));
   assert.equal(response.status(),200); assert.equal(await page.locator('main h1').textContent(),title);
   assert((await page.title()).includes(title));
   assert((await page.locator('meta[name="description"]').getAttribute('content')).length>0);
   assert.equal(await page.locator('link[rel="alternate"][hreflang]').count(),6);
   const canonical=await page.locator('link[rel="canonical"]').getAttribute('href');
   assert.equal(new URL(canonical).searchParams.get('lang'),language);
   assert((await page.locator('footer a').allTextContents()).includes(title));
   assert.equal(await page.evaluate(()=>document.querySelector('main').scrollWidth <= innerWidth),true);
  }
  const sources=['9163846877397','9009695293653','9187692970197','9089720189141','9089525088469','9089336017109','9341134569685'];
  for(const source of sources) {
   const response=await page.goto(localize(fixture.products[source].url,'pl')); assert.equal(response.status(),200);
   assert.equal(await page.locator('.product-safety').count(),1,source);
   assert.equal(new URL(await page.locator('.product-safety a').getAttribute('href')).searchParams.get('lang'),'pl');
  }
  await page.goto(localize(fixture.products['9163846877397'].url,'pl'));
  assert.match(await page.locator('.product-safety').innerText(),/klasy 4/);
  assert.match(await page.locator('.product-safety').innerText(),/zamkniętej osłonie/);
  assert.match(await page.locator('.product-safety').innerText(),/nie są dołączone/);
  php(`wp_update_post(['ID'=>${fixture.products['9163846877397'].id},'post_content'=>'A wholly new description']);`);
  await page.reload(); assert.match(await page.locator('.product-safety').innerText(),/klasy 4/);
  await page.goto(localize(fixture.products['9187692970197'].url,'pl'));
  assert.match(await page.locator('.product-safety').innerText(),/różnych klasach/);
  assert.doesNotMatch(await page.locator('.product-safety').innerText(),/jest laserem klasy 4/);
  await page.goto(localize(fixture.products['9089720189141'].url,'pl'));
  assert.match(await page.locator('.product-safety').innerText(),/z automatycznym podajnikiem/);
  await page.goto(localize(fixture.products['1111111111111'].url,'pl'));
  assert.equal(await page.locator('.product-safety').count(),0);
  console.log('Safety: localised page, metadata, model-specific notices, edited description and mobile layout passed.');
 } finally {
  if(browser) await browser.close();
  php(`foreach (${JSON.stringify(Object.values(fixture.products).map(x=>x.id))} as $id) wp_delete_post($id,true);`);
 }
})().catch(error=>{console.error(error);process.exitCode=1});
