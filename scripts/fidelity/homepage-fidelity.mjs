import { chromium } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const mode = process.argv[2] ?? 'all';
const pageKey = process.argv[3] ?? 'homepage';
const pageTargets = {
    homepage: { label: 'Homepage', staticPath: '/', laravelPath: '/' },
    collections: { label: 'Collections', staticPath: '/collections', laravelPath: '/collections' },
    shop: { label: 'Shop All', staticPath: '/shop', laravelPath: '/shop' },
    preorder: { label: 'Pre-Order', staticPath: '/pre-order', laravelPath: '/pre-order' },
    'limited-edition': { label: 'Limited Edition', staticPath: '/collections/limited-edition', laravelPath: '/limited-edition' },
    'gift-cards': { label: 'Gift Cards', staticPath: '/gift-cards', laravelPath: '/gift-cards' },
    login: { label: 'Customer Login', staticPath: '/login', laravelPath: '/login' },
    wishlist: { label: 'Wishlist', staticPath: '/wishlist', laravelPath: '/wishlist' },
    'taylor-oxford-shirt': { label: 'The Taylor Oxford Shirt', staticPath: '/product/the-taylor-oxford-shirt', laravelPath: '/products/the-taylor-oxford-shirt' },
    'mercerized-cotton-polo': { label: 'Mercerized Cotton Polo', staticPath: '/product/mercerized-cotton-polo', laravelPath: '/products/mercerized-cotton-polo' },
    'dar-es-salaam-linen-suit': { label: 'The Dar es Salaam Linen Suit', staticPath: '/product/dar-es-salaam-linen-suit', laravelPath: '/products/the-dar-es-salaam-linen-suit' },
    'slim-tapered-chinos': { label: 'Slim Tapered Chinos', staticPath: '/product/slim-tapered-chinos', laravelPath: '/products/slim-tapered-chinos' },
    'executive-overcoat': { label: 'The Executive Overcoat', staticPath: '/product/executive-overcoat', laravelPath: '/products/the-executive-overcoat' },
};
const pageTarget = pageTargets[pageKey];
if (!pageTarget) throw new Error(`Unknown fidelity page "${pageKey}". Expected: ${Object.keys(pageTargets).join(', ')}`);
const outputRoot = join(repositoryRoot, 'storage', 'app', 'fidelity', pageKey);
const reportRoot = join(outputRoot, 'reports');
const staticOrigin = 'http://127.0.0.1:4173';
const laravelOrigin = 'http://127.0.0.1:8000';
const staticUrl = `${staticOrigin}${pageTarget.staticPath}`;
const laravelUrl = `${laravelOrigin}${pageTarget.laravelPath}`;
const breakpointHeight = 900;
const primaryViewports = [
    { key: '375x812', width: 375, height: 812, kind: 'primary' },
    { key: '768x1024', width: 768, height: 1024, kind: 'primary' },
    { key: '1440x900', width: 1440, height: 900, kind: 'primary' },
];
const breakpointViewports = [639, 640, 767, 768, 1023, 1024, 1279, 1280].map((width) => ({
    key: `${width}x${breakpointHeight}`,
    width,
    height: breakpointHeight,
    kind: 'breakpoint',
}));
const viewports = [...primaryViewports, ...breakpointViewports];
const childProcesses = [];

async function ensureDirectories() {
    await Promise.all([
        mkdir(join(outputRoot, 'static'), { recursive: true }),
        mkdir(join(outputRoot, 'laravel'), { recursive: true }),
        mkdir(join(outputRoot, 'diff'), { recursive: true }),
        mkdir(reportRoot, { recursive: true }),
    ]);
}

