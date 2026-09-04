import { chromium } from '@playwright/test';
import { spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const router = join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php');
const evidencePath = join(root, 'storage', 'app', 'evidence', 'be6a1-admin-login-self-check.json');
const host = '127.0.0.1';
const readinessNonce = randomBytes(24).toString('hex');
const generatedAt = new Date().toISOString();
const adminEmail = 'admin@example.com';
const adminPassword = process.env.BE6A1_ADMIN_PASSWORD;
const javascriptMime = /^(?:application|text)\/(?:javascript|ecmascript)(?:;|$)/i;
const htmlMime = /^text\/html(?:;|$)/i;
const htmlBody = /^\s*(?:<!doctype\s+html|<html\b)/i;
const base44Requests = [];
const protectedBase44MediaRequests = [];
const consoleMessages = [];
const localJavascriptFailures = [];
const frameworkAssetTasks = [];
const frameworkAssets = [];
let serverProcess = null;
let browser = null;
let context = null;
let page = null;
let port = null;
let origin = null;
let executionError = null;
let serverStdout = '';
let serverStderr = '';
let browserPhase = 'login';
let login = {
    status: null,
    finalPath: null,
    moduleScriptCount: null,
    base44RequestCount: null,
    passed: false,
};
let authentication = {
    finalPath: null,
    adminShellVisible: false,
    passed: false,
};
let logout = {
    formCount: null,
    formMethod: null,
    afterSubmissionPath: null,
    directAdminRedirectChain: [],
    finalPath: null,
    adminShellVisible: null,
    passed: false,
};

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

function portable(path) {
    return relative(root, path).replaceAll('\\', '/');
}

function safeError(error) {
    return error instanceof Error ? error.message : String(error);
}

function appendBounded(current, chunk) {
    return `${current}${chunk}`.slice(-16_384);
}

function redactText(value) {
    let redacted = String(value)
        .replaceAll(readinessNonce, '[REDACTED-READINESS-NONCE]')
        .replace(/livewire-[0-9a-f]{8}/gi, 'livewire-[REDACTED-HASH]');
    if (typeof adminPassword === 'string' && adminPassword !== '') {
        redacted = redacted.replaceAll(adminPassword, '[REDACTED-ADMIN-PASSWORD]');
    }
    return redacted.replace(/([?&](?:token|signature|id|key|password|secret|nonce)=)[^&#\s]*/gi, '$1[REDACTED]');
}

function sanitizedPath(value) {
    try {
        const url = new URL(value, origin ?? 'http://127.0.0.1');
        return redactText(url.pathname);
    } catch {
        return redactText(String(value).split('?')[0]);
    }
}

function isBase44Url(value) {
    try {
        const url = new URL(value);
        return (
            /\/api\/apps\//i.test(url.pathname)
            || /\/api\/app-logs\//i.test(url.pathname)
            || url.pathname === '/_boost/browser-logs'
        );
    } catch {
        return /base44|\/api\/apps\//i.test(value);
    }
}

function isProtectedBase44MediaUrl(value) {
    try {
        return new URL(value).hostname.toLowerCase() === 'media.base44.com';
    } catch {
        return false;
    }
}

function isLocalJavascript(request) {
    try {
        const url = new URL(request.url());
        return (
            url.origin === origin
            && (
                request.resourceType() === 'script'
                || /\.(?:m?js)$/i.test(url.pathname)
            )
        );
    } catch {
        return false;
    }
}

function frameworkKind(value) {
    try {
        const path = new URL(value).pathname;
        if (/^\/flux\/flux(?:\.min)?\.js$/i.test(path)) return 'flux-main-script';
        if (/^\/livewire-[0-9a-f]{8}\/livewire(?:\.min)?\.js$/i.test(path)) {
            return 'livewire-main-script';
        }
    } catch {}
    return null;
}

function defaultServerEnvironment(appUrl) {
    const removedOverrides = /^(?:APP_(?:ENV|DEBUG|KEY|URL|CONFIG_CACHE)|DB_.*|DATABASE_URL|CACHE_(?:STORE|DRIVER|PREFIX)|SESSION_(?:DRIVER|CONNECTION)|QUEUE_CONNECTION|BE6A1_ADMIN_PASSWORD)$/i;
    const environment = Object.fromEntries(
        Object.entries(process.env).filter(([key]) => !removedOverrides.test(key)),
    );
    return {
        ...environment,
        APP_URL: appUrl,
        BE6A1_READINESS_NONCE: readinessNonce,
    };
}

async function allocatePort() {
    const reservation = createServer();
    await new Promise((resolveListen, rejectListen) => {
        reservation.once('error', rejectListen);
        reservation.listen(0, host, resolveListen);
    });
    const address = reservation.address();
    if (address === null || typeof address === 'string') {
        reservation.close();
        throw new Error('Unable to allocate an IPv4 loopback port.');
    }
    await new Promise((resolveClose, rejectClose) => {
        reservation.close((error) => error ? rejectClose(error) : resolveClose());
    });
    return address.port;
}

async function waitForReadiness() {
    const deadline = Date.now() + 15_000;
    let lastError = 'server did not respond';
    while (Date.now() < deadline) {
        if (serverProcess?.exitCode !== null) {
            throw new Error(`PHP server exited before readiness with code ${serverProcess.exitCode}.`);
        }
        try {
            const response = await fetch(`${origin}/login`, {
                redirect: 'manual',
                signal: AbortSignal.timeout(1_000),
            });
            await response.arrayBuffer();
            if (
                response.status === 200
                && response.headers.get('x-be6a1-readiness') === readinessNonce
            ) return;
            lastError = `status ${response.status} with an invalid readiness header`;
        } catch (error) {
            lastError = safeError(error);
        }
        await new Promise((resolveDelay) => setTimeout(resolveDelay, 50));
    }
    throw new Error(`PHP server readiness timed out: ${lastError}`);
}

async function inspectFrameworkResponse(response, kind) {
    try {
        const body = Buffer.from(await response.body());
        const contentType = response.headers()['content-type'] ?? '';
        const prefix = body.subarray(0, 256).toString('utf8');
        const htmlFallback = htmlMime.test(contentType) || htmlBody.test(prefix);
        frameworkAssets.push({
            kind,
            path: sanitizedPath(response.url()),
            status: response.status(),
            contentType,
            bytes: body.length,
            bodySha256: sha256(body),
            htmlFallback,
            passed: (
                response.status() >= 200
                && response.status() < 300
                && javascriptMime.test(contentType)
                && body.length > 0
                && !htmlFallback
            ),
        });
    } catch (error) {
        frameworkAssets.push({
            kind,
            path: sanitizedPath(response.url()),
            status: response.status(),
            contentType: response.headers()['content-type'] ?? '',
            bytes: null,
            bodySha256: null,
            htmlFallback: null,
            error: redactText(safeError(error)),
            passed: false,
        });
    }
}

async function redirectChain(response) {
    if (response === null) return [];
    const chain = [];
    let request = response.request();
    while (request !== null) {
        const requestResponse = await request.response();
        chain.push({
            method: request.method(),
            path: sanitizedPath(request.url()),
            status: requestResponse?.status() ?? null,
        });
        request = request.redirectedFrom();
    }
    return chain.reverse();
}

async function closeOwnedBrowser() {
    const result = {
        contextAttempted: context !== null,
        contextClosed: context === null,
        browserAttempted: browser !== null,
        browserClosed: browser === null,
        errors: [],
        passed: true,
    };
    if (context !== null) {
        try {
            await context.close();
            result.contextClosed = true;
        } catch (error) {
            result.errors.push(redactText(safeError(error)));
        }
    }
    if (browser !== null) {
        try {
            await browser.close();
            result.browserClosed = true;
        } catch (error) {
            result.errors.push(redactText(safeError(error)));
        }
    }
    result.passed = result.contextClosed && result.browserClosed && result.errors.length === 0;
    return result;
}

async function stopOwnedServer(child) {
    if (child === null) {
        return {
            attempted: false,
            ownedPid: null,
            signalSent: false,
            forced: false,
            exited: true,
            exitCode: null,
            signal: null,
            passed: true,
        };
    }
    const ownedPid = child.pid ?? null;
    if (child.exitCode !== null || child.signalCode !== null) {
        return {
            attempted: false,
            ownedPid,
            signalSent: false,
            forced: false,
            exited: true,
            exitCode: child.exitCode,
            signal: child.signalCode,
            passed: true,
        };
    }
    const waitForExit = (timeout) => new Promise((resolveExit) => {
        const finish = (result) => {
            clearTimeout(timer);
            child.off('exit', onExit);
            resolveExit(result);
        };
        const onExit = (code, signal) => finish({ exited: true, exitCode: code, signal });
        const timer = setTimeout(() => finish({
            exited: false,
            exitCode: child.exitCode,
            signal: child.signalCode,
        }), timeout);
        child.once('exit', onExit);
        if (child.exitCode !== null || child.signalCode !== null) {
            finish({ exited: true, exitCode: child.exitCode, signal: child.signalCode });
        }
    });
    let signalSent = false;
    try {
        signalSent = child.kill();
    } catch {}
    let result = await waitForExit(5_000);
    let forced = false;
    if (!result.exited) {
        forced = true;
        try {
            child.kill('SIGKILL');
        } catch {}
        result = await waitForExit(5_000);
    }
    return {
        attempted: true,
        ownedPid,
        signalSent,
        forced,
        ...result,
        passed: result.exited,
    };
}

try {
    if (typeof adminPassword !== 'string' || adminPassword === '') {
        throw new Error('BE6A1_ADMIN_PASSWORD must be supplied through the process environment.');
    }

    port = await allocatePort();
    origin = `http://${host}:${port}`;
    serverProcess = spawn(
        'php',
        ['-S', `${host}:${port}`, '-t', join(root, 'public'), router],
        {
            cwd: root,
            env: defaultServerEnvironment(origin),
            stdio: ['ignore', 'pipe', 'pipe'],
            windowsHide: true,
        },
    );
    serverProcess.stdout.on('data', (chunk) => {
        serverStdout = appendBounded(serverStdout, chunk.toString('utf8'));
    });
    serverProcess.stderr.on('data', (chunk) => {
        serverStderr = appendBounded(serverStderr, chunk.toString('utf8'));
    });
    await new Promise((resolveSpawn, rejectSpawn) => {
        serverProcess.once('spawn', resolveSpawn);
        serverProcess.once('error', rejectSpawn);
    });
    await waitForReadiness();

    browser = await chromium.launch({ headless: true });
    context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
        timezoneId: 'Africa/Nairobi',
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
    });
    page = await context.newPage();
    page.on('request', (request) => {
        if (isBase44Url(request.url())) {
            base44Requests.push({
                phase: browserPhase,
                method: request.method(),
                resourceType: request.resourceType(),
                path: sanitizedPath(request.url()),
            });
        }
        if (isProtectedBase44MediaUrl(request.url())) {
            protectedBase44MediaRequests.push({
                phase: browserPhase,
                method: request.method(),
                resourceType: request.resourceType(),
                path: sanitizedPath(request.url()),
                disposition: 'existing-protected-imported-media-not-an-api-or-framework-asset',
            });
        }
    });
    page.on('console', (message) => {
        if (!['error', 'warning'].includes(message.type())) return;
        consoleMessages.push({
            phase: browserPhase,
            type: message.type(),
            text: redactText(message.text()),
        });
    });
    page.on('requestfailed', (request) => {
        if (!isLocalJavascript(request)) return;
        localJavascriptFailures.push({
            phase: browserPhase,
            source: 'requestfailed',
            method: request.method(),
            resourceType: request.resourceType(),
            path: sanitizedPath(request.url()),
            status: null,
            error: redactText(request.failure()?.errorText ?? 'unknown request failure'),
        });
    });
    page.on('response', (response) => {
        const request = response.request();
        if (isLocalJavascript(request) && response.status() >= 400) {
            localJavascriptFailures.push({
                phase: browserPhase,
                source: 'http-response',
                method: request.method(),
                resourceType: request.resourceType(),
                path: sanitizedPath(response.url()),
                status: response.status(),
                error: null,
            });
        }
        const kind = frameworkKind(response.url());
        if (kind !== null) {
            frameworkAssetTasks.push(inspectFrameworkResponse(response, kind));
        }
    });

    const loginResponse = await page.goto(`${origin}/login`, {
        waitUntil: 'networkidle',
        timeout: 30_000,
    });
    login = {
        status: loginResponse?.status() ?? null,
        finalPath: sanitizedPath(page.url()),
        moduleScriptCount: await page.locator('script[type="module"]').count(),
        base44RequestCount: base44Requests.length,
        passed: false,
    };
    login.passed = (
        login.status === 200
        && login.finalPath === '/login'
        && login.moduleScriptCount === 0
        && login.base44RequestCount === 0
    );
    if (!login.passed) throw new Error('The public login-page contract failed.');

    await page.locator('#email').fill(adminEmail);
    await page.locator('#password').fill(adminPassword);
    browserPhase = 'admin-authentication';
    const adminNavigation = page.waitForURL(
        (url) => url.pathname === '/admin',
        { timeout: 30_000, waitUntil: 'domcontentloaded' },
    );
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await adminNavigation;
    await page.locator('.admin-shell').waitFor({ state: 'visible', timeout: 30_000 });
    await page.waitForLoadState('networkidle', { timeout: 30_000 });
    authentication = {
        finalPath: sanitizedPath(page.url()),
        adminShellVisible: await page.locator('.admin-shell').isVisible(),
        passed: false,
    };
    authentication.passed = (
        authentication.finalPath === '/admin'
        && authentication.adminShellVisible
    );
    if (!authentication.passed) throw new Error('Administrator authentication did not establish the Admin shell.');

    await Promise.all([...frameworkAssetTasks]);
    const successfulFlux = frameworkAssets.filter((asset) =>
        asset.kind === 'flux-main-script' && asset.passed);
    const successfulLivewire = frameworkAssets.filter((asset) =>
        asset.kind === 'livewire-main-script' && asset.passed);
    if (successfulFlux.length === 0 || successfulLivewire.length === 0) {
        throw new Error('Admin did not load successful Flux and Livewire main script responses.');
    }
    if (localJavascriptFailures.length !== 0) {
        throw new Error('Admin emitted one or more required local JavaScript failures.');
    }
    if (base44Requests.length !== 0) {
        throw new Error('Login/Admin emitted one or more Base44 requests.');
    }
    if (consoleMessages.length !== 0) {
        throw new Error('Login/Admin emitted one or more console errors or warnings.');
    }

    browserPhase = 'logout';
    const logoutForm = page.locator('form[action$="/logout"]');
    logout.formCount = await logoutForm.count();
    logout.formMethod = logout.formCount === 1
        ? (await logoutForm.getAttribute('method'))?.toUpperCase() ?? null
        : null;
    if (logout.formCount !== 1 || logout.formMethod !== 'POST') {
        throw new Error('Admin logout form contract is missing or invalid.');
    }
    const logoutNavigation = page.waitForNavigation({
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    await logoutForm.evaluate((form) => form.requestSubmit());
    await logoutNavigation;
    logout.afterSubmissionPath = sanitizedPath(page.url());

    const directAdminResponse = await page.goto(`${origin}/admin`, {
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
    });
    logout.directAdminRedirectChain = await redirectChain(directAdminResponse);
    logout.finalPath = sanitizedPath(page.url());
    logout.adminShellVisible = await page.locator('.admin-shell').isVisible().catch(() => false);
    const firstDirectHop = logout.directAdminRedirectChain[0] ?? null;
    const finalDirectHop = logout.directAdminRedirectChain.at(-1) ?? null;
    logout.passed = (
        firstDirectHop?.path === '/admin'
        && firstDirectHop.status >= 300
        && firstDirectHop.status < 400
        && finalDirectHop?.path === '/login'
        && finalDirectHop.status === 200
        && logout.finalPath === '/login'
        && !logout.adminShellVisible
    );
    if (!logout.passed) throw new Error('Logout did not protect a subsequent direct Admin request.');
} catch (error) {
    executionError = redactText(safeError(error));
}

const browserCleanup = await closeOwnedBrowser();
const serverCleanup = await stopOwnedServer(serverProcess);
const successfulFlux = frameworkAssets.filter((asset) =>
    asset.kind === 'flux-main-script' && asset.passed);
const successfulLivewire = frameworkAssets.filter((asset) =>
    asset.kind === 'livewire-main-script' && asset.passed);
const frameworkAssetsPassed = (
    successfulFlux.length > 0
    && successfulLivewire.length > 0
    && frameworkAssets.every((asset) => asset.passed)
);
const applicationPhases = new Set(['login', 'admin-authentication']);
const applicationBase44Requests = base44Requests.filter((event) =>
    applicationPhases.has(event.phase));
const applicationLocalJavascriptFailures = localJavascriptFailures.filter((event) =>
    applicationPhases.has(event.phase));
const applicationConsoleMessages = consoleMessages.filter((event) =>
    applicationPhases.has(event.phase));
const networkPassed = (
    applicationBase44Requests.length === 0
    && applicationLocalJavascriptFailures.length === 0
    && applicationConsoleMessages.length === 0
);
const passed = (
    executionError === null
    && login.passed
    && authentication.passed
    && frameworkAssetsPassed
    && networkPassed
    && logout.passed
    && browserCleanup.passed
    && serverCleanup.passed
);
const evidence = {
    schemaVersion: 1,
    phase: 'BE-6A.1',
    check: 'admin-login-router-self-check',
    generatedAt,
    completedAt: new Date().toISOString(),
    diagnostic: true,
    configuration: {
        origin: port === null ? null : `http://${host}:${port}`,
        router: portable(router),
        applicationConfiguration: 'repository-default-dotenv',
        databaseConfiguration: 'repository-default-configured-validation-database',
        runtimeAppUrlOverride: true,
        inheritedDatabaseOverridesRemoved: true,
        adminEmail,
        passwordSource: 'process.env.BE6A1_ADMIN_PASSWORD',
        password: '[REDACTED]',
        readinessNonce: '[REDACTED]',
    },
    runtime: {
        browserVersion: browser?.version() ?? null,
        serverPid: serverProcess?.pid ?? null,
    },
    login,
    authentication,
    frameworkAssets: {
        expectedKinds: ['flux-main-script', 'livewire-main-script'],
        observations: frameworkAssets,
        successfulFlux: successfulFlux.length,
        successfulLivewire: successfulLivewire.length,
        passed: frameworkAssetsPassed,
    },
    network: {
        base44Requests,
        protectedBase44MediaRequests,
        localJavascriptFailures,
        consoleMessages,
        scopedApplicationPhases: [...applicationPhases],
        applicationBase44Requests,
        applicationLocalJavascriptFailures,
        applicationConsoleMessages,
        logoutStorefrontEventsExcludedFromApplicationGate: true,
        passed: networkPassed,
    },
    logout,
    cleanup: {
        browser: browserCleanup,
        server: serverCleanup,
        passed: browserCleanup.passed && serverCleanup.passed,
    },
    executionError,
    serverOutput: {
        stdoutBytes: Buffer.byteLength(serverStdout),
        stdoutSha256: sha256(serverStdout),
        stderrBytes: Buffer.byteLength(serverStderr),
        stderrSha256: sha256(serverStderr),
        contentRecorded: false,
    },
    passed,
};

await mkdir(dirname(evidencePath), { recursive: true });
await writeFile(evidencePath, `${JSON.stringify(evidence, null, 2)}\n`, 'utf8');
console.log(JSON.stringify({
    passed,
    evidence: portable(evidencePath),
    login: login.passed,
    authentication: authentication.passed,
    frameworkAssets: frameworkAssetsPassed,
    network: networkPassed,
    logout: logout.passed,
    cleanup: evidence.cleanup.passed,
    error: executionError,
}));

if (!passed) process.exitCode = 1;
