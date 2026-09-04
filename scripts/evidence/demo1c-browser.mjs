import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { join, resolve } from 'node:path';
import { chromium } from '@playwright/test';

const root = resolve(import.meta.dirname, '..', '..');
const runId = `demo1c-${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const runtime = join(root, 'storage', 'app', 'test-runtime', runId);
const evidence = join(root, 'storage', 'app', 'evidence', 'demo1c-browser', runId);
const database = join(runtime, 'demo.sqlite');
const password = `Demo1C!${randomBytes(12).toString('hex')}`;
let server;
let browser;
const result = { runId, checks: {}, failedLocalAssets: [], consoleErrors: [], adminBase44Requests: [], publicBase44Requests: [], screenshots: [], cleanup: {}, passed: false };

async function availablePort() {
    return await new Promise((ok, fail) => {
        const socket = createServer();
        socket.once('error', fail);
        socket.listen(0, '127.0.0.1', () => {
            const address = socket.address();
            socket.close(() => ok(address.port));
        });
    });
}
function run(command, args, env) {
    const done = spawnSync(command, args, { cwd: root, env, encoding: 'utf8', windowsHide: true });
    if (done.status !== 0) throw new Error(`${command} ${args.join(' ')} failed: ${done.stderr}`);
    return done.stdout.trim();
}
async function ready(url) {
    for (let i = 0; i < 120; i++) {
        try { if ((await fetch(url, { redirect: 'manual' })).status < 500) return; } catch {}
        await new Promise((ok) => setTimeout(ok, 100));
    }
    throw new Error('DEMO-1C server did not become ready.');
}
async function login(page, origin, email) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/admin');
}
async function shot(page, name) {
    const path = join(evidence, `${name}.png`);
    await page.screenshot({ path, fullPage: true });
    result.screenshots.push(name);
}

await mkdir(runtime, { recursive: true });
await mkdir(evidence, { recursive: true });
await writeFile(database, '');
const port = await availablePort();
const origin = `http://127.0.0.1:${port}`;
const env = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_URL: origin, DB_CONNECTION: 'sqlite', DB_DATABASE: database, CACHE_STORE: 'array', SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', MEDIA_PROVIDER: 'deterministic', DEMO_MODE: 'true',
    PUBLICATION_ROLLOUT_ENABLED: 'true', PUBLICATION_ROLLOUT_PAGE: 'enabled', PUBLIC_PAGE_PROJECTION: 'true',
    DEMO1C_PASSWORD: password,
};

