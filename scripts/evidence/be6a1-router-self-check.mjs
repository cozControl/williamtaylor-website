import { spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const router = join(root, 'scripts', 'evidence', 'be6a1-laravel-router.php');
const evidencePath = join(root, 'storage', 'app', 'evidence', 'be6a1-router-self-check.json');
const host = '127.0.0.1';
const disposableAppKey = `base64:${Buffer.from('BE6A1-router-self-check-key-0000').toString('base64')}`;
const livewireHash = sha256(`${disposableAppKey}livewire-endpoint`).slice(0, 8);
const livewirePrefix = `/livewire-${livewireHash}`;
const wrongLivewireHash = livewireHash === '00000000' ? 'ffffffff' : '00000000';
const readinessNonce = randomBytes(24).toString('hex');
const generatedAt = new Date().toISOString();
const javascriptMime = /^(?:application|text)\/(?:javascript|ecmascript)(?:;|$)/i;
const htmlMime = /^text\/html(?:;|$)/i;
const htmlBody = /^\s*(?:<!doctype\s+html|<html\b)/i;
const checks = [];
let serverProcess = null;
let port = null;
let executionError = null;
let serverStdout = '';
let serverStderr = '';

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

function redact(value) {
    return value
        .replaceAll(disposableAppKey, '[REDACTED-DISPOSABLE-APP-KEY]')
        .replaceAll(readinessNonce, '[REDACTED-READINESS-NONCE]');
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

async function waitForReadiness(origin) {
    const deadline = Date.now() + 15_000;
    let lastError = 'server did not respond';
    while (Date.now() < deadline) {
        if (serverProcess?.exitCode !== null) {
            throw new Error(`PHP server exited before readiness with code ${serverProcess.exitCode}.`);
        }
        try {
            const response = await fetch(`${origin}/flux/flux.js?be6a1-readiness=1`, {
                redirect: 'manual',
                signal: AbortSignal.timeout(1_000),
            });
            if (
                response.status === 200
                && response.headers.get('x-be6a1-readiness') === readinessNonce
            ) {
                await response.arrayBuffer();
                return;
            }
            lastError = `status ${response.status} with an invalid readiness header`;
        } catch (error) {
            lastError = safeError(error);
        }
        await new Promise((resolveDelay) => setTimeout(resolveDelay, 50));
    }
    throw new Error(`PHP server readiness timed out: ${lastError}`);
}

async function probe(origin, specification) {
    const response = await fetch(`${origin}${specification.path}`, {
        redirect: 'manual',
        signal: AbortSignal.timeout(10_000),
    });
    const body = Buffer.from(await response.arrayBuffer());
    const contentType = response.headers.get('content-type') ?? '';
    const bodyPrefix = body.subarray(0, 256).toString('utf8');
    const isHtml = htmlMime.test(contentType) || htmlBody.test(bodyPrefix);
    const routerOwnedPlain404 = (
        response.status === 404
        && /^text\/plain(?:;|$)/i.test(contentType)
        && body.toString('utf8').trim() === 'Not Found'
    );
    const passed = specification.expect === 'javascript'
        ? response.status === 200
            && javascriptMime.test(contentType)
            && body.length > 0
            && !isHtml
        : routerOwnedPlain404 && !isHtml;
    checks.push({
        id: specification.id,
        expectation: specification.expect,
        path: specification.path,
        expectedStatus: specification.expect === 'javascript' ? 200 : 404,
        status: response.status,
        contentType,
        bytes: body.length,
        bodySha256: sha256(body),
        htmlFallback: isHtml,
        routerOwnedPlain404,
        passed,
    });
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

    let forced = false;
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

const specifications = [
    { id: 'flux-debug-script', expect: 'javascript', path: '/flux/flux.js' },
    { id: 'flux-debug-script-query', expect: 'javascript', path: '/flux/flux.js?id=be6a1-router-self-check' },
    { id: 'livewire-debug-script', expect: 'javascript', path: `${livewirePrefix}/livewire.js` },
    { id: 'livewire-debug-script-query', expect: 'javascript', path: `${livewirePrefix}/livewire.js?id=be6a1-router-self-check` },
    { id: 'flux-misspelled-script', expect: 'router-404', path: '/flux/fluz.js' },
    { id: 'flux-unrelated-script', expect: 'router-404', path: '/flux/unrelated.js' },
    { id: 'livewire-wrong-hash-script', expect: 'router-404', path: `/livewire-${wrongLivewireHash}/livewire.js` },
    { id: 'livewire-exact-prefix-fake-component', expect: 'router-404', path: `${livewirePrefix}/js/be6a1-router-fake-component.js` },
    { id: 'ordinary-missing-javascript', expect: 'router-404', path: '/js/be6a1-router-missing.js' },
    { id: 'ordinary-missing-module', expect: 'router-404', path: '/js/be6a1-router-missing.mjs' },
    { id: 'ordinary-missing-stylesheet', expect: 'router-404', path: '/css/be6a1-router-missing.css' },
];

try {
    port = await allocatePort();
    const origin = `http://${host}:${port}`;
    serverProcess = spawn(
        'php',
        ['-S', `${host}:${port}`, '-t', join(root, 'public'), router],
        {
            cwd: root,
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_DEBUG: 'true',
                APP_KEY: disposableAppKey,
                APP_URL: origin,
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: ':memory:',
                SESSION_DRIVER: 'array',
                CACHE_STORE: 'array',
                QUEUE_CONNECTION: 'sync',
                MAIL_MAILER: 'array',
                BE6A1_READINESS_NONCE: readinessNonce,
            },
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
    await waitForReadiness(origin);
    for (const specification of specifications) {
        await probe(origin, specification);
    }
} catch (error) {
    executionError = safeError(error);
}

const shutdown = await stopOwnedServer(serverProcess);
const passed = (
    executionError === null
    && checks.length === specifications.length
    && checks.every((check) => check.passed)
    && shutdown.passed
);
const evidence = {
    schemaVersion: 1,
    phase: 'BE-6A.1',
    check: 'evidence-router-self-check',
    generatedAt,
    completedAt: new Date().toISOString(),
    diagnostic: true,
    configuration: {
        host,
        port,
        router: portable(router),
        appDebug: true,
        appKey: '[REDACTED-DISPOSABLE-APP-KEY]',
        readinessNonce: '[REDACTED-READINESS-NONCE]',
        livewirePrefix,
    },
    ownership: {
        spawnedPid: serverProcess?.pid ?? null,
        shutdown,
    },
    checks,
    totals: {
        expected: specifications.length,
        present: checks.length,
        passed: checks.filter((check) => check.passed).length,
        failed: specifications.length - checks.filter((check) => check.passed).length,
    },
    executionError,
    serverOutput: executionError === null ? null : {
        stdout: redact(serverStdout),
        stderr: redact(serverStderr),
    },
    passed,
};

await mkdir(dirname(evidencePath), { recursive: true });
await writeFile(evidencePath, `${JSON.stringify(evidence, null, 2)}\n`, 'utf8');
console.log(JSON.stringify({
    passed,
    evidence: portable(evidencePath),
    totals: evidence.totals,
    shutdown: shutdown.passed,
    error: executionError,
}));

if (!passed) process.exitCode = 1;
