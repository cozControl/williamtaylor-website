import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const runtime = resolve(root, 'storage/app/test-runtime', `collections-${Date.now()}`);
mkdirSync(`${runtime}/sessions`, { recursive: true });
const env = { ...process.env, COLLECTION_BROWSER_RUNTIME: runtime, XDEBUG_MODE: 'off' };
const router = resolve(root, 'scripts/evidence/collections-runtime.php');
const seed = spawnSync('php', ['-d', 'xdebug.mode=off', router, 'seed'], { cwd: root, env, encoding: 'utf8', timeout: 120000, windowsHide: true });
if (seed.status !== 0 || !existsSync(`${runtime}/actor`)) throw new Error(seed.stdout + seed.stderr);
const server = spawn('php', ['-d', 'xdebug.mode=off', '-S', '127.0.0.1:8143', '-t', 'public', router], { cwd: root, env, windowsHide: true, stdio: 'ignore' });
const browser = await chromium.launch({ headless: true });
const results = [], errors = [];
const base = 'http://127.0.0.1:8143';
try {
    let ready = false;
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch(`${base}/up`, { signal: AbortSignal.timeout(1000) })).ok) { ready = true; break; } } catch {}
        await new Promise(r => setTimeout(r, 250));
    }
    assert(ready, 'Disposable server failed readiness');
    for (const [width, height] of [[375, 812], [768, 1024], [1440, 900]]) {
        console.log(`Checking catalogue at ${width}x${height}`);
        const context = await browser.newContext({ viewport: { width, height } });
        const page = await context.newPage();
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', route => {
            const request = route.request();
            if (request.url().includes('/catalogue-fixture/')) return route.fulfill({ path: resolve(root, 'public/website/images/0368482bc_image.jpg'), contentType: 'image/jpeg' });
            const fixtureEditorSave = request.method() === 'POST' && request.url() === `${base}/admin/homepage/hot-sale`;
            if ((request.method() !== 'GET' && !fixtureEditorSave) || !request.url().startsWith(base)) return route.abort();
            return route.continue();
        });
        async function visit(path) { const response = await page.goto(base + path); assert.equal(response.status(), 200, path); await page.locator('[data-catalogue-listing]').waitFor(); }
        async function capture(state) {
            const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth, grid: getComputedStyle(document.querySelector('[data-catalogue-grid]')).gridTemplateColumns, count: document.querySelector('[data-catalogue-count]').textContent.trim(), cards: document.querySelectorAll('[data-storefront-product-card]').length, footer: !!document.querySelector('#root footer'), header: !!document.querySelector('[data-smart-header]') }));
            assert(!layout.overflow, `Horizontal overflow at ${width}/${state}`);
            assert(layout.header && layout.footer);
            await page.screenshot({ path: `${runtime}/${width}-${state}.png`, fullPage: true });
            await page.screenshot({ path: `${runtime}/${width}-${state}-viewport.png` });
            results.push({ width, height, state, ...layout });
            return layout;
        }
        await page.goto(base + '/');
        const exploreLink = page.locator('[data-homepage-explore-collections] a').first();
        await exploreLink.waitFor();
        const expectedCollection = await exploreLink.getAttribute('href');
        await exploreLink.click();
        await page.waitForURL(expectedCollection);
        await page.locator('[data-catalogue-listing]').waitFor();
        assert.equal(new URL(page.url()).pathname, '/collections/formal-wear');
        await page.goto(base + '/collections');
        await page.locator('main a[href="' + base + '/collections/formal-wear"]').first().click();
        await page.waitForURL(base + '/collections/formal-wear');
        await visit('/shop'); const shop = await capture('shop');
        await visit('/collections/formal-wear'); const collection = await capture('collection');
        await page.locator('[data-storefront-product-card]').first().getByRole('button', { name: 'Add to wishlist' }).click();
        await page.goto(`${base}/wishlist`);
        await page.locator('[data-canonical-wishlist] [data-storefront-product-card]').waitFor();
        assert.equal(await page.locator('[data-canonical-wishlist] [data-storefront-product-card]').count(), 1);
        await page.getByRole('button', { name: 'Remove from wishlist' }).click();
        await page.getByRole('heading', { name: 'Your wishlist is empty' }).waitFor();
        await visit('/collections/formal-wear');
        assert.equal(shop.grid, collection.grid);
        assert.equal(collection.cards, 12);
        await page.getByRole('button', { name: 'Filters', exact: true }).click();
        await page.locator('#catalogue-filters').waitFor({ state: 'visible' });
        assert.equal(await page.getByRole('link', { name: 'Size XXL', exact: true }).count(), 0);
        await capture('filters-long-categories');
        if (width < 1024) {
            assert.equal(await page.locator('#catalogue-filters').getAttribute('aria-modal'), 'true');
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('#catalogue-filters').isVisible(), false);
            await page.getByRole('button', { name: 'Filters', exact: true }).click();
        }
        await page.locator('[data-category-filter]').getByText('Shirts', { exact: true }).click();
        await page.waitForURL('**category=shirts**');
        await capture('active-category');
        assert((await page.locator('[data-catalogue-count]').innerText()).includes('13'));
        await page.getByRole('link', { name: 'Next', exact: true }).click();
        await page.waitForURL('**page=2**');
        assert.equal(await page.locator('[data-storefront-product-card]').count(), 1);
        await capture('pagination');
        await page.reload();
        assert.equal(await page.locator('[data-storefront-product-card]').count(), 1);
        await page.goBack();
        assert.equal(await page.locator('[data-storefront-product-card]').count(), 12);
        await visit('/collections/formal-wear?category=shirts&size=l');
        await page.locator('[data-catalogue-empty]').waitFor();
        await capture('empty-filters');
        await page.getByRole('link', { name: 'Clear filters', exact: true }).last().click();
        await page.locator('[data-storefront-product-card]').first().waitFor();
        await page.getByRole('combobox', { name: 'Sort products' }).selectOption('price-desc');
        await page.waitForURL('**sort=price-desc**');
        assert((await page.locator('[data-storefront-product-card]').first().innerText()).includes('Tailored Suit'));
        await capture('sorted');
        if (width >= 768) { await page.getByRole('link', { name: 'List view', exact: true }).click(); await page.waitForURL('**view=list**'); await capture('list'); }
        await visit('/collections/coming-soon'); await capture('empty-collection');
        const loginResponse = await page.goto(`${base}/__catalogue_fixture_login`);
        console.log(JSON.stringify({ state: 'fixture-admin-login', width, status: loginResponse.status(), url: page.url(), title: await page.title() }));
        await page.screenshot({ path: `${runtime}/${width}-fixture-admin-login.png`, fullPage: true });
        assert.equal(loginResponse.status(), 200);
        await page.getByRole('heading', { name: 'Categories', exact: true }).waitFor();
        await page.screenshot({ path: `${runtime}/${width}-admin-categories.png`, fullPage: true });
        await page.goto(`${base}/admin/product-categories/${readFileSync(`${runtime}/category`, 'utf8')}/edit`);
        await page.locator('#category-collection').waitFor();
        await page.screenshot({ path: `${runtime}/${width}-admin-category.png`, fullPage: true });
        await page.goto(`${base}/admin/homepage/hot-sale`);
        const collectionDestination = 'collection:' + readFileSync(`${runtime}/casual-collection`, 'utf8');
        for (const position of [1, 2, 3]) {
            assert.equal(await page.locator(`#hot-sale-${position}-destination option`).filter({ hasText: 'Collection: Casual Wear' }).count(), 1);
        }
        await page.locator('#hot-sale-1-destination').selectOption(collectionDestination);
        await page.getByRole('button', { name: 'Save changes', exact: true }).click();
        await page.getByText("William's Hot Sale updated successfully.", { exact: true }).waitFor();
        assert.equal(await page.locator('#hot-sale-1-destination').inputValue(), collectionDestination);
        await page.screenshot({ path: `${runtime}/${width}-admin-hot-sale.png`, fullPage: true });
        await page.goto(base + '/');
        await page.locator('[data-homepage-hot-sale] [data-hot-sale-tile="1"] a').click();
        await page.waitForURL(base + '/collections/casual-wear');
        await page.locator('[data-catalogue-listing]').waitFor();
        assert.equal(await page.locator('[data-storefront-product-card]').count(), 1);
        results.push({ width, height, state: 'hot-sale-collection-save-and-click', destination: '/collections/casual-wear', result: 'passed' });
        await context.close();
    }
    assert.deepEqual(errors, []);
    writeFileSync(`${runtime}/results.json`, JSON.stringify({ results, errors }, null, 2));
    console.log(JSON.stringify({ runtime, checks: results.length, result: 'passed' }));
} finally {
    writeFileSync(`${runtime}/partial-results.json`, JSON.stringify({ results, errors }, null, 2));
    await browser.close();
    server.kill();
}
