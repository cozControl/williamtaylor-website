import { execFileSync, spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { access, mkdir, readFile, readdir, stat, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';
import { chromium } from '@playwright/test';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const evidenceBase = join(root, 'storage', 'app', 'evidence', 'be6a1-browser');
const stages = ['security', 'preview', 'visual', 'verify'];
const command = process.argv[2] ?? 'all';

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

function run(commandName, args, options = {}) {
    return execFileSync(commandName, args, {
        cwd: root,
        encoding: 'utf8',
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
        ...options,
    }).trim();
}

async function availablePort() {
    return await new Promise((resolvePort, reject) => {
        const server = createServer();
        server.unref();
        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : null;
            server.close(() => port ? resolvePort(port) : reject(new Error('Could not allocate manifest port.')));
        });
    });
}

async function fileHash(path) {
    try {
        return sha256(await readFile(path));
    } catch {
        return null;
    }
}

async function treeEntries(directory) {
    const entries = [];
    for (const name of await readdir(directory).catch(() => [])) {
        if (['.git', 'node_modules', 'storage', 'vendor'].includes(name)) continue;
        const path = join(directory, name);
        const metadata = await stat(path);
        if (metadata.isDirectory()) entries.push(...await treeEntries(path));
        else entries.push(path);
    }
    return entries;
}

async function workingTreeChecksum() {
    const files = await treeEntries(root);
    const rows = [];
    for (const path of files.sort()) rows.push(`${relative(root, path).replaceAll('\\', '/')}:${await fileHash(path)}`);
    return sha256(rows.join('\n'));
}

async function inventory() {
    const phpVersion = run('php', ['-r', 'echo PHP_VERSION;']);
    const branch = run('git', ['branch', '--show-current']);
    const commit = run('git', ['rev-parse', '--short=12', 'HEAD']);
    const routeOutput = run('php', ['artisan', 'route:list', '--json', '--except-vendor']);
    const scheduleOutput = run('php', ['artisan', 'schedule:list', '--json']);
    const routeCount = JSON.parse(routeOutput).length;
    const schedulerCount = JSON.parse(scheduleOutput).length;
    const willyPath = join(root, 'willy');
    const willyStat = await stat(willyPath);
    return {
        branch,
        commit,
        workingTreeChecksum: await workingTreeChecksum(),
        phpVersion,
        nodeVersion: process.version,
        playwrightVersion: JSON.parse(await readFile(join(root, 'node_modules', '@playwright', 'test', 'package.json'), 'utf8')).version,
        chromiumVersion: await (async () => {
            const browser = await chromium.launch({ headless: true });
            const version = browser.version();
            await browser.close();
            return version;
        })(),
        laravelEnvironment: 'testing',
        viteManifestChecksum: await fileHash(join(root, 'public', 'build', 'manifest.json')),
        composerLockChecksum: await fileHash(join(root, 'composer.lock')),
        npmLockChecksum: await fileHash(join(root, 'package-lock.json')),
        protectedStorefrontManifestChecksum: await fileHash(join(root, 'docs', 'phase-0', 'template-sha256.txt')),
        factoryProtectionManifestChecksum: await fileHash(join(root, 'scripts', 'validation', 'protected-php-baseline.json')),
        willy: { size: willyStat.size, sha256: await fileHash(willyPath) },
        routeCount,
        permissionCount: 56,
        roleCount: 4,
        schedulerCount,
        viewports: {
            homepage: ['375x812', '639x900', '640x900', '767x900', '768x900', '768x1024', '1023x900', '1024x900', '1279x900', '1280x900', '1440x900'],
            standard: ['375x812', '768x1024', '1023x900', '1024x900', '1279x900', '1280x900', '1440x900'],
        },
        readiness: { attempts: 48, frameIntervalMs: 250, stableFrames: 3, assetTimeoutMs: 2000, screenshotTimeoutMs: 15000 },
        motionNormalizationChecksum: sha256('animation-delay:0;animation-duration:0;animation-iteration-count:1;cursor:auto;scroll-behavior:auto;transition-delay:0;transition-duration:0'),
        stages: ['prepare', ...stages],
    };
}

