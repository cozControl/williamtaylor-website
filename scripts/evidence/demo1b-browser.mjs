import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { join, resolve } from 'node:path';
import { chromium } from '@playwright/test';

const root = resolve(import.meta.dirname, '..', '..');
const runId = `demo1b-${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const runtime = join(root, 'storage', 'app', 'test-runtime', runId);
const evidence = join(root, 'storage', 'app', 'evidence', 'demo1b', runId);
const database = join(runtime, 'demo.sqlite');
const password = `Demo1B!${randomBytes(12).toString('hex')}`;
let server;
let browser;

const result = { runId, generatedAt: new Date().toISOString(), checks: {}, failedLocalAssets: [], consoleErrors: [], adminConsoleErrors: [], cleanup: {}, passed: false };
const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    DEMO_MODE: 'true',
    PUBLICATION_ROLLOUT_ENABLED: 'true',
    DEMO1B_PASSWORD: password,
};

function run(command, args) {
    const completed = spawnSync(command, args, { cwd: root, env, encoding: 'utf8', windowsHide: true });
    if (completed.status !== 0) throw new Error(`${command} ${args.join(' ')} failed: ${completed.stderr}`);
    return completed.stdout.trim();
}

async function availablePort() {
    return await new Promise((resolvePort, reject) => {
        const socket = createServer();
        socket.once('error', reject);
        socket.listen(0, '127.0.0.1', () => {
            const address = socket.address();
            socket.close(() => resolvePort(address.port));
        });
    });
}

async function ready(url) {
    for (let attempt = 0; attempt < 150; attempt++) {
        try {
            const response = await fetch(url, { redirect: 'manual' });
            if (response.status < 500) return;
        } catch {}
        await new Promise((resolveWait) => setTimeout(resolveWait, 100));
    }
    throw new Error('Focused DEMO-1B server did not become ready.');
}

async function login(page, origin, email) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('**/admin');
}

await mkdir(runtime, { recursive: true });
await mkdir(evidence, { recursive: true });
await writeFile(database, '');
const port = await availablePort();
const origin = `http://127.0.0.1:${port}`;
env.APP_URL = origin;

try {
    run('php', ['artisan', 'migrate', '--force']);
    const fixture = JSON.parse(run('php', ['scripts/evidence/demo1b-fixture.php']));
    server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/evidence/be6a1-laravel-router.php'], { cwd: root, env, windowsHide: true, stdio: 'ignore' });
    await ready(`${origin}/login`);
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('requestfailed', (request) => {
        if (request.url().startsWith(origin)) result.failedLocalAssets.push(request.url());
    });
    page.on('console', (message) => {
        if (message.type() === 'error') {
            result.consoleErrors.push(message.text());
            if (new URL(page.url()).pathname.startsWith('/admin')) result.adminConsoleErrors.push(message.text());
        }
    });

    await login(page, origin, fixture.email);
    result.checks.banner = await page.getByText('Demo Environment \u2014 Content changes affect the client testing website only.').isVisible();
    await page.getByRole('link', { name: 'Orders', exact: true }).click();
    await page.getByLabel('Search').fill(fixture.orders.new);
    await page.getByRole('button', { name: 'Apply filters' }).click();
    result.checks.search = await page.getByText(fixture.orders.new, { exact: true }).isVisible();
    await page.getByRole('link', { name: 'Open' }).click();
    result.checks.snapshots = await page.getByText('Demo Customer New').isVisible() && await page.getByText('Demo Tailored Jacket').isVisible();
    await page.getByLabel('New immutable internal note').fill('Focused browser internal note');
    await page.getByRole('button', { name: 'Add internal note' }).click();
    await page.getByText('Internal note added.').waitFor();
    result.checks.note = await page.getByText(/Focused browser internal note/).isVisible();

    for (const label of ['Confirmed', 'In Preparation', 'Ready', 'Dispatched', 'Delivered']) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page.getByText('Order status updated.').waitFor();
    }
    result.checks.delivered = await page.getByText('Delivered', { exact: true }).first().isVisible();
    result.checks.history = await page.locator('section', { hasText: 'Immutable history' }).locator('li').count() === 6;

    const summaryPopup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'Print Order Summary' }).click();
    const summary = await summaryPopup;
    await summary.waitForLoadState('networkidle');
    result.checks.summary = await summary.getByText('Demo Order Summary').isVisible() && await summary.getByText('Focused browser internal note').count() === 0;
    await summary.close();

    await page.getByLabel('Status').selectOption('paid');
    await page.getByLabel('Internal reason').fill('Focused manual demonstration');
    await page.getByRole('button', { name: 'Update manual status' }).click();
    await page.getByText('Demonstration payment status updated.').waitFor();
    const receiptPopup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'View Demo Receipt' }).click();
    const receipt = await receiptPopup;
    await receipt.waitForLoadState('networkidle');
    result.checks.receipt = await receipt.getByText('Demo Receipt \u2014 Not a production tax receipt').isVisible() && await receipt.getByText('Focused browser internal note').count() === 0;
    await receipt.close();

    await page.locator('form[action$="/logout"]').evaluate((form) => form.requestSubmit());
    await page.waitForURL('**/');
    await page.goto(`${origin}/admin/orders`, { waitUntil: 'networkidle' });
    result.checks.logoutProtection = page.url().endsWith('/login');

    await login(page, origin, fixture.view_email);
    await page.goto(`${origin}/admin/orders/${fixture.orders.new}`, { waitUntil: 'networkidle' });
    result.checks.viewOnlyNoTransition = await page.getByRole('button', { name: 'Confirmed', exact: true }).count() === 0;
    const denied = await page.goto(`${origin}/admin/orders/${fixture.orders.new}/receipt`, { waitUntil: 'networkidle' });
    result.checks.receiptDenied = denied.status() === 403;
    result.failedLocalAssets = [...new Set(result.failedLocalAssets)];
    result.consoleErrors = [...new Set(result.consoleErrors.filter((error) => !error.includes('favicon.ico')))];
    result.checks.noLocalAssetFailures = result.failedLocalAssets.length === 0;
    result.adminConsoleErrors = [...new Set(result.adminConsoleErrors)];
    result.checks.noBase44AdminDependency = !result.adminConsoleErrors.some((error) => error.includes('Base44'));
    result.passed = Object.values(result.checks).every(Boolean);
    await context.close();
} finally {
    if (browser) await browser.close().catch(() => {});
    result.cleanup.browserClosed = true;
    if (server && server.exitCode === null) server.kill();
    result.cleanup.serverStopRequested = true;
    await writeFile(join(evidence, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
    await rm(runtime, { recursive: true, force: true });
    result.cleanup.runtimeRemoved = true;
}

if (!result.passed) throw new Error(`Focused DEMO-1B workflow failed: ${JSON.stringify(result)}`);
console.log(JSON.stringify(result));
