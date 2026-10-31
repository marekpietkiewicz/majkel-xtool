// PLAYWRIGHT_CORE=/path/to/playwright-core node tests/search-cache.cjs
// CHROME_BIN is optional when Playwright's Chromium is installed.
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const { readFileSync } = require('node:fs');
const { createServer } = require('node:http');
const { join } = require('node:path');
const assert = require('node:assert/strict');

const site = readFileSync(join(__dirname, '../wp-content/themes/lasery-xtool/assets/site.js'));
const requests = [];
let privateNow = false;
let heldQuery = '';
let release;
let started;
const server = createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (url.pathname === '/index') {
    const query = url.searchParams.get('q');
    requests.push(query);
    if (query === 'Broken') {
      response.writeHead(503);
      response.end();
      return;
    }
    const title = query === 'Latest' ? 'Latest item' : `${query} item`;
    const items = privateNow ? [] : [['p', title, '', '/product', '', '', 'Product']];
    const body = JSON.stringify({items, count: items.length});
    const respond = () => {
      response.writeHead(200, {'Content-Type': 'application/json', 'Cache-Control': 'public, max-age=600'});
      response.end(body);
    };
    if (heldQuery === query) {
      heldQuery = '';
      release = respond;
      started();
    } else respond();
  } else if (url.pathname === '/site.js') {
    response.writeHead(200, {'Content-Type': 'text/javascript'});
    response.end(site);
  } else {
    response.writeHead(200, {'Content-Type': 'text/html', 'Cache-Control': 'no-store'});
    response.end(`<!doctype html><button data-search-open>Search</button>
      <dialog data-search data-endpoint="/index?lang=pl" data-label-products="Products" data-label-articles="Articles"
        data-label-empty="No results for %s" data-label-count="Results: %d" data-label-all="All (%d)">
        <form action="/results"><input type="search" name="s"><button type="button" data-search-close>Close</button></form>
        <p data-search-status></p><div data-search-results></div>
      </dialog><script src="/site.js"></script>`);
  }
});

(async () => {
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch({headless: true, ...(process.env.CHROME_BIN ? {executablePath: process.env.CHROME_BIN} : {})});
  try {
    const page = await browser.newPage();
    const base = `http://127.0.0.1:${server.address().port}`;
    await page.goto(base);
    // Warm the browser HTTP cache with an earlier, publicly cacheable response.
    assert.equal((await page.evaluate(() => fetch('/index?lang=pl&q=Formerly').then((r) => r.json()))).count, 1);
    assert.equal(requests.length, 1);
    privateNow = true;
    await page.locator('[data-search-open]').click();
    assert.equal(requests.length, 1, 'an empty search must not request the index');
    const input = page.locator('[data-search] input[type="search"]');
    await input.fill('F');
    await input.fill('Fo');
    await input.fill('Formerly');
    await page.locator('.search-empty').waitFor();
    assert.equal(requests.length, 2, 'rapid typing should send one query and bypass the old HTTP cache');
    assert.equal(await page.locator('.search-result').count(), 0);
    console.log('PASS no stale browser cache and debounced typing');

    await page.locator('[data-search-close]').click();
    privateNow = false;
    await page.locator('[data-search-open]').click();
    await page.locator('.search-result').waitFor();
    await page.locator('[data-search-close]').click();
    privateNow = true;
    await page.locator('[data-search-open]').click();
    await page.locator('.search-empty').waitFor();
    assert.equal(await page.locator('.search-result').count(), 0);
    console.log('PASS reopening refreshes changed visibility');

    // On failure, Enter must submit the form rather than follow an earlier result.
    await page.locator('[data-search-close]').click();
    privateNow = false;
    await page.locator('[data-search-open]').click();
    await page.locator('.search-result').waitFor();
    const failed = page.waitForResponse((r) => new URL(r.url()).searchParams.get('q') === 'Broken' && r.status() === 503);
    await input.fill('Broken');
    assert.equal(await page.locator('.search-result').count(), 0);
    await failed;
    assert.equal(await page.locator('[data-search-status]').textContent(), '');
    await Promise.all([page.waitForURL(/\/results\?s=Broken/), input.press('Enter')]);
    console.log('PASS failed refresh clears stale results and Enter falls back to the form');

    // A newer query supersedes a held earlier response, even if it later arrives.
    await page.goto(base);
    await page.locator('[data-search-open]').click();
    const search = page.locator('[data-search] input[type="search"]');
    heldQuery = 'Outdated';
    const oldStarted = new Promise((resolve) => { started = resolve; });
    await search.fill('Outdated');
    await oldStarted;
    const finishOld = release;
    await search.fill('Latest');
    await page.locator('.search-result').filter({hasText:'Latest item'}).waitFor();
    finishOld();
    await page.waitForTimeout(100);
    assert.deepEqual(await page.locator('.search-result-title').allTextContents(), ['Latest item']);
    console.log('PASS an older response cannot overwrite a newer query');

    // Pressing Enter while a new response is pending must use the new form query.
    heldQuery = 'Pending';
    const pendingStarted = new Promise((resolve) => { started = resolve; });
    await search.fill('Pending');
    await pendingStarted;
    const finishPending = release;
    assert.equal(await page.locator('.search-result').count(), 0);
    await Promise.all([page.waitForURL(/\/results\?s=Pending/), search.press('Enter')]);
    finishPending();
    console.log('PASS Enter while a request is pending submits the new query');

    // The old request must not render when the same dialog is closed and reopened.
    await page.goto(base);
    await page.locator('[data-search-open]').click();
    const again = page.locator('[data-search] input[type="search"]');
    heldQuery = 'Private';
    const reopenStarted = new Promise((resolve) => { started = resolve; });
    await again.fill('Private');
    await reopenStarted;
    const finishPrivate = release;
    await page.locator('[data-search-close]').click();
    privateNow = true;
    await page.locator('[data-search-open]').click();
    await page.locator('.search-empty').waitFor();
    finishPrivate();
    await page.waitForTimeout(100);
    assert.equal(await page.locator('.search-result').count(), 0);
    console.log('PASS close and reopen cannot display the previous response');
  } finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
  }
})().catch((error) => {console.error(error);process.exitCode=1;server.close();});
