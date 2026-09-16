import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir, stat } from 'node:fs/promises';
import path from 'node:path';

// Real Blade output from isolated PHPUnit fixtures. No live CMS records changed.
const output = path.resolve('storage/app/ui-frontend-1b-evidence');
const publicRoot = path.resolve('public');
const origin = 'http://william.taylor';
await mkdir(output, { recursive: true });
const html = await readFile(path.join(output, 'responsive.html'), 'utf8');
const desktopHtml = await readFile(path.join(output, 'desktop-only.html'), 'utf8');
const adminHtml = await readFile(path.join(output, 'admin.html'), 'utf8');
const hero = JSON.parse(html.match(/id="homepage-hero-data">([\s\S]*?)<\/script>/)[1]);
const browser = await chromium.launch();
const evidence = [];
const errors = [];
try {
 const context = await browser.newContext();
 let documentHtml = html;
 const imageRequests = [];
 await context.route('**/*', async route => {
  const request = route.request(), url = new URL(request.url());
  if (/\/livewire(?:\.min)?\.js$/.test(url.pathname)) return route.fulfill({ contentType: 'text/javascript', body: await readFile('vendor/livewire/livewire/dist/livewire.js') });
  if (request.isNavigationRequest() && url.origin === origin) return route.fulfill({ contentType: 'text/html', body: documentHtml });
  if (request.url() === hero.background_url || request.url() === hero.mobile_background_url) {
   const mobile = request.url() === hero.mobile_background_url;
   imageRequests.push(mobile ? 'mobile' : 'desktop');
   return route.fulfill({ contentType: mobile ? 'image/jpeg' : 'image/png', body: await readFile(path.join(publicRoot, 'website/images', mobile ? '0368482bc_image.jpg' : '306170464_Screenshot2026-07-10at215946.png')) });
  }
  if (url.hostname === 'res.cloudinary.com' && url.pathname.includes('/homepage/')) return route.fulfill({ contentType: 'image/png', body: await readFile(path.join(publicRoot, 'website/images/306170464_Screenshot2026-07-10at215946.png')) });
  if (url.origin === origin || url.hostname === 'media.base44.com') {
   const file = url.hostname === 'media.base44.com'
    ? path.join(publicRoot, 'website/images', path.basename(url.pathname))
    : path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
   if (file.startsWith(publicRoot + path.sep)) {
    try {
     if ((await stat(file)).isFile()) return route.fulfill({ path: file });
    } catch {}
   }
  }
  // Dynamic APIs are irrelevant to these read-only render/scroll checkpoints.
  if (request.method() !== 'GET' || url.pathname.startsWith('/api/')) return route.fulfill({ json: {} });
  return route.continue();
 });
 const page = await context.newPage();
 page.on('pageerror', error => errors.push(error.message));
 const state = () => page.locator('[data-smart-header]').getAttribute('data-smart-header');
 const scroll = async y => { await page.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), y); await page.waitForTimeout(350); };
 for (const width of [1440, 1024, 768, 390, 375, 320]) {
  imageRequests.length = 0;
  await page.setViewportSize({ width, height: 900 });
  await page.goto(origin);
  await page.waitForTimeout(1800);
  await page.locator('[data-responsive-hero-media] img').waitFor();
  await scroll(0);
  const expected = width <= 767 ? 'mobile' : 'desktop';
  const geometry = await page.evaluate(() => {
   const brand = document.querySelector('.wt-header-brand').getBoundingClientRect();
   const hero = document.querySelector('[data-homepage-hero]');
   const img = hero.querySelector('picture img');
   return { drift: Math.abs(brand.x + brand.width / 2 - innerWidth / 2), currentSrc: img.currentSrc, imageLoaded: img.complete && img.naturalWidth > 0,
    gap: hero.nextElementSibling.getBoundingClientRect().top - hero.getBoundingClientRect().bottom,
    overflow: document.documentElement.scrollWidth > innerWidth, strip: document.querySelector('main').textContent.includes('Premium Fabrics') };
  });
  assert.equal(geometry.currentSrc, expected === 'mobile' ? hero.mobile_background_url : hero.background_url);
  assert(geometry.imageLoaded); assert(geometry.drift < 1); assert.equal(geometry.overflow, false); assert.equal(geometry.strip, false); assert(Math.abs(geometry.gap) < 1);
  assert(!imageRequests.includes(expected === 'mobile' ? 'desktop' : 'mobile'), `unselected image downloaded at ${width}`);
  assert.equal(await state(), 'top');
  await page.screenshot({ path: path.join(output, `home-${width}.png`) });
  await scroll(120); assert.equal(await state(), 'hidden-scrolled');
  assert((await page.locator('.wt-minimal-header').boundingBox()).y < -40);
  await scroll(119); assert.equal(await state(), 'hidden-scrolled');
  await scroll(120); assert.equal(await state(), 'hidden-scrolled');
  await scroll(110); assert.equal(await state(), 'visible-scrolled');
  if (width === 390) await page.screenshot({ path: path.join(output, 'header-revealed-390.png') });
  await page.locator('.wt-header-action[aria-label="Search"]').focus();
  await scroll(220); assert.equal(await state(), 'visible-scrolled');
  await page.locator('[data-cart-open].wt-header-action').click();
  await page.locator('#wt-cart-drawer[open]').waitFor();
  await scroll(350); assert.equal(await state(), 'visible-scrolled');
  await page.keyboard.press('Escape'); await page.waitForTimeout(400);
  assert(await page.locator('[data-cart-open].wt-header-action').evaluate(node => node === document.activeElement));
  await page.evaluate(() => document.activeElement.blur());
  await scroll(450); assert.equal(await state(), 'hidden-scrolled');
  await scroll(0); assert.equal(await state(), 'top');
  assert.equal(await page.locator('.wt-minimal-header').evaluate(node => getComputedStyle(node).backgroundColor), 'rgba(0, 0, 0, 0)');
  evidence.push({ width, ...geometry, selected: expected, imageRequests: [...imageRequests], scroll: true, jitter: true, focus: true, cart: true });
 }
 await page.emulateMedia({ reducedMotion: 'reduce' });
 assert.equal(await page.locator('.wt-minimal-header').evaluate(node => getComputedStyle(node).transitionDuration), '0s');
 await scroll(160); assert.equal(await state(), 'hidden-scrolled');
 await scroll(140); assert.equal(await state(), 'visible-scrolled');
 evidence.push({ reducedMotion: true });
 documentHtml = desktopHtml;
 imageRequests.length = 0;
 await page.goto(origin); await page.waitForTimeout(1800);
 assert.equal(await page.locator('[data-homepage-hero] picture source').count(), 0);
 assert.equal(await page.locator('[data-homepage-hero] picture img').evaluate(img => img.currentSrc), hero.background_url);
 evidence.push({ mobileDesktopFallback: true });
 documentHtml = adminHtml;
 await page.setViewportSize({ width: 1440, height: 1000 });
 await page.goto(origin + '/admin/homepage/hero');
 await page.waitForTimeout(1200);
 await page.locator('[data-media-picker="homepage-hero-mobile-media"] .admin-picker-selected-card').waitFor();
 await page.locator('[data-hero-mobile-editor]').scrollIntoViewIfNeeded();
 await page.screenshot({ path: path.join(output, 'admin-media.png') });
 assert.equal(await page.locator('[data-media-picker="homepage-hero-mobile-media"]').count(), 1);
 const mobilePicker = page.locator('[data-media-picker="homepage-hero-mobile-media"]');
 assert.equal(await mobilePicker.locator('input[name="mobile_background_media_id"]').count(), 1);
 await mobilePicker.getByRole('button', { name: 'Remove', exact: true }).click();
 assert.equal(await mobilePicker.locator('input[name="mobile_background_media_id"]').count(), 0);
 assert.equal(await page.locator('input[name="mobile_background_media_id"]').inputValue(), '');
 assert.equal(await page.locator('[data-media-picker="homepage-hero-media"] .admin-picker-selected-card').count(), 1);
 evidence.push({ adminRendered: true, mobileRemoveLeavesDesktop: true, emptyMobileSubmitted: true });
 assert.deepEqual(errors, []);
 await writeFile(path.join(output, 'browser-results.json'), JSON.stringify({ evidence, errors }, null, 2));
 console.log(JSON.stringify({ evidence, errors }));
} finally {
 await browser.close();
}
