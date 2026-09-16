import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const browser = await chromium.launch();
const results = [];
try {
 const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
 for (const path of ['/', '/collections', '/products/the-taylor-oxford-shirt', '/cart', '/search', '/pre-order']) {
  const response = await page.goto('http://william.taylor' + path);
  if (response.status() !== 200) { results.push({ path, status: response.status() }); continue; }
  await page.locator('[data-smart-header]').waitFor();
  await page.waitForTimeout(1500);
  const scroll = async y => { await page.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), y); await page.waitForTimeout(350); };
  await scroll(0);
  assert.equal(await page.locator('[data-smart-header]').getAttribute('data-smart-header'), 'top');
  await scroll(160);
  assert.equal(await page.locator('[data-smart-header]').getAttribute('data-smart-header'), 'hidden-scrolled');
  await scroll(140);
  assert.equal(await page.locator('[data-smart-header]').getAttribute('data-smart-header'), 'visible-scrolled');
  assert.equal(await page.locator('.wt-header-action').count(), 3);
  await page.locator('.wt-header-action[data-cart-open]').click();
  await page.locator('#wt-cart-drawer[open]').waitFor();
  await scroll(220);
  assert.equal(await page.locator('[data-smart-header]').getAttribute('data-smart-header'), 'visible-scrolled');
  await page.keyboard.press('Escape'); await page.waitForTimeout(400);
  if (path === '/') {
   const media = await page.locator('[data-homepage-hero] picture img').evaluate(img => ({ loaded: img.complete && img.naturalWidth > 0, selected: img.currentSrc }));
   assert.equal(await page.locator('main').getByText('Premium Fabrics', { exact: true }).count(), 0);
   results.push({ path, status: 200, smartHeader: true, cart: true, media });
  } else results.push({ path, status: 200, smartHeader: true, cart: true });
 }
 console.log(JSON.stringify(results));
} finally {
 await writeFile('storage/app/ui-frontend-1b-evidence/live-results.json', JSON.stringify(results, null, 2));
 await browser.close();
}
