import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BE4C1_BASE_URL ?? 'http://127.0.0.1:8127';
const password = process.env.BE4C1_EVIDENCE_PASSWORD;
const targetId = process.env.BE4C1_TARGET_ID;
const outputDirectory = path.resolve('storage/app/evidence/be-4c-1');

if (!password || !targetId) throw new Error('Evidence credentials and target identifier are required.');
await mkdir(outputDirectory, { recursive: true });

const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    browser: await browser.version(),
    viewports: ['1440x900', '375x812'],
    consoleErrors: [],
    consoleWarnings: [],
    failedRequests: [],
    failedLocalAssets: [],
    horizontalOverflow: {},
    checks: {},
    screenshots: [],
};
const context = await browser.newContext();
const page = await context.newPage();

page.on('console', (message) => {
    if (message.type() === 'error') findings.consoleErrors.push(message.text());
    if (message.type() === 'warning') findings.consoleWarnings.push(message.text());
});
page.on('requestfailed', (request) => findings.failedRequests.push({
    url: request.url(),
    error: request.failure()?.errorText,
}));
page.on('response', (response) => {
    if (response.status() >= 400 && new URL(response.url()).origin === new URL(baseUrl).origin) {
        findings.failedLocalAssets.push({ url: response.url(), status: response.status() });
    }
});

async function capture(name, viewport) {
    await page.setViewportSize(viewport);
    await page.waitForTimeout(150);
    findings.horizontalOverflow[`${name}:${viewport.width}`] = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth,
    );
    const file = path.join(outputDirectory, `${name}-${viewport.width}x${viewport.height}.png`);
    await page.screenshot({ path: file, fullPage: true });
    findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
}

const login = await context.request.get(`${baseUrl}/login`);
const token = (await login.text()).match(/name="_token" value="([^"]+)"/)?.[1];
if (!token) throw new Error('Fortify CSRF token was not found.');
await context.request.post(`${baseUrl}/login`, {
    form: {
        _token: token,
        email: 'be4c1.super@example.test',
        password,
    },
    maxRedirects: 0,
});

await page.goto(`${baseUrl}/admin/access/users/${targetId}`, { waitUntil: 'networkidle' });
await page.getByLabel('Change type').selectOption('revoke');
await page.getByLabel('Registered role', { exact: true }).selectOption('CMS Manager');
await page.getByLabel('Reason for change').fill('BE-4C.1 controlled browser evidence');
await page.getByRole('button', { name: 'Preview change' }).click();
await page.getByRole('heading', { name: 'Authoritative server preview' }).waitFor();

const initialPreview = await page.locator('.admin-preview').innerText();
findings.checks.rolePlusDirectPreview = initialPreview.includes('Administration access is retained');
findings.checks.noFalseAdminLoss = !initialPreview.includes('This account will lose administration access');
await capture('role-plus-direct-retained-access', { width: 1440, height: 900 });
await capture('mobile-preview', { width: 375, height: 812 });

execFileSync('php', [
    '-r',
    String.raw`require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $u=App\Models\User::findOrFail(${Number(targetId)}); $u->givePermissionTo(App\Domain\Identity\Support\PermissionRegistry::USERS_VIEW);`,
], { cwd: process.cwd(), env: process.env });

await page.getByLabel('I confirm this proposed access change.').check();
await page.getByRole('button', { name: 'Apply confirmed change' }).click();
await page.getByText(/Access changed after this preview was prepared/).waitFor();

findings.checks.staleWarningVisible = true;
findings.checks.confirmationReset = !(await page.getByLabel('I confirm this proposed access change.').isChecked());
findings.checks.requestPreserved = await page.getByLabel('Reason for change').inputValue()
    === 'BE-4C.1 controlled browser evidence';
findings.checks.focusMovedToLiveRegion = await page.evaluate(
    () => document.activeElement?.id === 'access-feedback',
);
findings.checks.assertiveLiveRegion = await page.locator('#access-feedback').getAttribute('aria-live') === 'assertive';
await capture('stale-preview-updated-confirmation-reset', { width: 1440, height: 900 });

await page.getByLabel('I confirm this proposed access change.').check();
await page.getByRole('button', { name: 'Apply confirmed change' }).click();
await page.getByText(/Access was updated and the audit record was committed/).waitFor();
findings.checks.reconfirmationSucceeded = true;
await capture('successful-application-after-reconfirmation', { width: 1440, height: 900 });

await browser.close();
await writeFile(
    path.join(outputDirectory, 'browser-findings.json'),
    `${JSON.stringify(findings, null, 2)}\n`,
);
console.log(JSON.stringify(findings, null, 2));
