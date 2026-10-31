// Run against a disposable local WordPress only; this creates and removes 104 posts.
// PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://127.0.0.1:18991 node tests/search-history.cjs
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local WordPress container and URL are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], {input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8'}).trim();
const marker = `FindOldArticle${Date.now()}`;
const fixture = JSON.parse(php(`
 $ids=[];
 $old=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'${marker} Historical','post_date'=>'2001-01-01 00:00:00','post_date_gmt'=>'2001-01-01 00:00:00']);
 $ids[]=$old; update_post_meta($old,'_lasery_language','pl');
 for ($i=0; $i<101; $i++) {
   $id=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'${marker} recent fixture '.$i]);
   $ids[]=$id; update_post_meta($id,'_lasery_language','pl');
 }
 $locked=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'${marker} Historical protected','post_password'=>'test-password']);
 $ids[]=$locked; update_post_meta($locked,'_lasery_language','pl');
 $other=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'${marker} Historical english']);
 $ids[]=$other; update_post_meta($other,'_lasery_language','en');
 lasery_xtool_search_refresh(); echo wp_json_encode(['ids'=>$ids,'old'=>$old]);
`));

(async () => {
 let browser;
 try {
  browser=await chromium.launch({headless:true,...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
  const context=await browser.newContext();
  const page=await context.newPage();
  const oldTitle=`${marker} Historical`;
  const url=new URL(base); url.searchParams.set('s',oldTitle); url.searchParams.set('lang','pl');
  const response=await page.goto(url.href);
  assert(response.ok());
  const results=page.locator('.search-page-results');
  assert((await results.textContent()).includes(oldTitle));
  assert(!(await results.textContent()).includes(`${oldTitle} protected`));
  assert(!(await results.textContent()).includes(`${oldTitle} english`));
  const noScriptContext=await browser.newContext({javaScriptEnabled:false});
  const noScript=await noScriptContext.newPage(); await noScript.goto(url.href);
  assert((await noScript.locator('.search-page-results').textContent()).includes(marker));
  const common = `${marker} recent fixture`;
  const query=new URL(base); query.searchParams.set('s',common); query.searchParams.set('lang','pl');
  await noScript.goto(query.href);
  const cards=noScript.locator('.search-page-results .post-card');
  assert.equal(await cards.count(),24);
  const firstPage=await cards.allTextContents();
  const second=noScript.locator('.pagination a').filter({hasText:/^2$/}).first();
  const link=new URL(await second.getAttribute('href'));
  assert.equal(link.searchParams.get('lang'),'pl');
  assert.equal(link.searchParams.get('s'),common);
  assert.equal(link.searchParams.get('strona'),'2');
  await second.click();
  assert.equal(await cards.count(),24);
  assert((await cards.allTextContents()).every(text=>!firstPage.includes(text)));
  const api=new URL(base); api.searchParams.set('rest_route','/lasery/v1/search'); api.searchParams.set('lang','pl'); api.searchParams.set('q',common);
  const data=await (await page.request.get(api.href)).json();
  assert.equal(data.count,101);
  assert(data.items.length <= 12);
  await noScriptContext.close();
  await page.goto(`${base}/?lang=pl`);
  await page.locator('[data-search-open]').first().click();
  await page.locator('[data-search] input[type=search]').fill(oldTitle);
  await page.locator('.search-result').filter({hasText:oldTitle}).first().waitFor();
  const titles=await page.locator('.search-result-title').allTextContents();
  assert(titles.includes(oldTitle));
  assert(!titles.includes(`${oldTitle} protected`));
  assert(!titles.includes(`${oldTitle} english`));
  await context.close();
  console.log('PASS old article, bounded REST and HTML pagination; protected and other-language posts are excluded');
 } finally {
  if (browser) await browser.close();
  php(`foreach (${JSON.stringify(fixture.ids)} as $id) wp_delete_post($id,true); lasery_xtool_search_refresh();`);
 }
})().catch(error=>{console.error(error);process.exitCode=1});
