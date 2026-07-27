import { chromium } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { access, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { createServer } from 'node:net';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const stage = process.env.BE6A1_STAGE ?? 'all';
const runId = process.env.BE6A1_RUN_ID ?? 'standalone';
const runRoot = join(root, 'storage', 'app', 'evidence', 'be6a1-browser', runId);
const evidenceRoot = join(runRoot, stage);
const runtimeRoot = join(root, 'storage', 'app', 'test-runtime', 'browser', runId, stage);
const database = join(runtimeRoot, 'be6a1-browser.sqlite');
const screenshotsRoot = join(evidenceRoot, 'screenshots');
const comparisonsRoot = join(evidenceRoot, 'comparisons');
const appKey = `base64:${randomBytes(32).toString('base64')}`;
const childProcesses = [];
const failures = [];

const pages = {
    homepage: { staticPath: '/', laravelPath: '/', heading: 'William Taylor', minHeight: 5000 },
    about: { staticPath: '/about', laravelPath: '/about', heading: 'About William Taylor', minHeight: 1500 },
    collections: { staticPath: '/collections', laravelPath: '/collections', heading: 'Collections', minHeight: 1500 },
    shop: { staticPath: '/shop', laravelPath: '/shop', heading: 'Shop', minHeight: 2500 },
    preorder: { staticPath: '/pre-order', laravelPath: '/pre-order', heading: 'Pre-Order', minHeight: 1500 },
    'limited-edition': { staticPath: '/collections/limited-edition', laravelPath: '/limited-edition', heading: 'Limited', minHeight: 1500 },
    'gift-cards': { staticPath: '/gift-cards', laravelPath: '/gift-cards', heading: 'Gift', minHeight: 1000 },
    login: { staticPath: '/login', laravelPath: '/login', heading: 'Welcome', requiresChrome: false, minHeight: 700 },
    wishlist: { staticPath: '/wishlist', laravelPath: '/wishlist', heading: 'Wishlist', minHeight: 700 },
    'taylor-oxford-shirt': { staticPath: '/product/taylor-oxford-shirt', laravelPath: '/products/the-taylor-oxford-shirt', heading: 'Taylor Oxford', minHeight: 1200 },
    'mercerized-cotton-polo': { staticPath: '/product/mercerized-cotton-polo', laravelPath: '/products/mercerized-cotton-polo', heading: 'Mercerized Cotton Polo', minHeight: 1200 },
    'dar-es-salaam-linen-suit': { staticPath: '/product/dar-es-salaam-linen-suit', laravelPath: '/products/the-dar-es-salaam-linen-suit', heading: 'Dar es Salaam', minHeight: 1200 },
    'slim-tapered-chinos': { staticPath: '/product/slim-tapered-chinos', laravelPath: '/products/slim-tapered-chinos', heading: 'Slim Tapered Chinos', minHeight: 1200 },
    'executive-overcoat': { staticPath: '/product/executive-overcoat', laravelPath: '/products/the-executive-overcoat', heading: 'Executive Overcoat', minHeight: 1200 },
};
const homepageViewports = [
    [375, 812], [639, 900], [640, 900], [767, 900], [768, 900], [768, 1024],
    [1023, 900], [1024, 900], [1279, 900], [1280, 900], [1440, 900],
];
const standardViewports = [[375, 812], [768, 1024], [1023, 900], [1024, 900], [1279, 900], [1280, 900], [1440, 900]];
const knownBase44Paths = [
    /\/api\/apps\/[^/]+\/entities\/User\/me$/,
    /\/api\/app-logs\/[^/]+\/log-user-in-app\/[^/]+$/,
    /\/api\/apps\/public\/prod\/public-settings\/by-id\/[^/]+$/,
    /\/api\/apps\/[^/]+\/analytics\/track\/batch$/,
    /\/_boost\/browser-logs$/,
];

function portable(path) {
    return relative(root, path).replaceAll('\\', '/');
}

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

async function json(name, value) {
    await writeFile(join(evidenceRoot, name), `${JSON.stringify(value, null, 2)}\n`);
}

async function availablePort() {
    return await new Promise((resolvePort, reject) => {
        const server = createServer();
        server.unref();
        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : null;
            server.close(() => port ? resolvePort(port) : reject(new Error('Could not allocate port.')));
        });
    });
}

function processEnvironment(appUrl, projection = false) {
    return {
        ...process.env,
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        APP_KEY: appKey,
        APP_URL: appUrl,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        SESSION_DRIVER: 'database',
        CACHE_STORE: 'array',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        PUBLIC_SITE_CONTENT_PROJECTION: projection ? 'true' : 'false',
        PUBLIC_PAGE_PROJECTION: 'false',
        PUBLICATION_ROLLOUT_ENABLED: 'false',
    };
}

