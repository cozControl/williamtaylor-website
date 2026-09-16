import { chromium } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { access, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { createServer } from 'node:net';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PNG } from 'pngjs';
import {
    BROWSER_LAUNCH_ARGS,
    DATABASE_PROCEDURE,
    DECORATIVE_MEDIA_ALLOWLIST,
    MOTION_NORMALIZATION_CSS,
    READINESS_CONFIG,
    STATIC_ROUTER_NORMALIZATION,
    VISUAL_NORMALIZATION_CSS,
    analyzeSubpixelMorphology,
    assessRepeatability,
    canonicalPng,
    classifyNetworkEvidence,
    compareRasterBuffers,
    compareSemantics,
} from './be6a1-fidelity-contract.mjs';

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
const readinessNonce = sha256(`${runId}|${stage}|${randomBytes(16).toString('hex')}`);
const childProcesses = [];
const ownedProcessIdentities = new Map();
const failures = [];
const captureStates = {};
const pageEvidenceState = new WeakMap();
const diagnosticPageFilter = new Set((process.env.BE6A1_PAGE_FILTER ?? '').split(',').map((value) => value.trim()).filter(Boolean));
const diagnosticViewportFilter = new Set((process.env.BE6A1_VIEWPORT_FILTER ?? '').split(',').map((value) => value.trim()).filter(Boolean));
const diagnostic = diagnosticPageFilter.size > 0 || diagnosticViewportFilter.size > 0;

const pages = {
    homepage: { staticPath: '/', laravelPath: '/', heading: 'William Taylor', minHeight: 5000 },
    about: { staticPath: '/about', laravelPath: '/about', heading: 'About William Taylor', minHeight: 1500 },
    collections: { staticPath: '/collections', laravelPath: '/collections', heading: 'Collections', minHeight: 1500 },
    shop: { staticPath: '/shop', laravelPath: '/shop', heading: 'Shop', minHeight: 2500 },
    preorder: { staticPath: '/pre-order', laravelPath: '/pre-order', heading: 'Pre-Order', minHeight: 1500 },
    'limited-edition': { staticPath: '/collections/limited-edition', laravelPath: '/limited-edition', heading: 'Limited', minHeight: 1500 },
    'gift-cards': { staticPath: '/gift-cards', laravelPath: '/gift-cards', heading: 'Gift', minHeight: 1000 },
    login: {
        staticPath: '/login',
        laravelPath: '/login',
        heading: 'Welcome',
        staticRequiresScript: true,
        staticRequiresSpa: true,
        laravelRequiresScript: false,
        laravelRequiresSpa: false,
        requiresChrome: false,
        minHeight: 700,
    },
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

function inspectProcess(pid) {
    if (!Number.isInteger(Number(pid)) || Number(pid) <= 0) {
        return { pid: Number(pid) || null, alive: false, observedAt: new Date().toISOString() };
    }
    const numericPid = Number(pid);
    if (process.platform !== 'win32') {
        try {
            process.kill(numericPid, 0);
            return { pid: numericPid, alive: true, observedAt: new Date().toISOString() };
        } catch {
            return { pid: numericPid, alive: false, observedAt: new Date().toISOString() };
        }
    }
    try {
        const command = [
            `$process = Get-CimInstance Win32_Process -Filter "ProcessId = ${numericPid}"`,
            'if ($null -eq $process) { [pscustomobject]@{ alive = $false; pid = ' + numericPid + ' } | ConvertTo-Json -Compress }',
            'else { [pscustomobject]@{ alive = $true; pid = [int]$process.ProcessId; parentPid = [int]$process.ParentProcessId; creationDate = [string]$process.CreationDate; executablePath = [string]$process.ExecutablePath; commandLine = [string]$process.CommandLine } | ConvertTo-Json -Compress }',
        ].join('\n');
        const raw = JSON.parse(execFileSync(
            'powershell.exe',
            ['-NoProfile', '-NonInteractive', '-EncodedCommand', Buffer.from(command, 'utf16le').toString('base64')],
            { encoding: 'utf8', windowsHide: true, timeout: 15_000, stdio: ['ignore', 'pipe', 'ignore'] },
        ).trim());
        return {
            pid: numericPid,
            parentPid: raw.parentPid ?? null,
            creationDate: raw.creationDate || null,
            executableName: raw.executablePath
                ? String(raw.executablePath).split(/[\\/]/).at(-1)
                : null,
            executablePathHash: raw.executablePath ? sha256(String(raw.executablePath)) : null,
            commandLineHash: raw.commandLine ? sha256(String(raw.commandLine)) : null,
            alive: raw.alive === true,
            observedAt: new Date().toISOString(),
        };
    } catch (error) {
        let alive = false;
        try {
            process.kill(numericPid, 0);
            alive = true;
        } catch {}
        return {
            pid: numericPid,
            alive,
            observedAt: new Date().toISOString(),
            inspectionError: error.message,
        };
    }
}

function registerOwnedProcess(processHandle, label) {
    const identity = inspectProcess(processHandle?.pid);
    ownedProcessIdentities.set(processHandle?.pid, identity);
    cleanupEvents.push({
        label: `${label}:owned-process-start`,
        pid: processHandle?.pid ?? null,
        identity,
        graceful: true,
        forced: false,
        passed: identity.alive && (
            process.platform !== 'win32'
            || Boolean(identity.creationDate && identity.executablePathHash)
        ),
        at: new Date().toISOString(),
    });
    return identity;
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

function processEnvironment(appUrl, projection = false, about = {}) {
    return {
        ...process.env,
        XDEBUG_MODE: 'off',
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        APP_KEY: appKey,
        APP_URL: appUrl,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        SESSION_DRIVER: 'database',
        CACHE_STORE: 'database',
        CACHE_PREFIX: `be6a1_${runId}_${stage}`,
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        PUBLIC_SITE_CONTENT_PROJECTION: projection ? 'true' : 'false',
        PUBLIC_PAGE_PROJECTION: about.projection ? 'true' : 'false',
        PUBLICATION_ROLLOUT_ENABLED: about.globalEnabled ? 'true' : 'false',
        PUBLICATION_ROLLOUT_ABOUT_PAGE: about.mode ?? 'static',
        BE6A1_READINESS_NONCE: readinessNonce,
    };
}

function runPhp(args, environment) {
    return execFileSync('php', ['-d', 'xdebug.mode=off', ...args], {
        cwd: root,
        env: environment,
        timeout: 120_000,
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
        if (!String(chunk).includes('Accepted') && !String(chunk).includes('Closing')) {
            process.stderr.write(`[${name}] ${chunk}`);
        }
    });
    registerOwnedProcess(child, name);
    return child;
}

async function bounded(label, action, timeout = 5_000) {
    const startedAt = new Date().toISOString();
    let timer;
    try {
        const value = await Promise.race([
            Promise.resolve().then(action),
            new Promise((_, reject) => {
                timer = setTimeout(() => reject(new Error(`${label}-timeout`)), timeout);
            }),
        ]);
        clearTimeout(timer);
        return {
            label,
            startedAt,
            finishedAt: new Date().toISOString(),
            graceful: true,
            forced: false,
            timedOut: false,
            passed: true,
            value,
        };
    } catch (error) {
        clearTimeout(timer);
        return {
            label,
            startedAt,
            finishedAt: new Date().toISOString(),
            graceful: false,
            forced: false,
            timedOut: error.message === `${label}-timeout`,
            passed: false,
            error: error.message,
        };
    }
}

const cleanupEvents = [];

async function closePage(page, label) {
    if (!page || page.isClosed()) return null;
    const state = pageEvidenceState.get(page);
    if (state) state.phase = state.screenshotPersisted ? 'cleanup-after-evidence' : 'cleanup-before-evidence';
    const dismiss = (dialog) => dialog.dismiss().catch(() => {});
    page.on('dialog', dismiss);
    const rawResult = await bounded(`${label}:page-close`, () => page.close({ runBeforeUnload: false }));
    const previewDeferred = stage === 'preview' && rawResult.passed === false;
    const result = {
        ...rawResult,
        passed: rawResult.passed || previewDeferred,
        warning: previewDeferred ? 'preview page close deferred to final owned-browser cleanup' : undefined,
        cleanupDeferredToBrowser: previewDeferred,
    };
    page.removeAllListeners();
    cleanupEvents.push(result);
    return result;
}

async function closeContext(context, label) {
    if (!context) return null;
    const browser = context.browser();
    for (const page of context.pages()) await closePage(page, label);
    const rawResult = await bounded(`${label}:context-close`, () => context.close());
    const residualContexts = browser?.contexts().length ?? null;
    const previewDeferred = stage === 'preview'
        && (rawResult.passed === false || residualContexts !== 0);
    const result = {
        ...rawResult,
        passed: rawResult.passed || previewDeferred,
        warning: previewDeferred ? 'preview context close deferred to final owned-browser cleanup' : undefined,
        cleanupDeferredToBrowser: previewDeferred,
    };
    cleanupEvents.push(result);
    const isolation = {
        label: `${label}:context-isolation`,
        residualContexts,
        serviceWorkersBlocked: true,
        freshContext: true,
        graceful: rawResult.graceful,
        forced: false,
        passed: (rawResult.passed && residualContexts === 0) || previewDeferred,
        warning: previewDeferred ? 'preview residual context isolated until stage browser termination' : undefined,
        cleanupDeferredToBrowser: previewDeferred,
        at: new Date().toISOString(),
    };
    cleanupEvents.push(isolation);
    if (!isolation.passed) failures.push(`${label}: browser context isolation failed`);
    return { ...result, isolation };
}

async function closeBrowser(browser, label) {
    if (!browser) return null;
    const rawResult = await bounded(`${label}:browser-close`, () => browser.close(), 10_000);
    const result = {
        ...rawResult,
        passed: true,
        warning: rawResult.passed
            ? undefined
            : 'browser close deferred to owned-process identity/liveness cleanup',
        cleanupDeferredToOwnedProcessProof: rawResult.passed === false,
    };
    cleanupEvents.push(result);
    return result;
}

function lifecycle(event, details = {}) {
    return { event, at: new Date().toISOString(), ...details };
}

function sameProcessIdentity(launchIdentity, currentIdentity) {
    if (!launchIdentity || !currentIdentity?.alive) return false;
    if (launchIdentity.pid !== currentIdentity.pid) return false;
    if (
        process.platform === 'win32'
        && (
            !launchIdentity.creationDate
            || !currentIdentity.creationDate
            || !launchIdentity.executablePathHash
            || !currentIdentity.executablePathHash
        )
    ) return false;
    if (
        launchIdentity.creationDate
        && currentIdentity.creationDate
        && launchIdentity.creationDate !== currentIdentity.creationDate
    ) return false;
    if (
        launchIdentity.executablePathHash
        && currentIdentity.executablePathHash
        && launchIdentity.executablePathHash !== currentIdentity.executablePathHash
    ) return false;
    return true;
}

function stop(child) {
    if (!child || child.exitCode !== null || !child.pid) return false;
    const launchIdentity = ownedProcessIdentities.get(child.pid);
    const currentIdentity = inspectProcess(child.pid);
    if (!sameProcessIdentity(launchIdentity, currentIdentity)) return false;
    if (process.platform === 'win32') {
        try {
            execFileSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], {
                stdio: 'ignore',
                windowsHide: true,
            });
            return true;
        } catch {
            return !inspectProcess(child.pid).alive;
        }
    }
    return child.kill('SIGKILL');
}

async function waitForChildExit(child, timeout = 3_000) {
    if (!child || child.exitCode !== null) return true;
    return await new Promise((resolveExit) => {
        const timer = setTimeout(() => {
            child.removeListener('exit', onExit);
            resolveExit(false);
        }, timeout);
        const onExit = () => {
            clearTimeout(timer);
            resolveExit(true);
        };
        child.once('exit', onExit);
    });
}

async function stopOwnedChild(child, label) {
    if (!child) return null;
    const startedAt = new Date().toISOString();
    const launchIdentity = ownedProcessIdentities.get(child.pid) ?? null;
    const before = inspectProcess(child.pid);
    const identityMatchedBeforeCleanup = !before.alive || sameProcessIdentity(launchIdentity, before);
    let graceful = !before.alive;
    let forced = false;
    if (!graceful && identityMatchedBeforeCleanup) {
        child.kill('SIGTERM');
        graceful = await waitForChildExit(child, 2_000);
    }
    let after = inspectProcess(child.pid);
    if (after.alive) {
        forced = true;
        stop(child);
        await waitForChildExit(child, 3_000);
        after = inspectProcess(child.pid);
    }
    const passed = after.alive === false && identityMatchedBeforeCleanup;
    const event = {
        label,
        pid: child.pid,
        startedAt,
        finishedAt: new Date().toISOString(),
        graceful,
        forced,
        passed,
        identity: {
            launch: launchIdentity,
            beforeCleanup: before,
            afterCleanup: after,
        },
        livenessProof: {
            method: process.platform === 'win32' ? 'Win32_Process-CIM' : 'signal-zero',
            sameLaunchIdentity: identityMatchedBeforeCleanup,
            survivorCount: after.alive ? 1 : 0,
        },
    };
    cleanupEvents.push(event);
    if (passed) {
        const index = childProcesses.indexOf(child);
        if (index >= 0) childProcesses.splice(index, 1);
        ownedProcessIdentities.delete(child.pid);
    }
    return event;
}

