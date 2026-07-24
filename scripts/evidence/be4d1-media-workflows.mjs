import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8128';
const password = process.env.BE4D_EVIDENCE_PASSWORD;
const assetId = process.env.BE4D_ASSET_ID;
const output = path.resolve('storage/app/evidence/be-4d-1');
if (!password || !assetId) throw new Error('Controlled evidence fixture is missing.');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    browser: await browser.version(),
    viewports: ['1440x900', '768x1024', '375x812'],
    consoleErrors: [], warnings: [], failedRequests: [], failedLocalAssets: [],
    overflow: {}, checks: {}, screenshots: [], intentionalAuthorizationResponses: [],
};

async function session(email) {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('console', (message) => {
        if (message.type() === 'error') findings.consoleErrors.push(message.text());
        if (message.type() === 'warning') findings.warnings.push(message.text());
    });
    page.on('requestfailed', (request) => {
        findings.failedRequests.push(request.url());
        if (request.url().startsWith(baseUrl)) findings.failedLocalAssets.push(request.url());
    });
    const login = await context.request.get(`${baseUrl}/login`);
    const token = (await login.text()).match(/name="_token" value="([^"]+)"/)?.[1];
    await context.request.post(`${baseUrl}/login`, { form: { _token: token, email, password }, maxRedirects: 0 });
    return { context, page };
}

async function capture(page, name, width, height) {
    await page.setViewportSize({ width, height });
    findings.overflow[`${name}:${width}`] = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    const file = path.join(output, `${name}-${width}x${height}.png`);
    await page.screenshot({ path: file, fullPage: true });
    findings.screenshots.push(file.replaceAll('\\', '/'));
}

const cms = await session('be4d.cms@example.test');
await cms.page.setViewportSize({ width: 1440, height: 900 });
await cms.page.goto(`${baseUrl}/admin/media`, { waitUntil: 'networkidle' });
findings.checks.uploadWorkspaceVisible = await cms.page.locator('[data-media-upload-queue]').count() === 1;
await capture(cms.page, 'upload-empty', 1440, 900);

const input = cms.page.locator('[data-media-files]');
await input.setInputFiles([
    { name: 'valid-one.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(12000, 1) },
    { name: 'valid-two.webp', mimeType: 'image/webp', buffer: Buffer.alloc(14000, 2) },
    { name: 'blocked.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg/>') },
]);
await capture(cms.page, 'upload-multi-valid-invalid', 1440, 900);
findings.checks.clientValidation = await cms.page.getByText('This file type is not allowed.').count() > 0;
await cms.page.getByRole('button', { name: 'Upload media', exact: true }).click();
await cms.page.waitForTimeout(120);
await capture(cms.page, 'upload-active-progress', 1440, 900);
await cms.page.waitForTimeout(1200);
findings.checks.successfulConfirmation = await cms.page.locator('[data-queue-state="completed"]').count() >= 2;
await capture(cms.page, 'upload-success-and-failure', 1440, 900);

