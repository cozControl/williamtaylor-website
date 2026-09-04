import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { join, resolve } from 'node:path';
import { chromium } from '@playwright/test';

const root = resolve(import.meta.dirname, '..', '..');
const runId = `demo1a-${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const runtime = join(root, 'storage', 'app', 'test-runtime', runId);
const evidence = join(root, 'storage', 'app', 'evidence', 'demo1a', runId);
const database = join(runtime, 'demo.sqlite');
const password = `Demo1A!${randomBytes(12).toString('hex')}`;
const appKey = `base64:${randomBytes(32).toString('base64')}`;
let server;
let browser;

async function port() {
    return await new Promise((resolvePort, reject) => {
        const socket = createServer();
        socket.once('error', reject);
        socket.listen(0, '127.0.0.1', () => {
            const address = socket.address();
            socket.close(() => resolvePort(address.port));
        });
    });
}

function run(command, args, env) {
    const result = spawnSync(command, args, { cwd: root, env, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw new Error(`${command} ${args.join(' ')} failed: ${result.stderr}`);
    return result.stdout.trim();
}

async function healthy(url) {
    for (let attempt = 0; attempt < 100; attempt++) {
        try {
            const response = await fetch(url, { redirect: 'manual' });
            if (response.status < 500) return;
        } catch {}
        await new Promise((resolveWait) => setTimeout(resolveWait, 100));
    }
    throw new Error('Focused demo server did not become ready.');
}

await mkdir(runtime, { recursive: true });
await mkdir(evidence, { recursive: true });
await writeFile(database, '');
const selectedPort = await port();
const origin = `http://127.0.0.1:${selectedPort}`;
const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_KEY: appKey,
    APP_URL: origin,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    MEDIA_PROVIDER: 'deterministic',
    DEMO_MODE: 'true',
    PUBLICATION_ROLLOUT_ENABLED: 'true',
    PUBLICATION_ROLLOUT_SITE_CONTENT: 'enabled',
    PUBLIC_SITE_CONTENT_PROJECTION: 'true',
    DEMO1A_PASSWORD: password,
};
const result = {
    runId,
    generatedAt: new Date().toISOString(),
    checks: {},
    failedLocalAssets: [],
    consoleErrors: [],
    cleanup: {},
    passed: false,
};

try {
    run('php', ['artisan', 'migrate', '--force'], env);
    const fixture = JSON.parse(run('php', ['scripts/evidence/demo1a-fixture.php'], env));
    server = spawn('php', ['-S', `127.0.0.1:${selectedPort}`, '-t', 'public', 'scripts/evidence/be6a1-laravel-router.php'], {
        cwd: root,
        env,
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    await healthy(`${origin}/login`);
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('requestfailed', (request) => {
        if (request.url().startsWith(origin)) result.failedLocalAssets.push(request.url());
    });
    page.on('console', (message) => {
        if (message.type() === 'error') result.consoleErrors.push(message.text());
    });

    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Email').fill(fixture.email);
    await page.getByLabel('Password').fill(password);
    const loginResponsePromise = page.waitForResponse((response) => response.url() === `${origin}/login` && response.request().method() === 'POST');
    await page.getByRole('button', { name: /log in/i }).click();
    const loginResponse = await loginResponsePromise;
    await page.waitForLoadState('networkidle');
    result.login = {
        status: loginResponse.status(),
        url: page.url(),
        error: await page.locator('[role="alert"]').textContent().catch(() => null),
    };
    if (!/^\/admin\/?$/.test(new URL(page.url()).pathname)) {
        throw new Error(`Focused login failed: ${JSON.stringify(result.login)}`);
    }
    result.checks.login = true;
    result.checks.banner = await page.getByText('Demo Environment \u2014 Content changes affect the client testing website only.').isVisible();
    result.checks.dashboard = await page.getByText('Client Demo', { exact: true }).isVisible();
    await page.getByRole('link', { name: 'Global content workspace' }).click();
    await page.waitForURL('**/admin/settings');
    result.checks.workspace = await page.getByText('Contact and WhatsApp').isVisible();

    const change = `DEMO-1A verified footer ${runId}`;
    await page.getByLabel('Description').last().fill(change);
    await page.getByRole('group', { name: 'Footer image' }).getByText('demo-footer.jpg', { exact: true }).click();
    await page.getByLabel('Change summary').fill('Focused DEMO-1A browser workflow');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.getByText('Draft saved as an immutable revision.').waitFor();
    result.checks.immutableRevision = await page.getByText('Revision 2', { exact: false }).count() > 0;
    result.checks.mediaSelected = await page.getByText('Current selection').count() > 0;

    const popup = page.waitForEvent('popup');
    await page.getByRole('button', { name: 'Secure preview' }).click();
    const preview = await popup;
    await preview.waitForLoadState('networkidle');
    result.checks.preview = await preview.getByText(change).isVisible()
        && await preview.locator('meta[name="robots"][content="noindex,nofollow"]').count() === 1;
    await preview.close();

    const publicBefore = await context.newPage();
    await publicBefore.goto(`${origin}/`, { waitUntil: 'networkidle' });
    result.checks.unchangedBeforePublish = await publicBefore.getByText(change).count() === 0;
    await publicBefore.close();

    await page.getByLabel('Submission or review note').fill('Ready for demo review');
    await page.getByRole('button', { name: 'Submit for review' }).click();
    await page.getByText('Submitted for review.').waitFor();
    await page.getByRole('button', { name: 'Approve candidate' }).click();
    await page.getByText('Candidate approved.').waitFor();
    result.checks.reviewApproval = true;
    await page.getByRole('button', { name: 'Designate published' }).click();
    await page.getByText(/Revision designated published/).waitFor();

    const published = await context.newPage();
    await published.goto(`${origin}/`, { waitUntil: 'networkidle' });
    result.checks.published = await published.getByText(change).isVisible();
    result.checks.protectedAdminLink = await published.locator('footer a[href$="/admin"]', { hasText: 'Admin' }).count() === 1;
    await published.close();

    await page.getByLabel('High-impact reason').fill('Restore static fallback after focused demo');
    await page.getByRole('button', { name: 'Unpublish' }).click();
    await page.getByText('Published designation removed.').waitFor();
    const fallback = await context.newPage();
    await fallback.goto(`${origin}/`, { waitUntil: 'networkidle' });
    result.checks.staticFallback = await fallback.getByText(change).count() === 0;
    await fallback.close();

    await page.locator('form[action$="/logout"]').evaluate((form) => form.requestSubmit());
    await page.waitForURL('**/');
    await page.goto(`${origin}/admin`, { waitUntil: 'networkidle' });
    result.checks.logoutProtection = page.url().endsWith('/login');
    result.failedLocalAssets = [...new Set(result.failedLocalAssets)];
    result.consoleErrors = [...new Set(result.consoleErrors.filter((value) => !value.includes('favicon.ico')))];
    result.checks.noLocalAssetFailures = result.failedLocalAssets.length === 0;
    result.checks.noBase44AdminDependency = !result.failedLocalAssets.some((url) => url.includes('base44'));
    result.knownLimitations = result.consoleErrors.filter((value) => value.includes('Base44Error') || value.includes('404'));
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

if (!result.passed) throw new Error(`Focused DEMO-1A workflow failed: ${JSON.stringify(result)}`);
console.log(JSON.stringify(result));