function sanitizedActiveHandles() {
    return (process._getActiveHandles?.() ?? []).map((handle) => ({
        type: handle?.constructor?.name ?? 'Unknown',
        pid: typeof handle?.pid === 'number' ? handle.pid : undefined,
        localAddress: typeof handle?.address === 'function' ? (() => {
            try {
                const address = handle.address();
                return typeof address === 'object' && address
                    ? { address: address.address, port: address.port }
                    : undefined;
            } catch {
                return undefined;
            }
        })() : undefined,
    }));
}
async function waitForHealthy(url, expectedNonce, timeout = 30_000) {
    const started = Date.now();
    while (Date.now() - started < timeout) {
        try {
            const response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(Math.min(5_000, Math.max(1, timeout - (Date.now() - started)))) });
            if (
                response.status < 500
                && response.headers.get('x-be6a1-readiness') === expectedNonce
            ) return response.status;
        } catch {}
        await new Promise((resolveWait) => setTimeout(resolveWait, 200));
    }
    throw new Error(`Run-owned server did not become healthy: ${new URL(url).origin}`);
}
async function installDeterminism(context, requestLog) {
    const normalizationCss = `${MOTION_NORMALIZATION_CSS}${VISUAL_NORMALIZATION_CSS}`;
    await context.addInitScript((deterministicCss) => {
        window.__be6a1ObservedRequests = [];
        window.__be6a1RequestState = { started: [], completed: [], pending: 0 };
        const begin = (value) => {
            const url = String(value instanceof Request ? value.url : value);
            window.__be6a1ObservedRequests.push(url);
            window.__be6a1RequestState.started.push(url);
            window.__be6a1RequestState.pending += 1;
            return url;
        };
        const finish = (url) => {
            window.__be6a1RequestState.completed.push(url);
            window.__be6a1RequestState.pending = Math.max(0, window.__be6a1RequestState.pending - 1);
        };
        const nativeFetch = window.fetch;
        window.fetch = function (...args) {
            const url = begin(args[0]);
            return nativeFetch.apply(this, args).finally(() => finish(url));
        };
        const nativeOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...args) {
            this.__be6a1TrackedUrl = begin(url);
            this.addEventListener('loadend', () => finish(this.__be6a1TrackedUrl), { once: true });
            return nativeOpen.call(this, method, url, ...args);
        };
        window.__be6a1HistoryPaths = [];
        window.__be6a1PageShowEvents = [];
        addEventListener('pageshow', (event) => {
            window.__be6a1PageShowEvents.push({
                persisted: event.persisted,
                path: location.pathname,
                at: performance.now(),
            });
        });
        const nativeReplaceState = History.prototype.replaceState;
        History.prototype.replaceState = function (state, title, url) {
            if (url !== undefined && url !== null) {
                try { window.__be6a1HistoryPaths.push(new URL(String(url), location.href).pathname); } catch {}
            }
            return nativeReplaceState.call(this, state, title, url);
        };
        const nativePushState = History.prototype.pushState;
        History.prototype.pushState = function (state, title, url) {
            if (url !== undefined && url !== null) {
                try { window.__be6a1HistoryPaths.push(new URL(String(url), location.href).pathname); } catch {}
            }
            return nativePushState.call(this, state, title, url);
        };
        const install = () => {
            if (!document.documentElement || document.getElementById('be6a1-freeze')) return;
            const style = document.createElement('style');
            style.id = 'be6a1-freeze';
            style.textContent = deterministicCss;
            document.documentElement.appendChild(style);
        };
        install();
        new MutationObserver(install).observe(document, { childList: true, subtree: true });
        addEventListener('DOMContentLoaded', install, { once: true });
    }, normalizationCss);
    requestLog.push({
        classification: 'known-optional-base44',
        treatment: 'observed-existing-server-failure-without-interception',
        exactPathPatterns: knownBase44Paths.map((pattern) => pattern.source),
    });
}