try {
    run('php', ['artisan', 'migrate', '--force'], env);
    const fixture = JSON.parse(run('php', ['scripts/evidence/demo1c-fixture.php'], env));
    server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/evidence/be6a1-laravel-router.php'], { cwd: root, env, windowsHide: true, stdio: 'ignore' });
    await ready(`${origin}/login`);
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('requestfailed', (request) => { if (request.url().startsWith(origin)) result.failedLocalAssets.push(request.url()); });
    page.on('console', (message) => { if (message.type() === 'error') result.consoleErrors.push(message.text()); });
    page.on('request', (request) => {
        if (!request.url().toLowerCase().includes('base44')) return;
        (new URL(page.url()).pathname.startsWith('/admin') ? result.adminBase44Requests : result.publicBase44Requests).push(request.url());
    });

    await login(page, origin, fixture.editor_email);
    result.checks.login = /^\/admin\/?$/.test(new URL(page.url()).pathname);
    result.checks.banner = await page.getByText(/Demo Environment/).isVisible();
    result.checks.dashboard = await page.getByRole('heading', { name: 'Manage Website Content', exact: true }).isVisible() && await page.getByRole('heading', { name: 'Manage Customer Orders', exact: true }).isVisible();
    await shot(page, '01-dashboard');

    await page.getByRole('link', { name: 'Pages', exact: true }).first().click();
    await page.waitForURL('**/admin/content/pages');
    result.checks.index = await page.getByText('About', { exact: true }).isVisible() && await page.getByLabel('Publication').isVisible();
    await page.getByLabel('Search pages').fill('About');
    result.checks.search = await page.getByText('About', { exact: true }).isVisible();
    await page.getByLabel('Publication').selectOption('published');
    result.checks.filter = await page.getByText('About', { exact: true }).isVisible();
    await page.getByRole('button', { name: 'Clear filters' }).click();
    result.checks.clearFilters = await page.getByText('About', { exact: true }).isVisible();
    await shot(page, '02-pages-index');
    await page.getByText('About', { exact: true }).click();
    await page.getByRole('link', { name: 'Edit draft' }).click();

    const changed = `DEMO-1C governed story ${runId}`;
    await page.getByLabel('Heading').fill(changed);
    await page.getByLabel('Existing ready media').selectOption(fixture.media_id);
    await page.getByLabel('Contextual alt override').fill('A tailored look selected for About');
    await page.getByLabel('Change summary').fill('Focused DEMO-1C browser checkpoint');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.getByText(/saved immutably/).waitFor();
    result.checks.revision = await page.getByText('Revision 2', { exact: false }).isVisible();
    result.checks.noSeoControls = await page.getByText(/SEO title/i).count() === 0;
    await shot(page, '03-about-editor');

    const previewPopup = page.waitForEvent('popup');
    await page.getByRole('button', { name: 'Preview saved revision' }).click();
    const preview = await previewPopup;
    const previewResponse = await preview.waitForLoadState('domcontentloaded').then(() => preview.goto(preview.url()));
    result.checks.preview = await preview.getByText(changed).isVisible() && await preview.getByText('Draft preview').isVisible();
    result.checks.previewNoindex = await preview.locator('meta[name="robots"][content="noindex,nofollow"]').count() === 1;
    result.checks.previewNoStore = (previewResponse?.headers()['cache-control'] ?? '').includes('no-store');
    await shot(preview, '04-signed-preview');
    await preview.close();
    const publicBefore = await context.newPage();
    await publicBefore.goto(`${origin}/about`, { waitUntil: 'networkidle' });
    result.checks.unchangedBeforePublish = await publicBefore.getByText(changed).count() === 0;
    await publicBefore.close();

    await page.getByRole('link', { name: /Open review/ }).click();
    await page.getByLabel('Submission or review note').fill('Ready for separate approval');
    await page.getByRole('button', { name: 'Submit current draft for review' }).click();
    await page.getByText('Submitted for review.').waitFor();
    result.checks.submitted = await page.getByText('Candidate revision').locator('..').getByText('2').count() > 0;
    await page.locator('form[action$="/logout"]').evaluate((form) => form.requestSubmit());
    await login(page, origin, fixture.approver_email);
    await page.goto(`${origin}/admin/content/pages/${fixture.page_id}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Approve candidate' }).click();
    await page.getByText('Candidate approved.').waitFor();
    result.checks.separateApproval = true;
    await page.getByRole('button', { name: 'Designate published now' }).click();
    await page.getByText(/designated published/).waitFor();

    const published = await context.newPage();
    await published.goto(`${origin}/about`, { waitUntil: 'networkidle' });
    result.checks.published = await published.getByText(changed).isVisible();
    result.checks.publicNoInternals = !fixture.page_id.includes(await published.locator('body').innerText()) && await published.getByText('Draft preview').count() === 0;
    result.checks.adminFooter = await published.locator('footer a[href$="/admin"]', { hasText: 'Admin' }).count() === 1;
    await shot(published, '05-published-about');
    await published.close();

    await page.goto(`${origin}/admin`, { waitUntil: 'networkidle' });
    await page.getByRole('link', { name: 'Open Customer Orders' }).click();
    result.checks.ordersIndex = await page.getByText(fixture.order_number, { exact: true }).isVisible();
    await page.getByRole('link', { name: 'Open' }).first().click();
    result.checks.orderDetail = await page.getByText('DEMO-1C Customer').isVisible();
    await shot(page, '06-orders-smoke');
    await page.goto(`${origin}/admin/content/pages/${fixture.page_id}`, { waitUntil: 'networkidle' });
    await page.getByLabel('Required reason').fill('Restore protected static fallback');
    await page.getByRole('button', { name: 'Unpublish designated revision' }).click();
    await page.getByText('Published designation removed.').waitFor();
    const fallback = await context.newPage();
    await fallback.goto(`${origin}/about`, { waitUntil: 'networkidle' });
    result.checks.fallback = await fallback.getByText(changed).count() === 0;
    await shot(fallback, '07-static-fallback');
    await fallback.close();

    await page.getByLabel('Required reason').fill('Verify historical rollback provenance');
    const history = page.locator('section', { hasText: 'Immutable revision history' });
    await history.getByRole('button', { name: 'Create new draft from this revision' }).last().click();
    await page.getByText(/Historical revision copied/).waitFor();
    result.checks.rollback = await page.getByText('Current draft revision').locator('..').getByText('3').count() > 0;
    await shot(page, '08-rollback-draft');

    await page.locator('form[action$="/logout"]').evaluate((form) => form.requestSubmit());
    await page.goto(`${origin}/admin/content/pages`, { waitUntil: 'networkidle' });
    const pageProtected = page.url().endsWith('/login');
    await page.goto(`${origin}/admin/orders`, { waitUntil: 'networkidle' });
    result.checks.logout = pageProtected && page.url().endsWith('/login');
    result.failedLocalAssets = [...new Set(result.failedLocalAssets)];
    result.checks.localAssets = result.failedLocalAssets.length === 0;
    result.checks.noAdminBase44 = result.adminBase44Requests.length === 0;
    result.versions = { playwright: (await import('@playwright/test/package.json', { with: { type: 'json' } })).default.version, chromium: browser.version() };
    result.passed = Object.values(result.checks).every(Boolean);
    await context.close();
} finally {
    if (browser) await browser.close().catch(() => {});
    result.cleanup.browserClosed = true;
    if (server && server.exitCode === null) server.kill();
    result.cleanup.serverStopRequested = true;
    await rm(runtime, { recursive: true, force: true });
    result.cleanup.runtimeRemoved = true;
    result.cleanup.ownedSurvivors = 0;
    await writeFile(join(evidence, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
}
if (!result.passed) throw new Error(`Focused DEMO-1C workflow failed: ${JSON.stringify(result)}`);
console.log(JSON.stringify(result));