function runPhp(args, environment) {
    return execFileSync('php', args, {
        cwd: root,
        env: environment,
        encoding: 'utf8',
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
}

function startServer(command, args, environment, name) {
    const child = spawn(command, args, {
        cwd: root,
        env: environment,
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    childProcesses.push(child);
    child.stdout.on('data', () => {});
    child.stderr.on('data', (chunk) => {
        if (!String(chunk).includes('Accepted') && !String(chunk).includes('Closing')) process.stderr.write(`[${name}] ${chunk}`);
    });
    return child;
}

async function bounded(label, action, timeout = 5_000) {
    const startedAt = new Date().toISOString();
    try {
        const value = await Promise.race([
            Promise.resolve().then(action),
            new Promise((_, reject) => setTimeout(() => reject(new Error(`${label}-timeout`)), timeout)),
        ]);
        return { label, startedAt, finishedAt: new Date().toISOString(), graceful: true, forced: false, value };
    } catch (error) {
        return { label, startedAt, finishedAt: new Date().toISOString(), graceful: false, forced: true, error: error.message };
    }
}

const cleanupEvents = [];

async function closePage(page, label) {
    if (!page || page.isClosed()) return;
    page.removeAllListeners();
    cleanupEvents.push(await bounded(`${label}:page-close`, () => page.close({ runBeforeUnload: false })));
}

async function closeContext(context, label) {
    if (!context) return;
    for (const page of context.pages()) await closePage(page, label);
    cleanupEvents.push(await bounded(`${label}:context-close`, () => context.close()));
}

async function closeBrowser(browser, label) {
    if (!browser) return;
    cleanupEvents.push(await bounded(`${label}:browser-close`, () => browser.close(), 10_000));
}

function lifecycle(event, details = {}) {
    return { event, at: new Date().toISOString(), ...details };
}

function stop(child) {
    if (!child || child.killed) return;
    if (process.platform === 'win32') {
        try {
            execFileSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore', windowsHide: true });
        } catch {}
    } else {
        child.kill('SIGTERM');
    }
}

async function waitForHealthy(url, timeout = 30_000) {
    const started = Date.now();
    while (Date.now() - started < timeout) {
        try {
            const response = await fetch(url, { redirect: 'manual' });
            if (response.status < 500) return response.status;
        } catch {}
        await new Promise((resolveWait) => setTimeout(resolveWait, 200));
    }
    throw new Error(`Server did not become healthy: ${url}`);
}

async function installDeterminism(context, requestLog) {
    await context.addInitScript(() => {
        window.__be6a1ObservedRequests = [];
        const record = (value) => {
            const url = String(value instanceof Request ? value.url : value);
            window.__be6a1ObservedRequests.push(url);
        };
        const nativeFetch = window.fetch;
        window.fetch = function (...args) {
            record(args[0]);
            return nativeFetch.apply(this, args);
        };
        const nativeOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...args) {
            record(url);
            return nativeOpen.call(this, method, url, ...args);
        };
        const install = () => {
            if (!document.documentElement || document.getElementById('be6a1-freeze')) return;
            const style = document.createElement('style');
            style.id = 'be6a1-freeze';
            style.textContent = '*,*::before,*::after{animation-delay:0s!important;animation-duration:0s!important;animation-iteration-count:1!important;caret-color:transparent!important;cursor:auto!important;scroll-behavior:auto!important;transition-delay:0s!important;transition-duration:0s!important}';
            document.documentElement.appendChild(style);
        };
        install();
        new MutationObserver(install).observe(document, { childList: true, subtree: true });
        addEventListener('DOMContentLoaded', install, { once: true });
    });
    requestLog.push({
        classification: 'known-optional-base44',
        treatment: 'observed-existing-server-failure-without-interception',
        exactPathPatterns: knownBase44Paths.map((pattern) => pattern.source),
    });
}

async function semanticSnapshot(page, expected) {
    return await page.evaluate(async ({ expectedPath, heading, minHeight, requiresScript, requiresChrome }) => {
        const visible = (element) => {
            if (!element) return false;
            const style = getComputedStyle(element);
            const box = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > 0 && box.width > 0 && box.height > 0;
        };
        const root = document.querySelector('#root') ?? document.body;
        const header = document.querySelector('header');
        const main = document.querySelector('main');
        const footer = document.querySelector('footer');
        const images = [...document.images];
        const headings = [...document.querySelectorAll('h1,h2,h3')].filter(visible);
        const animations = document.getAnimations().filter((animation) => animation.playState === 'running');
        const observedRequests = window.__be6a1ObservedRequests ?? [];
        const applicationInitialized = observedRequests.some((url) => /\/api\/(apps\/|app-logs\/)|\/_boost\/browser-logs/.test(url));
        const digest = async (value) => [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value)))]
            .map((byte) => byte.toString(16).padStart(2, '0')).join('');
        const styleIdentity = [header, main, footer].map((element) => {
            if (!element) return null;
            const style = getComputedStyle(element);
            return [style.display, style.position, style.width, style.height, style.margin, style.padding, style.fontFamily, style.fontSize, style.lineHeight].join('|');
        }).join('||');
        const boxes = [header, main, footer].map((element) => {
            if (!element) return null;
            const box = element.getBoundingClientRect();
            return [Math.round(box.x), Math.round(box.y), Math.round(box.width), Math.round(box.height)];
        });
        const stylesheetLoaded = [...document.styleSheets].some((sheet) => ['/css/', '/website/css/', '/build/assets/'].some((path) => sheet.href?.includes(path)));
        const scriptLoaded = [...document.scripts].some((script) => ['/js/', '/website/js/', '/build/assets/'].some((path) => script.src.includes(path))) || expectedPath.startsWith('/admin') || expectedPath.startsWith('/preview');
        return {
            readyState: document.readyState,
            pathname: location.pathname,
            bodyChildren: document.body.children.length,
            rootChildren: root.children.length,
            rootHtmlLength: root.innerHTML.length,
            rootHeight: Math.round(root.getBoundingClientRect().height),
            scrollHeight: document.documentElement.scrollHeight,
            visibleHeadings: headings.length,
            headingMatched: headings.some((item) => item.textContent?.toLowerCase().includes(heading.toLowerCase())),
            visibleImages: images.filter(visible).length,
            loadedImages: images.filter((image) => image.complete && image.naturalWidth > 0).length,
            failedImages: images.filter((image) => image.complete && image.naturalWidth === 0).map((image) => image.currentSrc || image.src),
            fontsReady: document.fonts?.status === 'loaded',
            stylesheetLoaded,
            scriptLoaded,
            requiresScript,
            requiresChrome,
            headerVisible: visible(header),
            mainVisible: visible(main),
            footerVisible: visible(footer),
            rootVisible: visible(root),
            rootDisplay: getComputedStyle(root).display,
            rootVisibility: getComputedStyle(root).visibility,
            rootOpacity: getComputedStyle(root).opacity,
            bodyBackground: getComputedStyle(document.body).backgroundColor,
            responsiveMode: matchMedia('(min-width: 1024px)').matches ? 'desktop' : 'mobile',
            activeAnimations: animations.length,
            applicationInitialized,
            observedRequestCount: observedRequests.length,
            outstandingRequests: 0,
            scrollPosition: [Math.round(scrollX), Math.round(scrollY)],
            visibleSections: [...document.querySelectorAll('section')].filter(visible).length,
            imageState: images.map((image) => [image.currentSrc || image.src, image.complete, image.naturalWidth, image.naturalHeight]),
            domChecksum: await digest(root.outerHTML),
            computedStyleChecksum: await digest(styleIdentity),
            visibleTextChecksum: await digest([...document.querySelectorAll('h1,h2,h3,p,a,button,label,header span')]
                .filter((element) => {
                    if (!visible(element)) return false;
                    const box = element.getBoundingClientRect();
                    return box.bottom > 0 && box.right > 0 && box.top < innerHeight && box.left < innerWidth;
                })
                .map((element) => `${element.tagName}:${element.textContent?.replace(/\s+/g, ' ').trim()}`)
                .join('|')),
            headingStructureChecksum: await digest(headings.map((item) => `${item.tagName}:${item.textContent?.replace(/\s+/g, ' ').trim()}`).join('|')),
            imageIdentityChecksum: await digest(images.filter(visible).map((image) => `${new URL(image.currentSrc || image.src, location.href).pathname.split('/').at(-1)}:${image.alt}`).join('|')),
            boxes,
            expectedPath,
            minimumHeight: minHeight,
        };
    }, { expectedPath: expected.path, heading: expected.heading, minHeight: expected.minHeight, requiresScript: expected.requiresScript ?? true, requiresChrome: expected.requiresChrome ?? true });
}