async function prepare() {
    const base = await inventory();
    const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    const runId = `be6a1-${stamp}-${base.commit.slice(0, 8)}`;
    const directory = join(evidenceBase, runId);
    await mkdir(directory, { recursive: true });
    const selectedPorts = {};
    const allocatedPorts = new Set();
    const takePort = async () => {
        let port;
        do port = await availablePort(); while (allocatedPorts.has(port));
        allocatedPorts.add(port);
        return port;
    };
    for (const stage of stages) selectedPorts[stage] = { static: await takePort(), laravel: await takePort() };
    const manifest = {
        runId,
        generatedAt: new Date().toISOString(),
        ...base,
        selectedPorts,
        browserProfileIsolation: 'playwright-per-process-temporary-user-data-dir',
        isolatedDatabaseProcedureChecksum: sha256('sqlite-disposable:create-empty;migrate-fresh;fixture;stage-scoped-path;remove-runtime'),
    };
    await writeFile(join(directory, 'run-manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, { flag: 'wx' });
    await writeFile(join(evidenceBase, 'current-run-id.txt'), `${runId}\n`);
    return manifest;
}

async function currentManifest() {
    const runId = (await readFile(join(evidenceBase, 'current-run-id.txt'), 'utf8')).trim();
    return JSON.parse(await readFile(join(evidenceBase, runId, 'run-manifest.json'), 'utf8'));
}

async function verifyImmutable(manifest) {
    const current = await inventory();
    const keys = ['branch', 'commit', 'workingTreeChecksum', 'phpVersion', 'nodeVersion', 'playwrightVersion', 'chromiumVersion', 'viteManifestChecksum', 'composerLockChecksum', 'npmLockChecksum', 'protectedStorefrontManifestChecksum', 'factoryProtectionManifestChecksum', 'routeCount', 'permissionCount', 'roleCount', 'schedulerCount', 'motionNormalizationChecksum'];
    const changed = keys.filter((key) => JSON.stringify(manifest[key]) !== JSON.stringify(current[key]));
    if (JSON.stringify(manifest.willy) !== JSON.stringify(current.willy)) changed.push('willy');
    if (changed.length) throw new Error(`Immutable run manifest changed: ${changed.join(', ')}`);
}

async function executeStage(stage, manifest) {
    await verifyImmutable(manifest);
    const startedAt = new Date().toISOString();
    const child = spawn(process.execPath, [join(root, 'scripts', 'evidence', 'be6a1-browser.mjs')], {
        cwd: root,
        env: { ...process.env, BE6A1_RUN_ID: manifest.runId, BE6A1_STAGE: stage },
        windowsHide: true,
        stdio: 'inherit',
    });
    const timeoutMs = stage === 'visual' ? 3_600_000 : 600_000;
    const result = await new Promise((resolveResult) => {
        const timer = setTimeout(() => {
            if (process.platform === 'win32') {
                try { execFileSync('taskkill', ['/PID', String(child.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore' }); } catch {}
            } else child.kill('SIGKILL');
            resolveResult({ exitCode: 124, timedOut: true });
        }, timeoutMs);
        child.once('exit', (code) => {
            clearTimeout(timer);
            resolveResult({ exitCode: code ?? 1, timedOut: false });
        });
    });
    const directory = join(evidenceBase, manifest.runId, stage);
    const resultPath = join(directory, 'stage-result.json');
    await access(resultPath, fsConstants.R_OK).catch(() => {
        throw new Error(`${stage} omitted stage-result.json`);
    });
    const stageResult = JSON.parse(await readFile(resultPath, 'utf8'));
    if (stageResult.runId !== manifest.runId) throw new Error(`${stage} used a different run ID`);
    if (result.exitCode !== 0 || result.timedOut || !stageResult.passed) {
        throw new Error(`${stage} failed (exit ${result.exitCode}, timeout ${result.timedOut})`);
    }
    if (stage === 'verify') {
        const ports = [];
        for (const [stageName, selected] of Object.entries(manifest.selectedPorts)) {
            for (const [kind, port] of Object.entries(selected)) {
                const released = await new Promise((resolveReleased) => {
                    const server = createServer();
                    server.unref();
                    server.once('error', () => resolveReleased(false));
                    server.listen(port, '127.0.0.1', () => server.close(() => resolveReleased(true)));
                });
                ports.push({ stage: stageName, kind, port, released });
            }
        }
        if (ports.some((item) => !item.released)) throw new Error('One or more selected browser/server ports remain occupied.');
        const finalPath = join(evidenceBase, manifest.runId, 'final-result.json');
        const aggregate = JSON.parse(await readFile(finalPath, 'utf8'));
        aggregate.orphanProcessResult = { passed: true, selectedPortsReleased: ports };
        await writeFile(finalPath, `${JSON.stringify(aggregate, null, 2)}\n`);
    }
    return { stage, startedAt, finishedAt: new Date().toISOString(), ...result };
}

async function main() {
    if (command === 'prepare') {
        console.log(JSON.stringify(await prepare(), null, 2));
        return;
    }
    const manifest = command === 'all' ? await prepare() : await currentManifest();
    if (command === 'all') {
        for (const stage of stages) await executeStage(stage, manifest);
        return;
    }
    if (!stages.includes(command)) throw new Error(`Unknown BE-6A.1 stage: ${command}`);
    await executeStage(command, manifest);
}

main().catch((error) => {
    console.error(error.stack ?? error.message);
    process.exitCode = 1;
});