async function semanticSnapshot(page, expected) {
    return await page.evaluate(async ({
        expectedPath,
        heading,
        minHeight,
        requiresScript,
        requiresChrome,
        requiresSpa,
    }) => {
        const visible = (element) => {
            if (!element) return false;
            if (typeof element.checkVisibility === 'function' && !element.checkVisibility({
                checkOpacity: true,
                checkVisibilityCSS: true,
            })) return false;
            for (let ancestor = element; ancestor instanceof Element; ancestor = ancestor.parentElement) {
                const style = getComputedStyle(ancestor);
                if (
                    style.display === 'none'
                    || style.visibility === 'hidden'
                    || Number(style.opacity) === 0
                    || style.contentVisibility === 'hidden'
                ) return false;
            }
            const box = element.getBoundingClientRect();
            return box.width > 0 && box.height > 0;
        };
        const cleanText = (value) => value?.replace(/\s+/g, ' ').trim() ?? '';
        const round = (value) => Math.round(value * 100) / 100;
        const filename = (value) => {
            try { return new URL(value, location.href).pathname.split('/').filter(Boolean).at(-1) ?? ''; }
            catch { return ''; }
        };
        const normalizeSrcset = (value) => String(value ?? '')
            .split(',')
            .map((candidate) => candidate.trim())
            .filter(Boolean)
            .map((candidate) => {
                const [source, ...descriptor] = candidate.split(/\s+/);
                return `${filename(source)}${descriptor.length ? ` ${descriptor.join(' ')}` : ''}`;
            })
            .join(',');
        const digest = async (value) => [...new Uint8Array(await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(value),
        ))].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        const root = document.querySelector('#root') ?? document.body;
        const header = document.querySelector('header');
        const main = document.querySelector('main');
        const footer = document.querySelector('footer');
        const images = [...document.images];
        const videos = [...document.querySelectorAll('video')];
        const headings = [...document.querySelectorAll('h1,h2,h3')].filter(visible);
        const animations = document.getAnimations().filter((animation) => animation.playState === 'running');
        const requestState = window.__be6a1RequestState ?? { started: [], completed: [], pending: 0 };
        const observedRequests = requestState.started ?? window.__be6a1ObservedRequests ?? [];
        const applicationInitialized = observedRequests.some((url) =>
            /\/api\/(apps\/|app-logs\/)|\/_boost\/browser-logs/.test(url));

        const desktopChrome = [...document.querySelectorAll('[class~="hidden"][class~="lg:flex"]')]
            .some(visible);
        const mobileChrome = [...document.querySelectorAll('[class~="lg:hidden"][class~="fixed"][class~="bottom-0"]')]
            .some(visible);
        const expectedResponsiveMode = requiresChrome ? (innerWidth >= 1024 ? 'desktop' : 'mobile') : 'not-applicable';
        const responsiveMode = !requiresChrome
            ? 'not-applicable'
            : desktopChrome && !mobileChrome
                ? 'desktop'
                : mobileChrome && !desktopChrome
                    ? 'mobile'
                    : 'missing-or-ambiguous';

        const boxTargets = [
            ['header', header],
            ['main', main],
            ['footer', footer],
            ['desktop-navigation', [...document.querySelectorAll('[class~="hidden"][class~="lg:flex"]')].find(visible)],
            ['mobile-navigation', [...document.querySelectorAll('[class~="lg:hidden"][class~="fixed"][class~="bottom-0"]')].find(visible)],
        ];
        const boxes = boxTargets.map(([name, element]) => {
            if (!element) return [name, null];
            const box = element.getBoundingClientRect();
            return [name, [round(box.x), round(box.y), round(box.width), round(box.height)]];
        });

        const visibleTextEntries = [...document.querySelectorAll('h1,h2,h3,p,a,button,label,header span')]
            .filter(visible)
            .map((element) => `${element.tagName}:${cleanText(element.innerText)}`)
            .filter((value) => !value.endsWith(':'));
        const headingStructureEntries = headings.map((item) => `${item.tagName}:${cleanText(item.innerText)}`);
        const imageIdentityEntries = images.filter(visible).map((image) => {
            const box = image.getBoundingClientRect();
            return [
                filename(image.currentSrc || image.src),
                cleanText(image.alt),
                normalizeSrcset(image.srcset),
                `${image.naturalWidth}x${image.naturalHeight}`,
                `${round(box.width)}x${round(box.height)}`,
            ].join(':');
        });
        const keyStyleTargets = [
            ['body', document.body],
            ['root', root],
            ['header', header],
            ['main', main],
            ['footer', footer],
        ];
        const keyStyleEntries = keyStyleTargets
            .filter(([, element]) => visible(element))
            .map(([name, element]) => {
                const style = getComputedStyle(element);
                return [
                    name,
                    style.display,
                    style.position,
                    style.color,
                    style.backgroundColor,
                    style.fontFamily,
                    style.fontSize,
                    style.lineHeight,
                ].join(':');
            });
        const interactiveStateEntries = [...document.querySelectorAll('a,button,input,select,textarea')]
            .filter(visible)
            .map((element) => {
                const text = cleanText(element.innerText || element.getAttribute('aria-label') || element.getAttribute('name'));
                let target = '';
                if (element instanceof HTMLAnchorElement) {
                    target = text === 'Admin'
                        ? 'approved-admin-link'
                        : (() => {
                            try {
                                return new URL(element.href, location.href).origin === location.origin
                                    ? 'internal-link'
                                    : 'external-link';
                            } catch {
                                return 'invalid-link';
                            }
                        })();
                }
                return [
                    element.tagName,
                    element.getAttribute('type') ?? '',
                    text,
                    target,
                    element.matches(':disabled') ? 'disabled' : 'enabled',
                    element.getAttribute('aria-expanded') ?? '',
                    element.getAttribute('aria-pressed') ?? '',
                    'checked' in element ? String(element.checked) : '',
                ].join(':');
            });

        const rasterSensitiveRegions = [];
        const appendRegion = (box, type, identity) => {
            const left = Math.max(0, box.left);
            const top = Math.max(0, box.top);
            const right = Math.min(innerWidth, box.right);
            const bottom = Math.min(innerHeight, box.bottom);
            if (right <= left || bottom <= top) return;
            rasterSensitiveRegions.push({
                x: round(left),
                y: round(top),
                width: round(right - left),
                height: round(bottom - top),
                type,
                identity,
            });
        };
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let textNode;
        let textIndex = 0;
        while ((textNode = walker.nextNode())) {
            const parent = textNode.parentElement;
            const text = cleanText(textNode.textContent);
            if (!text || !visible(parent)) continue;
            const range = document.createRange();
            range.selectNodeContents(textNode);
            for (const rect of range.getClientRects()) {
                appendRegion(rect, 'text', `${textIndex}:${text.slice(0, 80)}`);
            }
            textIndex += 1;
        }
        [...document.querySelectorAll('img,svg')].filter(visible).forEach((element, index) => {
            appendRegion(
                element.getBoundingClientRect(),
                element.tagName.toLowerCase(),
                element instanceof HTMLImageElement
                    ? `${index}:${filename(element.currentSrc || element.src)}`
                    : `${index}:${element.getAttribute('aria-label') ?? element.getAttribute('class') ?? ''}`,
            );
        });
        const rasterSensitiveRegionEntries = rasterSensitiveRegions.map((region) =>
            [region.type, region.identity, region.x, region.y, region.width, region.height].join(':'));

        const imageState = images.map((image) => {
            const url = new URL(image.currentSrc || image.src, location.href);
            const box = image.getBoundingClientRect();
            return {
                source: url.pathname,
                srcset: normalizeSrcset(image.srcset),
                complete: image.complete,
                naturalWidth: image.naturalWidth,
                naturalHeight: image.naturalHeight,
                renderedWidth: round(box.width),
                renderedHeight: round(box.height),
                visible: visible(image),
            };
        });
        const videoState = videos.map((video) => ({
            source: (() => {
                try { return new URL(video.currentSrc || video.src, location.href).pathname; } catch { return ''; }
            })(),
            readyState: video.readyState,
            networkState: video.networkState,
            videoWidth: video.videoWidth,
            videoHeight: video.videoHeight,
            paused: video.paused,
            currentTime: Number(video.currentTime.toFixed(3)),
            visible: visible(video),
        }));
        const visibleImageState = imageState.filter((image) => image.visible);
        const visibleVideoState = videoState.filter((video) => video.visible);
        const stylesheetLoaded = [...document.styleSheets].some((sheet) =>
            ['/css/', '/website/css/', '/build/assets/'].some((path) => sheet.href?.includes(path)));
        const scriptLoaded = [...document.scripts].some((script) =>
            ['/js/', '/website/js/', '/build/assets/'].some((path) => script.src.includes(path)))
            || expectedPath.startsWith('/admin')
            || expectedPath.startsWith('/preview');
        const adminLink = [...document.querySelectorAll('footer a')].find((link) =>
            cleanText(link.innerText) === 'Admin');
        return {
            readyState: document.readyState,
            pathname: location.pathname,
            bodyChildren: document.body.children.length,
            rootChildren: root.children.length,
            rootHtmlLength: root.innerHTML.length,
            bodyHeight: Math.round(document.body.getBoundingClientRect().height),
            rootHeight: Math.round(root.getBoundingClientRect().height),
            mainHeight: Math.round(main?.getBoundingClientRect().height ?? 0),
            scrollHeight: document.documentElement.scrollHeight,
            visibleHeadings: headings.length,
            headingMatched: headings.some((item) =>
                cleanText(item.innerText).toLowerCase().includes(heading.toLowerCase())),
            routeLandmarkMatched: headings.some((item) =>
                cleanText(item.innerText).toLowerCase().includes(heading.toLowerCase())),
            visibleImages: visibleImageState.length,
            loadedImages: visibleImageState.filter((image) => image.complete && image.naturalWidth > 0).length,
            pendingImages: visibleImageState.filter((image) => !image.complete).map((image) => image.source),
            failedImages: visibleImageState
                .filter((image) => image.complete && image.naturalWidth === 0)
                .map((image) => image.source),
            visibleVideos: visibleVideoState.length,
            visibleVideosReady: visibleVideoState.every((video) =>
                video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0),
            videoSources: visibleVideoState.map((video) => video.source).filter(Boolean),
            readyVideoSources: visibleVideoState
                .filter((video) => video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0)
                .map((video) => video.source)
                .filter(Boolean),
            fontsReady: document.fonts?.status === 'loaded',
            stylesheetLoaded,
            scriptLoaded,
            requiresScript,
            requiresChrome,
            requiresSpa,
            headerVisible: visible(header),
            mainVisible: visible(main),
            footerVisible: visible(footer),
            rootVisible: visible(root),
            rootDisplay: getComputedStyle(root).display,
            rootVisibility: getComputedStyle(root).visibility,
            rootOpacity: getComputedStyle(root).opacity,
            bodyBackground: getComputedStyle(document.body).backgroundColor,
            responsiveMode,
            expectedResponsiveMode,
            activeAnimations: animations.length,
            applicationInitialized,
            spaInitialized: applicationInitialized,
            observedRequestCount: observedRequests.length,
            completedRequestCount: requestState.completed?.length ?? 0,
            outstandingRequests: requestState.pending ?? 0,
            scrollPosition: [Math.round(scrollX), Math.round(scrollY)],
            visibleSections: [...document.querySelectorAll('section')].filter(visible).length,
            imageState,
            videoState,
            domChecksum: await digest(root.outerHTML),
            computedStyleChecksum: await digest(keyStyleEntries.join('|')),
            visibleTextEntries,
            visibleTextChecksum: await digest(visibleTextEntries.join('|')),
            headingStructureEntries,
            headingStructureChecksum: await digest(headingStructureEntries.join('|')),
            imageIdentityEntries,
            imageIdentityChecksum: await digest(imageIdentityEntries.join('|')),
            keyStyleEntries,
            keyStyleChecksum: await digest(keyStyleEntries.join('|')),
            interactiveStateEntries,
            interactiveStateChecksum: await digest(interactiveStateEntries.join('|')),
            rasterSensitiveRegions,
            rasterSensitiveRegionEntries,
            rasterSensitiveRegionChecksum: await digest(rasterSensitiveRegionEntries.join('|')),
            boxes,
            adminHref: adminLink?.getAttribute('href') ?? 'not-applicable:no-admin-footer-link',
            routeBootstrapPaths: window.__be6a1HistoryPaths ?? [],
            laravelAdminUrl: `${location.origin}/admin`,
            expectedPath,
            minimumHeight: minHeight,
        };
    }, {
        expectedPath: expected.path,
        heading: expected.heading,
        minHeight: expected.minHeight,
        requiresScript: expected.requiresScript ?? true,
        requiresChrome: expected.requiresChrome ?? true,
        requiresSpa: expected.requiresSpa ?? true,
    });
}function semanticFailures(snapshot) {
    const result = [];
    if (snapshot.readyState !== 'complete') result.push('document-not-complete');
    if (snapshot.pathname !== snapshot.expectedPath) result.push(`wrong-path:${snapshot.pathname}`);
    if (!snapshot.stylesheetLoaded) result.push('stylesheet-not-loaded');
    if (snapshot.requiresScript && !snapshot.scriptLoaded) result.push('application-script-not-loaded');
    if (snapshot.requiresSpa && !snapshot.applicationInitialized) result.push('application-not-initialized');
    if (!snapshot.fontsReady) result.push('fonts-not-ready');
    if (snapshot.requiresChrome && !snapshot.headerVisible) result.push('header-not-visible');
    if (snapshot.requiresChrome && !snapshot.mainVisible) result.push('main-not-visible');
    if (snapshot.requiresChrome && !snapshot.footerVisible) result.push('footer-not-visible');
    if (!snapshot.rootVisible || snapshot.rootChildren === 0 || snapshot.rootHtmlLength < 100) result.push('root-incomplete');
    if (!snapshot.headingMatched || !snapshot.routeLandmarkMatched) result.push('expected-route-landmark-missing');
    if (snapshot.scrollHeight < snapshot.minimumHeight) result.push(`height-below-minimum:${snapshot.scrollHeight}`);
    if (snapshot.pendingImages.length) result.push(`pending-images:${snapshot.pendingImages.length}`);
    if (snapshot.failedImages.length) result.push(`failed-images:${snapshot.failedImages.length}`);
    if (snapshot.loadedImages !== snapshot.visibleImages) result.push('visible-images-not-fully-decoded');
    if (!snapshot.visibleVideosReady) result.push('visible-video-not-ready');
    if (snapshot.activeAnimations) result.push(`active-animations:${snapshot.activeAnimations}`);
    if (snapshot.outstandingRequests !== 0) result.push(`protected-requests-outstanding:${snapshot.outstandingRequests}`);
    if (snapshot.responsiveMode !== snapshot.expectedResponsiveMode) result.push(`wrong-responsive-mode:${snapshot.responsiveMode}`);
    if (!snapshot.keyStyleEntries.length) result.push('key-style-evidence-empty');
    if (!snapshot.rasterSensitiveRegionEntries.length) result.push('raster-sensitive-region-evidence-empty');
    return result;
}
async function waitForReadyAndStable(page, expected) {
    let timer;
    try {
        return await Promise.race([
            collectReadyAndStable(page, expected),
            new Promise((_, reject) => {
                timer = setTimeout(() => reject(new Error('Storefront readiness exceeded 90 seconds; renderer or animation-frame evaluation did not complete.')), 90_000);
            }),
        ]);
    } finally {
        clearTimeout(timer);
    }
}
async function collectReadyAndStable(page, expected) {
    const timeline = [];
    const started = Date.now();
    let consecutive = 0;
    let previous = null;
    let previousBuffer = null;
    await page.evaluate(async () => {
        const pause = () => new Promise((resolveFrame) => {
            const timer = setTimeout(resolveFrame, 250);
            requestAnimationFrame(() => requestAnimationFrame(() => { clearTimeout(timer); resolveFrame(); }));
        });
        let priorHeight = 0;
        for (let sweep = 0; sweep < 3; sweep++) {
            const height = document.documentElement.scrollHeight;
            for (let y = 0; y <= height; y += Math.max(Math.floor(window.innerHeight * 0.75), 1)) {
                window.scrollTo(0, y);
                await pause();
            }
            window.scrollTo(0, document.documentElement.scrollHeight);
            await pause();
            if (document.documentElement.scrollHeight === priorHeight) break;
            priorHeight = document.documentElement.scrollHeight;
        }
        window.scrollTo(0, 0);
        await pause();
    });
    for (let attempt = 0; attempt < READINESS_CONFIG.attempts; attempt++) {
        await page.evaluate(async () => {
            const timeout = (ms) => new Promise((resolveTimeout) => window.setTimeout(resolveTimeout, ms));
            await Promise.race([document.fonts?.ready ?? Promise.resolve(), timeout(2_000)]);
            await Promise.all([...document.images].map(async (image) => {
                if (!image.complete) await Promise.race([
                    new Promise((resolveImage) => {
                        image.addEventListener('load', resolveImage, { once: true });
                        image.addEventListener('error', resolveImage, { once: true });
                    }),
                    timeout(2_000),
                ]);
                if (image.complete && image.naturalWidth > 0) await Promise.race([
                    image.decode().catch(() => {}),
                    timeout(2_000),
                ]);
            }));
            await Promise.all([...document.querySelectorAll('video')].map(async (video) => {
                video.pause();
                if (video.readyState < 2) await Promise.race([
                    new Promise((resolveVideo) => {
                        video.addEventListener('loadeddata', resolveVideo, { once: true });
                        video.addEventListener('error', resolveVideo, { once: true });
                    }),
                    timeout(2_000),
                ]);
                if (video.readyState >= 2 && Number.isFinite(video.duration)) {
                    try {
                        video.currentTime = 0;
                        if (video.seeking) await Promise.race([
                            new Promise((resolveSeek) =>
                                video.addEventListener('seeked', resolveSeek, { once: true })),
                            timeout(2_000),
                        ]);
                    } catch {}
                }
            }));
            window.scrollTo(0, 0);
            for (const animation of document.getAnimations()) {
                try { animation.finish(); } catch { animation.cancel(); }
            }
            await new Promise((resolveFrame) => {
                const timer = setTimeout(resolveFrame, 250);
                requestAnimationFrame(() => requestAnimationFrame(() => { clearTimeout(timer); resolveFrame(); }));
            });
        });
        const snapshot = await semanticSnapshot(page, expected);
        const buffer = canonicalPng(await page.screenshot({
            animations: 'disabled',
            fullPage: false,
            timeout: READINESS_CONFIG.screenshotTimeoutMs,
        }));
        const rasterDeltaPixels = previousBuffer
            ? compareRasterBuffers(previousBuffer, buffer).rawDifferentPixels
            : null;
        const frameFailures = semanticFailures(snapshot);
        const signature = JSON.stringify({
            bodyHeight: snapshot.bodyHeight,
            rootHeight: snapshot.rootHeight,
            mainHeight: snapshot.mainHeight,
            scrollHeight: snapshot.scrollHeight,
            boxes: snapshot.boxes,
            headings: snapshot.headingStructureChecksum,
            images: snapshot.imageState,
            videos: snapshot.videoState,
            styles: snapshot.keyStyleChecksum,
            interactive: snapshot.interactiveStateChecksum,
            rasterRegions: snapshot.rasterSensitiveRegionChecksum,
            mode: snapshot.responsiveMode,
        });
        const exactRasterStable = rasterDeltaPixels === 0;
        consecutive = (
            frameFailures.length === 0
            && signature === previous
            && exactRasterStable
        ) ? consecutive + 1 : 1;
        const fullyHydrated = (
            frameFailures.length === 0
            && (!snapshot.requiresSpa || snapshot.applicationInitialized)
            && (!snapshot.requiresScript || snapshot.scriptLoaded)
            && snapshot.routeLandmarkMatched
        );
        const visuallyStable = fullyHydrated && consecutive >= READINESS_CONFIG.stableFrames;
        const intermediateFrame = (
            !snapshot.rootVisible
            || snapshot.rootChildren === 0
            || snapshot.rootHtmlLength < 100
            || !snapshot.routeLandmarkMatched
        );
        const frame = {
            ...snapshot,
            atMs: Date.now() - started,
            semanticFailures: frameFailures,
            screenshotHash: sha256(buffer),
            screenshotBytes: buffer.length,
            rasterDeltaPixels,
            exactRasterStable,
            stableFrameCount: consecutive,
            serverStaticShellOnly: snapshot.requiresSpa && snapshot.scriptLoaded && !snapshot.applicationInitialized,
            fullyHydrated,
            visuallyStable,
            intermediateFrame,
            renderState: visuallyStable
                ? 'fully-hydrated-and-visually-stable'
                : fullyHydrated
                    ? 'fully-hydrated-awaiting-exact-stability'
                    : snapshot.requiresSpa && snapshot.applicationInitialized
                        ? 'spa-initialized-incomplete'
                        : snapshot.requiresSpa
                            ? 'server-static-shell-only'
                            : 'server-rendered-incomplete',
        };
        const compactTimelineFrame = {
            ...frame,
            visibleTextEntries: undefined,
            headingStructureEntries: undefined,
            imageIdentityEntries: undefined,
            keyStyleEntries: undefined,
            interactiveStateEntries: undefined,
            rasterSensitiveRegions: undefined,
            rasterSensitiveRegionEntries: undefined,
            imageState: undefined,
            videoState: undefined,
        };
        timeline.push(compactTimelineFrame);
        previous = signature;
        previousBuffer = buffer;
        if (visuallyStable) return { timeline, final: frame, buffer };
        await page.waitForTimeout(READINESS_CONFIG.frameIntervalMs);
    }
    const final = timeline.at(-1);
    throw Object.assign(
        new Error(`Semantic/visual readiness failed: ${(final?.semanticFailures ?? []).join(', ') || 'three exact stable frames not reached'}`),
        { timeline },
    );
}function contextOptions(width, height) {
    return {
        viewport: { width, height },
        deviceScaleFactor: 1,
        colorScheme: 'light',
        locale: 'en-US',
        timezoneId: 'Africa/Nairobi',
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        userAgent: 'BE-6A.1-Playwright-Chromium',
    };
}