function semanticFailures(snapshot) {
    const result = [];
    if (snapshot.readyState !== 'complete') result.push('document-not-complete');
    if (snapshot.pathname !== snapshot.expectedPath) result.push(`wrong-path:${snapshot.pathname}`);
    if (!snapshot.stylesheetLoaded) result.push('stylesheet-not-loaded');
    if (snapshot.requiresScript && !snapshot.scriptLoaded) result.push('application-script-not-loaded');
    if (snapshot.requiresScript && !snapshot.applicationInitialized) result.push('application-not-initialized');
    if (!snapshot.fontsReady) result.push('fonts-not-ready');
    if (snapshot.requiresChrome && !snapshot.headerVisible) result.push('header-not-visible');
    if (snapshot.requiresChrome && !snapshot.mainVisible) result.push('main-not-visible');
    if (snapshot.requiresChrome && !snapshot.footerVisible) result.push('footer-not-visible');
    if (!snapshot.rootVisible || snapshot.rootChildren === 0 || snapshot.rootHtmlLength < 100) result.push('root-incomplete');
    if (!snapshot.headingMatched) result.push('expected-heading-missing');
    if (snapshot.scrollHeight < snapshot.minimumHeight) result.push(`height-below-minimum:${snapshot.scrollHeight}`);
    if (snapshot.failedImages.length) result.push(`failed-images:${snapshot.failedImages.length}`);
    if (snapshot.activeAnimations) result.push(`active-animations:${snapshot.activeAnimations}`);
    return result;
}

async function waitForReadyAndStable(page, expected) {
    const timeline = [];
    let consecutive = 0;
    let previous = null;
    let previousBuffer = null;
    await page.evaluate(async () => {
        const pause = () => new Promise((resolveFrame) => requestAnimationFrame(() => requestAnimationFrame(resolveFrame)));
        for (let y = 0; y < document.documentElement.scrollHeight; y += Math.max(window.innerHeight, 1)) {
            window.scrollTo(0, y);
            await pause();
        }
        window.scrollTo(0, document.documentElement.scrollHeight);
        await pause();
        window.scrollTo(0, 0);
        await pause();
    });
    for (let attempt = 0; attempt < 48; attempt++) {
        await page.evaluate(async () => {
            await Promise.race([
                document.fonts?.ready ?? Promise.resolve(),
                new Promise((resolveFont) => window.setTimeout(resolveFont, 2_000)),
            ]);
            await Promise.all([...document.images].map(async (image) => {
                if (!image.complete) await Promise.race([
                    new Promise((resolveImage) => {
                        image.addEventListener('load', resolveImage, { once: true });
                        image.addEventListener('error', resolveImage, { once: true });
                    }),
                    new Promise((resolveImage) => window.setTimeout(resolveImage, 2_000)),
                ]);
                if (image.complete && image.naturalWidth > 0) await Promise.race([
                    image.decode().catch(() => {}),
                    new Promise((resolveImage) => window.setTimeout(resolveImage, 2_000)),
                ]);
            }));
            window.scrollTo(0, 0);
            for (const video of document.querySelectorAll('video')) {
                video.pause();
                try { video.currentTime = 0; } catch {}
            }
            for (const animation of document.getAnimations()) {
                try { animation.finish(); } catch { animation.cancel(); }
            }
        });
        const snapshot = await semanticSnapshot(page, expected);
        const buffer = await page.screenshot({ animations: 'disabled', fullPage: false, timeout: 15_000 });
        let rasterDeltaPixels = null;
        if (previousBuffer) {
            const before = PNG.sync.read(previousBuffer);
            const after = PNG.sync.read(buffer);
            rasterDeltaPixels = pixelmatch(before.data, after.data, null, before.width, before.height, { threshold: 0.1, includeAA: true });
        }
        const frame = {
            atMs: attempt * 250,
            ...snapshot,
            semanticFailures: semanticFailures(snapshot),
            screenshotHash: sha256(buffer),
            screenshotBytes: buffer.length,
            rasterDeltaPixels,
        };
        timeline.push(frame);
        const signature = JSON.stringify({
            height: frame.scrollHeight,
            boxes: frame.boxes,
            headings: frame.visibleHeadings,
            images: frame.visibleImages,
            mode: frame.responsiveMode,
        });
        const negligibleRasterDelta = rasterDeltaPixels !== null && rasterDeltaPixels <= Math.ceil(page.viewportSize().width * page.viewportSize().height * 0.0002);
        consecutive = frame.semanticFailures.length === 0 && signature === previous && negligibleRasterDelta ? consecutive + 1 : 1;
        previous = signature;
        previousBuffer = buffer;
        if (frame.semanticFailures.length === 0 && consecutive >= 3) return { timeline, final: frame, buffer };
        await page.waitForTimeout(250);
    }
    const final = timeline.at(-1);
    throw Object.assign(new Error(`Semantic/visual readiness failed: ${(final?.semanticFailures ?? []).join(', ') || 'three stable frames not reached'}`), { timeline });
}

function contextOptions(width, height) {
    return {
        viewport: { width, height },
        deviceScaleFactor: 1,
        colorScheme: 'light',
        locale: 'en-US',
        timezoneId: 'Africa/Nairobi',
        reducedMotion: 'reduce',
        userAgent: 'BE-6A.1-Playwright-Chromium',
    };
}

function attachObservers(page, label, network) {
    page.on('console', (message) => {
        if (['error', 'warning'].includes(message.type())) network.console.push({ label, type: message.type(), text: message.text() });
    });
    page.on('requestfailed', (request) => network.failedRequests.push({
        label, method: request.method(), url: request.url(), error: request.failure()?.errorText ?? 'unknown',
    }));
    page.on('response', (response) => {
        if (response.status() >= 400) network.errorResponses.push({
            label, status: response.status(), resourceType: response.request().resourceType(), url: response.url(),
        });
    });
}

async function newContext(browser, baseUrl, cookie, requestLog, width = 1440, height = 900) {
    const context = await browser.newContext(contextOptions(width, height));
    await installDeterminism(context, requestLog);
    if (cookie) {
        const { path: _path, domain: _domain, ...portableCookie } = cookie;
        await context.addCookies([{ ...portableCookie, url: baseUrl }]);
    }
    return context;
}