await input.setInputFiles({ name: 'cancel-me.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(16000, 3) });
await cms.page.getByRole('button', { name: 'Upload media', exact: true }).click();
await cms.page.waitForTimeout(100);
await cms.page.getByRole('button', { name: /Cancel cancel-me\.jpg/ }).click();
findings.checks.cancelState = await cms.page.locator('[data-queue-state="cancelled"]').count() === 1;
await cms.page.getByRole('button', { name: /Retry cancel-me\.jpg/ }).evaluate((button) => button.click());
await cms.page.waitForTimeout(900);
findings.checks.retryState = await cms.page.getByText('cancel-me.jpg').locator('..').locator('..').getByText('completed').count() >= 1;
await capture(cms.page, 'upload-cancel-retry', 1440, 900);

await input.setInputFiles({ name: 'fail-confirmation.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(10000, 4) });
await cms.page.getByRole('button', { name: 'Upload media', exact: true }).click();
await cms.page.waitForTimeout(900);
findings.checks.providerFailureVisible = await cms.page.getByText('Secure provider confirmation failed. Retry confirmation or reselect the file.').count() > 0;
await capture(cms.page, 'upload-provider-failure', 1440, 900);

await input.setInputFiles({ name: 'duplicate.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(11000, 5) });
await cms.page.getByRole('button', { name: 'Upload media', exact: true }).click();
await cms.page.waitForTimeout(900);
findings.checks.duplicateDecisionVisible = await cms.page.getByText('Exact duplicate detected').count() === 1;
await cms.page.getByRole('button', { name: 'Create separate logical asset' }).click();
findings.checks.duplicateReasonRequired = await cms.page.getByText('A reason is required.').count() === 1;
await capture(cms.page, 'upload-duplicate-decision', 1440, 900);
await cms.page.getByRole('button', { name: 'Reuse existing asset' }).click();
await cms.page.waitForTimeout(300);
findings.checks.duplicateReuseCompleted = await cms.page.getByRole('link', { name: 'Inspect media' }).count() >= 1;
await capture(cms.page, 'upload-mobile-queue', 375, 812);

await cms.page.setViewportSize({ width: 1440, height: 900 });
await cms.page.goto(`${baseUrl}/admin/media/${assetId}`, { waitUntil: 'networkidle' });
findings.checks.replacementWorkspaceVisible = await cms.page.locator('[data-media-replacement]').count() === 1;
await cms.page.locator('[data-replacement-file]').setInputFiles({ name: 'wide-replacement.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(18000, 6) });
await cms.page.getByRole('button', { name: 'Upload and compare' }).click();
await cms.page.waitForTimeout(900);
findings.checks.replacementComparisonVisible = await cms.page.getByText('Current and proposed file facts').count() === 1;
findings.checks.replacementReasonPresent = await cms.page.getByLabel('Replacement reason').count() === 1;
findings.checks.noDelete = await cms.page.getByRole('button', { name: /delete/i }).count() === 0;
await capture(cms.page, 'replacement-comparison', 1440, 900);
await capture(cms.page, 'replacement-comparison', 768, 1024);
await cms.page.getByLabel('Replacement reason').fill('Controlled browser replacement');
await cms.page.getByLabel(/I reviewed/).check();
await cms.page.getByRole('button', { name: 'Apply confirmed replacement' }).click();
await cms.page.waitForTimeout(500);
findings.checks.replacementApplied = await cms.page.getByText('Media replacement was committed and audited. The previous provider asset remains retained.').count() > 0;
await capture(cms.page, 'replacement-success', 1440, 900);
await cms.context.close();

const viewer = await session('be4d.viewer@example.test');
await viewer.page.goto(`${baseUrl}/admin/media`, { waitUntil: 'networkidle' });
findings.checks.viewerNoUpload = await viewer.page.locator('[data-media-upload-queue]').count() === 0;
await capture(viewer.page, 'viewer-no-upload', 1440, 900);
await viewer.page.goto(`${baseUrl}/admin/media/${assetId}`, { waitUntil: 'networkidle' });
findings.checks.viewerNoReplacement = await viewer.page.locator('[data-media-replacement]').count() === 0;
await capture(viewer.page, 'viewer-no-replacement', 1440, 900);
await viewer.context.close();

const ordinary = await session('be4d.user@example.test');
const response = await ordinary.page.goto(`${baseUrl}/admin/media`, { waitUntil: 'networkidle' });
findings.intentionalAuthorizationResponses.push({ route: '/admin/media', status: response?.status() });
await capture(ordinary.page, 'ordinary-user-forbidden', 1440, 900);
await ordinary.context.close();

findings.consoleErrors = [...new Set(findings.consoleErrors.filter((entry) => !entry.includes('403 (Forbidden)')))];
findings.warnings = [...new Set(findings.warnings)];
findings.failedRequests = [...new Set(findings.failedRequests)];
findings.failedLocalAssets = [...new Set(findings.failedLocalAssets)];
await browser.close();
await writeFile(path.join(output, 'browser-findings.json'), JSON.stringify(findings, null, 2) + '\n');
console.log(JSON.stringify(findings, null, 2));
