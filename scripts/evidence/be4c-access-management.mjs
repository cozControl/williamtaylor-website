import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BE4C_BASE_URL ?? 'http://127.0.0.1:8126';
const password = process.env.BE4C_EVIDENCE_PASSWORD;
const targetId = process.env.BE4C_TARGET_ID;
const adminId = process.env.BE4C_ADMIN_ID;
const outputDirectory = path.resolve('storage/app/evidence/be-4c');

if (!password || !targetId || !adminId) throw new Error('Evidence credentials and fixture identifiers are required.');
await mkdir(outputDirectory, { recursive: true });

const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    baseUrl,
    browser: await browser.version(),
    consoleErrors: [],
    consoleWarnings: [],
    failedRequests: [],
    failedLocalAssets: [],
    intentionalAuthorizationResponses: [],
    checks: {},
    screenshots: [],
};

async function authenticate(email) {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('console', (message) => {
        if (message.type() === 'error') findings.consoleErrors.push({ email, text: message.text(), url: page.url() });
        if (message.type() === 'warning') findings.consoleWarnings.push({ email, text: message.text(), url: page.url() });
    });
    page.on('requestfailed', (request) => findings.failedRequests.push({ email, url: request.url(), error: request.failure()?.errorText }));
    page.on('response', (response) => {
        if (response.status() >= 400 && new URL(response.url()).origin === new URL(baseUrl).origin) {
            findings.failedLocalAssets.push({ email, url: response.url(), status: response.status() });
        }
    });
    const loginPage = await context.request.get(`${baseUrl}/login`);
    const token = (await loginPage.text()).match(/name="_token" value="([^"]+)"/)?.[1];
    if (!token) throw new Error('Fortify CSRF token was not found.');
    await context.request.post(`${baseUrl}/login`, { form: { _token: token, email, password }, maxRedirects: 0 });
    return { context, page };
}

async function capture(page, name, viewport) {
    await page.setViewportSize(viewport);
    await page.waitForTimeout(150);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    findings.checks[`overflow:${name}:${viewport.width}`] = overflow;
    const file = path.join(outputDirectory, `${name}-${viewport.width}x${viewport.height}.png`);
    await page.screenshot({ path: file, fullPage: true });
    findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
}

const superSession = await authenticate('be4c.super@example.test');
const page = superSession.page;
await page.goto(`${baseUrl}/admin/access/users`, { waitUntil: 'networkidle' });
findings.checks.superUsersVisible = await page.getByRole('heading', { name: 'Users', exact: true }).count() > 0;
findings.checks.searchLabel = await page.getByLabel('Search name or email').count() === 1;
findings.checks.tableHeaders = await page.locator('th[scope="col"]').count();
await capture(page, 'super-users-index', { width: 1440, height: 900 });
await capture(page, 'super-users-index', { width: 375, height: 812 });

await page.goto(`${baseUrl}/admin/access/users/${targetId}`, { waitUntil: 'networkidle' });
findings.checks.sensitiveValuesAbsent = !/two.factor|recovery.code|password hash|session token/i.test(await page.locator('main').innerText());
await capture(page, 'super-user-detail', { width: 1440, height: 900 });
await capture(page, 'super-user-detail', { width: 375, height: 812 });

await page.getByLabel('Change type').selectOption('assign');
await page.getByLabel('Registered role', { exact: true }).selectOption('CMS Manager');
await page.getByLabel('Reason for change').fill('Browser evidence preview only');
await page.getByRole('button', { name: 'Preview change' }).focus();
findings.checks.previewButtonKeyboardFocused = await page.evaluate(() => document.activeElement?.textContent?.includes('Preview change') ?? false);
await page.keyboard.press('Enter');
await page.getByRole('heading', { name: 'Authoritative server preview' }).waitFor();
findings.checks.previewShowsAdminGain = (await page.locator('.admin-preview').innerText()).includes('Enter administration');
await capture(page, 'effective-access-preview', { width: 1440, height: 900 });
await page.getByLabel('I confirm this proposed access change.').check();
findings.checks.confirmationChecked = await page.getByLabel('I confirm this proposed access change.').isChecked();
await capture(page, 'role-assignment-confirmation', { width: 768, height: 1024 });

await page.goto(`${baseUrl}/admin/access/roles`, { waitUntil: 'networkidle' });
findings.checks.roleCatalogueCount = await page.locator('.admin-role-catalogue > article').count();
findings.checks.roleCreateAbsent = await page.getByText('Create Role', { exact: true }).count() === 0;
await capture(page, 'roles-index', { width: 1440, height: 900 });
await page.getByRole('link', { name: 'Inspect role' }).first().click();
await page.waitForLoadState('networkidle');
findings.checks.roleMutationAbsent = await page.locator('[data-admin-mutation]').count() === 0;
await capture(page, 'role-detail', { width: 768, height: 1024 });

await page.goto(`${baseUrl}/admin/access/users/${adminId}`, { waitUntil: 'networkidle' });
await page.getByLabel('Change type').selectOption('revoke');
await page.getByLabel('Registered role', { exact: true }).selectOption('Super Administrator');
await page.getByLabel('Reason for change').fill('Intentional final administrator block evidence');
await page.getByRole('button', { name: 'Preview change' }).click();
await page.getByLabel('I confirm this proposed access change.').check();
await page.getByRole('button', { name: 'Apply confirmed change' }).click();
await page.getByText(/blocked because it would violate/).waitFor();
findings.checks.finalAdministratorBlocked = true;
await capture(page, 'final-super-administrator-blocked', { width: 1440, height: 900 });
await superSession.context.close();

const cmsSession = await authenticate('be4c.cms@example.test');
await cmsSession.page.goto(`${baseUrl}/admin`, { waitUntil: 'networkidle' });
const cmsNavigation = (await cmsSession.page.locator('.admin-sidebar .admin-nav-link').allTextContents()).map((text) => text.trim());
findings.checks.cmsNavigation = cmsNavigation;
findings.checks.cmsUsersRolesHidden = !cmsNavigation.includes('Users') && !cmsNavigation.includes('Roles');
await capture(cmsSession.page, 'cms-navigation', { width: 1440, height: 900 });
for (const destination of ['users', 'roles']) {
    const response = await cmsSession.page.goto(`${baseUrl}/admin/access/${destination}`, { waitUntil: 'networkidle' });
    findings.intentionalAuthorizationResponses.push({ destination, status: response?.status() });
    await capture(cmsSession.page, `cms-${destination}-forbidden`, { width: 1440, height: 900 });
}
await cmsSession.context.close();

findings.failedLocalAssets = findings.failedLocalAssets.filter((item) => !findings.intentionalAuthorizationResponses.some(
    (intentional) => item.url.endsWith(`/admin/access/${intentional.destination}`) && item.status === intentional.status,
));
findings.consoleErrors = findings.consoleErrors.filter((item) => !item.text.includes('403 (Forbidden)'));
await browser.close();
await writeFile(path.join(outputDirectory, 'browser-findings.json'), `${JSON.stringify(findings, null, 2)}\n`);
console.log(JSON.stringify(findings, null, 2));