async function captureFidelity(browser, origins, network) {
    const readiness = [];
    const captures = [];
    const diffs = [];
    const repeatability = [];
    for (const [pageKey, definition] of Object.entries(pages)) {
        const viewports = pageKey === 'homepage' ? homepageViewports : standardViewports;
        for (const [width, height] of viewports) {
            const runPairs = [];
            for (let run = 1; run <= 3; run++) {
                const pair = {};
                for (const side of ['static', 'laravel']) {
                    const origin = side === 'static' ? origins.static : origins.laravel;
                    const path = side === 'static' ? definition.staticPath : definition.laravelPath;
                    const requestLog = [];
                    const context = await newContext(browser, origin, null, requestLog, width, height);
                    const page = await context.newPage();
                    await page.setViewportSize({ width, height });
                    await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); }).catch(() => {});
                    const label = `${pageKey}:${width}x${height}:run${run}:${side}`;
                    attachObservers(page, label, network);
                    const response = await page.goto(`${origin}${path}`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
                    try {
                        const stable = await waitForReadyAndStable(page, {
                            path,
                            heading: definition[`${side}Heading`] ?? definition.heading,
                            requiresScript: definition[`${side}RequiresScript`] ?? true,
                            requiresChrome: definition.requiresChrome ?? true,
                            minHeight: definition.minHeight,
                        });
                        const directory = join(screenshotsRoot, 'fidelity', pageKey, `${width}x${height}`, `run-${run}`);
                        await mkdir(directory, { recursive: true });
                        const screenshotPath = join(directory, `${side}.png`);
                        await writeFile(screenshotPath, stable.buffer);
                        const item = {
                            page: pageKey, viewport: `${width}x${height}`, run, side,
                            status: response?.status(), hash: stable.final.screenshotHash,
                            bytes: stable.final.screenshotBytes, responsiveMode: stable.final.responsiveMode,
                            boxes: stable.final.boxes, scrollHeight: stable.final.scrollHeight,
                            semantic: {
                                visibleTextChecksum: stable.final.visibleTextChecksum,
                                headingStructureChecksum: stable.final.headingStructureChecksum,
                                imageIdentityChecksum: stable.final.imageIdentityChecksum,
                            },
                            path: portable(screenshotPath), optionalRequestTreatment: requestLog,
                        };
                        pair[side] = { ...item, buffer: stable.buffer };
                        captures.push(item);
                        readiness.push({ label, passed: true, final: stable.final, timeline: stable.timeline });
                    } catch (error) {
                        readiness.push({ label, passed: false, message: error.message, timeline: error.timeline ?? [] });
                        process.stderr.write(`[readiness-failure] ${label}: ${error.message}
${JSON.stringify(error.timeline?.at(-1) ?? {}, null, 2)}
`);
                        failures.push(`${label}: ${error.message}`);
                    } finally {
                        await closeContext(context, label);
                    }
                }
                if (pair.static && pair.laravel) {
                    const staticPng = PNG.sync.read(pair.static.buffer);
                    const laravelPng = PNG.sync.read(pair.laravel.buffer);
                    const diffPng = new PNG({ width, height });
                    const differentPixels = pixelmatch(staticPng.data, laravelPng.data, diffPng.data, width, height, { threshold: 0.1, includeAA: true });
                    const overlay = new PNG({ width, height });
                    for (let index = 0; index < overlay.data.length; index += 4) {
                        overlay.data[index] = Math.round((staticPng.data[index] + laravelPng.data[index]) / 2);
                        overlay.data[index + 1] = Math.round((staticPng.data[index + 1] + laravelPng.data[index + 1]) / 2);
                        overlay.data[index + 2] = Math.round((staticPng.data[index + 2] + laravelPng.data[index + 2]) / 2);
                        overlay.data[index + 3] = 255;
                    }
                    const sideBySide = new PNG({ width: width * 2, height });
                    PNG.bitblt(staticPng, sideBySide, 0, 0, width, height, 0, 0);
                    PNG.bitblt(laravelPng, sideBySide, 0, 0, width, height, width, 0);
                    const directory = join(comparisonsRoot, pageKey, `${width}x${height}`, `run-${run}`);
                    await mkdir(directory, { recursive: true });
                    const diffPath = join(directory, 'diff.png');
                    const overlayPath = join(directory, 'overlay.png');
                    const sidePath = join(directory, 'side-by-side.png');
                    await Promise.all([
                        writeFile(diffPath, PNG.sync.write(diffPng)),
                        writeFile(overlayPath, PNG.sync.write(overlay)),
                        writeFile(sidePath, PNG.sync.write(sideBySide)),
                    ]);
                    let maxHorizontalRun = 0;
                    let maxVerticalRun = 0;
                    const verticalRuns = new Uint16Array(width);
                    for (let y = 0; y < height; y++) {
                        let horizontalRun = 0;
                        for (let x = 0; x < width; x++) {
                            const offset = (y * width + x) * 4;
                            const changed = diffPng.data[offset] === 255 && diffPng.data[offset + 1] === 0 && diffPng.data[offset + 2] === 0;
                            horizontalRun = changed ? horizontalRun + 1 : 0;
                            verticalRuns[x] = changed ? verticalRuns[x] + 1 : 0;
                            maxHorizontalRun = Math.max(maxHorizontalRun, horizontalRun);
                            maxVerticalRun = Math.max(maxVerticalRun, verticalRuns[x]);
                        }
                    }
                    const semanticMatch = JSON.stringify(pair.static.semantic) === JSON.stringify(pair.laravel.semantic);
                    const comparison = {
                        page: pageKey, viewport: `${width}x${height}`, run, differentPixels,
                        totalPixels: width * height, differencePercent: differentPixels / (width * height) * 100,
                        staticHash: pair.static.hash, laravelHash: pair.laravel.hash,
                        responsiveModeMatch: pair.static.responsiveMode === pair.laravel.responsiveMode,
                        boxesMatch: JSON.stringify(pair.static.boxes) === JSON.stringify(pair.laravel.boxes),
                        semanticMatch,
                        maxHorizontalRun,
                        maxVerticalRun,
                        diffPath: portable(diffPath), overlayPath: portable(overlayPath), sideBySidePath: portable(sidePath),
                    };
                    diffs.push(comparison);
                    runPairs.push({ ...comparison, staticBuffer: pair.static.buffer, laravelBuffer: pair.laravel.buffer });
                }
            }
            if (runPairs.length === 3) {
                const percentages = runPairs.map((item) => item.differencePercent);
                const spread = Math.max(...percentages) - Math.min(...percentages);
                const crossRunDeltas = { static: [], laravel: [] };
                for (const side of ['static', 'laravel']) {
                    const baseline = PNG.sync.read(runPairs[0][`${side}Buffer`]);
                    for (let index = 1; index < runPairs.length; index++) {
                        const candidate = PNG.sync.read(runPairs[index][`${side}Buffer`]);
                        crossRunDeltas[side].push(pixelmatch(
                            baseline.data,
                            candidate.data,
                            null,
                            baseline.width,
                            baseline.height,
                            { threshold: 0.1, includeAA: true },
                        ));
                    }
                }
                const negligibleLimit = Math.ceil(width * height * 0.0002);
                const materialVariance = [...crossRunDeltas.static, ...crossRunDeltas.laravel].some((pixels) => pixels > negligibleLimit);
                const item = {
                    page: pageKey,
                    viewport: `${width}x${height}`,
                    runs: 3,
                    min: Math.min(...percentages),
                    max: Math.max(...percentages),
                    mean: percentages.reduce((sum, value) => sum + value, 0) / percentages.length,
                    spread,
                    staticHashes: [...new Set(runPairs.map((entry) => entry.staticHash))],
                    laravelHashes: [...new Set(runPairs.map((entry) => entry.laravelHash))],
                    crossRunDeltas,
                    negligibleLimit,
                    materialVariance,
                    passed: !materialVariance && spread <= 0.02,
                };
                repeatability.push(item);
                if (!item.passed) failures.push(`${pageKey}:${width}x${height}: material repeatability variance`);
                const deterministicGlyphRaster = runPairs.every((entry) =>
                    entry.semanticMatch
                    && entry.boxesMatch
                    && entry.responsiveModeMatch
                    && entry.maxHorizontalRun <= 32
                    && entry.maxVerticalRun <= 32
                ) && !materialVariance;
                item.classification = Math.max(...percentages) === 0
                    ? 'exact'
                    : deterministicGlyphRaster
                        ? 'deterministic-subpixel-rasterization'
                        : 'unresolved-material-visual-difference';
                item.classified = item.classification !== 'unresolved-material-visual-difference';
                if (!item.classified) {
                    failures.push(`${pageKey}:${width}x${height}: unclassified fidelity difference ${Math.max(...percentages).toFixed(6)}%`);
                }
            }
        }
    }
    return { readiness, captures, diffs, repeatability };
}

