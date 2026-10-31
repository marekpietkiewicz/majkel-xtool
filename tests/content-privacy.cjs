// Run only against a disposable local WordPress container, never against a live site.
// PLAYWRIGHT_CORE=/path/to/playwright-core WP_TEST_CONTAINER=... WP_TEST_URL=http://localhost:8091 node tests/content-privacy.cjs
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local container and URL are required');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8' }).trim();
const adminPhp = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php define('WP_ADMIN', true); require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8' }).trim();
const marker = `PrivateArticle${Date.now()}`;
const publicMarker = `PublicArticle${Date.now()}`;
const fixture = JSON.parse(php(`
  $public = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${publicMarker}', 'post_content'=>'<p>Public content</p>']);
  $private = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'${marker}', 'post_excerpt'=>'${marker} secret excerpt', 'post_content'=>'<p>${marker} secret article content</p>']);
  $author = wp_create_user('author${Date.now()}', 'testpassword', 'author${Date.now()}@example.test');
  (new WP_User($author))->set_role('author');
  $own = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_author'=>$author, 'post_password'=>'own-password', 'post_title'=>'Author private fixture']);
  $privateMedia = wp_insert_attachment(['post_title'=>'Private media fixture', 'post_mime_type'=>'image/jpeg', 'guid'=>home_url('/wp-content/uploads/private-fixture.jpg')], false, $private);
  $ownMedia = wp_insert_attachment(['post_title'=>'Author media fixture', 'post_mime_type'=>'image/jpeg', 'guid'=>home_url('/wp-content/uploads/author-fixture.jpg')], false, $own);
  $publicMedia = wp_insert_attachment(['post_title'=>'Public media fixture', 'post_mime_type'=>'image/jpeg', 'guid'=>home_url('/wp-content/uploads/public-fixture.jpg')], false, $public);
  update_post_meta($private, '_lasery_language', 'en');
  update_post_meta($private, '_lasery_translation_of', $public);
  $blog = get_page_by_path('blog'); $cart = get_page_by_path('koszyk');
  $front = wp_insert_post(['post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Test front page']);
  echo wp_json_encode(['private'=>$private, 'privateUrl'=>get_permalink($private), 'public'=>$public, 'publicUrl'=>get_permalink($public), 'author'=>$author, 'own'=>$own, 'privateMedia'=>$privateMedia, 'ownMedia'=>$ownMedia, 'publicMedia'=>$publicMedia, 'blog'=>$blog->ID, 'blogUrl'=>get_permalink($blog), 'cart'=>$cart->ID, 'cartUrl'=>get_permalink($cart), 'front'=>$front, 'oldFront'=>get_option('page_on_front'), 'oldShow'=>get_option('show_on_front')]);
`));
const localized = (url, language) => { const result = new URL(url); result.searchParams.set('lang', language); return result.href; };
const checkNoCache = (response) => assert.match(response.headers()['cache-control'], /no-cache|no-store/);
const unlock = async (page, password) => {
  await page.locator('form.post-password-form input[type=password]').fill(password);
  await Promise.all([page.waitForURL(/./), page.locator('form.post-password-form input[type=submit]').click()]);
};

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const one = await browser.newContext();
    const two = await browser.newContext();
    const a = await one.newPage();
    const b = await two.newPage();
    const search = async (page, language) => (await page.request.get(`${base}/wp-json/lasery/v1/search?lang=${language}&q=${encodeURIComponent(language === 'pl' ? publicMarker : marker)}`)).text();
    assert.match(await search(a, 'pl'), new RegExp(publicMarker));
    assert.match(await (await a.request.get(fixture.publicUrl)).text(), /Public content/);
    assert.match(await (await a.request.get(localized(fixture.blogUrl, 'pl'))).text(), new RegExp(publicMarker));
    assert.match(await search(a, 'en'), new RegExp(marker));
    assert.equal(php(`wp_update_post(['ID'=>${fixture.private}, 'post_password'=>'post-password']); echo did_action('litespeed_purge_all');`), '1');
    const switchedUsers = JSON.parse(php(`wp_set_current_user(get_user_by('login', 'testadmin')->ID); $admin = lasery_xtool_rest_hidden_posts(); wp_set_current_user(${fixture.author}); $author = lasery_xtool_rest_hidden_posts(); echo wp_json_encode(['admin'=>$admin,'author'=>$author]);`));
    assert.ok(!switchedUsers.admin.includes(fixture.private));
    assert.ok(switchedUsers.author.includes(fixture.private));
    assert.ok(!switchedUsers.author.includes(fixture.own));
    const translations = JSON.parse(php(`echo wp_json_encode(lasery_xtool_post_translations(${fixture.public}));`));
    const editorTranslations = JSON.parse(adminPhp(`echo wp_json_encode(lasery_xtool_post_translations(${fixture.public}, false));`));
    assert.ok(!Object.values(translations).includes(fixture.private));
    assert.ok(Object.values(editorTranslations).includes(fixture.private));

    for (const page of [a, b]) {
      const locked = await page.goto(fixture.privateUrl);
      checkNoCache(locked);
      assert.equal(await page.locator('form.post-password-form').count(), 1);
      assert.doesNotMatch(await page.content(), /secret (excerpt|article content)/);
      assert.doesNotMatch(await (await page.request.get(localized(fixture.blogUrl, 'en'))).text(), new RegExp(marker));
      const sitemap = await page.request.get(`${base}/wp-sitemap-posts-post-1.xml`);
      assert.ok(sitemap.ok());
      assert.ok(!(await sitemap.text()).includes(new URL(fixture.privateUrl).pathname.split('/').filter(Boolean).pop()));
      for (const url of [`${base}/wp-json/wp/v2/posts?search=${marker}`, `${base}/wp-json/wp/v2/search?search=${marker}&subtype=post`]) {
        assert.doesNotMatch(await (await page.request.get(url)).text(), new RegExp(marker), url);
      }
      assert.equal((await page.request.get(`${base}/wp-json/wp/v2/posts/${fixture.private}`)).status(), 404);
      const embed = await page.request.get(`${base}/wp-json/oembed/1.0/embed?url=${encodeURIComponent(fixture.privateUrl)}`);
      assert.equal(embed.status(), 404);
      assert.doesNotMatch(await embed.text(), new RegExp(marker));
      assert.equal((await page.request.get(`${base}/wp-json/wp/v2/media/${fixture.privateMedia}`)).status(), 404);
      assert.ok(!(await (await page.request.get(`${base}/wp-json/wp/v2/media?parent=${fixture.private}`)).text()).includes('Private media fixture'));
      assert.ok(!(await (await page.request.get(`${base}/?rest_route=/wp/v2/media&parent=${fixture.private}`)).text()).includes('Private media fixture'));
      const mediaSearch = await page.request.get(`${base}/wp-json/wp/v2/search?search=Private%20media%20fixture`);
      assert.ok(mediaSearch.ok());
      assert.doesNotMatch(await mediaSearch.text(), /Private media fixture/);
      assert.equal((await page.request.get(`${base}/wp-json/wp/v2/media/${fixture.publicMedia}`)).status(), 200);
    }
    await unlock(a, 'post-password');
    const unlocked = await a.goto(fixture.privateUrl);
    checkNoCache(unlocked);
    assert.match(await a.content(), /secret article content/);
    const head = await a.locator('head').innerHTML();
    assert.doesNotMatch(head, /secret (excerpt|article content)|application\/ld\+json|rel="canonical"/);
    assert.match(head, /noindex/);
    // Force a rebuild with the unlocked visitor's cookie, then check another visitor's shared response.
    php(`delete_transient('lasery_xtool_search_en');`);
    assert.doesNotMatch(await search(a, 'en'), new RegExp(marker));
    assert.doesNotMatch(await search(b, 'en'), new RegExp(marker));
    assert.equal((await a.request.get(`${base}/wp-json/wp/v2/posts/${fixture.private}`)).status(), 404);
    await b.goto(fixture.privateUrl);
    assert.doesNotMatch(await b.content(), /secret article content/);

    const pages = [
      { id: fixture.blog, url: localized(fixture.blogUrl, 'pl'), selector: '.post-grid', password: 'blog-password' },
      { id: fixture.cart, url: localized(fixture.cartUrl, 'pl'), selector: '.cart-page', password: 'cart-password' },
    ];
    php(`update_option('show_on_front', 'page'); update_option('page_on_front', ${fixture.front});`);
    pages.push({ id: fixture.front, url: `${base}/?lang=pl`, selector: '.hero', password: 'front-password' });
    for (const item of pages) {
      assert.equal(php(`wp_update_post(['ID'=>${item.id}, 'post_password'=>'${item.password}']); echo did_action('litespeed_purge_all');`), '1');
      const locked = await a.goto(item.url);
      checkNoCache(locked);
      assert.equal(await a.locator('form.post-password-form').count(), 1, item.url);
      assert.equal(await a.locator(item.selector).count(), 0, item.url);
      assert.equal((await a.request.get(`${base}/wp-json/wp/v2/pages/${item.id}`)).status(), 404);
      assert.ok(!(await (await a.request.get(`${base}/wp-json/wp/v2/pages?include=${item.id}`)).text()).includes(`"id":${item.id}`));
      await unlock(a, item.password);
      const open = await a.goto(item.url);
      checkNoCache(open);
      assert.equal(await a.locator(item.selector).count(), 1, item.url);
      assert.doesNotMatch(await a.locator('head').innerHTML(), /application\/ld\+json|rel="canonical"/);
      await b.goto(item.url);
      assert.equal(await b.locator('form.post-password-form').count(), 1, item.url);
      php(`wp_update_post(['ID'=>${item.id}, 'post_password'=>'']);`);
      await b.goto(item.url);
      assert.equal(await b.locator(item.selector).count(), 1, item.url);
    }
    const editor = await browser.newContext();
    const editorPage = await editor.newPage();
    await editorPage.goto(`${base}/wp-login.php`);
    await editorPage.locator('#user_login').fill('testadmin');
    await editorPage.locator('#user_pass').fill('testpassword');
    await Promise.all([editorPage.waitForURL(/wp-admin/), editorPage.locator('#wp-submit').click()]);
    const loggedIn = (await editor.cookies()).find((cookie) => cookie.name.startsWith('wordpress_logged_in_'));
    assert.ok(loggedIn);
    const cookie = Buffer.from(decodeURIComponent(loggedIn.value)).toString('base64');
    const nonce = php(`$_COOKIE[LOGGED_IN_COOKIE]=base64_decode('${cookie}'); wp_set_current_user(get_user_by('login', 'testadmin')->ID); echo wp_create_nonce('wp_rest');`);
    const editorPost = await editor.request.get(`${base}/wp-json/wp/v2/posts/${fixture.private}?context=edit`, { headers: { 'X-WP-Nonce': nonce } });
    const editorBody = await editorPost.text();
    assert.equal(editorPost.status(), 200, editorBody);
    assert.match(editorBody, /secret article content/);
    const editorCollection = await editor.request.get(`${base}/wp-json/wp/v2/posts?context=edit&search=${marker}`, { headers: { 'X-WP-Nonce': nonce } });
    assert.match(await editorCollection.text(), new RegExp(marker));
    assert.equal((await editor.request.get(`${base}/wp-json/wp/v2/media/${fixture.privateMedia}?context=edit`, { headers: { 'X-WP-Nonce': nonce } })).status(), 200);
    php(`wp_update_post(['ID'=>${fixture.blog}, 'post_password'=>'editor-page-password']);`);
    assert.equal((await editor.request.get(`${base}/wp-json/wp/v2/pages/${fixture.blog}?context=edit`, { headers: { 'X-WP-Nonce': nonce } })).status(), 200);
    await editor.close();
    const authorLogin = php(`echo get_user_by('id', ${fixture.author})->user_login;`);
    const author = await browser.newContext();
    const authorPage = await author.newPage();
    await authorPage.goto(`${base}/wp-login.php`);
    await authorPage.locator('#user_login').fill(authorLogin);
    await authorPage.locator('#user_pass').fill('testpassword');
    await Promise.all([authorPage.waitForURL(/wp-admin/), authorPage.locator('#wp-submit').click()]);
    const authorCookie = (await author.cookies()).find((item) => item.name.startsWith('wordpress_logged_in_'));
    const authorToken = Buffer.from(decodeURIComponent(authorCookie.value)).toString('base64');
    const authorNonce = php(`$_COOKIE[LOGGED_IN_COOKIE]=base64_decode('${authorToken}'); wp_set_current_user(${fixture.author}); echo wp_create_nonce('wp_rest');`);
    const authorHeaders = { 'X-WP-Nonce': authorNonce };
    assert.equal((await author.request.get(`${base}/wp-json/wp/v2/posts/${fixture.own}?context=edit`, { headers: authorHeaders })).status(), 200);
    assert.equal((await author.request.get(`${base}/wp-json/wp/v2/posts/${fixture.private}`, { headers: authorHeaders })).status(), 404);
    assert.equal((await author.request.get(`${base}/wp-json/wp/v2/pages/${fixture.blog}`, { headers: authorHeaders })).status(), 404);
    const authoredPosts = await (await author.request.get(`${base}/wp-json/wp/v2/posts?include=${fixture.own},${fixture.private}`, { headers: authorHeaders })).json();
    assert.ok(authoredPosts.some((post) => post.id === fixture.own));
    assert.ok(!authoredPosts.some((post) => post.id === fixture.private));
    assert.equal((await author.request.get(`${base}/wp-json/wp/v2/media/${fixture.ownMedia}`, { headers: authorHeaders })).status(), 200);
    assert.equal((await author.request.get(`${base}/wp-json/wp/v2/media/${fixture.privateMedia}`, { headers: authorHeaders })).status(), 404);
    const authoredMedia = await (await author.request.get(`${base}/wp-json/wp/v2/media?include=${fixture.ownMedia},${fixture.privateMedia}`, { headers: authorHeaders })).json();
    assert.ok(authoredMedia.some((media) => media.id === fixture.ownMedia));
    assert.ok(!authoredMedia.some((media) => media.id === fixture.privateMedia));
    await author.close();
    console.log('Protected articles and pages: two sessions, shared search, CMS translations and custom page templates passed.');
  } finally {
    await browser.close();
    php(`foreach ([${fixture.blog}, ${fixture.cart}, ${fixture.front}] as $id) wp_update_post(['ID'=>$id, 'post_password'=>'']); update_option('show_on_front', '${fixture.oldShow}'); update_option('page_on_front', ${Number(fixture.oldFront)}); foreach ([${fixture.privateMedia}, ${fixture.ownMedia}, ${fixture.publicMedia}, ${fixture.private}, ${fixture.public}, ${fixture.own}, ${fixture.front}] as $id) wp_delete_post($id, true); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user(${fixture.author});`);
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