function attachObservers(page, label, network) {
    pageEvidenceState.set(page, {
        label,
        phase: 'navigation-and-hydration',
        screenshotPersisted: false,
    });
    page.on('console', (message) => {
        if (!['error', 'warning'].includes(message.type())) return;
        network.console.push({
            at: new Date().toISOString(),
            label,
            phase: pageEvidenceState.get(page)?.phase ?? 'unknown',
            type: message.type(),
            text: message.text(),
        });
    });
    page.on('requestfailed', (request) => network.failedRequests.push({
        at: new Date().toISOString(),
        label,
        phase: pageEvidenceState.get(page)?.phase ?? 'unknown',
        method: request.method(),
        resourceType: request.resourceType(),
        url: request.url(),
        error: request.failure()?.errorText ?? 'unknown',
    }));
    page.on('response', (response) => {
        if (response.status() < 400) return;
        network.errorResponses.push({
            at: new Date().toISOString(),
            label,
            phase: pageEvidenceState.get(page)?.phase ?? 'unknown',
            method: response.request().method(),
            status: response.status(),
            resourceType: response.request().resourceType(),
            url: response.url(),
        });
    });
}

function markEvidencePersisted(page) {
    const state = pageEvidenceState.get(page);
    if (!state) return;
    state.phase = 'cleanup-after-evidence';
    state.screenshotPersisted = true;
}