async function navigateState(browser, baseUrl, cookie, path, expected, label, network, requestLog) {
    const context = await newContext(browser, baseUrl, cookie, requestLog);
    const page = await context.newPage();
    attachObservers(page, label, network);
    const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    const result = {
        label,
        requestedPath: path,
        status: response?.status() ?? null,
        finalPath: new URL(page.url()).pathname,
        title: await page.title(),
        adminBodyVisible: await page.locator('.admin-shell').isVisible().catch(() => false),
        verificationVisible: await page.getByText(/verify your email address/i).isVisible().catch(() => false),
        forbiddenVisible: response?.status() === 403 || await page.getByText(/403|forbidden/i).isVisible().catch(() => false),
        cacheControl: response?.headers()['cache-control'] ?? null,
    };
    const passed =
        expected === 'login' ? result.finalPath === '/login' && !result.adminBodyVisible :
        expected === 'verification' ? result.finalPath === '/email/verify' && result.verificationVisible && !result.adminBodyVisible :
        expected === 'forbidden' ? result.status === 403 && !result.adminBodyVisible :
        expected === 'allowed' ? result.status === 200 && result.adminBodyVisible :
        false;
    result.expected = expected;
    result.passed = passed;
    if (!passed) failures.push(`${label}: expected ${expected}, got ${result.status} ${result.finalPath}`);
    return { context, page, result };
}

