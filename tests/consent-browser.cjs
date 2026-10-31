// Run against a disposable local WordPress container with PLAYWRIGHT_CORE, WP_TEST_CONTAINER and WP_TEST_URL set.
const { execFileSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const container = process.env.WP_TEST_CONTAINER;
const base = process.env.WP_TEST_URL;
if (!container || !base || !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('A disposable local container and URL are required');
const input = `<div style='width:20px;height:20px;background-image:u\\72l("https://consent-audit.invalid/escaped")'></div>
<div style='width:20px;height:20px;background-image:image-set("https://consent-audit.invalid/imageset" 1x)'></div>
<style>@import url("https://consent-audit.invalid/import.css");</style>
<link rel="stylesheet" href="https://consent-audit.invalid/link.css">
<iframe srcdoc='&lt;img src="https://consent-audit.invalid/frame"&gt;'></iframe>
<iframe src="data:text/html,%3Cimg%20src%3Dhttps%3A%2F%2Fconsent-audit.invalid%2Fdata-frame%3E"></iframe>
<table background="https://consent-audit.invalid/background"><tr><td>Cell</td></tr></table>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20"><image width="20" height="20" xlink:href="https://consent-audit.invalid/svg-image" /></svg>`;
const encoded = Buffer.from(input).toString('base64');
const content = execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php require '/var/www/html/wp-load.php'; echo lasery_xtool_gate_external_content(base64_decode('${encoded}'));`, encoding: 'utf8' });
const consentScript = readFileSync('wp-content/themes/lasery-xtool/assets/cookie-consent.js', 'utf8');
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const requests = [];
    await page.route('https://consent-audit.invalid/**', async (route) => { requests.push(new URL(route.request().url()).pathname); await route.abort(); });
    await page.goto(`${base}/?lang=pl`);
    await page.setContent(`<section data-lasery-consent-banner><button data-lasery-consent-choice="allow">allow</button><button data-lasery-consent-choice="deny">deny</button></section>${content}`);
    await page.evaluate(consentScript);
    await page.waitForTimeout(200);
    assert.deepEqual(requests, []);
    await page.locator('[data-lasery-consent-choice="allow"]').click();
    await page.waitForTimeout(500);
    for (const path of ['/escaped', '/imageset', '/import.css', '/link.css', '/frame', '/data-frame', '/background', '/svg-image']) assert.ok(requests.includes(path), `${path} was not restored after consent: ${requests}`);
    requests.length = 0;
    await page.evaluate(() => { document.querySelector('[data-lasery-consent-banner]').hidden = false; });
    await page.locator('[data-lasery-consent-choice="deny"]').click();
    await page.evaluate(() => { document.querySelector('[data-lasery-consent-css-type]').textContent += '@import url("https://consent-audit.invalid/after.css");'; });
    await page.waitForTimeout(300);
    assert.equal(await page.locator('[data-lasery-consent-css-type]').getAttribute('type'), 'text/plain');
    assert.equal(await page.locator('iframe').first().getAttribute('srcdoc'), null);
    assert.equal(await page.locator('iframe').last().getAttribute('src'), null);
    assert.equal(await page.locator('link').getAttribute('href'), null);
    assert.deepEqual(requests, []);
    console.log('External CSS, stylesheet links and srcdoc require consent and stop after consent is withdrawn.');
  } finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
