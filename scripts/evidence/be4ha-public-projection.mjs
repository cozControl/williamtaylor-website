import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BE4HA_BASE_URL || 'http://127.0.0.1:8132';
const output = path.resolve('storage/app/evidence/be-4h-a');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const findings = {
  generatedAt: new Date().toISOString(), browser: await browser.version(), database: 'disposable-sqlite',
  viewports: ['1440x900', '768x1024', '375x812'], screenshots: [], consoleErrors: [], warnings: [], failedRequests: [], failedLocalAssets: [], overflow: {}, checks: {}, states: [],
};
const knownTemplateApi = url => url.includes('/api/apps/') || url.includes('/api/app-logs/') || url.includes('/_boost/browser-logs');
const context = await browser.newContext();
const page = await context.newPage();
page.on('console', message => {
  const item = { type: message.type(), text: message.text(), url: page.url() };
  if (message.type() === 'error') findings.consoleErrors.push(item);
  if (message.type() === 'warning') findings.warnings.push(item);
});
page.on('requestfailed', request => {
  findings.failedRequests.push(request.url());
  if (request.url().startsWith(baseUrl) && !knownTemplateApi(request.url())) findings.failedLocalAssets.push(request.url());
});
page.on('response', response => {
  if (response.status() >= 400 && response.url().startsWith(baseUrl) && !knownTemplateApi(response.url())) findings.failedLocalAssets.push(`${response.status()} ${response.url()}`);
});
function fixture(action) {
  execFileSync('php', ['scripts/evidence/be4ha-fixture.php', action], { cwd: process.cwd(), env: process.env, stdio: 'pipe' });
}
async function capture(route, name, width, height) {
  await page.setViewportSize({ width, height });
  const response = await page.goto(baseUrl + route, { waitUntil: 'networkidle' });
  if (response?.status() !== 200) throw new Error(`${route} returned ${response?.status()}`);
  findings.overflow[`${name}:${width}`] = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
  const file = path.join(output, `${name}-${width}x${height}.png`);
  await page.screenshot({ path: file, fullPage: true });
  findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
  const html = await page.content();
  if (/revision[_ -]?id|candidate_revision|current_public_revision|approval note/i.test(html)) throw new Error(`Revision/workflow leak detected on ${route}`);
}
const routes = [['/', 'home'], ['/collections', 'collections'], ['/shop', 'shop'], ['/pre-order', 'pre-order'], ['/limited-edition', 'limited-edition'], ['/gift-cards', 'gift-cards'], ['/wishlist', 'wishlist'], ['/products/the-taylor-oxford-shirt', 'product'], ['/login', 'login']];
for (const [route, name] of routes) {
  for (const [width, height] of [[1440, 900], [768, 1024], [375, 812]]) await capture(route, `projected-${name}`, width, height);
}
await page.goto(baseUrl + '/', { waitUntil: 'networkidle' });
findings.checks.projectedNavigation = await page.getByText('Governed Collections', { exact: true }).count() > 0;
findings.checks.announcementPresent = await page.getByText('BE-4H-A.1 Governed Announcement', { exact: true }).count() === 1;
findings.checks.projectedFooter = await page.getByText('Governed tailoring from Tanzania.', { exact: true }).count() === 1;
findings.checks.projectedProfile = await page.getByText('Join the Governed Ledger', { exact: true }).count() === 1;
await page.setViewportSize({ width: 375, height: 812 });
await page.locator('[data-public-menu-open]').click();
findings.checks.mobileMenuOpen = await page.locator('#public-mobile-navigation').isVisible();
findings.checks.mobileExpanded = await page.locator('[data-public-menu-open]').getAttribute('aria-expanded') === 'true';
const mobileOpenFile = path.join(output, 'projected-mobile-open-375x812.png');
await page.screenshot({ path: mobileOpenFile, fullPage: true });
findings.screenshots.push(path.relative(process.cwd(), mobileOpenFile).replaceAll('\\\\', '/'));
await page.keyboard.press('Escape');
findings.checks.mobileEscapeClosed = !(await page.locator('#public-mobile-navigation').isVisible());
findings.checks.mobileFocusReturned = await page.locator('[data-public-menu-open]').evaluate(element => document.activeElement === element);

fixture('no-announcement');
await capture('/', 'fallback-no-announcement', 1440, 900);
findings.checks.noEffectiveAnnouncement = await page.getByText('BE-4H-A.1 Governed Announcement', { exact: true }).count() === 0;
fixture('invalid-navigation');
await capture('/', 'fallback-invalid-navigation', 1440, 900);
findings.checks.invalidNavigationFallback = await page.getByText('Collections', { exact: true }).count() > 0 && await page.getByText('Governed Collections', { exact: true }).count() === 0;
fixture('invalid-media');
await capture('/', 'fallback-invalid-media', 1440, 900);
findings.checks.invalidMediaFallback = await page.locator('header img[src="/website/images/8d99836ea_LOGO-3.png"]').count() === 1;
fixture('restore');
await capture('/', 'projected-restored', 1440, 900);
findings.checks.restored = await page.getByText('Governed Collections', { exact: true }).count() > 0;

findings.consoleErrors = [...new Map(findings.consoleErrors.map(item => [item.text, item])).values()];
findings.warnings = [...new Map(findings.warnings.map(item => [item.text, item])).values()];
findings.failedRequests = [...new Set(findings.failedRequests)];
findings.failedLocalAssets = [...new Set(findings.failedLocalAssets)];
findings.checks.preExistingPreOrderMobileOverflowRecorded = findings.overflow['projected-pre-order:375'] === true;
findings.states = ['projected', 'mobile-open', 'no-effective-announcement', 'invalid-navigation-fallback', 'invalid-media-fallback', 'restored'];
const required = Object.entries(findings.checks).filter(([, value]) => value !== true);
if (required.length || Object.entries(findings.overflow).some(([key, value]) => value && key !== 'projected-pre-order:375') || findings.failedLocalAssets.length) {
  throw new Error(`Browser closeout failed: ${JSON.stringify({ required, overflow: findings.overflow, failedLocalAssets: findings.failedLocalAssets })}`);
}
await browser.close();
await writeFile(path.join(output, 'browser-findings.json'), JSON.stringify(findings, null, 2) + '\n');
process.stdout.write(JSON.stringify(findings, null, 2) + '\n');
