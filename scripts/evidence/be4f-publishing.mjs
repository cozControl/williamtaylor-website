import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8129';
const password = process.env.BE4F_EVIDENCE_PASSWORD;
const ids = JSON.parse(process.env.BE4F_PAGE_IDS || '{}');
if (!password || !ids.review_id) throw new Error('Controlled BE-4F evidence fixture is missing.');

const output = path.resolve('storage/app/evidence/be-4f');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    browser: await browser.version(),
    viewports: ['1440x900', '768x1024', '375x812'],
    consoleErrors: [], warnings: [], failedRequests: [], failedLocalAssets: [],
    overflow: {}, checks: {}, screenshots: [], intentionalAuthorizationResponses: [],
};

function observe(page) {
    page.on('console', message => {
        if (message.type() === 'error') findings.consoleErrors.push(message.text());
        if (message.type() === 'warning') findings.warnings.push(message.text());
    });
    page.on('requestfailed', request => {
        findings.failedRequests.push(request.url());
        if (request.url().startsWith(baseUrl)) findings.failedLocalAssets.push(request.url());
    });
}

async function session(email) {
    const context = await browser.newContext();
    const page = await context.newPage();
    observe(page);
    const login = await context.request.get(`${baseUrl}/login`);
    const token = (await login.text()).match(/name="_token" value="([^"]+)"/)?.[1];
    const response = await context.request.post(`${baseUrl}/login`, { form: { _token: token, email, password }, maxRedirects: 0 });
    if (response.status() !== 302) throw new Error(`Authentication failed with ${response.status()}: ${(await response.text()).slice(0, 300)}`);
    findings.checks[`auth-${email}`] = { status: response.status(), location: response.headers()['location'], cookies: (await context.cookies()).map(cookie => cookie.name) };
    return { context, page };
}

async function capture(page, name, width, height) {
    await page.setViewportSize({ width, height });
    findings.overflow[`${name}:${width}`] = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    const file = path.join(output, `${name}-${width}x${height}.png`);
    await page.screenshot({ path: file, fullPage: true });
    findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
}

const cms = await session('be4f.cms@example.test');
await cms.page.goto(`${baseUrl}/admin/content/pages/review`, { waitUntil: 'networkidle' });
findings.checks.queueUrl = cms.page.url();
findings.checks.queueBody = (await cms.page.locator('body').innerText()).slice(0, 160);
findings.checks.queueStates = await cms.page.getByText('Review candidate').count() > 0
    && await cms.page.getByRole('option', { name: 'Scheduled' }).count() > 0
    && await cms.page.getByRole('option', { name: 'Approved' }).count() > 0;
findings.checks.keyboardFilters = await cms.page.getByLabel('Search Pages').count() === 1;
await capture(cms.page, 'review-queue', 1440, 900);
await capture(cms.page, 'review-queue', 768, 1024);
await capture(cms.page, 'review-queue', 375, 812);

for (const [name, id] of Object.entries(ids)) {
    await cms.page.goto(`${baseUrl}/admin/content/pages/${id}`, { waitUntil: 'networkidle' });
    findings.checks[`${name}Workflow`] = await cms.page.locator('.admin-definition-grid dt').count() >= 6
        && await cms.page.getByRole('heading', { name: 'Workflow actions' }).count() === 1;
    findings.checks[`${name}Timeline`] = await cms.page.getByText('Transition history').count() > 0;
    await capture(cms.page, name.replace('_id', ''), 1440, 900);
}

await cms.page.goto(`${baseUrl}/admin/content/pages/${ids.review_id}`, { waitUntil: 'networkidle' });
findings.checks.liveRegion = await cms.page.locator('[aria-live]').count() > 0;
findings.checks.noteLabel = await cms.page.getByLabel('Submission or review note').count() > 0;
findings.checks.focusableActions = await cms.page.getByRole('button').evaluateAll(buttons => buttons.every(button => button.tabIndex >= 0));
await capture(cms.page, 'review-detail', 375, 812);
await cms.context.close();

const ordinary = await session('be4f.user@example.test');
const forbidden = await ordinary.page.goto(`${baseUrl}/admin/content/pages/review`, { waitUntil: 'networkidle' });
findings.intentionalAuthorizationResponses.push({ route: '/admin/content/pages/review', status: forbidden?.status() });
await capture(ordinary.page, 'ordinary-user-forbidden', 1440, 900);
await ordinary.context.close();

findings.consoleErrors = [...new Set(findings.consoleErrors.filter(entry => !entry.includes('403 (Forbidden)')))];
findings.warnings = [...new Set(findings.warnings)];
findings.failedRequests = [...new Set(findings.failedRequests)];
findings.failedLocalAssets = [...new Set(findings.failedLocalAssets)];
await browser.close();
await writeFile(path.join(output, 'browser-findings.json'), `${JSON.stringify(findings, null, 2)}\n`);
process.stdout.write(`${JSON.stringify(findings, null, 2)}\n`);