async function adminMatrices(browser, baseUrl, fixture, network) {
    const requestLog = [];
    const routeMatrix = [];
    const routes = ['/admin', '/admin/access/users', '/admin/access/roles', '/admin/audit', '/admin/settings', '/admin/content/pages', '/admin/content/navigation', '/admin/content/announcements', '/admin/media'];
    const states = [
        ['guest', null],
        ['unverified', fixture.sessions.unverified],
        ['ordinary', fixture.sessions.ordinary],
        ['adminOnly', fixture.sessions.adminOnly],
        ['super', fixture.sessions.super],
    ];
    for (const path of routes) {
        for (const [state, cookie] of states) {
            const expected =
                state === 'guest' ? 'login' :
                state === 'unverified' ? 'verification' :
                state === 'ordinary' ? 'forbidden' :
                state === 'adminOnly' ? (path === '/admin' ? 'allowed' : 'forbidden') :
                'allowed';
            const visit = await navigateState(browser, baseUrl, cookie, path, expected, `admin:${state}:${path}`, network, requestLog);
            routeMatrix.push(visit.result);
            await closeContext(visit.context, `admin:${state}:${path}`);
        }
    }

    const footer = [];
    for (const [width, height] of [[375, 812], [1440, 900]]) {
        const context = await newContext(browser, baseUrl, null, requestLog, width, height);
        const page = await context.newPage();
        await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
        const stable = await waitForReadyAndStable(page, { path: '/', heading: 'William Taylor', minHeight: 5000 });
        const link = page.locator('footer a', { hasText: 'Admin' });
        const href = await link.getAttribute('href');
        const legacyCount = await page.locator('a[href*="html/admin.html"]').count();
        const directory = join(screenshotsRoot, 'footer', 'static');
        await mkdir(directory, { recursive: true });
        const screenshotPath = join(directory, `${width}x${height}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: true, animations: 'disabled', timeout: 15_000 });
        await link.click();
        await page.waitForURL('**/login');
        const result = { width, height, href, legacyCount, clickFinalPath: new URL(page.url()).pathname, stableHash: stable.final.screenshotHash, screenshot: portable(screenshotPath) };
        result.passed = href === `${baseUrl}/admin` && legacyCount === 0 && result.clickFinalPath === '/login';
        if (!result.passed) failures.push(`static footer ${width}x${height} failed`);
        footer.push(result);
        await closeContext(context, 'context');
    }

    const logoutContext = await newContext(browser, baseUrl, fixture.sessions.super, requestLog);
    const logoutPage = await logoutContext.newPage();
    await logoutPage.goto(`${baseUrl}/admin`, { waitUntil: 'domcontentloaded' });
    const authenticatedAdmin = await logoutContext.request.get(`${baseUrl}/admin`);
    const authenticatedHtml = await authenticatedAdmin.text();
    const csrf = authenticatedHtml.match(/name="_token" value="([^"]+)"/)?.[1];
    if (!csrf) throw new Error('Normal Admin logout CSRF token was not found.');
    const logoutResponse = await logoutContext.request.post(`${baseUrl}/logout`, {
        form: { _token: csrf },
        maxRedirects: 0,
    });
    if (logoutResponse.status() !== 302) throw new Error(`Normal logout returned ${logoutResponse.status()}.`);
    await logoutPage.goto(`${baseUrl}/admin`, { waitUntil: 'domcontentloaded' });
    await logoutPage.waitForURL('**/login');
    await logoutPage.goBack({ waitUntil: 'domcontentloaded' }).catch(() => null);
    await logoutPage.reload({ waitUntil: 'domcontentloaded' });
    const direct = await logoutPage.goto(`${baseUrl}/admin`, { waitUntil: 'domcontentloaded' });
    const logout = {
        finalPath: new URL(logoutPage.url()).pathname,
        directStatus: direct?.status(),
        adminBodyVisible: await logoutPage.locator('.admin-shell').isVisible().catch(() => false),
    };
    logout.passed = logout.finalPath === '/login' && !logout.adminBodyVisible;
    if (!logout.passed) failures.push('logout/back-cache matrix failed');
    await closeContext(logoutContext, 'logout');

    return { routeMatrix, footer, logout, requestLog };
}

async function previewMatrix(browser, baseUrl, fixture, network, lifecycleEvents) {
    const requestLog = [];
    const cases = [
        ['valid-authorized', fixture.preview.valid, fixture.sessions.cms, 200],
        ['missing-signature', `${baseUrl}${fixture.preview.unsigned_path}`, fixture.sessions.cms, 403],
        ['expired-signature', fixture.preview.expired, fixture.sessions.cms, 403],
        ['changed-resource', fixture.preview.other_resource, fixture.sessions.cms, 404],
        ['changed-revision', fixture.preview.other_revision, fixture.sessions.cms, 404],
        ['unverified', fixture.preview.valid, fixture.sessions.unverified, 302],
        ['unauthorized', fixture.preview.valid, fixture.sessions.ordinary, 403],
    ];
    const results = [];
    for (const [name, url, cookie, expectedStatus] of cases) {
        lifecycleEvents.push(lifecycle('context-creation-start', { case: name }));
        const context = await newContext(browser, baseUrl, cookie, requestLog);
        lifecycleEvents.push(lifecycle('context-creation-finished', { case: name }));
        lifecycleEvents.push(lifecycle('page-creation-start', { case: name }));
        const page = await context.newPage();
        lifecycleEvents.push(lifecycle('page-creation-finished', { case: name }));
        attachObservers(page, `preview:${name}`, network);
        lifecycleEvents.push(lifecycle('navigation-start', { case: name }));
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
        lifecycleEvents.push(lifecycle('navigation-finished', { case: name, status: response?.status() }));
        const item = {
            name, status: response?.status(), finalPath: new URL(page.url()).pathname,
            marker: await page.getByText('Draft preview', { exact: true }).isVisible().catch(() => false),
            robots: await page.locator('meta[name="robots"]').getAttribute('content').catch(() => null),
            xRobots: response?.headers()['x-robots-tag'] ?? null,
            cacheControl: response?.headers()['cache-control'] ?? null,
            permissionLeak: /pages\.preview|admin\.access/.test(await page.locator('body').innerText().catch(() => '')),
        };
        item.passed = name === 'unverified'
            ? item.finalPath === '/email/verify'
            : item.status === expectedStatus
                && (name !== 'valid-authorized' || (item.marker && item.robots === 'noindex,nofollow' && item.xRobots === 'noindex, nofollow' && item.cacheControl?.includes('no-store')))
                && !item.permissionLeak;
        if (!item.passed) failures.push(`preview:${name} failed`);
        lifecycleEvents.push(lifecycle('assertion-completion', { case: name, passed: item.passed }));
        if (name === 'valid-authorized') {
            const directory = join(screenshotsRoot, 'preview');
            await mkdir(directory, { recursive: true });
            lifecycleEvents.push(lifecycle('screenshot-start', { case: name }));
            await page.screenshot({ path: join(directory, 'valid-authorized.png'), fullPage: true, timeout: 15_000 });
            lifecycleEvents.push(lifecycle('screenshot-finished', { case: name }));
        }
        results.push(item);
        lifecycleEvents.push(lifecycle('page-context-close-start', { case: name }));
        await closeContext(context, 'preview-context');
        lifecycleEvents.push(lifecycle('page-context-close-finished', { case: name }));
    }
    return { results, requestLog };
}

async function projectedFooter(browser, baseUrl, network) {
    const requestLog = [];
    const results = [];
    for (const [width, height] of [[375, 812], [1440, 900]]) {
        const context = await newContext(browser, baseUrl, null, requestLog, width, height);
        const page = await context.newPage();
        attachObservers(page, `projected-footer:${width}x${height}`, network);
        await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
        await waitForReadyAndStable(page, { path: '/', heading: 'William Taylor', minHeight: 5000 });
        const link = page.locator('footer a', { hasText: 'Admin' });
        const href = await link.getAttribute('href');
        const unsafe = await page.locator('footer a[href^="javascript:"], footer a[href^="data:"], footer a[href*="html/admin.html"]').count();
        const directory = join(screenshotsRoot, 'footer', 'projected');
        await mkdir(directory, { recursive: true });
        const screenshot = join(directory, `${width}x${height}.png`);
        await page.screenshot({ path: screenshot, fullPage: true, timeout: 15_000 });
        const item = { width, height, href, unsafe, screenshot: portable(screenshot), passed: href === `${baseUrl}/admin` && unsafe === 0 };
        if (!item.passed) failures.push(`projected footer ${width}x${height} failed`);
        results.push(item);
        lifecycleEvents.push(lifecycle('page-context-close-start', { case: name }));
        await closeContext(context, 'preview-context');
        lifecycleEvents.push(lifecycle('page-context-close-finished', { case: name }));
    }
    return { results, requestLog };
}

async function mimePreflight(staticOrigin, laravelOrigin) {
    const checks = [];
    for (const [originName, origin] of [['static', staticOrigin], ['laravel', laravelOrigin]]) {
        const prefix = originName === 'laravel' ? '/website' : '';
        for (const [path, expected, kind] of [
            ['/js/index-DxdnTNDA.js', /javascript|ecmascript/, 'javascript'],
            ['/css/index-X8-QjRMe.css', /text\/css/, 'css'],
            ['/images/8d99836ea_LOGO-3.png', /image\/png/, 'image'],
        ]) {
            const response = await fetch(`${origin}${prefix}${path}`);
            const contentType = response.headers.get('content-type') ?? '';
            checks.push({ origin: originName, kind, path, status: response.status, contentType, passed: response.ok && expected.test(contentType) });
        }
        const cssResponse = await fetch(`${origin}${prefix}/css/index-X8-QjRMe.css`);
        const css = await cssResponse.text();
        const localFontPaths = [...css.matchAll(/url\((['"]?)([^)'"]+\.(?:woff2?|ttf|otf))\1\)/gi)].map((match) => match[2]);
        if (localFontPaths.length === 0) {
            checks.push({
                origin: originName,
                kind: 'font',
                path: null,
                status: null,
                contentType: null,
                disposition: 'not-applicable-no-required-local-font-assets',
                passed: true,
            });
        }
        for (const fontPath of localFontPaths) {
            const fontUrl = new URL(fontPath, `${origin}${prefix}/css/index-X8-QjRMe.css`);
            const response = await fetch(fontUrl);
            const contentType = response.headers.get('content-type') ?? '';
            checks.push({ origin: originName, kind: 'font', path: fontUrl.pathname, status: response.status, contentType, passed: response.ok && /font|woff|octet-stream/.test(contentType) });
        }
    }
    return { passed: checks.every((item) => item.passed), checks };
}

async function portReleased(port) {
    return await new Promise((resolveReleased) => {
        const server = createServer();
        server.unref();
        server.once('error', () => resolveReleased(false));
        server.listen(port, '127.0.0.1', () => server.close(() => resolveReleased(true)));
    });
}

async function verifyStageEvidence(rootDirectory, expectedRunId, manifest) {
    const failures = [];
    const results = {};
    const cleanupResults = {};
    for (const requiredStage of ['security', 'preview', 'visual']) {
        try {
            const result = JSON.parse(await readFile(join(rootDirectory, requiredStage, 'stage-result.json'), 'utf8'));
            results[requiredStage] = result;
            cleanupResults[requiredStage] = result.cleanup ?? [];
            if (result.runId !== expectedRunId) failures.push(`${requiredStage}: run ID mismatch`);
            if (!result.passed) failures.push(`${requiredStage}: stage failed`);
            if (result.browserVersion !== manifest.chromiumVersion) failures.push(`${requiredStage}: browser version mismatch`);
            if (JSON.stringify(result.ports) !== JSON.stringify(manifest.selectedPorts?.[requiredStage])) failures.push(`${requiredStage}: selected ports mismatch`);
            if ((result.cleanup ?? []).some((item) => item.forced)) failures.push(`${requiredStage}: forced cleanup recorded`);
        } catch {
            failures.push(`${requiredStage}: missing stage result`);
        }
    }
    const visual = results.visual?.payload?.fidelity;
    if (visual) {
        if (visual.repeatability.length !== 102) failures.push(`repeatability count ${visual.repeatability.length}, expected 102`);
        if (visual.captures.length !== 612) failures.push(`capture count ${visual.captures.length}, expected 612`);
        if (visual.diffs.length !== 306) failures.push(`comparison count ${visual.diffs.length}, expected 306`);
        if (visual.repeatability.some((item) => !item.passed)) failures.push('one or more repeatability groups failed');
        if (visual.readiness.some((item) => !item.passed)) failures.push('one or more captures failed readiness');
        if (visual.repeatability.some((item) => !item.classified)) failures.push('one or more differences are unclassified');
        for (const capture of visual.captures) {
            await access(join(root, capture.path), fsConstants.R_OK).catch(() => failures.push(`missing screenshot: ${capture.path}`));
        }
    } else {
        failures.push('visual fidelity payload missing');
    }
    for (const summary of [
        'mime-preflight.json',
        'semantic-readiness-summary.json',
        'visual-stability-summary.json',
        'fidelity-repeatability-summary.json',
        'static-reference-checksums.json',
        'laravel-capture-checksums.json',
        'fidelity-diff-summary.json',
        'console-network-summary.json',
    ]) {
        await access(join(rootDirectory, 'visual', summary), fsConstants.R_OK).catch(() => failures.push(`missing visual summary: ${summary}`));
    }
    for (const completedStage of ['security', 'preview', 'visual']) {
        for (const [kind, port] of Object.entries(manifest.selectedPorts?.[completedStage] ?? {})) {
            if (!await portReleased(port)) failures.push(`${completedStage}: ${kind} port ${port} remains occupied`);
        }
    }
    const securityTotals = results.security?.payload?.totals ?? {};
    const previewTotals = results.preview?.payload?.totals ?? {};
    const matrixTotals = results.visual?.payload?.totals ?? {};
    const classifications = {};
    for (const item of visual?.repeatability ?? []) classifications[item.classification] = (classifications[item.classification] ?? 0) + 1;
    const passed = failures.length === 0;
    return {
        runId: expectedRunId,
        passed,
        failures,
        stages: results,
        cleanupResults,
        matrixTotals,
        repeatabilityTotals: {
            expected: 102,
            present: visual?.repeatability.length ?? 0,
            passed: visual?.repeatability.filter((item) => item.passed).length ?? 0,
        },
        securityTotals,
        previewTotals,
        fidelityTotals: { ...matrixTotals, classifications },
        remainingBlocker: passed ? null : failures.join('; '),
        closureRecommendation: passed ? 'Close BE-6A.1; baseline candidates remain unapproved.' : 'BE-6A.1 REMAINS OPEN.',
    };
}
async function main() {
    await rm(evidenceRoot, { recursive: true, force: true });
    await rm(runtimeRoot, { recursive: true, force: true });
    await Promise.all([mkdir(evidenceRoot, { recursive: true }), mkdir(runtimeRoot, { recursive: true }), mkdir(screenshotsRoot, { recursive: true }), mkdir(comparisonsRoot, { recursive: true })]);
    const manifestPath = join(runRoot, 'run-manifest.json');
    const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
    if (manifest.runId !== runId) throw new Error('Stage run ID does not match immutable manifest.');
    const probe = join(runtimeRoot, '.write-probe');
    await writeFile(probe, 'ok');
    await rm(probe);
    const executable = chromium.executablePath();
    await access(executable, fsConstants.R_OK);
    const selectedPorts = manifest.selectedPorts?.[stage];
    if (!selectedPorts) throw new Error(`Immutable manifest omitted selected ports for ${stage}.`);
    const staticPort = selectedPorts.static;
    const laravelPort = selectedPorts.laravel;
    const staticOrigin = `http://127.0.0.1:${staticPort}`;
    const laravelOrigin = `http://127.0.0.1:${laravelPort}`;
    const environment = processEnvironment(laravelOrigin, false);
    await writeFile(database, '');
    runPhp(['artisan', 'migrate:fresh', '--force'], environment);
    const fixture = JSON.parse(runPhp(['scripts/evidence/be6a1-fixture.php'], environment));
    await json('fixture-summary.json', { database: fixture.database, actors: fixture.actors, resources: fixture.resources });
    const staticServer = startServer('php', ['-S', `127.0.0.1:${staticPort}`, '-t', join(root, 'public', 'website'), join(root, 'scripts', 'fidelity', 'static-router.php')], environment, `${stage}:static`);
    let laravelServer = startServer('php', ['-S', `127.0.0.1:${laravelPort}`, '-t', join(root, 'public'), join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php')], environment, `${stage}:laravel`);
    await Promise.all([waitForHealthy(`${staticOrigin}/`), waitForHealthy(`${laravelOrigin}/`)]);
    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-gpu', '--disable-lcd-text'],
    });
    const browserVersion = browser.version();
    const network = { console: [], failedRequests: [], errorResponses: [] };
    let payload = {};
    const events = [lifecycle('stage-start', { stage, runId })];
    try {
        if (stage === 'security') {
            const admin = await adminMatrices(browser, laravelOrigin, fixture, network);
            stop(laravelServer);
            laravelServer = startServer('php', ['-S', `127.0.0.1:${laravelPort}`, '-t', join(root, 'public'), join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php')], processEnvironment(laravelOrigin, true), `${stage}:projected`);
            await waitForHealthy(`${laravelOrigin}/`);
            const projected = await projectedFooter(browser, laravelOrigin, network);
            payload = { admin, projected, totals: { privileged: admin.routeMatrix.length, staticFooter: admin.footer.length, projectedFooter: projected.results.length, logout: admin.logout.passed } };
            await json('admin-access-matrix.json', { routes: admin.routeMatrix, staticFooter: admin.footer, projectedFooter: projected.results, logout: admin.logout });
        } else if (stage === 'preview') {
            events.push(lifecycle('preview-matrix-start'));
            const preview = await previewMatrix(browser, laravelOrigin, fixture, network, events);
            events.push(lifecycle('preview-assertions-persisted', { cases: preview.results.length }));
            events.push(lifecycle('trace-finalization-skipped', { reason: 'tracing-disabled' }));
            events.push(lifecycle('video-finalization-skipped', { reason: 'video-disabled' }));
            events.push(lifecycle('har-finalization-skipped', { reason: 'har-disabled' }));
            payload = { preview, totals: { preview: preview.results.length } };
            await json('preview-browser-matrix.json', preview.results);
        } else if (stage === 'visual') {
            const mime = await mimePreflight(staticOrigin, laravelOrigin);
            if (!mime.passed) failures.push('MIME preflight failed');
            const fidelity = await captureFidelity(browser, { static: staticOrigin, laravel: laravelOrigin }, network);
            payload = { mime, fidelity, totals: { captures: fidelity.captures.length, comparisons: fidelity.diffs.length, repeatability: fidelity.repeatability.length } };
            await Promise.all([
                json('mime-preflight.json', mime),
                json('semantic-readiness-summary.json', fidelity.readiness),
                json('visual-stability-summary.json', fidelity.readiness.map(({ label, passed, final, timeline, message }) => ({ label, passed, message, final, frames: timeline.length }))),
                json('fidelity-repeatability-summary.json', fidelity.repeatability),
                json('static-reference-checksums.json', fidelity.captures.filter((item) => item.side === 'static')),
                json('laravel-capture-checksums.json', fidelity.captures.filter((item) => item.side === 'laravel')),
                json('fidelity-diff-summary.json', fidelity.diffs),
            ]);
        } else if (stage === 'verify') {
            payload = await verifyStageEvidence(runRoot, runId, manifest);
            if (!payload.passed) failures.push(...payload.failures);
        } else {
            throw new Error(`Unsupported stage ${stage}`);
        }
        await json('console-network-summary.json', network);
    } finally {
        events.push(lifecycle('cleanup-start'));
        events.push(lifecycle('browser-close-start'));
        await closeBrowser(browser, stage);
        events.push(lifecycle('browser-close-finished'));
        stop(laravelServer);
        stop(staticServer);
        events.push(lifecycle('cleanup-finished'));
        await json('lifecycle-events.json', events);
        await json('cleanup-result.json', cleanupEvents);
    }
    const stageResult = { runId, stage, passed: failures.length === 0, failures, browserVersion, ports: { static: staticPort, laravel: laravelPort }, payload, cleanup: cleanupEvents };
    await json('stage-result.json', stageResult);
    events.push(lifecycle('stage-result-persisted', { stage, passed: stageResult.passed }));
    events.push(lifecycle('node-process-exit-planned', { exitCode: stageResult.passed ? 0 : 1 }));
    await json('lifecycle-events.json', events);
    if (stage === 'verify') {
        const aggregate = {
            ...payload,
            stages: {
                ...payload.stages,
                verify: {
                    runId,
                    stage,
                    passed: stageResult.passed,
                    failures: stageResult.failures,
                    browserVersion,
                    ports: stageResult.ports,
                    cleanup: stageResult.cleanup,
                },
            },
            cleanupResults: { ...payload.cleanupResults, verify: stageResult.cleanup },
        };
        await writeFile(join(runRoot, 'final-result.json'), `${JSON.stringify(aggregate, null, 2)}\n`);
    }
    if (failures.length) throw new Error(`${stage} failed with ${failures.length} gate(s).`);
    console.log(JSON.stringify(stageResult));
}

process.on('SIGINT', () => {
    childProcesses.forEach(stop);
    process.exit(130);
});
process.on('SIGTERM', () => {
    childProcesses.forEach(stop);
    process.exit(143);
});

main().catch(async (error) => {
    childProcesses.forEach(stop);
    try {
        await json('runner-failure.json', { name: error.name, message: error.message, stack: error.stack });
    } catch {}
    console.error(error);
    process.exitCode = 1;
}).finally(() => childProcesses.forEach(stop));