function startServer(command, args, name) {
    const child = spawn(command, args, {
        cwd: repositoryRoot,
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    child.stdout.on('data', (chunk) => process.stdout.write(`[${name}] ${chunk}`));
    child.stderr.on('data', (chunk) => process.stderr.write(`[${name}] ${chunk}`));
    childProcesses.push(child);
}

async function waitForUrl(url, timeoutMs = 30_000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeoutMs) {
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch {
            // Server is still starting.
        }
        await new Promise((resolvePromise) => setTimeout(resolvePromise, 250));
    }
    throw new Error(`Timed out waiting for ${url}`);
}

function stopServers() {
    for (const child of childProcesses) {
        if (child.killed) continue;

        if (process.platform === 'win32') {
            try {
                execFileSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], {
                    stdio: 'ignore',
                    windowsHide: true,
                });
            } catch {
                // The process may already have exited.
            }
        } else {
            child.kill('SIGTERM');
        }
    }
}

async function waitForVisualReadiness(page) {
    await page.evaluate(async () => {
        if (document.fonts?.ready) await document.fonts.ready;
        await Promise.all(
            [...document.images]
                .filter((image) => !image.complete)
                .map(
                    (image) =>
                        new Promise((resolveImage) => {
                            image.addEventListener('load', resolveImage, { once: true });
                            image.addEventListener('error', resolveImage, { once: true });
                        }),
                ),
        );
    });
    await page.addStyleTag({
        content: `
            *, *::before, *::after {
                animation-delay: 0s !important;
                animation-duration: 0s !important;
                animation-iteration-count: 1 !important;
                caret-color: transparent !important;
                scroll-behavior: auto !important;
                transition-delay: 0s !important;
                transition-duration: 0s !important;
            }
        `,
    });
    await page.evaluate(async () => {
        window.scrollTo(0, 0);
        for (const video of document.querySelectorAll('video')) {
            video.pause();
            try {
                video.currentTime = 0;
            } catch {
                // Remote video may not expose a seekable range.
            }
        }
        await new Promise((resolveFrame) =>
            requestAnimationFrame(() => requestAnimationFrame(resolveFrame)),
        );
    });
}