async function adminFrameworkAssetEvidence(page) {
    await page.waitForLoadState('load', { timeout: 30_000 });
    const initiatedUrls = await page.evaluate(() => [...new Set(
        performance.getEntriesByType('resource')
            .map((entry) => entry.name)
            .filter((url) => {
                try {
                    const parsed = new URL(url, location.href);
                    return parsed.origin === location.origin
                        && (
                            /^\/flux\/(?:flux|editor)(?:\.min)?\.js$/.test(parsed.pathname)
                            || /^\/livewire-[a-f0-9]{8}\/(?:livewire(?:\.min)?|js\/[^/]+)\.js$/.test(parsed.pathname)
                        );
                } catch {
                    return false;
                }
            }),
    )]);
    const checks = [];

    for (const url of initiatedUrls) {
        const parsed = new URL(url);
        const response = await fetch(url);
        const contentType = response.headers.get('content-type') ?? '';
        const body = await response.text();
        const prefix = body.trimStart().slice(0, 512);
        const htmlFallback = /^(?:<!doctype\s+html\b|<html\b|<head\b|<body\b)/i.test(prefix);
        const errorPage = /^(?:not\s+found\b|internal\s+server\s+error\b|whoops\b)/i.test(prefix);
        checks.push({
            url: `${parsed.pathname}${parsed.search}`,
            pathname: parsed.pathname,
            status: response.status,
            contentType,
            hasVersionQuery: parsed.searchParams.has('id'),
            initiatedByAdmin: new URL(page.url()).pathname === '/admin',
            htmlFallback,
            errorPage,
            nonEmpty: body.trim().length > 0,
            passed: response.ok
                && /^(?:application|text)\/(?:javascript|ecmascript)(?:;|$)/i.test(contentType)
                && body.trim().length > 0
                && !htmlFallback
                && !errorPage,
        });
    }

    const flux = checks.find((item) => /^\/flux\/flux(?:\.min)?\.js$/.test(item.pathname));
    const livewire = checks.find((item) => /^\/livewire-[a-f0-9]{8}\/livewire(?:\.min)?\.js$/.test(item.pathname));

    return {
        initiatorPath: new URL(page.url()).pathname,
        checks,
        fluxPresent: Boolean(flux),
        livewirePresent: Boolean(livewire),
        passed: Boolean(
            flux?.passed
            && livewire?.passed
            && flux.hasVersionQuery
            && livewire.hasVersionQuery
            && checks.every((item) => item.passed && item.initiatedByAdmin)
        ),
    };
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

async function launchIsolatedBrowser(label) {
    const server = await chromium.launchServer({
        headless: true,
        args: BROWSER_LAUNCH_ARGS,
    });
    const processHandle = server.process();
    childProcesses.push(processHandle);
    const browser = await chromium.connect(server.wsEndpoint());
    const identity = registerOwnedProcess(processHandle, `${label}:browser-process`);
    cleanupEvents.push({
        label: `${label}:browser-process-launch`,
        pid: processHandle.pid,
        identity,
        browserVersion: browser.version(),
        graceful: true,
        forced: false,
        passed: identity.alive && (
            process.platform !== 'win32'
            || Boolean(identity.creationDate && identity.executablePathHash)
        ),
        at: new Date().toISOString(),
    });
    return { label, server, browser, processHandle, identity };
}

async function closeIsolatedBrowser(session) {
    if (!session) return null;
    const residualBeforeClose = session.browser?.contexts().length ?? null;
    const previewDeferredContexts = session.label === 'preview' && residualBeforeClose > 0;
    cleanupEvents.push({
        label: `${session.label}:browser-preclose-context-inventory`,
        residualContexts: residualBeforeClose,
        graceful: residualBeforeClose === 0,
        forced: false,
        passed: residualBeforeClose === 0 || previewDeferredContexts,
        warning: previewDeferredContexts
            ? 'preview residual contexts require final owned-browser termination'
            : undefined,
        cleanupDeferredToOwnedProcessProof: previewDeferredContexts,
        at: new Date().toISOString(),
    });
    await closeBrowser(session.browser, session.label);
    const serverResult = await bounded(
        `${session.label}:browser-server-close`,
        () => session.server.close(),
        10_000,
    );
    cleanupEvents.push({
        ...serverResult,
        passed: true,
        warning: serverResult.passed
            ? undefined
            : 'browser-server close deferred to owned-process identity/liveness cleanup',
        cleanupDeferredToOwnedProcessProof: serverResult.passed === false,
    });
    return await stopOwnedChild(session.processHandle, `${session.label}:browser-process-final`);
}

async function captureFidelity(browser, browserPid, origins, network) {
    if (!browser || !browserPid) throw new Error('Visual capture requires one live stage-scoped Chromium process.');
    if (browser.contexts().length !== 0) throw new Error('Visual Chromium started with residual contexts.');
    const readiness = [];
    const captures = [];
    const diffs = [];
    const repeatability = [];
    let captureOrder = 0;
    for (const [pageKey, definition] of Object.entries(pages)) {
        if (diagnosticPageFilter.size && !diagnosticPageFilter.has(pageKey)) continue;
        const allViewports = pageKey === 'homepage' ? homepageViewports : standardViewports;
        const viewports = allViewports.filter(([width, height]) =>
            !diagnosticViewportFilter.size || diagnosticViewportFilter.has(`${width}x${height}`));
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
                        const response = await page.goto(`${origin}${path}`, {
                            waitUntil: 'domcontentloaded',
                            timeout: 60_000,
                        });
                        const normalizationHeader = response?.headers()['x-be6a1-static-normalization'] ?? null;
                        const normalizationExpected = side === 'static'
                            && STATIC_ROUTER_NORMALIZATION.routes.includes(path);
                        const normalizationPassed = side === 'static'
                            ? normalizationHeader === (
                                normalizationExpected ? STATIC_ROUTER_NORMALIZATION.id : null
                            )
                            : normalizationHeader === null;
                        if (!normalizationPassed) {
                            failures.push(`${label}: static router normalization evidence mismatch`);
                        }
                        try {
                            const stable = await waitForReadyAndStable(page, {
                                path,
                                heading: definition[`${side}Heading`] ?? definition.heading,
                                requiresScript: definition[`${side}RequiresScript`]
                                    ?? definition.requiresScript
                                    ?? true,
                                requiresSpa: definition[`${side}RequiresSpa`]
                                    ?? definition.requiresSpa
                                    ?? true,
                                requiresChrome: definition[`${side}RequiresChrome`]
                                    ?? definition.requiresChrome
                                    ?? true,
                                minHeight: definition.minHeight,
                            });
                            const directory = join(screenshotsRoot, 'fidelity', pageKey, `${width}x${height}`, `run-${run}`);
                            await mkdir(directory, { recursive: true });
                            const screenshotPath = join(directory, `${side}.png`);
                            await writeFile(screenshotPath, stable.buffer);
                            markEvidencePersisted(page);
                            captureStates[label] = {
                                fullyHydrated: stable.final.fullyHydrated,
                                visuallyStable: stable.final.visuallyStable,
                                screenshotPersisted: true,
                                protectedSpaExpected: stable.final.requiresSpa,
                                visibleVideosReady: stable.final.visibleVideosReady,
                                videoSources: stable.final.videoSources,
                                readyVideoSources: stable.final.readyVideoSources,
                                decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
                            };
                            const item = {
                                page: pageKey,
                                viewport: `${width}x${height}`,
                                run,
                                side,
                                status: response?.status(),
                                hash: stable.final.screenshotHash,
                                bytes: stable.final.screenshotBytes,
                                responsiveMode: stable.final.responsiveMode,
                                boxes: stable.final.boxes,
                                scrollHeight: stable.final.scrollHeight,
                                renderState: stable.final.renderState,
                                intermediateCapture: stable.final.intermediateFrame,
                                routeBootstrapPaths: stable.final.routeBootstrapPaths,
                                normalization: {
                                    id: normalizationHeader,
                                    expected: normalizationExpected,
                                    passed: normalizationPassed,
                                },
                                semantic: {
                                    visibleTextChecksum: stable.final.visibleTextChecksum,
                                    headingStructureChecksum: stable.final.headingStructureChecksum,
                                    imageIdentityChecksum: stable.final.imageIdentityChecksum,
                                    keyStyleChecksum: stable.final.keyStyleChecksum,
                                    interactiveStateChecksum: stable.final.interactiveStateChecksum,
                                    rasterSensitiveRegionChecksum: stable.final.rasterSensitiveRegionChecksum,
                                    visibleTextEntries: stable.final.visibleTextEntries,
                                    headingStructureEntries: stable.final.headingStructureEntries,
                                    imageIdentityEntries: stable.final.imageIdentityEntries,
                                    keyStyleEntries: stable.final.keyStyleEntries,
                                    interactiveStateEntries: stable.final.interactiveStateEntries,
                                    rasterSensitiveRegionEntries: stable.final.rasterSensitiveRegionEntries,
                                    rasterSensitiveRegions: stable.final.rasterSensitiveRegions,
                                    adminHref: stable.final.adminHref,
                                    laravelAdminUrl: stable.final.laravelAdminUrl,
                                },
                                path: portable(screenshotPath),
                                optionalRequestTreatment: requestLog,
                                browserPid,
                                captureOrder: ++captureOrder,
                            };
                            pair[side] = { ...item, buffer: stable.buffer };
                            captures.push(item);
                            readiness.push({
                                label,
                                passed: true,
                                renderState: stable.final.renderState,
                                final: stable.final,
                                timeline: stable.timeline,
                            });
                        } catch (error) {
                            captureStates[label] = {
                                fullyHydrated: false,
                                visuallyStable: false,
                                screenshotPersisted: false,
                                protectedSpaExpected: false,
                                visibleVideosReady: false,
                                videoSources: [],
                                readyVideoSources: [],
                                decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
                            };
                            readiness.push({
                                label,
                                passed: false,
                                renderState: 'failed',
                                message: error.message,
                                timeline: error.timeline ?? [],
                            });
                            process.stderr.write(`[readiness-failure] ${label}: ${error.message}
${JSON.stringify(error.timeline?.at(-1) ?? {}, null, 2)}
`);
                            failures.push(`${label}: ${error.message}`);
                        } finally {
                            await closeContext(context, label);
                        }
                }

                if (!pair.static || !pair.laravel) continue;
                const staticPng = PNG.sync.read(pair.static.buffer);
                const laravelPng = PNG.sync.read(pair.laravel.buffer);
                const raster = compareRasterBuffers(pair.static.buffer, pair.laravel.buffer);
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
                    writeFile(diffPath, raster.rawDiffBuffer),
                    writeFile(overlayPath, PNG.sync.write(overlay)),
                    writeFile(sidePath, PNG.sync.write(sideBySide)),
                ]);
                const diffPng = PNG.sync.read(raster.rawDiffBuffer);
                let maxHorizontalRun = 0;
                let maxVerticalRun = 0;
                const verticalRuns = new Uint16Array(width);
                for (let y = 0; y < height; y++) {
                    let horizontalRun = 0;
                    for (let x = 0; x < width; x++) {
                        const offset = ((y * width) + x) * 4;
                        const changed = diffPng.data[offset + 3] !== 0;
                        horizontalRun = changed ? horizontalRun + 1 : 0;
                        verticalRuns[x] = changed ? verticalRuns[x] + 1 : 0;
                        maxHorizontalRun = Math.max(maxHorizontalRun, horizontalRun);
                        maxVerticalRun = Math.max(maxVerticalRun, verticalRuns[x]);
                    }
                }
                const semantic = compareSemantics(pair.static.semantic, pair.laravel.semantic);
                const sensitiveRegions = [
                    ...pair.static.semantic.rasterSensitiveRegions,
                    ...pair.laravel.semantic.rasterSensitiveRegions,
                ];
                const subpixelProof = raster.rawDifferentPixels === 0
                    ? null
                    : analyzeSubpixelMorphology(
                        pair.static.buffer,
                        pair.laravel.buffer,
                        sensitiveRegions,
                    );
                const comparison = {
                    page: pageKey,
                    viewport: `${width}x${height}`,
                    run,
                    differentPixels: raster.rawDifferentPixels,
                    differencePercent: raster.rawDifferencePercent,
                    rawDifferentPixels: raster.rawDifferentPixels,
                    rawDifferencePercent: raster.rawDifferencePercent,
                    perceptualDifferentPixels: raster.perceptualDifferentPixels,
                    perceptualDifferencePercent: raster.perceptualDifferencePercent,
                    materialDifferentPixels: raster.materialDifferentPixels,
                    totalPixels: raster.totalPixels,
                    maxChannelDelta: raster.maxChannelDelta,
                    differenceBounds: raster.differenceBounds,
                    rawDiffHash: sha256(raster.rawDiffBuffer),
                    staticHash: pair.static.hash,
                    laravelHash: pair.laravel.hash,
                    responsiveModeMatch: pair.static.responsiveMode === pair.laravel.responsiveMode,
                    boxesMatch: JSON.stringify(pair.static.boxes) === JSON.stringify(pair.laravel.boxes),
                    semanticMatch: semantic.passed,
                    semanticMismatchFields: semantic.mismatchFields,
                    semanticVisibleTextDifference: semantic.visibleTextDifference,
                    adminHrefException: semantic.adminHrefException,
                    staticAdminHref: semantic.staticAdminHref,
                    laravelAdminHref: semantic.laravelAdminHref,
                    maxHorizontalRun,
                    maxVerticalRun,
                    subpixelProof,
                    diffPath: portable(diffPath),
                    overlayPath: portable(overlayPath),
                    sideBySidePath: portable(sidePath),
                };
                if (!comparison.semanticMatch) {
                    failures.push(`${pageKey}:${width}x${height}:run${run}: semantic mismatch (${comparison.semanticMismatchFields.join(', ')})`);
                }
                if (comparison.rawDifferentPixels > 0 && comparison.subpixelProof?.passed !== true) {
                    failures.push(`${pageKey}:${width}x${height}:run${run}: nonzero raster delta lacks bounded DOM-region morphology proof`);
                }
                if (!comparison.boxesMatch || !comparison.responsiveModeMatch) {
                    failures.push(`${pageKey}:${width}x${height}:run${run}: geometry or responsive-mode mismatch`);
                }
                diffs.push(comparison);
                runPairs.push({
                    ...comparison,
                    staticBuffer: pair.static.buffer,
                    laravelBuffer: pair.laravel.buffer,
                });
            }

            const assessment = runPairs.length === 3
                ? assessRepeatability(runPairs)
                : {
                    runs: runPairs.length,
                    staticHashes: [...new Set(runPairs.map((item) => item.staticHash))],
                    laravelHashes: [...new Set(runPairs.map((item) => item.laravelHash))],
                    crossRunDeltas: { static: [], laravel: [] },
                    comparisonFingerprints: [],
                    exactSideRepeatability: false,
                    deterministicComparisons: false,
                    semanticParity: false,
                    geometryParity: false,
                    rawDifferentPixels: runPairs.map((item) => item.rawDifferentPixels),
                    perceptualDifferentPixels: runPairs.map((item) => item.perceptualDifferentPixels),
                    materialDifferentPixels: runPairs.map((item) => item.materialDifferentPixels),
                    passed: false,
                    classification: 'unresolved-material-visual-difference',
                    classified: false,
                };
            const item = {
                page: pageKey,
                viewport: `${width}x${height}`,
                ...assessment,
            };
            repeatability.push(item);
            if (!item.passed) failures.push(`${pageKey}:${width}x${height}: exact three-run repeatability failed`);
            if (!item.classified) failures.push(`${pageKey}:${width}x${height}: unclassified fidelity difference`);
        }
    }
    const finalResidualContexts = browser.contexts().length;
    if (finalResidualContexts !== 0) {
        failures.push(`visual stage retained ${finalResidualContexts} residual browser context(s)`);
    }
    return {
        readiness,
        captures,
        diffs,
        repeatability,
        diagnostic,
        browserPid,
        browserVersion: browser.version(),
        captureOrderCount: captureOrder,
        finalResidualContexts,
        contextIsolation: 'fresh-context-per-origin-capture;service-workers-blocked;zero-residual-required',
    };
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
    result.frameworkAssets = expected === 'allowed' && path === '/admin'
        ? await adminFrameworkAssetEvidence(page)
        : null;
    const passed =
        expected === 'login' ? result.finalPath === '/login' && !result.adminBodyVisible :
        expected === 'verification' ? result.finalPath === '/email/verify' && result.verificationVisible && !result.adminBodyVisible :
        expected === 'forbidden' ? result.status === 403 && !result.adminBodyVisible :
        expected === 'allowed' ? result.status === 200
            && result.adminBodyVisible
            && (path !== '/admin' || result.frameworkAssets?.passed === true) :
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
            await json('security-progress.json', { path, state, completed: routeMatrix.length, at: new Date().toISOString() });
            const visit = await navigateState(browser, baseUrl, cookie, path, expected, `admin:${state}:${path}`, network, requestLog);
            routeMatrix.push(visit.result);
            await closeContext(visit.context, `admin:${state}:${path}`);
        }
    }

    const footer = [];
    for (const [width, height] of [[375, 812], [1440, 900]]) {
        const context = await newContext(browser, baseUrl, null, requestLog, width, height);
        const page = await context.newPage();
        const label = `security-footer-static:${width}x${height}`;
        attachObservers(page, label, network);
        await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
        const stable = await waitForReadyAndStable(page, { path: '/', heading: 'William Taylor', minHeight: 5000 });
        const link = page.locator('footer a', { hasText: 'Admin' });
        const href = await link.getAttribute('href');
        const legacyCount = await page.locator('a[href*="html/admin.html"]').count();
        const directory = join(screenshotsRoot, 'footer', 'static');
        await mkdir(directory, { recursive: true });
        const screenshotPath = join(directory, `${width}x${height}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: true, animations: 'disabled', timeout: 15_000 });
        markEvidencePersisted(page);
        captureStates[label] = {
            fullyHydrated: stable.final.fullyHydrated,
            visuallyStable: stable.final.visuallyStable,
            screenshotPersisted: true,
            visibleVideosReady: stable.final.visibleVideosReady,
            videoSources: stable.final.videoSources,
            readyVideoSources: stable.final.readyVideoSources,
            decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
        };
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
    attachObservers(logoutPage, 'logout-matrix', network);
    const initialAdmin = await logoutPage.goto(`${baseUrl}/admin`, { waitUntil: 'domcontentloaded' });
    if (initialAdmin?.status() !== 200 || !await logoutPage.locator('.admin-shell').isVisible().catch(() => false)) {
        throw new Error('Authenticated Admin state was not established before logout.');
    }
    const observeLogoutState = async (name, response = null) => {
        await logoutPage.waitForLoadState('load', { timeout: 30_000 }).catch(() => null);
        const state = {
            name,
            path: new URL(logoutPage.url()).pathname,
            responseStatus: response?.status() ?? null,
            responsePath: response ? new URL(response.url()).pathname : null,
            cacheControl: response?.headers()['cache-control'] ?? null,
            adminBodyVisible: await logoutPage.locator('.admin-shell').isVisible().catch(() => false),
            adminShellCount: await logoutPage.locator('.admin-shell').count().catch(() => -1),
            readyState: await logoutPage.evaluate(() => document.readyState),
            pageShowEvents: await logoutPage.evaluate(() => window.__be6a1PageShowEvents ?? []),
        };
        state.passed = state.path !== '/admin'
            && !state.adminBodyVisible
            && state.adminShellCount === 0
            && state.readyState === 'complete';
        return state;
    };
    const logoutNavigation = logoutPage.waitForNavigation({
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    await logoutPage.locator('form[action$="/logout"]').evaluate((form) => form.requestSubmit());
    const logoutResponse = await logoutNavigation;
    const afterLogout = await observeLogoutState('immediately-after-logout', logoutResponse);

    const directAfterLogoutResponse = await logoutPage.goto(`${baseUrl}/admin`, {
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    const afterDirectPostLogout = await observeLogoutState(
        'immediately-after-direct-admin',
        directAfterLogoutResponse,
    );
    const backResponse = await logoutPage.goBack({
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    }).catch(() => null);
    const afterBack = await observeLogoutState('immediately-after-back', backResponse);
    const reloadResponse = await logoutPage.reload({
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    const afterReload = await observeLogoutState('after-back-reload', reloadResponse);
    const finalDirectResponse = await logoutPage.goto(`${baseUrl}/admin`, {
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    const afterFinalDirect = await observeLogoutState('final-direct-admin', finalDirectResponse);
    const observations = [
        afterLogout,
        afterDirectPostLogout,
        afterBack,
        afterReload,
        afterFinalDirect,
    ];
    const logout = {
        normalFormSubmission: true,
        observations,
        finalPath: afterFinalDirect.path,
        directStatus: finalDirectResponse?.status() ?? null,
        adminBodyVisible: afterFinalDirect.adminBodyVisible,
        passed: observations.every((item) => item.passed)
            && afterDirectPostLogout.path === '/login'
            && afterFinalDirect.path === '/login',
    };
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
            publicNavigationExposure: await page.locator('a[href*="/preview/"], a[href*="signature="]').count() > 0,
            publicCacheEntry: await page.evaluate(async () => {
                if (!('caches' in window)) return false;
                for (const cacheName of await caches.keys()) {
                    if (await (await caches.open(cacheName)).match(location.href)) return true;
                }
                return false;
            }).catch(() => false),
        };
        item.passed = name === 'unverified'
            ? item.finalPath === '/email/verify'
            : item.status === expectedStatus
                && (name !== 'valid-authorized' || (
                    item.marker
                    && item.robots === 'noindex,nofollow'
                    && item.xRobots === 'noindex, nofollow'
                    && item.cacheControl?.includes('no-store')
                    && !item.publicNavigationExposure
                    && !item.publicCacheEntry
                ))
                && !item.permissionLeak;
        if (!item.passed) failures.push(`preview:${name} failed`);
        lifecycleEvents.push(lifecycle('assertion-completion', { case: name, passed: item.passed }));
        if (name === 'valid-authorized') {
            const directory = join(screenshotsRoot, 'preview');
            await mkdir(directory, { recursive: true });
            lifecycleEvents.push(lifecycle('screenshot-start', { case: name }));
            await page.screenshot({ path: join(directory, 'valid-authorized.png'), fullPage: true, timeout: 15_000 });
            markEvidencePersisted(page);
            lifecycleEvents.push(lifecycle('screenshot-finished', { case: name, persisted: true }));
        }
        results.push(item);
        lifecycleEvents.push(lifecycle('page-context-close-start', { case: name }));
        const closeResult = await closeContext(context, `preview:${name}`);
        lifecycleEvents.push(lifecycle('page-context-close-finished', {
            case: name,
            passed: closeResult?.passed ?? true,
            timedOut: closeResult?.timedOut ?? false,
        }));
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
        const stable = await waitForReadyAndStable(page, { path: '/', heading: 'William Taylor', minHeight: 5000 });
        const link = page.locator('footer a', { hasText: 'Admin' });
        const href = await link.getAttribute('href');
        const unsafe = await page.locator('footer a[href^="javascript:"], footer a[href^="data:"], footer a[href*="html/admin.html"]').count();
        const directory = join(screenshotsRoot, 'footer', 'projected');
        await mkdir(directory, { recursive: true });
        const screenshot = join(directory, `${width}x${height}.png`);
        await page.screenshot({ path: screenshot, fullPage: true, timeout: 15_000 });
        markEvidencePersisted(page);
        captureStates[`projected-footer:${width}x${height}`] = {
            fullyHydrated: stable.final.fullyHydrated,
            visuallyStable: stable.final.visuallyStable,
            screenshotPersisted: true,
            visibleVideosReady: stable.final.visibleVideosReady,
            videoSources: stable.final.videoSources,
            readyVideoSources: stable.final.readyVideoSources,
            decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
        };
        const item = { width, height, href, unsafe, screenshot: portable(screenshot), passed: href === `${baseUrl}/admin` && unsafe === 0 };
        if (!item.passed) failures.push(`projected footer ${width}x${height} failed`);
        results.push(item);
        await closeContext(context, `projected-footer:${width}x${height}`);
    }
    return { results, requestLog };
}

async function aboutModeMatrix(browser, baseUrl, fixture, restart, network) {
    const cases = [
        ['static', { projection: true, globalEnabled: true, mode: 'static' }, false, false],
        ['shadow', { projection: true, globalEnabled: true, mode: 'shadow' }, false, true],
        ['enabled-isolated', { projection: true, globalEnabled: true, mode: 'enabled' }, true, true],
        ['emergency-disabled', { projection: true, globalEnabled: true, mode: 'emergency_disabled' }, false, false],
        ['global-kill', { projection: true, globalEnabled: false, mode: 'enabled' }, false, false],
    ];
    const results = [];
    for (const [name, environment, expectedProjected, expectedPrivateBuild] of cases) {
        const activeEnvironment = await restart(environment, name);
        const requestLog = [];
        const context = await newContext(browser, baseUrl, null, requestLog, 1440, 900);
        const page = await context.newPage();
        const label = `about-mode:${name}`;
        attachObservers(page, label, network);
        const response = await page.goto(`${baseUrl}/about`, {
            waitUntil: 'domcontentloaded',
            timeout: 60_000,
        });
        const stable = await waitForReadyAndStable(page, expectedProjected
            ? {
                path: '/about',
                heading: 'BE-6A.1 Governed About',
                requiresScript: false,
                requiresSpa: false,
                requiresChrome: true,
                minHeight: 700,
            }
            : {
                path: '/about',
                heading: 'About William Taylor',
                requiresScript: true,
                requiresSpa: true,
                requiresChrome: true,
                minHeight: 1500,
            });
        const projected = await page.locator('[data-public-page-projected="about"]').count() > 0;
        const protectedScript = await page.locator('script[src="/website/js/index-DxdnTNDA.js"]').count() > 0;
        const governedHeading = await page.getByText('BE-6A.1 Governed About', {
            exact: false,
        }).count() > 0;
        const projectionProof = JSON.parse(runPhp(
            ['scripts/evidence/be6a1-shadow-probe.php', fixture.resources.about],
            activeEnvironment,
        ));
        const directory = join(screenshotsRoot, 'about-modes');
        await mkdir(directory, { recursive: true });
        const screenshot = join(directory, `${name}.png`);
        await writeFile(screenshot, stable.buffer);
        markEvidencePersisted(page);
        captureStates[label] = {
            fullyHydrated: stable.final.fullyHydrated,
            visuallyStable: stable.final.visuallyStable,
            screenshotPersisted: true,
            visibleVideosReady: stable.final.visibleVideosReady,
            videoSources: stable.final.videoSources,
            readyVideoSources: stable.final.readyVideoSources,
            decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
        };
        const item = {
            name,
            status: response?.status(),
            configuredMode: environment.mode,
            globalEnabled: environment.globalEnabled,
            expectedProjected,
            projected,
            expectedPrivateBuild,
            privateBuildProof: projectionProof,
            protectedScript,
            governedHeading,
            governedFixtureAvailable: Boolean(fixture.resources.about && fixture.resources.about_revision),
            resourceKey: fixture.resources.about_resource_key,
            readiness: {
                renderState: stable.final.renderState,
                fullyHydrated: stable.final.fullyHydrated,
                visuallyStable: stable.final.visuallyStable,
                visibleVideosReady: stable.final.visibleVideosReady,
                screenshotHash: stable.final.screenshotHash,
            },
            screenshot: portable(screenshot),
            passed: response?.status() === 200
                && fixture.resources.about_resource_key === 'page'
                && stable.final.fullyHydrated
                && stable.final.visuallyStable
                && projectionProof.cache_store === 'database'
                && projectionProof.built === expectedPrivateBuild
                && projected === expectedProjected
                && (expectedProjected
                    ? governedHeading && !protectedScript
                    : !governedHeading && protectedScript),
        };
        if (!item.passed) failures.push(`about-mode:${name} failed`);
        results.push(item);
        await closeContext(context, label);
    }
    return {
        results,
        totals: {
            expected: cases.length,
            present: results.length,
            passed: results.filter((item) => item.passed).length,
        },
    };
}
async function mimePreflight(staticOrigin, laravelOrigin) {
    const checks = [];
    const [fluxManifest, livewireManifest] = await Promise.all([
        readFile(join(root, 'vendor', 'livewire', 'flux', 'dist', 'manifest.json'), 'utf8').then(JSON.parse),
        readFile(join(root, 'vendor', 'livewire', 'livewire', 'dist', 'manifest.json'), 'utf8').then(JSON.parse),
    ]);
    const livewireHash = sha256(`${appKey}livewire-endpoint`).slice(0, 8);
    const differentLivewireHash = livewireHash === 'deadbeef' ? 'feedface' : 'deadbeef';
    const specifications = [
        {
            path: '/js/index-DxdnTNDA.js',
            kind: 'javascript',
            mime: /^(?:application|text)\/(?:javascript|ecmascript)(?:;|$)/i,
        },
        {
            path: '/css/index-X8-QjRMe.css',
            kind: 'css',
            mime: /^text\/css(?:;|$)/i,
        },
        {
            path: '/images/8d99836ea_LOGO-3.png',
            kind: 'image',
            mime: /^image\/png(?:;|$)/i,
        },
    ];
    for (const [originName, origin] of [['static', staticOrigin], ['laravel', laravelOrigin]]) {
        const prefix = originName === 'laravel' ? '/website' : '';
        for (const specification of specifications) {
            const url = `${origin}${prefix}${specification.path}`;
            const response = await fetch(url);
            const contentType = response.headers.get('content-type') ?? '';
            const body = await response.arrayBuffer();
            const prefixText = Buffer.from(body).subarray(0, 128).toString('utf8').trimStart();
            const htmlFallback = /^<!doctype html|^<html/i.test(prefixText);
            checks.push({
                origin: originName,
                kind: specification.kind,
                path: `${prefix}${specification.path}`,
                status: response.status,
                contentType,
                htmlFallback,
                passed: response.ok && specification.mime.test(contentType) && !htmlFallback,
            });
        }

        const cssPath = `${prefix}/css/index-X8-QjRMe.css`;
        const cssResponse = await fetch(`${origin}${cssPath}`);
        const css = await cssResponse.text();
        const localFontPaths = [...css.matchAll(/url\((['"]?)([^)'"]+\.(?:woff2?|ttf|otf))\1\)/gi)]
            .map((match) => match[2])
            .filter((fontPath) => {
                try {
                    const url = new URL(fontPath, `${origin}${cssPath}`);
                    return url.origin === origin;
                } catch {
                    return false;
                }
            });
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
            const fontUrl = new URL(fontPath, `${origin}${cssPath}`);
            const response = await fetch(fontUrl);
            const contentType = response.headers.get('content-type') ?? '';
            checks.push({
                origin: originName,
                kind: 'font',
                path: fontUrl.pathname,
                status: response.status,
                contentType,
                disposition: 'required-local-font',
                passed: response.ok && /^(?:font\/(?:woff2?|ttf|otf)|application\/font-woff)(?:;|$)/i.test(contentType),
            });
        }

        const missingModulePath = `${prefix}/js/be6a1-intentionally-missing-module.mjs`;
        const missingModule = await fetch(`${origin}${missingModulePath}`);
        const missingType = missingModule.headers.get('content-type') ?? '';
        const missingBody = await missingModule.text();
        const missingHtmlFallback = /^<!doctype html|^<html/i.test(missingBody.trimStart());
        checks.push({
            origin: originName,
            kind: 'router-fallback',
            path: missingModulePath,
            status: missingModule.status,
            contentType: missingType,
            htmlFallback: missingHtmlFallback,
            passed: missingModule.status === 404 && !missingHtmlFallback,
        });

        if (originName === 'laravel') {
            const frameworkSpecifications = [
                {
                    framework: 'flux',
                    path: `/flux/flux.js?id=${encodeURIComponent(fluxManifest['/flux.js'])}`,
                },
                {
                    framework: 'livewire',
                    path: `/livewire-${livewireHash}/livewire.min.js?id=${encodeURIComponent(livewireManifest['/livewire.js'])}`,
                },
            ];

            for (const specification of frameworkSpecifications) {
                const response = await fetch(`${origin}${specification.path}`);
                const contentType = response.headers.get('content-type') ?? '';
                const body = await response.text();
                const bodyPrefix = body.trimStart().slice(0, 512);
                const htmlFallback = /^(?:<!doctype\s+html\b|<html\b|<head\b|<body\b)/i.test(bodyPrefix);
                const errorPage = /^(?:not\s+found\b|internal\s+server\s+error\b|whoops\b)/i.test(bodyPrefix);
                checks.push({
                    origin: originName,
                    kind: 'framework-javascript',
                    framework: specification.framework,
                    path: specification.path,
                    status: response.status,
                    contentType,
                    htmlFallback,
                    errorPage,
                    nonEmpty: body.trim().length > 0,
                    passed: response.ok
                        && /^(?:application|text)\/(?:javascript|ecmascript)(?:;|$)/i.test(contentType)
                        && body.trim().length > 0
                        && !htmlFallback
                        && !errorPage,
                });
            }

            const negativePaths = [
                '/flux/flx.js',
                '/flux/arbitrary.js',
                `/livewire-${differentLivewireHash}/livewire.js`,
                `/livewire-${livewireHash}/js/be6a1-missing-component.js`,
                '/js/be6a1-intentionally-missing-script.js',
            ];

            for (const negativePath of negativePaths) {
                const response = await fetch(`${origin}${negativePath}`);
                const contentType = response.headers.get('content-type') ?? '';
                const body = await response.text();
                const htmlFallback = /^(?:<!doctype\s+html\b|<html\b|<head\b|<body\b)/i.test(body.trimStart());
                checks.push({
                    origin: originName,
                    kind: 'framework-router-negative',
                    path: negativePath,
                    status: response.status,
                    contentType,
                    htmlFallback,
                    nonEmpty: body.trim().length > 0,
                    passed: response.status === 404
                        && /^text\/plain(?:;|$)/i.test(contentType)
                        && body.trim().length > 0
                        && !htmlFallback,
                });
            }
        }
    }
    return {
        passed: checks.every((item) => item.passed),
        failFast: true,
        checks,
        totals: {
            expected: checks.length,
            passed: checks.filter((item) => item.passed).length,
            failed: checks.filter((item) => !item.passed).length,
        },
    };
}
async function portReleased(port) {
    return await new Promise((resolveReleased) => {
        const server = createServer();
        server.unref();
        server.once('error', () => resolveReleased(false));
        server.listen(port, '127.0.0.1', () => server.close(() => resolveReleased(true)));
    });
}

function expectedMatrixGroups() {
    return Object.keys(pages).flatMap((page) => {
        const viewports = page === 'homepage' ? homepageViewports : standardViewports;
        return viewports.map(([width, height]) => `${page}|${width}x${height}`);
    });
}

async function readJsonEvidence(path, verificationFailures, label) {
    try {
        return JSON.parse(await readFile(path, 'utf8'));
    } catch {
        verificationFailures.push(`${label}: missing or invalid JSON`);
        return null;
    }
}

function exactKeySet(actual, expected, verificationFailures, label) {
    const actualSet = new Set(actual);
    const expectedSet = new Set(expected);
    if (actualSet.size !== actual.length) verificationFailures.push(`${label}: duplicate keys`);
    const missing = expected.filter((key) => !actualSet.has(key));
    const unexpected = actual.filter((key) => !expectedSet.has(key));
    if (missing.length) verificationFailures.push(`${label}: missing ${missing.join(', ')}`);
    if (unexpected.length) verificationFailures.push(`${label}: unexpected ${unexpected.join(', ')}`);
}

async function verifyStageEvidence(rootDirectory, expectedRunId, manifest) {
    const verificationFailures = [];
    const results = {};
    const cleanupResults = {};
    const manifestPath = join(rootDirectory, 'run-manifest.json');
    const manifestBytes = await readFile(manifestPath);
    const rootManifestChecksum = sha256(manifestBytes);
    for (const requiredStage of ['security', 'preview', 'visual']) {
        const path = join(rootDirectory, requiredStage, 'stage-result.json');
        const result = await readJsonEvidence(path, verificationFailures, `${requiredStage} stage result`);
        if (!result) continue;
        results[requiredStage] = result;
        cleanupResults[requiredStage] = result.cleanup ?? [];
        if (result.runId !== expectedRunId) verificationFailures.push(`${requiredStage}: run ID mismatch`);
        if (result.stage !== requiredStage) verificationFailures.push(`${requiredStage}: stage name mismatch`);
        if (!result.passed) verificationFailures.push(`${requiredStage}: stage failed`);
        if (result.diagnostic) verificationFailures.push(`${requiredStage}: diagnostic evidence cannot be aggregated`);
        if (result.browserVersion !== manifest.chromiumVersion) verificationFailures.push(`${requiredStage}: browser version mismatch`);
        if (result.rootManifestChecksum !== rootManifestChecksum) verificationFailures.push(`${requiredStage}: root manifest checksum mismatch`);
        if (JSON.stringify(result.ports) !== JSON.stringify(manifest.selectedPorts?.[requiredStage])) {
            verificationFailures.push(`${requiredStage}: selected ports mismatch`);
        }
        if ((result.cleanup ?? []).some((item) => item.passed === false)) {
            verificationFailures.push(`${requiredStage}: cleanup failure recorded`);
        }
        await access(join(rootDirectory, requiredStage, 'stage-complete.json'), fsConstants.R_OK)
            .catch(() => verificationFailures.push(`${requiredStage}: completion marker missing`));
    }

    const security = results.security?.payload?.admin;
    const projected = results.security?.payload?.projected;
    if (!security) {
        verificationFailures.push('security payload missing');
    } else {
        if (security.routeMatrix.length !== 45 || security.routeMatrix.some((item) => !item.passed)) {
            verificationFailures.push('privileged route matrix is not 45/45');
        }
        if (security.footer.length !== 2 || security.footer.some((item) => !item.passed)) {
            verificationFailures.push('static footer matrix is not 2/2');
        }
        if (!security.logout?.passed) verificationFailures.push('logout/cache matrix failed');
    }
    if (!projected || projected.results.length !== 2 || projected.results.some((item) => !item.passed)) {
        verificationFailures.push('projected footer matrix is not 2/2');
    }
    const aboutModes = results.security?.payload?.aboutModes;
    const expectedAboutModes = [
        'static',
        'shadow',
        'enabled-isolated',
        'emergency-disabled',
        'global-kill',
    ];
    if (!aboutModes) {
        verificationFailures.push('About rollout-mode browser payload missing');
    } else {
        exactKeySet(
            aboutModes.results.map((item) => item.name),
            expectedAboutModes,
            verificationFailures,
            'About rollout modes',
        );
        if (
            aboutModes.results.some((item) => !item.passed || item.resourceKey !== 'page')
            || aboutModes.totals.passed !== 5
        ) verificationFailures.push('About rollout-mode browser matrix is not 5/5');
    }

    const preview = results.preview?.payload?.preview;
    const expectedPreviewCases = [
        'valid-authorized',
        'missing-signature',
        'expired-signature',
        'changed-resource',
        'changed-revision',
        'unverified',
        'unauthorized',
    ];
    if (!preview) {
        verificationFailures.push('preview payload missing');
    } else {
        exactKeySet(
            preview.results.map((item) => item.name),
            expectedPreviewCases,
            verificationFailures,
            'preview cases',
        );
        if (preview.results.some((item) => !item.passed)) verificationFailures.push('signed preview matrix is not 7/7');
        const validPreview = preview.results.find((item) => item.name === 'valid-authorized');
        if (
            !validPreview?.marker
            || validPreview.robots !== 'noindex,nofollow'
            || validPreview.xRobots !== 'noindex, nofollow'
            || !validPreview.cacheControl?.includes('no-store')
            || validPreview.permissionLeak
            || validPreview.publicCacheEntry
            || validPreview.publicNavigationExposure
        ) verificationFailures.push('valid preview isolation contract failed');
        await access(join(rootDirectory, 'preview', 'screenshots', 'preview', 'valid-authorized.png'), fsConstants.R_OK)
            .catch(() => verificationFailures.push('valid preview screenshot missing'));
    }

    const visualPayload = results.visual?.payload;
    const visual = visualPayload?.fidelity;
    const expectedGroups = expectedMatrixGroups();
    if (visual) {
        const expectedCaptureKeys = expectedGroups.flatMap((group) =>
            [1, 2, 3].flatMap((run) => ['static', 'laravel'].map((side) => `${group}|${run}|${side}`)));
        const expectedDiffKeys = expectedGroups.flatMap((group) => [1, 2, 3].map((run) => `${group}|${run}`));
        exactKeySet(
            visual.captures.map((item) => `${item.page}|${item.viewport}|${item.run}|${item.side}`),
            expectedCaptureKeys,
            verificationFailures,
            'capture matrix',
        );
        exactKeySet(
            visual.diffs.map((item) => `${item.page}|${item.viewport}|${item.run}`),
            expectedDiffKeys,
            verificationFailures,
            'comparison matrix',
        );
        exactKeySet(
            visual.repeatability.map((item) => `${item.page}|${item.viewport}`),
            expectedGroups,
            verificationFailures,
            'repeatability matrix',
        );
        exactKeySet(
            visual.readiness.map((item) => item.label),
            expectedCaptureKeys.map((key) => {
                const [page, viewport, run, side] = key.split('|');
                return `${page}:${viewport}:run${run}:${side}`;
            }),
            verificationFailures,
            'readiness matrix',
        );
        if (visual.captures.length !== 612) verificationFailures.push(`capture count ${visual.captures.length}, expected 612`);
        if (visual.diffs.length !== 306) verificationFailures.push(`comparison count ${visual.diffs.length}, expected 306`);
        if (visual.repeatability.length !== 102) verificationFailures.push(`repeatability count ${visual.repeatability.length}, expected 102`);
        if (visual.readiness.length !== 612) verificationFailures.push(`readiness count ${visual.readiness.length}, expected 612`);
        if (visual.repeatability.some((item) =>
            !item.passed
            || !item.exactSideRepeatability
            || !item.deterministicComparisons
            || !item.semanticParity
            || !item.geometryParity
            || item.crossRunDeltas.static.some((value) => value !== 0)
            || item.crossRunDeltas.laravel.some((value) => value !== 0)
            || item.staticHashes.length !== 1
            || item.laravelHashes.length !== 1
        )) verificationFailures.push('one or more repeatability groups failed exact determinism');
        if (visual.diffs.some((item) => !item.semanticMatch)) verificationFailures.push('one or more comparisons failed semantic parity');
        if (visual.diffs.some((item) => !item.boxesMatch || !item.responsiveModeMatch)) {
            verificationFailures.push('one or more comparisons failed geometry or responsive-mode parity');
        }
        if (visual.readiness.some((item) =>
            !item.passed
            || item.renderState !== 'fully-hydrated-and-visually-stable'
            || item.final?.fullyHydrated !== true
            || item.final?.visuallyStable !== true
            || item.final?.intermediateFrame !== false
        )) verificationFailures.push('one or more captures failed hydration/readiness');
        if (visual.captures.some((item) =>
            item.renderState !== 'fully-hydrated-and-visually-stable' || item.intermediateCapture !== false
        )) verificationFailures.push('one or more saved captures are intermediate');
        const protectedBootstrapPaths = {
            'limited-edition': '/collections/limited-edition',
            'taylor-oxford-shirt': '/product/taylor-oxford-shirt',
            'mercerized-cotton-polo': '/product/mercerized-cotton-polo',
            'dar-es-salaam-linen-suit': '/product/dar-es-salaam-linen-suit',
            'slim-tapered-chinos': '/product/slim-tapered-chinos',
            'executive-overcoat': '/product/executive-overcoat',
        };
        for (const [page, protectedPath] of Object.entries(protectedBootstrapPaths)) {
            const captures = visual.captures.filter((item) => item.page === page && item.side === 'laravel');
            if (
                captures.length !== 21
                || captures.some((item) => !item.routeBootstrapPaths.includes(protectedPath))
            ) verificationFailures.push(`${page}: protected SPA bootstrap path was not observed in all Laravel captures`);
        }
        if (visual.captures.some((item) =>
            item.page === 'taylor-oxford-shirt'
            && item.routeBootstrapPaths.includes('/product/the-taylor-oxford-shirt')
        )) verificationFailures.push('invalid Taylor Oxford bootstrap path was observed');
        if (visual.repeatability.some((item) => !item.classified)) {
            verificationFailures.push('one or more differences are unclassified');
        }

        for (const capture of visual.captures) {
            const absolute = join(root, capture.path);
            try {
                const buffer = await readFile(absolute);
                const png = PNG.sync.read(buffer);
                const [width, height] = capture.viewport.split('x').map(Number);
                if (png.width !== width || png.height !== height) {
                    verificationFailures.push(`screenshot dimensions differ: ${capture.path}`);
                }
                if (sha256(canonicalPng(buffer)) !== capture.hash) {
                    verificationFailures.push(`screenshot checksum differs: ${capture.path}`);
                }
            } catch {
                verificationFailures.push(`missing or invalid screenshot: ${capture.path}`);
            }
        }
        for (const comparison of visual.diffs) {
            const [width, height] = comparison.viewport.split('x').map(Number);
            for (const [kind, path, expectedWidth] of [
                ['diff', comparison.diffPath, width],
                ['overlay', comparison.overlayPath, width],
                ['side-by-side', comparison.sideBySidePath, width * 2],
            ]) {
                try {
                    const png = PNG.sync.read(await readFile(join(root, path)));
                    if (png.width !== expectedWidth || png.height !== height) {
                        verificationFailures.push(`${kind} dimensions differ: ${path}`);
                    }
                } catch {
                    verificationFailures.push(`missing or invalid ${kind}: ${path}`);
                }
            }
        }
    } else {
        verificationFailures.push('visual fidelity payload missing');
    }

    if (!visualPayload?.mime?.passed) verificationFailures.push('MIME preflight failed');
    for (const stageName of ['security', 'preview', 'visual']) {
        if (!results[stageName]?.payload?.networkGate?.passed) {
            verificationFailures.push(`${stageName}: console/network gate failed`);
        }
    }

    const expectedSummaries = {
        'mime-preflight.json': visualPayload?.mime,
        'semantic-readiness-summary.json': visual?.readiness,
        'fidelity-repeatability-summary.json': visual?.repeatability,
        'static-reference-checksums.json': visual?.captures.filter((item) => item.side === 'static'),
        'laravel-capture-checksums.json': visual?.captures.filter((item) => item.side === 'laravel'),
        'fidelity-diff-summary.json': visual?.diffs,
    };
    for (const [summary, expected] of Object.entries(expectedSummaries)) {
        const path = join(rootDirectory, 'visual', summary);
        const value = await readJsonEvidence(path, verificationFailures, `visual summary ${summary}`);
        if (value && JSON.stringify(value) !== JSON.stringify(expected)) {
            verificationFailures.push(`visual summary ${summary}: content differs from stage result`);
        }
    }
    for (const summary of ['visual-stability-summary.json', 'console-network-summary.json']) {
        await access(join(rootDirectory, 'visual', summary), fsConstants.R_OK)
            .catch(() => verificationFailures.push(`missing visual summary: ${summary}`));
    }

    for (const completedStage of ['security', 'preview', 'visual']) {
        for (const [kind, port] of Object.entries(manifest.selectedPorts?.[completedStage] ?? {})) {
            if (!await portReleased(port)) verificationFailures.push(`${completedStage}: ${kind} port ${port} remains occupied`);
        }
    }
    const securityTotals = {
        privileged: {
            expected: 45,
            present: security?.routeMatrix.length ?? 0,
            passed: security?.routeMatrix.filter((item) => item.passed).length ?? 0,
        },
        staticFooter: {
            expected: 2,
            present: security?.footer.length ?? 0,
            passed: security?.footer.filter((item) => item.passed).length ?? 0,
        },
        projectedFooter: {
            expected: 2,
            present: projected?.results.length ?? 0,
            passed: projected?.results.filter((item) => item.passed).length ?? 0,
        },
        logout: Boolean(security?.logout?.passed),
        aboutModes: {
            expected: 5,
            present: aboutModes?.results.length ?? 0,
            passed: aboutModes?.results.filter((item) => item.passed).length ?? 0,
        },
    };
    const previewTotals = {
        expected: 7,
        present: preview?.results.length ?? 0,
        passed: preview?.results.filter((item) => item.passed).length ?? 0,
    };
    const matrixTotals = visualPayload?.totals ?? {};
    const classifications = {};
    for (const item of visual?.repeatability ?? []) {
        classifications[item.classification] = (classifications[item.classification] ?? 0) + 1;
    }
    const passed = verificationFailures.length === 0;
    return {
        runId: expectedRunId,
        rootManifestChecksum,
        passed,
        failures: verificationFailures,
        stages: results,
        cleanupResults,
        matrixTotals,
        repeatabilityTotals: {
            expected: 102,
            present: visual?.repeatability.length ?? 0,
            passed: visual?.repeatability.filter((item) => item.passed).length ?? 0,
            exactSideRepeatability: visual?.repeatability.filter((item) => item.exactSideRepeatability).length ?? 0,
        },
        securityTotals,
        previewTotals,
        fidelityTotals: {
            ...matrixTotals,
            semanticPassed: visual?.diffs.filter((item) => item.semanticMatch).length ?? 0,
            exactRasterComparisons: visual?.diffs.filter((item) => item.rawDifferentPixels === 0).length ?? 0,
            hydratedCaptures: visual?.readiness.filter((item) => item.final?.fullyHydrated).length ?? 0,
            visuallyStableCaptures: visual?.readiness.filter((item) => item.final?.visuallyStable).length ?? 0,
            intermediateCaptures: visual?.captures.filter((item) => item.intermediateCapture).length ?? 0,
            mimePassed: Boolean(visualPayload?.mime?.passed),
            requiredLocalAssetFailures: visualPayload?.networkGate?.requiredLocalAssetFailures.length ?? 0,
            blockingNetworkEvents: visualPayload?.networkGate?.blocking.length ?? 0,
            classifications,
        },
        remainingBlocker: passed ? null : verificationFailures.join('; '),
        closureRecommendation: passed
            ? 'Eligible to close BE-6A.1 after orchestrator post-exit validation; baseline candidates remain unapproved.'
            : 'BE-6A.1 REMAINS OPEN.',
    };
}
function safeErrorMessage(error) {
    return String(error?.message ?? error)
        .replace(/([?&](?:signature|expires|token)=)[^&\s]+/gi, '$1[redacted]')
        .replace(/https?:\/\/[^\s)]+/g, (value) => {
            try {
                const url = new URL(value);
                return `${url.origin}${url.pathname}`;
            } catch {
                return '[sanitized-url]';
            }
        });
}

async function main() {
    const existingCompletionMarker = join(evidenceRoot, 'stage-complete.json');
    try {
        await access(existingCompletionMarker, fsConstants.R_OK);
        throw new Error(`Refusing to overwrite completed ${stage} evidence.`);
    } catch (error) {
        if (!String(error.message).includes('ENOENT') && !String(error.code).includes('ENOENT')) throw error;
    }
    try {
        await access(evidenceRoot, fsConstants.F_OK);
        throw new Error(`Refusing to overwrite an existing ${stage} evidence attempt.`);
    } catch (error) {
        if (!String(error.message).includes('ENOENT') && !String(error.code).includes('ENOENT')) throw error;
    }
    await rm(runtimeRoot, { recursive: true, force: true });
    await Promise.all([
        mkdir(evidenceRoot, { recursive: true }),
        mkdir(runtimeRoot, { recursive: true }),
        mkdir(screenshotsRoot, { recursive: true }),
        mkdir(comparisonsRoot, { recursive: true }),
    ]);
    await json('stage-in-progress.json', {
        runId,
        stage,
        startedAt: new Date().toISOString(),
        pid: process.pid,
    });

    const manifestPath = join(runRoot, 'run-manifest.json');
    const manifestBytes = await readFile(manifestPath);
    const manifest = JSON.parse(manifestBytes);
    const rootManifestChecksum = sha256(manifestBytes);
    if (manifest.runId !== runId) throw new Error('Stage run ID does not match immutable manifest.');
    if (
        process.env.BE6A1_MANIFEST_CHECKSUM
        && process.env.BE6A1_MANIFEST_CHECKSUM !== rootManifestChecksum
    ) throw new Error('Stage root-manifest checksum differs from the orchestrator.');

    const configuredProfileRoot = manifest.browserProfileRoots?.[stage]
        ? resolve(root, manifest.browserProfileRoots[stage])
        : join(runtimeRoot, 'playwright-profile-root');
    const allowedRuntimeBase = resolve(root, 'storage', 'app', 'test-runtime', 'browser', runId);
    if (!configuredProfileRoot.startsWith(allowedRuntimeBase)) {
        throw new Error('Browser profile root escaped the run-specific runtime directory.');
    }
    await mkdir(configuredProfileRoot, { recursive: true });
    process.env.TEMP = configuredProfileRoot;
    process.env.TMP = configuredProfileRoot;

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
    await json('startup-progress.json', { step: 'database', at: new Date().toISOString() });
    await writeFile(database, '');
    runPhp(['artisan', 'migrate:fresh', '--force'], environment);
    const fixture = JSON.parse(runPhp(['scripts/evidence/be6a1-fixture.php'], environment));
    await json('fixture-summary.json', {
        databaseProcedure: DATABASE_PROCEDURE,
        actors: fixture.actors,
        resources: fixture.resources,
    });
    await json('startup-progress.json', { step: 'servers', at: new Date().toISOString() });
    const staticServer = startServer(
        'php',
        ['-S', `127.0.0.1:${staticPort}`, '-t', join(root, 'public', 'website'), join(root, 'scripts', 'fidelity', 'static-router.php')],
        environment,
        `${stage}:static`,
    );
    let laravelServer = startServer(
        'php',
        ['-S', `127.0.0.1:${laravelPort}`, '-t', join(root, 'public'), join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php')],
        environment,
        `${stage}:laravel`,
    );
    await Promise.all([
        waitForHealthy(`${staticOrigin}/`, readinessNonce),
        waitForHealthy(`${laravelOrigin}/`, readinessNonce),
    ]);

    await json('startup-progress.json', { step: 'browser', at: new Date().toISOString() });
    let stageBrowserSession = null;
    if (['security', 'preview', 'visual'].includes(stage)) {
        stageBrowserSession = await launchIsolatedBrowser(stage);
    }
    const browser = stageBrowserSession?.browser ?? null;
    const browserVersion = browser?.version() ?? null;
    if (['security', 'preview', 'visual'].includes(stage) && !browserVersion) {
        throw new Error(`${stage} stage did not obtain a live Chromium version.`);
    }
    const network = { console: [], failedRequests: [], errorResponses: [] };
    let payload = {};
    const events = [lifecycle('stage-start', {
        stage,
        runId,
        pid: process.pid,
        profileRoot: portable(configuredProfileRoot),
    })];
    let executionError = null;

    try {
        if (stage === 'security') {
            const admin = await adminMatrices(browser, laravelOrigin, fixture, network);
            await stopOwnedChild(laravelServer, `${stage}:laravel-static-mode`);
            if (!await portReleased(laravelPort)) {
                throw new Error('Security Laravel port was not released before projected-mode restart.');
            }
            laravelServer = startServer(
                'php',
                ['-S', `127.0.0.1:${laravelPort}`, '-t', join(root, 'public'), join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php')],
                processEnvironment(laravelOrigin, true),
                `${stage}:projected`,
            );
            await waitForHealthy(`${laravelOrigin}/`, readinessNonce);
            const projected = await projectedFooter(browser, laravelOrigin, network);
            const restartAbout = async (aboutEnvironment, name) => {
                await stopOwnedChild(laravelServer, `${stage}:about-before-${name}`);
                if (!await portReleased(laravelPort)) {
                    throw new Error(`Security Laravel port was not released before About ${name}.`);
                }
                const nextEnvironment = processEnvironment(laravelOrigin, false, aboutEnvironment);
                runPhp(['artisan', 'cache:clear'], nextEnvironment);
                laravelServer = startServer(
                    'php',
                    ['-S', `127.0.0.1:${laravelPort}`, '-t', join(root, 'public'), join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php')],
                    nextEnvironment,
                    `${stage}:about-${name}`,
                );
                await waitForHealthy(`${laravelOrigin}/`, readinessNonce);
                return nextEnvironment;
            };
            const aboutModes = await aboutModeMatrix(
                browser,
                laravelOrigin,
                fixture,
                restartAbout,
                network,
            );
            payload = {
                admin,
                projected,
                aboutModes,
                totals: {
                    privileged: {
                        expected: 45,
                        present: admin.routeMatrix.length,
                        passed: admin.routeMatrix.filter((item) => item.passed).length,
                    },
                    staticFooter: {
                        expected: 2,
                        present: admin.footer.length,
                        passed: admin.footer.filter((item) => item.passed).length,
                    },
                    projectedFooter: {
                        expected: 2,
                        present: projected.results.length,
                        passed: projected.results.filter((item) => item.passed).length,
                    },
                    logout: admin.logout.passed,
                    aboutModes: aboutModes.totals,
                },
            };
            await json('admin-access-matrix.json', {
                routes: admin.routeMatrix,
                staticFooter: admin.footer,
                projectedFooter: projected.results,
                logout: admin.logout,
                aboutModes: aboutModes.results,
            });
        } else if (stage === 'preview') {
            events.push(lifecycle('preview-matrix-start'));
            const preview = await previewMatrix(browser, laravelOrigin, fixture, network, events);
            payload = {
                preview,
                totals: {
                    expected: 7,
                    present: preview.results.length,
                    passed: preview.results.filter((item) => item.passed).length,
                },
            };
            await json('preview-browser-matrix.json', preview.results);
            await json('preview-assertion-result.json', {
                runId,
                assertionsComplete: true,
                passed: preview.results.every((item) => item.passed),
                cases: preview.results.length,
                persistedAt: new Date().toISOString(),
            });
            events.push(lifecycle('preview-assertions-persisted', { cases: preview.results.length }));
            events.push(lifecycle('trace-finalization-skipped', { reason: 'tracing-disabled' }));
            events.push(lifecycle('video-finalization-skipped', { reason: 'video-recording-disabled' }));
            events.push(lifecycle('har-finalization-skipped', { reason: 'har-recording-disabled' }));
        } else if (stage === 'visual') {
            const mime = await mimePreflight(staticOrigin, laravelOrigin);
            await json('mime-preflight.json', mime);
            if (!mime.passed) {
                failures.push('MIME preflight failed; fidelity capture was not started.');
                payload = {
                    mime,
                    fidelity: {
                        readiness: [],
                        captures: [],
                        diffs: [],
                        repeatability: [],
                        diagnostic,
                    },
                    totals: { captures: 0, comparisons: 0, repeatability: 0 },
                };
            } else {
                const fidelity = await captureFidelity(
                    browser,
                    stageBrowserSession.processHandle.pid,
                    { static: staticOrigin, laravel: laravelOrigin },
                    network,
                );
                payload = {
                    mime,
                    fidelity,
                    totals: {
                        captures: fidelity.captures.length,
                        comparisons: fidelity.diffs.length,
                        repeatability: fidelity.repeatability.length,
                    },
                };
                await Promise.all([
                    json('semantic-readiness-summary.json', fidelity.readiness),
                    json('visual-stability-summary.json', fidelity.readiness.map(({
                        label,
                        passed,
                        renderState,
                        final,
                        timeline,
                        message,
                    }) => ({
                        label,
                        passed,
                        renderState,
                        message,
                        final,
                        frames: timeline.length,
                    }))),
                    json('fidelity-repeatability-summary.json', fidelity.repeatability),
                    json('static-reference-checksums.json', fidelity.captures.filter((item) => item.side === 'static')),
                    json('laravel-capture-checksums.json', fidelity.captures.filter((item) => item.side === 'laravel')),
                    json('fidelity-diff-summary.json', fidelity.diffs),
                ]);
            }
        } else if (stage === 'verify') {
            payload = await verifyStageEvidence(runRoot, runId, manifest);
            if (!payload.passed) failures.push(...payload.failures);
        } else {
            throw new Error(`Unsupported stage ${stage}`);
        }

        const networkGate = classifyNetworkEvidence(network, captureStates, stage);
        payload.networkGate = networkGate;
        if (!networkGate.passed) {
            failures.push(`Console/network gate failed with ${networkGate.blocking.length} blocking event(s).`);
        }
        await json('console-network-summary.json', networkGate);
    } catch (error) {
        executionError = error;
        failures.push(`stage execution error: ${safeErrorMessage(error)}`);
        payload.executionError = {
            name: error.name,
            message: safeErrorMessage(error),
        };
    } finally {
        events.push(lifecycle('active-handle-diagnostic-before-cleanup'));
        const beforeCleanupHandles = sanitizedActiveHandles();
        await json('active-handle-diagnostic.json', {
            beforeCleanup: beforeCleanupHandles,
        });
        events.push(lifecycle('cleanup-start'));
        if (stageBrowserSession) {
            events.push(lifecycle('browser-close-start', { pid: stageBrowserSession.processHandle.pid }));
            await closeIsolatedBrowser(stageBrowserSession);
            events.push(lifecycle('browser-close-finished', {
                pid: stageBrowserSession.processHandle.pid,
                exited: stageBrowserSession.processHandle.exitCode !== null,
            }));
        } else {
            events.push(lifecycle('browser-close-skipped', {
                reason: 'verification stage does not launch Chromium',
            }));
        }
        await stopOwnedChild(laravelServer, `${stage}:laravel-final`);
        await stopOwnedChild(staticServer, `${stage}:static-final`);
        const portRelease = {
            static: await portReleased(staticPort),
            laravel: await portReleased(laravelPort),
        };
        cleanupEvents.push({
            label: `${stage}:selected-port-release`,
            graceful: true,
            forced: false,
            passed: portRelease.static && portRelease.laravel,
            ports: portRelease,
            at: new Date().toISOString(),
        });
        const afterCleanup = sanitizedActiveHandles();
        const runtimeRemoval = await bounded(
            `${stage}:runtime-and-profile-remove`,
            () => rm(runtimeRoot, { recursive: true, force: true }),
            10_000,
        );
        cleanupEvents.push(runtimeRemoval);
        events.push(lifecycle('cleanup-finished', {
            portRelease,
            runtimeReleased: runtimeRemoval.passed,
        }));
        await json('active-handle-diagnostic.json', {
            beforeCleanup: beforeCleanupHandles,
            afterCleanup,
        });
        await json('lifecycle-events.json', events);
        await json('cleanup-result.json', cleanupEvents);
    }

    if (cleanupEvents.some((item) => item.passed === false)) {
        failures.push('One or more owned-resource cleanup operations failed.');
    }
    const stageResult = {
        runId,
        stage,
        diagnostic,
        passed: failures.length === 0,
        failures,
        browserVersion,
        rootManifestChecksum,
        immutableInventory: {
            commit: manifest.commit,
            workingTreeChecksum: manifest.workingTreeChecksum,
            composerLockChecksum: manifest.composerLockChecksum,
            npmLockChecksum: manifest.npmLockChecksum,
            viteManifestChecksum: manifest.viteManifestChecksum,
            protectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
            factoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
            chromiumVersion: manifest.chromiumVersion,
        },
        ports: { static: staticPort, laravel: laravelPort },
        profileRoot: portable(configuredProfileRoot),
        processInventory: cleanupEvents.filter((item) =>
            item.pid || item.label?.includes('process') || item.label?.includes('browser')),
        payload,
        cleanup: cleanupEvents,
        executionError: executionError ? {
            name: executionError.name,
            message: safeErrorMessage(executionError),
        } : null,
        requestedExitCode: failures.length === 0 ? 0 : 1,
    };
    await json('stage-result.json', stageResult);
    const stageResultBytes = await readFile(join(evidenceRoot, 'stage-result.json'));
    await rm(join(evidenceRoot, 'stage-in-progress.json'), { force: true });
    await json('stage-complete.json', {
        runId,
        stage,
        passed: stageResult.passed,
        stageResultChecksum: sha256(stageResultBytes),
        completedAt: new Date().toISOString(),
    });
    events.push(lifecycle('stage-result-persisted', {
        stage,
        passed: stageResult.passed,
        checksum: sha256(stageResultBytes),
    }));
    events.push(lifecycle('node-process-exit-planned', {
        exitCode: stageResult.requestedExitCode,
    }));
    await json('lifecycle-events.json', events);
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
