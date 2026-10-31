// Run only against a disposable local WordPress and Mailpit instance; this test creates products,
// submits orders, clears rate limits and briefly stops Mailpit.
// PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://127.0.0.1:18991 \
// WP_TEST_MAILPIT_URL=http://127.0.0.1:18992 WP_TEST_MAILPIT_CONTAINER=... node tests/cart-browser.cjs
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
const mailpit = process.env.WP_TEST_MAILPIT_URL;
const mailContainer = process.env.WP_TEST_MAILPIT_CONTAINER;
if (!container || !mailContainer || !base || !mailpit || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base) || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(mailpit)) throw new Error('A disposable local WordPress and Mailpit container are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], {input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8'}).trim();
const fixture = JSON.parse(php(`
  $old = get_option('lasery_xtool_order_recipient', '');
  update_option('lasery_xtool_order_recipient', 'owner@example.test');
  $ids = [];
  foreach (['Alpha', 'Beta'] as $name) {
    $id = wp_insert_post(['post_title'=>'Cart browser test '.$name, 'post_type'=>LASERY_XTOOL_PRODUCT_TYPE, 'post_status'=>'publish']);
    update_post_meta($id, '_lasery_order_price', '12.34');
    update_post_meta($id, '_lasery_order_currency', 'EUR');
    $ids[] = $id;
  }
  echo wp_json_encode(['ids'=>$ids, 'recipient'=>$old]);
`));
const [alpha, beta] = fixture.ids;
const reset = () => php(`global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_lasery\\_order\\_rate\\_%'");`);
const mails = async () => (await (await fetch(`${mailpit}/api/v1/messages`)).json()).total;
async function create(browser, cookie = `${alpha}::1|${beta}::1`, js = true) {
 const c = await browser.newContext({javaScriptEnabled: js});
 await c.addCookies([{name:'lasery_cart', value:encodeURIComponent(cookie), url:base}]);
 const p = await c.newPage();
 p.on('pageerror', error => { console.error('PAGEERROR',error); process.exitCode = 1; });
 await p.goto(`${base}/?pagename=koszyk&lang=pl`);
 return [c,p];
}
async function details(p) {
 await p.locator('#order-first-name').fill('Alicja');
 await p.locator('#order-last-name').fill('Przykładowa');
 await p.locator('#order-email').fill('client@example.test');
 await p.locator('#order-phone').fill('+48123456789');
 await p.locator('#order-address1').fill('Tajna 123');
 await p.locator('#order-zip').fill('00-001');
 await p.locator('#order-city').fill('Warszawa');
 await p.locator('#order-country').selectOption('PL');
 await p.locator('#order-invoice').fill('Tajna faktura');
 await p.locator('#order-notes').fill('Tajna uwaga');
}
const submit = p => p.locator('button.btn-primary[data-cart-send]').click();
const hasDetails = async p => { assert.equal(await p.locator('#order-first-name').inputValue(),'Alicja'); assert.equal(await p.locator('#order-address1').inputValue(),'Tajna 123'); assert.equal(await p.locator('#order-invoice').inputValue(),'Tajna faktura'); };
(async()=>{
 const browser=await chromium.launch({headless:true, ...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
 try {
  {
   const [c,p]=await create(browser); await details(p);
   let updateBody; p.on('request',req=>{if(req.method()==='POST'&&req.url().includes('lasery_xtool_cart_update'))updateBody=req.postData()});
   await p.locator('.cart-line').nth(1).locator('[data-quantity]').fill('2');
   await p.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('lasery_xtool_cart_update'));
   assert(updateBody); for(const secret of ['Alicja','client@example.test','Tajna','123456789']) assert(!updateBody.includes(secret));
   assert(updateBody.includes(`quantity[${beta}:]`)); assert(updateBody.includes('language')); assert(updateBody.includes('_wpnonce'));
   await hasDetails(p); await c.close(); console.log('PASS update excludes personal fields');
  }
  {
   const [c,p]=await create(browser); await details(p);
   let release; const gate=new Promise(r=>release=r); let started; const start=new Promise(r=>started=r); let first=true;
   await p.route('**/wp-admin/admin-post.php**',async(route)=>{
    if(first && route.request().method()==='POST' && route.request().url().includes('lasery_xtool_cart_update')) {first=false; started(); await gate;}
    await route.continue();
   });
   const beta=p.locator('#cart-quantity-1');
   await beta.fill('2'); await Promise.race([start, new Promise((_, reject) => setTimeout(() => reject(new Error('update did not start')), 5000))]);
   await p.locator('.cart-line').first().locator('[form="cart-remove"]').click();
   await beta.fill('3'); release();
   await p.waitForTimeout(1000); assert.equal(await beta.inputValue(),'3');
   assert.equal(await p.locator('.cart-line').count(),1); await hasDetails(p); await c.close(); console.log('PASS delayed update and removal preserve newer input');
  }
  {
   const [c,p]=await create(browser); await details(p); let requests=[]; let n=0;
   await p.route('**/wp-admin/admin-post.php**', async route=>{
     const req=route.request();
     if(req.method()==='POST'&&req.url().includes('lasery_xtool_cart_update')) {requests.push(req.postData()); if(n++===0){await route.abort('failed'); return;}}
     await route.continue();
   });
   await p.locator('#cart-quantity-1').fill('2');
   await p.waitForURL(/cart=updated/);
   assert.equal(requests.length,2);
   for(const raw of requests) {const body=decodeURIComponent(raw.replace(/\+/g,' ')); assert(body.includes(`quantity[${beta}:]`)); for(const secret of ['Alicja','client@example.test','Tajna','123456789']) assert(!body.includes(secret));}
   await c.close(); console.log('PASS failed update falls back without personal fields');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser); await details(p);
   let release; const gate=new Promise(r=>release=r); let started; const start=new Promise(r=>started=r); let orders=0; let first=true;
   await p.route('**/wp-admin/admin-post.php**',async route=>{
     const req=route.request();
     if(first&&req.method()==='POST'&&req.url().includes('lasery_xtool_cart_update')) {first=false;started();await gate;}
     if(req.method()==='POST'&&req.url().includes('lasery_xtool_order')) orders++;
     await route.continue();
   });
   await p.locator('#cart-quantity-1').fill('2'); await start;
   await submit(p); await p.waitForTimeout(200); assert.equal(orders,0); release();
   await p.waitForURL(/cart=sent/); assert.equal(orders,1); assert.equal(await mails(),before+2);
   await c.close(); console.log('PASS order waits for pending update and sends once');
  }
  {
   reset(); const [c,p]=await create(browser); await details(p);
   execFileSync('docker',['stop',mailContainer],{stdio:'ignore'});
   try {
     await submit(p); await p.locator('[data-cart-error]').waitFor({timeout:15000}); await hasDetails(p);
     assert.match(await p.locator('[data-cart-error]').textContent(),/Nie udało się ustalić/);
     assert(await p.locator('button.btn-primary[data-cart-send]').isDisabled());
     const [noJsContext,noJsPage]=await create(browser,`${alpha}::1`,false);
     try {
      await details(noJsPage);
      await noJsPage.locator('button.btn-primary[data-cart-send]').evaluate(el => el.click());
      await noJsPage.waitForURL(/cart=failed/);
      assert.match(await noJsPage.locator('[data-cart-error]').textContent(),/Nie udało się ustalić/);
     } finally {await noJsContext.close();}
   } finally {execFileSync('docker',['start',mailContainer],{stdio:'ignore'});await c.close();}
   for(let i=0;i<30;i++){try {await mails();break;}catch {await new Promise(r=>setTimeout(r,100));}} console.log('PASS SMTP failure reports uncertain status without retry');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser); await details(p);
   await p.locator('[name="_wpnonce"]').evaluate(el=>el.value='invalid'); await submit(p);
   await p.locator('[data-cart-error]').waitFor(); await hasDetails(p);
   assert.equal(await mails(),before); assert(await p.locator('button.btn-primary[data-cart-send]').isEnabled());
   assert.notEqual(await p.locator('[name="_wpnonce"]').inputValue(),'invalid'); await c.close(); console.log('PASS rejected nonce preserves fields and refreshes nonce');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser); await details(p);
   await p.locator('[name="snapshot"]').evaluate(el=>el.value='invalid'); await submit(p);
   await p.locator('[data-cart-error]').waitFor(); await hasDetails(p); assert.equal(await mails(),before);
   assert.notEqual(await p.locator('[name="snapshot"]').inputValue(),'invalid'); await c.close(); console.log('PASS changed offer preserves fields and refreshes snapshot');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser); await details(p);
   await submit(p); await p.waitForURL(/cart=sent/); assert.equal(await mails(),before+2);
   const list=await (await fetch(`${mailpit}/api/v1/messages`)).json();
   const customer=list.messages.find(message=>message.To.some(recipient=>recipient.Address==='client@example.test'));
   const message=await (await fetch(`${mailpit}/api/v1/message/${customer.ID}`)).json();
   assert(message.Text.includes('Sprzedawcą jest xTool. Link do płatności prowadzi do xTool.'));
   assert(!p.url().includes('Alicja')&&!p.url().includes('client%40')); await c.close(); console.log('PASS successful order and receipt once');
  }
  {
   const before=await mails(); const [c,p]=await create(browser); await details(p); await submit(p);
   await p.locator('[data-cart-error]').waitFor(); await hasDetails(p); assert.equal(await mails(),before);
   assert(await p.locator('button.btn-primary[data-cart-send]').isEnabled()); await c.close(); console.log('PASS rate limit preserves data');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser); await details(p);
   let orders=0;
   await p.route('**/wp-admin/admin-post.php**',async route=>{if(route.request().url().includes('lasery_xtool_order')){orders++; await route.fetch(); await route.abort('failed');}else await route.continue();});
   await submit(p); await p.locator('[data-cart-error]').waitFor(); await hasDetails(p);
   assert.equal(await mails(),before+2); assert.equal(orders,1); assert(await p.locator('button.btn-primary[data-cart-send]').isDisabled());
   assert.match(await p.locator('[data-cart-error]').textContent(),/Nie udało się ustalić/);
   await p.waitForTimeout(500); assert.equal(orders,1); await c.close(); console.log('PASS lost response after delivery does not resend');
  }
  {
   reset(); const [c,p]=await create(browser,`${alpha}::1`); await details(p); const before=await mails();
   php(`wp_update_post(['ID'=>${alpha}, 'post_password'=>'secret']);`);
   try { await submit(p); await p.locator('[data-cart-error]').waitFor(); await hasDetails(p); assert(await p.locator('button.btn-primary[data-cart-send]').isDisabled()); assert.equal(await mails(),before); }
   finally {php(`wp_update_post(['ID'=>${alpha}, 'post_password'=>'']);`); await c.close();}
   console.log('PASS cart becomes empty while ordering without data loss or email');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser,`${alpha}::1`,false); await details(p); await p.locator('button.btn-primary[data-cart-send]').evaluate(el => el.click());
   await p.waitForURL(/cart=sent/); assert.equal(await mails(),before+2); await c.close(); console.log('PASS no JavaScript successful order');
  }
  {
   reset(); const before=await mails(); const [c,p]=await create(browser,`${alpha}::1`,false); await details(p);
   await p.locator('[name="_wpnonce"]').evaluate(el => { el.value = 'invalid'; });
   await p.locator('button.btn-primary[data-cart-send]').evaluate(el => el.click());
   await p.waitForURL(/cart=invalid/); assert.equal(await mails(),before); await c.close(); console.log('PASS no JavaScript rejection does not send mail');
  }
  {
   const original = php(`echo base64_encode(serialize(get_option('lasery_xtool_text_overrides', [])));`);
   php(`$all=get_option('lasery_xtool_text_overrides', []); foreach (['pl','en','de','ru','uk'] as $lang) $all[$lang]['order']['failed']='OLD_RETRY_OVERRIDE'; update_option('lasery_xtool_text_overrides', $all);`);
   try {
    for (const language of ['pl','en','de','ru','uk']) {
     const [c,p]=await create(browser,`${alpha}::1`,false);
     await p.goto(`${base}/?pagename=koszyk&lang=${language}&cart=failed`);
     const message=await p.locator('[data-cart-error]').textContent();
     assert(!message.includes('OLD_RETRY_OVERRIDE')); assert(message.includes('@'));
     await c.close();
    }
    php(`$all=get_option('lasery_xtool_text_overrides', []); $all['pl']['order']['outcome_unknown']='Status 100% %d %s'; update_option('lasery_xtool_text_overrides', $all);`);
    const [c,p]=await create(browser,`${alpha}::1`,false);
    await p.goto(`${base}/?pagename=koszyk&lang=pl&cart=failed`);
    const message=await p.locator('[data-cart-error]').textContent();
    assert(message.includes('100% %d')); assert(message.includes('@'));
    assert.equal(await p.locator('[data-cart-form]').getAttribute('data-order-unknown'),message);
    await c.close();
   } finally { php(`update_option('lasery_xtool_text_overrides', unserialize(base64_decode('${original}')));`); }
   console.log('PASS uncertain server outcome in all languages ignores legacy retry overrides');
  }
  {
   const expected=JSON.parse(php(`$texts=[]; $lines=lasery_xtool_cart_lines([[${alpha},'',1]]); foreach (['pl','en','de','ru','uk'] as $lang) $texts[$lang]=[lasery_xtool_order_texts($lang)['seller_note'],lasery_xtool_order_emails_customer($lines,$lang)['note']]; echo wp_json_encode($texts);`));
   for (const language of Object.keys(expected)) {
    const [c,p]=await create(browser,`${alpha}::1`,false);
    await p.goto(`${base}/?pagename=koszyk&lang=${language}`);
    assert((await p.locator('.order-summary').textContent()).includes(expected[language][0]));
    assert(expected[language][1].includes(expected[language][0]));
    await c.close();
   }
   console.log('PASS seller and payment information in each cart language');
  }
 } finally {
  await browser.close();
  const recipient = Buffer.from(fixture.recipient).toString('base64');
  php(`wp_delete_post(${alpha}, true); wp_delete_post(${beta}, true); update_option('lasery_xtool_order_recipient', base64_decode('${recipient}'));`);
 }
})().catch(e=>{console.error(e);process.exitCode=1});