async function collectInteractions(page, targetName) {
    const result = {
        target: targetName,
        desktopNavigationVisible: await page.locator('nav').first().isVisible().catch(() => false),
        mobileMenu: { present: false, clickChangedVisibleState: false },
        mobileBottomNavigationVisible: false,
        search: { present: false, clickChangedVisibleState: false },
        accountActionPresent: (await page.locator('a[href*="account"], a[href*="login"]').count()) > 0,
        wishlistActionPresent: (await page.locator('a[href*="wishlist"], button[aria-label="Add to wishlist"]').count()) > 0,
        bagAction: { present: false, clickChangedVisibleState: false },
        newsletter: { formPresent: false, emailInputPresent: false, submitPresent: false },
        productSliderControls: await page
            .locator('button:has(svg.lucide-chevron-left), button:has(svg.lucide-chevron-right)')
            .count(),
        whatsappActionPresent: (await page.locator('a[href^="https://wa.me/"]').count()) > 0,
        videos: await page.locator('video').evaluateAll((videos) =>
            videos.map((video) => ({
                autoplay: video.autoplay,
                loop: video.loop,
                muted: video.muted,
                playsInline: video.playsInline,
                source: video.currentSrc || video.getAttribute('src'),
            })),
        ),
        directLoadPassed: (await page.title()).includes('William Taylor'),
        backForwardPassed: false,
        footerLinkCount: await page.locator('footer a[href]').count(),
        pageSpecific: {},
        notes: [],
    };
    if (pageKey === 'collections') {
        result.pageSpecific = {
            collectionLinkCount: await page.locator('main a[href*="collection"], main a[href*="shop"]').count(),
            collectionImageCount: await page.locator('main img').count(),
        };
    } else if (pageKey === 'shop') {
        result.pageSpecific = {
            productCardLinkCount: await page.locator('main a[href*="product"]').count(),
            wishlistButtonCount: await page.locator('main button[aria-label="Add to wishlist"]').count(),
            filterControlCount: await page.locator('main button').filter({ hasText: /filter/i }).count(),
            orderingControlCount: await page.locator('main select, main button').filter({ hasText: /sort|newest|price/i }).count(),
        };
    } else if (pageKey === 'preorder') {
        result.pageSpecific = {
            formCount: await page.locator('form').count(),
            pageFormCount: await page.locator('main form').count(),
            submitControlCount: await page.locator('form button[type="submit"]').count(),
            reserveLinkCount: await page.locator('main a').filter({ hasText: /Reserve Your Piece/i }).count(),
            productOptionControlCount: await page.locator('main select, main input[type="radio"], main input[type="checkbox"]').count(),
            visibleValidationStateCount: await page.locator('[aria-invalid="true"], .error:visible, [role="alert"]:visible').count(),
        };
    } else if (pageKey === 'limited-edition') {
        result.pageSpecific = {
            productLinkCount: await page.locator('main a[href*="product"]').count(),
            wishlistButtonCount: await page.locator('main button[aria-label="Add to wishlist"]').count(),
            limitedBadgeCount: await page.locator('main span').filter({ hasText: /^LIMITED$/ }).count(),
            scarcityClaimCount: await page.getByText(/Only \d+ (Made|left)/i).count(),
        };
    } else if (pageKey === 'login') {
        result.pageSpecific = {
            formCount: await page.locator('form').count(),
            emailInputCount: await page.locator('input[type="email"]').count(),
            passwordInputCount: await page.locator('input[type="password"]').count(),
            submitCount: await page.locator('button[type="submit"]').count(),
            googleActionCount: await page.locator('button').filter({ hasText: /Continue with Google/i }).count(),
            validationStateCount: await page.locator('[role="alert"]').count(),
        };
    } else if (pageKey === 'wishlist') {
        result.pageSpecific = {
            formCount: await page.locator('form').count(),
            emptyStateCount: await page.getByText('Your wishlist is empty', { exact: true }).count(),
            productCardCount: await page.locator('[aria-label="Add to wishlist"]').count(),
            shopActionCount: await page.locator('a').filter({ hasText: /Explore the Collection/i }).count(),
        };
    } else if (pageKey === 'taylor-oxford-shirt' || pageKey === 'mercerized-cotton-polo' || pageKey === 'dar-es-salaam-linen-suit' || pageKey === 'slim-tapered-chinos' || pageKey === 'executive-overcoat') {
        result.pageSpecific = {
            galleryImageCount: await page.locator('main img').count(),
            colorOptionCount: pageKey === 'dar-es-salaam-linen-suit' || pageKey === 'executive-overcoat' ? 0 : await page.locator(pageKey === 'taylor-oxford-shirt' ? 'main button[title="Ivory"], main button[title="Noir"]' : pageKey === 'mercerized-cotton-polo' ? 'main button[title="Camel"], main button[title="Sand"]' : 'main button[title="Sage"], main button[title="Cream"]').count(),
            sizeOptionCount: await page.locator('main button').filter({ hasText: /^(XS|S|M|L|XL|XXL|3XL)$/ }).count(),
            quantityControlCount: await page.locator('main button:has(svg.lucide-minus), main button:has(svg.lucide-plus)').count(),
            addToCartCount: await page.locator('main button').filter({ hasText: /^Add to Cart$/i }).count(),
            reserveYourPieceCount: await page.locator('main button').filter({ hasText: /^Reserve Your Piece$/i }).count(),
            buyNowCount: await page.locator('main button').filter({ hasText: /^Buy Now$/i }).count(),
            addToWishlistCount: await page.locator('main button').filter({ hasText: /^Add to Wishlist$/i }).count(),
            reviewLabelCount: await page.locator('main button').filter({ hasText: pageKey === 'taylor-oxford-shirt' ? /Reviews \(20\)/ : pageKey === 'mercerized-cotton-polo' ? /Reviews \(7\)/ : pageKey === 'dar-es-salaam-linen-suit' ? /Reviews \(6\)/ : pageKey === 'slim-tapered-chinos' ? /Reviews \(26\)/ : /Reviews \(9\)/ }).count(),
            pageFormCount: await page.locator('main form').count(),
        };
    } else if (pageKey === 'gift-cards') {
        result.pageSpecific = {
            formCount: await page.locator('form').count(),
            pageFormCount: await page.locator('main form').count(),
            amountSelectorCount: await page.locator('main form button[type="button"]').filter({ hasText: /TZS/ }).count(),
            designSelectorCount: await page.locator('main form button[type="button"]').filter({ hasText: /Signature|Luminous|Understated/ }).count(),
            recipientAndPurchaserInputCount: await page.locator('main form input[required]').count(),
            messageFieldCount: await page.locator('main form textarea').count(),
            submitControlCount: await page.locator('form button[type="submit"]').count(),
            visibleValidationStateCount: await page.locator('[aria-invalid="true"], .error:visible, [role="alert"]:visible').count(),
        };
    }

    const newsletterForm = page.locator('form:has(input[type="email"])').last();
    result.newsletter.formPresent = (await newsletterForm.count()) > 0;
    result.newsletter.emailInputPresent =
        result.newsletter.formPresent && (await newsletterForm.locator('input[type="email"]').count()) > 0;
    result.newsletter.submitPresent =
        result.newsletter.formPresent &&
        (await newsletterForm.locator('button[type="submit"], input[type="submit"]').count()) > 0;

    const searchButton = page.locator('button:has(svg.lucide-search)').first();
    result.search.present = (await searchButton.count()) > 0;
    if (result.search.present) {
        const before = await page.locator('input[type="search"], [role="dialog"]:visible').count();
        await searchButton.click({ force: true }).catch(() => {});
        const after = await page.locator('input[type="search"], [role="dialog"]:visible').count();
        result.search.clickChangedVisibleState = after !== before;
        await page.keyboard.press('Escape').catch(() => {});
    }

    const bagButton = page.locator('button:has(svg.lucide-shopping-bag)').first();
    result.bagAction.present = (await bagButton.count()) > 0;
    if (result.bagAction.present) {
        const before = await page.locator('[role="dialog"]:visible').count();
        await bagButton.click({ force: true }).catch(() => {});
        const after = await page.locator('[role="dialog"]:visible').count();
        result.bagAction.clickChangedVisibleState = after !== before;
        await page.keyboard.press('Escape').catch(() => {});
    }

    await page.setViewportSize({ width: 375, height: 812 });
    result.mobileBottomNavigationVisible = await page
        .locator('div.lg\\:hidden.fixed.bottom-0')
        .first()
        .isVisible()
        .catch(() => false);
    const menuButton = page.locator('button:has(svg.lucide-menu)').first();
    result.mobileMenu.present = (await menuButton.count()) > 0;
    if (result.mobileMenu.present) {
        const before = await page.locator('[role="dialog"]:visible, nav:visible').count();
        await menuButton.click({ force: true }).catch(() => {});
        const after = await page.locator('[role="dialog"]:visible, nav:visible').count();
        result.mobileMenu.clickChangedVisibleState = after !== before;
        await page.keyboard.press('Escape').catch(() => {});
    }

    const currentUrl = page.url().split('#')[0];
    await page.goto(`${currentUrl}#fidelity-history`, { waitUntil: 'domcontentloaded' });
    await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => null);
    await page.goForward({ waitUntil: 'domcontentloaded' }).catch(() => null);
    result.backForwardPassed = page.url().includes('#fidelity-history');

    if (!result.search.clickChangedVisibleState)
        result.notes.push('Search is present but no visible state change was detected.');
    if (!result.bagAction.clickChangedVisibleState)
        result.notes.push('Bag is present but no visible dialog change was detected.');
    if (!result.mobileMenu.clickChangedVisibleState)
        result.notes.push('Mobile menu is present but no visible state change was detected.');
    return result;
}

