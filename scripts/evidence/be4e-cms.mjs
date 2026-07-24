import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8128';
const password = process.env.BE4E_EVIDENCE_PASSWORD;
const pageId = process.env.BE4E_PAGE_ID;
if (!password || !pageId) throw new Error('Controlled BE-4E evidence fixture is missing.');

const output = path.resolve('storage/app/evidence/be-4e');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const findings = {
    generatedAt: new Date().toISOString(),
    browser: await browser.version(),
    viewports: ['1440x900', '768x1024', '375x812'],
    consoleErrors: [],
    warnings: [],
    failedRequests: [],
    failedLocalAssets: [],
    overflow: {},
    checks: {},
    screenshots: [],
    intentionalAuthorizationResponses: [],
};

async function session(email) {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('console', message => {
        if (message.type() === 'error') findings.consoleErrors.push(message.text());
        if (message.type() === 'warning') findings.warnings.push(message.text());
    });
    page.on('requestfailed', request => {
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
    findings.screenshots.push(path.relative(process.cwd(), file).replaceAll('\\', '/'));
}

const cms = await session('be4e.cms@example.test');
await cms.page.goto(`${baseUrl}/admin/content/pages`, { waitUntil: 'networkidle' });
findings.checks.populatedIndex = await cms.page.getByText('BE-4E Editorial Landing').count() > 0;
await capture(cms.page, 'pages-index', 1440, 900);
await capture(cms.page, 'pages-index-mobile-filters', 375, 812);
await cms.page.locator('#page-search').fill('no matching page');
await cms.page.getByText('No matching draft pages').waitFor();
findings.checks.noResults = await cms.page.getByText('No matching draft pages').count() > 0;
await capture(cms.page, 'pages-index-no-results', 1440, 900);

await cms.page.goto(`${baseUrl}/admin/content/pages/create`, { waitUntil: 'networkidle' });
await capture(cms.page, 'page-create', 1440, 900);
await cms.page.locator('.cms-create-form').evaluate(form => { form.noValidate = true; });
await cms.page.getByRole('button', { name: 'Create initial draft' }).click();
await cms.page.getByText('Correct the page details').waitFor();
findings.checks.creationValidation = await cms.page.getByText('Correct the page details').count() > 0;
await capture(cms.page, 'page-create-validation', 375, 812);

await cms.page.goto(`${baseUrl}/admin/content/pages/${pageId}/edit`, { waitUntil: 'networkidle' });
findings.checks.editorRegions = await cms.page.locator('.cms-outline, .cms-canvas, .cms-context').count() === 3;
findings.checks.richTextToolbar = await cms.page.getByRole('toolbar', { name: 'Rich text formatting' }).count() === 0;
findings.checks.noPublishingControls = await cms.page.getByRole('button', { name: /^(Publish|Review|Approve|Schedule)$/i }).count() === 0;
await capture(cms.page, 'page-editor', 1440, 900);
await capture(cms.page, 'page-editor', 768, 1024);
await capture(cms.page, 'page-editor', 375, 812);
await cms.page.getByRole('button', { name: '2. Rich text' }).click();
await cms.page.waitForTimeout(200);
findings.checks.richTextToolbar = await cms.page.getByRole('toolbar', { name: 'Rich text formatting' }).count() > 0;
await capture(cms.page, 'rich-text-toolbar', 1440, 900);
await cms.page.getByRole('button', { name: 'Move section 2 up' }).click();
await cms.page.waitForTimeout(350);
findings.checks.keyboardReorderControl = await cms.page.getByRole('button', { name: /1\. Rich text/ }).count() > 0;
await cms.page.getByRole('button', { name: 'Duplicate' }).first().click();
await cms.page.waitForTimeout(350);
findings.checks.sectionDuplicate = await cms.page.locator('.cms-outline li').count() === 4;
await cms.page.getByRole('button', { name: 'Remove' }).first().click();
await cms.page.waitForTimeout(350);
findings.checks.sectionRemove = await cms.page.locator('.cms-outline li').count() === 3;
await cms.page.getByLabel('Page title').fill('BE-4E Editorial Landing Updated');
await cms.page.getByLabel('Page title').press('Tab');
await cms.page.waitForTimeout(350);
findings.checks.unsavedState = await cms.page.locator('[data-cms-editor][data-dirty="true"]').count() > 0;
await cms.page.getByRole('button', { name: 'Save draft' }).click();
await cms.page.waitForTimeout(500);
findings.checks.saveSuccess = await cms.page.getByText(/Draft revision .* saved immutably\./).count() > 0;
await capture(cms.page, 'page-editor-save-success', 1440, 900);

await cms.page.goto(`${baseUrl}/admin/content/pages/${pageId}`, { waitUntil: 'networkidle' });
findings.checks.readOnlyHistory = await cms.page.getByText('Immutable revision history').count() > 0;
await capture(cms.page, 'page-detail-history', 1440, 900);
await cms.page.getByLabel('Reason').fill('Controlled archive evidence');
await cms.page.getByRole('button', { name: 'Archive draft page' }).click();
await cms.page.waitForTimeout(300);
findings.checks.archivedReadOnly = await cms.page.getByText('Archived', { exact: true }).count() === 1
    && await cms.page.getByRole('link', { name: 'Edit draft' }).count() === 0;
await capture(cms.page, 'page-archived-read-only', 1440, 900);
await cms.page.getByLabel('Reason').fill('Controlled restore evidence');
await cms.page.getByRole('button', { name: 'Restore active draft' }).click();
await cms.page.getByText('Page restored to active draft.').waitFor();
findings.checks.restored = await cms.page.getByText('Active draft', { exact: true }).count() > 0;
await capture(cms.page, 'page-restored', 1440, 900);

const previewPromise = cms.context.waitForEvent('page');
await cms.page.getByRole('button', { name: 'Preview current revision' }).click();
const preview = await previewPromise;
await preview.waitForLoadState('networkidle');
findings.checks.previewBanner = await preview.getByText('Draft preview').count() > 0;
findings.checks.previewNoEditControls = await preview.getByRole('button', { name: /save|publish|edit/i }).count() === 0;
findings.checks.sanitizedProjection = await preview.getByText('Restricted rich text is validated and sanitized on the server.').count() > 0;
findings.checks.mediaAlt = await preview.locator('img[alt="Tailored jacket displayed in the studio"]').count() > 0;
await capture(preview, 'draft-preview', 1440, 900);
await capture(preview, 'draft-preview', 768, 1024);
await capture(preview, 'draft-preview', 375, 812);
await preview.close();
await cms.context.close();

const ordinary = await session('be4e.user@example.test');
const forbidden = await ordinary.page.goto(`${baseUrl}/admin/content/pages`, { waitUntil: 'networkidle' });
findings.intentionalAuthorizationResponses.push({ route: '/admin/content/pages', status: forbidden?.status() });
await capture(ordinary.page, 'ordinary-user-forbidden', 1440, 900);
await ordinary.context.close();

findings.consoleErrors = [...new Set(findings.consoleErrors.filter(entry => !entry.includes('403 (Forbidden)')))];
findings.warnings = [...new Set(findings.warnings)];
findings.failedRequests = [...new Set(findings.failedRequests)];
findings.failedLocalAssets = [...new Set(findings.failedLocalAssets)];
await browser.close();
await writeFile(path.join(output, 'browser-findings.json'), `${JSON.stringify(findings, null, 2)}\n`);
console.log(JSON.stringify(findings, null, 2));
