import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BE4B_BASE_URL ?? 'http://127.0.0.1:8124';
const outputDirectory = path.resolve('storage/app/evidence/be-4b');
const password = process.env.BE4B_EVIDENCE_PASSWORD;

if (!password) {
    throw new Error('BE4B_EVIDENCE_PASSWORD is required.');
}

await mkdir(outputDirectory, { recursive: true });

const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    baseUrl,
    browser: await browser.version(),
    consoleErrors: [],
    consoleWarnings: [],
    failedRequests: [],
    errorResponses: [],
    checks: {},
    screenshots: [],
};

async function createSession(email) {
    const context = await browser.newContext();
    const page = await context.newPage();

    page.on('console', (message) => {
        if (message.type() === 'error') findings.consoleErrors.push({ email, text: message.text() });
        if (message.type() === 'warning') findings.consoleWarnings.push({ email, text: message.text() });
    });
    page.on('requestfailed', (request) => findings.failedRequests.push({
        email,
        url: request.url(),
        error: request.failure()?.errorText,
    }));
    page.on('response', (response) => {
        if (response.status() >= 400) {
            findings.errorResponses.push({ email, url: response.url(), status: response.status() });
        }
    });

    const loginPage = await context.request.get(`${baseUrl}/login`);
    const loginHtml = await loginPage.text();
    const token = loginHtml.match(/name="_token" value="([^"]+)"/)?.[1];
    if (!token) throw new Error('Fortify CSRF token was not found.');
    await context.request.post(`${baseUrl}/login`, {
        form: { _token: token, email, password },
        maxRedirects: 0,
    });
    await page.goto(`${baseUrl}/admin`, { waitUntil: 'networkidle' });

    return { context, page };
}

async function screenshot(page, name, viewport) {
    await page.setViewportSize(viewport);
    await page.waitForTimeout(100);
    const file = path.join(outputDirectory, `${name}-${viewport.width}x${viewport.height}.png`);
    await page.screenshot({ path: file, fullPage: true });
    findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
}

const cms = await createSession('cms.evidence@example.test');
const cmsLinks = await cms.page.locator('.admin-sidebar .admin-nav-link').allTextContents();
findings.checks.cmsNavigation = cmsLinks.map((text) => text.trim());
findings.checks.cmsNavigationCorrect = JSON.stringify(findings.checks.cmsNavigation) === JSON.stringify(['Dashboard', 'Audit log', 'Settings']);
console.log(JSON.stringify({ url: cms.page.url(), title: await cms.page.title(), cmsNavigation: findings.checks.cmsNavigation, text: (await cms.page.locator('body').innerText()).slice(0, 300) }));

for (const viewport of [
    { width: 375, height: 812 },
    { width: 768, height: 1024 },
    { width: 1440, height: 900 },
]) {
    await screenshot(cms.page, 'cms-dashboard', viewport);
}

await cms.page.setViewportSize({ width: 375, height: 812 });
await cms.page.getByRole('button', { name: 'Open administration navigation' }).click();
findings.checks.drawerOpened = await cms.page.locator('#admin-navigation-dialog').evaluate((element) => element.open);
await screenshot(cms.page, 'cms-mobile-drawer', { width: 375, height: 812 });
await cms.page.keyboard.press('Escape');
findings.checks.drawerClosedWithEscape = !await cms.page.locator('#admin-navigation-dialog').evaluate((element) => element.open);
findings.checks.drawerFocusRestored = await cms.page.evaluate(() => document.activeElement?.matches('[data-admin-nav-open]') ?? false);

await cms.page.goto(`${baseUrl}/admin/audit`, { waitUntil: 'networkidle' });
findings.checks.auditActiveState = await cms.page.locator('.admin-sidebar a[aria-current="page"]').first().innerText();
findings.checks.auditPlaceholderHasTable = await cms.page.locator('[data-admin-workspace] table').count() > 0;
await screenshot(cms.page, 'cms-audit-placeholder', { width: 1440, height: 900 });

const deniedResponse = await cms.page.goto(`${baseUrl}/admin/access/users`, { waitUntil: 'networkidle' });
findings.checks.cmsUsersStatus = deniedResponse?.status();
await screenshot(cms.page, 'cms-users-forbidden', { width: 1440, height: 900 });
await cms.context.close();

const superSession = await createSession('super.evidence@example.test');
const superLinks = await superSession.page.locator('.admin-sidebar .admin-nav-link').allTextContents();
findings.checks.superNavigation = superLinks.map((text) => text.trim());
findings.checks.superNavigationCorrect = JSON.stringify(findings.checks.superNavigation) === JSON.stringify(['Dashboard', 'Users', 'Roles', 'Audit log', 'Settings']);
await screenshot(superSession.page, 'super-dashboard', { width: 1440, height: 900 });
await superSession.page.getByRole('link', { name: 'Users', exact: true }).first().click();
await superSession.page.waitForURL('**/admin/access/users');
findings.checks.usersPlaceholderHasTable = await superSession.page.locator('[data-admin-workspace] table').count() > 0;
await screenshot(superSession.page, 'super-users-placeholder', { width: 768, height: 1024 });
await superSession.context.close();

await browser.close();
await writeFile(path.join(outputDirectory, 'browser-findings.json'), `${JSON.stringify(findings, null, 2)}\n`);
console.log(JSON.stringify(findings, null, 2));