async function checkInternalLinks(page, origin) {
    const links = await page.locator('a[href]').evaluateAll((anchors) =>
        anchors.map((anchor) => ({
            href: anchor.href,
            rawHref: anchor.getAttribute('href'),
            text: (anchor.textContent ?? '').replace(/\s+/g, ' ').trim(),
        })),
    );
    const uniqueLinks = [
        ...new Map(
            links
                .filter((link) => link.href.startsWith(origin))
                .map((link) => [link.href, link]),
        ).values(),
    ];
    const results = [];
    for (const link of uniqueLinks) {
        try {
            const response = await fetch(link.href, { redirect: 'manual' });
            results.push({ ...link, status: response.status, ok: response.status < 400 });
        } catch (error) {
            results.push({ ...link, status: null, ok: false, error: error.message });
        }
    }
    return results;
}

async function captureTarget(browser, target) {
    const findings = {
        target: target.name,
        url: target.url,
        captures: [],
        console: [],
        failedRequests: [],
        errorResponses: [],
        failedLocalAssets: [],
        links: [],
        interactions: null,
    };
    for (const viewport of viewports) {
        const context = await browser.newContext({
            viewport: { width: viewport.width, height: viewport.height },
            deviceScaleFactor: 1,
            colorScheme: 'light',
            locale: 'en-US',
            reducedMotion: 'reduce',
            timezoneId: 'Africa/Nairobi',
        });
        const page = await context.newPage();
        page.on('console', (message) => {
            if (['error', 'warning'].includes(message.type()))
                findings.console.push({ viewport: viewport.key, type: message.type(), text: message.text() });
        });
        page.on('requestfailed', (request) =>
            findings.failedRequests.push({
                viewport: viewport.key,
                resourceType: request.resourceType(),
                url: request.url(),
                failure: request.failure()?.errorText ?? 'unknown',
            }),
        );
        page.on('response', (response) => {
            if (response.status() < 400) return;
            const entry = {
                viewport: viewport.key,
                resourceType: response.request().resourceType(),
                status: response.status(),
                url: response.url(),
            };
            findings.errorResponses.push(entry);
            if (
                response.url().startsWith(target.origin) &&
                ['stylesheet', 'script', 'image', 'media', 'font'].includes(entry.resourceType)
            )
                findings.failedLocalAssets.push(entry);
        });
        await page.goto(target.url, { waitUntil: 'networkidle', timeout: 60_000 });
        await waitForVisualReadiness(page);
        const screenshotPath = join(outputRoot, target.name, `${viewport.key}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false, animations: 'disabled' });
        findings.captures.push({
            ...viewport,
            path: screenshotPath.slice(repositoryRoot.length + 1).replaceAll('\\', '/'),
            document: await page.evaluate(() => ({
                width: document.documentElement.scrollWidth,
                height: document.documentElement.scrollHeight,
            })),
        });
        if (viewport.key === '1440x900') {
            findings.links = await checkInternalLinks(page, target.origin);
            findings.interactions = await collectInteractions(page, target.name);
        }
        await context.close();
    }
    return findings;
}

async function capture() {
    await ensureDirectories();
    startServer(
        'php',
        [
            '-S',
            '127.0.0.1:4173',
            '-t',
            join(repositoryRoot, 'public', 'website'),
            join(repositoryRoot, 'scripts', 'fidelity', 'static-router.php'),
        ],
        'static',
    );
    startServer('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], 'laravel');
    await Promise.all([waitForUrl(staticUrl), waitForUrl(laravelUrl)]);
    let browser;
    try {
        browser = await chromium.launch({ headless: true });
        const metadata = {
            generatedAt: new Date().toISOString(),
            page: { key: pageKey, ...pageTarget, staticUrl, laravelUrl },
            browser: { name: 'Chromium', version: browser.version(), deviceScaleFactor: 1, zoom: '100%' },
            normalization: {
                appliedEqually: ['static', 'laravel'],
                animationsAndTransitions: 'Durations forced to zero after page readiness.',
                video: 'Attributes recorded, then video paused at time zero.',
                fonts: 'document.fonts.ready awaited.',
                images: 'All image load/error events awaited.',
                announcement: 'Initial state retained; no storage or DOM masking applied.',
                masking: 'No screenshot regions are masked.',
                breakpointHeight,
            },
            viewports,
        };
        const staticFindings = await captureTarget(browser, {
            name: 'static',
            url: staticUrl,
            origin: staticOrigin,
        });
        const laravelFindings = await captureTarget(browser, {
            name: 'laravel',
            url: laravelUrl,
            origin: laravelOrigin,
        });
        await writeFile(
            join(reportRoot, 'capture.json'),
            JSON.stringify({ metadata, static: staticFindings, laravel: laravelFindings }, null, 2),
        );
    } catch (error) {
        await writeFile(
            join(reportRoot, 'capture-failure.json'),
            JSON.stringify(
                { generatedAt: new Date().toISOString(), name: error.name, message: error.message, stack: error.stack },
                null,
                2,
            ),
        );
        throw error;
    } finally {
        await browser?.close();
        stopServers();
    }
}

async function compare() {
    await ensureDirectories();
    const comparisons = [];
    for (const viewport of viewports) {
        const staticPng = PNG.sync.read(await readFile(join(outputRoot, 'static', `${viewport.key}.png`)));
        const laravelPng = PNG.sync.read(await readFile(join(outputRoot, 'laravel', `${viewport.key}.png`)));
        if (staticPng.width !== laravelPng.width || staticPng.height !== laravelPng.height) {
            comparisons.push({
                viewport: viewport.key,
                kind: viewport.kind,
                status: 'dimension-mismatch',
                static: { width: staticPng.width, height: staticPng.height },
                laravel: { width: laravelPng.width, height: laravelPng.height },
            });
            continue;
        }
        const diff = new PNG({ width: staticPng.width, height: staticPng.height });
        const differentPixels = pixelmatch(
            staticPng.data,
            laravelPng.data,
            diff.data,
            staticPng.width,
            staticPng.height,
            { threshold: 0.1, includeAA: true },
        );
        const totalPixels = staticPng.width * staticPng.height;
        const diffPath = join(outputRoot, 'diff', `${viewport.key}.png`);
        await writeFile(diffPath, PNG.sync.write(diff));
        comparisons.push({
            viewport: viewport.key,
            kind: viewport.kind,
            status: differentPixels === 0 ? 'identical' : 'different',
            differentPixels,
            totalPixels,
            differencePercent: (differentPixels / totalPixels) * 100,
            diffPath: diffPath.slice(repositoryRoot.length + 1).replaceAll('\\', '/'),
        });
    }
    await writeFile(join(reportRoot, 'comparison.json'), JSON.stringify(comparisons, null, 2));
    const markdown = [
        `# ${pageTarget.label} fidelity comparison`,
        '',
        `Generated: ${new Date().toISOString()}`,
        '',
        '| Viewport | Kind | Result | Different pixels | Difference |',
        '|---|---|---:|---:|---:|',
        ...comparisons.map(
            (item) =>
                `| ${item.viewport} | ${item.kind} | ${item.status} | ${item.differentPixels ?? 'n/a'} | ${
                    item.differencePercent === undefined ? 'n/a' : `${item.differencePercent.toFixed(6)}%`
                } |`,
        ),
        '',
        'Any non-zero difference requires review against its diff image. No threshold automatically approves a visible difference.',
        '',
    ].join('\n');
    await writeFile(join(reportRoot, 'comparison.md'), markdown);
}

async function main() {
    if (!['capture', 'compare', 'all'].includes(mode))
        throw new Error('Usage: node scripts/fidelity/homepage-fidelity.mjs [capture|compare|all] [homepage|collections|shop|preorder|limited-edition|gift-cards|login|wishlist|taylor-oxford-shirt]');
    if (['capture', 'all'].includes(mode)) await capture();
    if (['compare', 'all'].includes(mode)) await compare();
}

process.on('SIGINT', () => {
    stopServers();
    process.exit(130);
});
process.on('SIGTERM', () => {
    stopServers();
    process.exit(143);
});
main().catch((error) => {
    stopServers();
    console.error(error);
    process.exitCode = 1;
});
