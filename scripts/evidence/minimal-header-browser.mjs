import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

// Focused header checkpoint only. No purchases, account or catalogue mutations.
const origin = 'http://william.taylor';
const output = 'storage/app/minimal-header-evidence';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const results = [];
const utilitiesOnly = process.argv.includes('--utilities-only');
let completed = false;
try {
 const page = await browser.newPage();
 const errors = [];
 page.on('pageerror', error => errors.push(error.message));
 if (!utilitiesOnly) {
 for (const width of [1440, 1024, 768, 390, 375, 320]) {
  await page.setViewportSize({ width, height: 900 });
  await page.goto(origin, { waitUntil: 'load' });
  await page.locator('.wt-header-brand img').waitFor();
  await page.waitForTimeout(1800);
  const geometry = await page.evaluate(() => {
   const brand = document.querySelector('.wt-header-brand').getBoundingClientRect();
   const utilities = document.querySelector('.wt-header-utilities').getBoundingClientRect();
   const nav = document.querySelector('.wt-minimal-header');
   return { drift: Math.abs(brand.x + brand.width / 2 - innerWidth / 2), gap: utilities.x - brand.right, right: utilities.right, overflow: document.documentElement.scrollWidth > innerWidth, background: getComputedStyle(nav).backgroundColor, controls: nav.querySelectorAll('.wt-header-action').length };
  });
  assert(geometry.drift < 1);
  assert(geometry.gap >= 8);
  assert(geometry.right <= width - 8);
  assert.equal(geometry.overflow, false);
  assert.equal(geometry.controls, 3);
  assert.equal(geometry.background, 'rgba(0, 0, 0, 0)');
  await page.screenshot({ path: `${output}/home-${width}.png` });
  await page.locator('.wt-header-action[data-cart-open]').click();
  await page.locator('#wt-cart-drawer[open]').waitFor();
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  assert.equal(await page.locator('.wt-header-action[data-cart-open]').evaluate(el => el === document.activeElement), true);
  await page.evaluate(() => scrollTo(0, 300));
  await page.waitForTimeout(250);
  assert.equal(await page.locator('[data-canonical-shop-header][data-scrolled]').count(), 1);
  await page.screenshot({ path: `${output}/scrolled-${width}.png`, clip: { x: 0, y: 0, width, height: 160 } });
  results.push({ width, ...geometry, cartOpened: true, focusRestored: true, scrollState: true });
 }
 await page.setViewportSize({ width: 1440, height: 900 });
 await page.goto(origin, { waitUntil: 'load' });
 await page.locator('.wt-header-brand').waitFor();
 // Browser-only contrast fixtures; no settings or media records are changed.
 for (const [name, background] of [['light', '#fff'], ['dark', '#151515'], ['midtone', '#808080'], ['mixed', 'linear-gradient(90deg,#151515 0%,#151515 48%,#fff 52%,#fff 100%)']]) {
  await page.evaluate(background => {
   document.getElementById('header-contrast-fixture')?.remove();
   const fixture = document.createElement('div');
   fixture.id = 'header-contrast-fixture';
   fixture.style.cssText = `position:fixed;inset:0;z-index:30;pointer-events:none;background:${background}`;
   document.querySelector('#root').append(fixture);
  }, background);
  await page.waitForTimeout(100);
  assert.equal(await page.locator('.wt-header-brand').getAttribute('data-header-tone'), name === 'light' ? 'dark' : 'light');
  await page.screenshot({ path: `${output}/contrast-${name}.png`, clip: { x: 0, y: 0, width: 1440, height: 120 } });
 }
 results.push({ contrastFixtures: ['light', 'dark', 'midtone', 'mixed'] });
 await page.evaluate(() => {
  document.getElementById('header-contrast-fixture').remove();
  const content = document.querySelector('[data-header-announcement] > div');
  content.style.maxHeight = '100px';
  content.style.opacity = '1';
 });
 await page.waitForTimeout(400);
 const announcement = await page.locator('[data-header-announcement]').boundingBox();
 const nav = await page.locator('.wt-minimal-header').boundingBox();
 assert(announcement.height > 20);
 assert(Math.abs(nav.y - announcement.height) < 1);
 await page.screenshot({ path: `${output}/announcement.png`, clip: { x: 0, y: 0, width: 1440, height: 160 } });
 await page.locator('[data-header-announcement] button').click();
 await page.waitForTimeout(100);
 assert.equal((await page.locator('.wt-minimal-header').boundingBox()).y, 0);
 results.push({ announcementFixture: { offset: true, dismiss: true } });
 for (const path of ['/collections', '/products/the-taylor-oxford-shirt', '/cart', '/pre-order', '/limited-edition', '/search']) {
  const response = await page.goto(origin + path, { waitUntil: 'load' });
  if (response.status() !== 200) {
   results.push({ path, status: response.status(), header: 'blocked by HTTP response' });
   continue;
  }
  assert.equal(await page.locator('#root .wt-minimal-header').count(), 1, path);
  await page.screenshot({ path: `${output}/${path.replaceAll('/', '-').slice(1)}.png` });
  results.push({ path, status: response.status(), header: true });
 }
 }
 const entryResponse = await page.goto(origin + '/search', { waitUntil: 'load' });
 assert.equal(entryResponse.status(), 200, 'Search entry must respond successfully');
 await page.locator('.wt-header-action[aria-label="Search"]').click();
 await page.locator('#storefront-search').fill('Oxford');
 const [searchResponse] = await Promise.all([
  page.waitForResponse(response => response.request().isNavigationRequest() && response.url().includes('/search?q=Oxford')),
  page.locator('form[role="search"] button').click(),
 ]);
 assert.equal(searchResponse.status(), 200);
 await page.waitForURL('**/search?q=Oxford');
 assert.match(await page.locator('main').innerText(), /results? for/);
 results.push({ searchSubmitted: true, productLinks: await page.locator('main [data-storefront-product-card]').count() });
 await page.screenshot({ path: `${output}/search-results.png` });
 await page.locator('.wt-header-action[aria-label="Sign in"]').click();
 await page.waitForURL('**/login');
 results.push({ guestLogin: true });
 assert.deepEqual(errors, []);
 completed = true;
 console.log(JSON.stringify({ passed: !results.some(result => result.status && result.status !== 200), results }));
} finally {
 await writeFile(`${output}/${utilitiesOnly ? 'utilities' : 'results'}.json`, JSON.stringify({ completed, results }, null, 2));
 await browser.close();
}
