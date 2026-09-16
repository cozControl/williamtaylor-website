import { execFileSync, spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
    access,
    cp,
    mkdir,
    readFile,
    readdir,
    rename,
    rm,
    stat,
    writeFile,
} from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';
import { chromium } from '@playwright/test';
import {
    BROWSER_LAUNCH_ARGS,
    DATABASE_PROCEDURE,
    DECORATIVE_MEDIA_ALLOWLIST,
    MOTION_NORMALIZATION_CSS,
    READINESS_CONFIG,
    STATIC_ROUTER_NORMALIZATION,
    VISUAL_NORMALIZATION_CSS,
} from './be6a1-fidelity-contract.mjs';
import {
    probeSelectedPorts,
    validateBaselineCandidate,
    validateEvidenceAggregate,
} from './be6a1-harness.mjs';
import {
    createCandidateTransactionCoordinator,
    normalizeExecutableName,
    normalizeProcessCreationTime,
    physicalTreeIdentity,
} from './be6a1-candidate-transaction.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const evidenceBase = join(root, 'storage', 'app', 'evidence', 'be6a1-browser');
const candidateRoot = join(root, 'storage', 'app', 'evidence', 'be6a1-baseline-candidate');
const candidateLockRoot = `${candidateRoot}.finalization-lock`;
const stages = ['security', 'preview', 'visual', 'verify'];
const command = process.argv[2] ?? 'all';

const candidateTransactions = createCandidateTransactionCoordinator({
    workspaceRoot: root,
    evidenceBase,
    candidateRoot,
    candidateLockRoot,
    processSnapshot: () => processSnapshot(),
});

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

function run(commandName, args, options = {}) {
    return execFileSync(commandName, args, {
        cwd: root,
        encoding: 'utf8',
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: 60_000,
        ...options,
    }).trim();
}

async function availablePort() {
    return await new Promise((resolvePort, reject) => {
        const server = createServer();
        server.unref();
        server.once('error', reject);
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
    for (const name of await readdir(directory)) {
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
    for (const path of files.sort()) {
        rows.push(`${relative(root, path).replaceAll('\\', '/')}:${await fileHash(path)}`);
    }
    return sha256(rows.join('\n'));
}

async function workingTreeDiffChecksum() {
    const tracked = run('git', ['diff', '--binary', '--no-ext-diff', '--', '.']);
    const untrackedNames = run('git', ['ls-files', '--others', '--exclude-standard'])
        .split(/\r?\n/)
        .filter(Boolean)
        .sort();
    const untracked = [];
    for (const name of untrackedNames) {
        untracked.push(`${name}:${await fileHash(join(root, name))}`);
    }
    return sha256(`${tracked}\n--UNTRACKED--\n${untracked.join('\n')}`);
}

function chromiumVersionFromFile() {
    const executable = chromium.executablePath();
    if (process.platform === 'win32') {
        return run(
            'powershell.exe',
            ['-NoProfile', '-Command', '(Get-Item -LiteralPath $env:BE6A1_CHROMIUM_PATH).VersionInfo.ProductVersion'],
            {
                env: { ...process.env, BE6A1_CHROMIUM_PATH: executable },
                timeout: 10_000,
            },
        );
    }
    const output = run(executable, ['--version'], { timeout: 10_000 });
    return output.match(/\d+(?:\.\d+)+/)?.[0] ?? output;
}

function registryCounts() {
    const code = [
        "require 'vendor/autoload.php';",
        'echo json_encode([',
        "'permissionCount' => count(\\App\\Domain\\Identity\\Support\\PermissionRegistry::all()),",
        "'roleCount' => count(\\App\\Domain\\Identity\\Support\\RoleRegistry::permissionBundles()),",
        ']);',
    ].join('');
    return JSON.parse(run('php', ['-r', code]));
}

async function inventory() {
    const phpVersion = run('php', ['-r', 'echo PHP_VERSION;']);
    const branch = run('git', ['branch', '--show-current']);
    const commit = run('git', ['rev-parse', '--short=12', 'HEAD']);
    const routeOutput = run('php', ['artisan', 'route:list', '--json', '--except-vendor']);
    const scheduleOutput = run('php', ['artisan', 'schedule:list', '--json']);
    const routeCount = JSON.parse(routeOutput).length;
    const schedulerCount = JSON.parse(scheduleOutput).length;
    const counts = registryCounts();
    const willyPath = join(root, 'willy');
    const willyStat = await stat(willyPath);
    return {
        branch,
        commit,
        workingTreeChecksum: await workingTreeChecksum(),
        workingTreeDiffChecksum: await workingTreeDiffChecksum(),
        phpVersion,
        nodeVersion: process.version,
        playwrightVersion: JSON.parse(
            await readFile(join(root, 'node_modules', '@playwright', 'test', 'package.json'), 'utf8'),
        ).version,
        chromiumVersion: chromiumVersionFromFile(),
        laravelEnvironment: 'testing',
        viteManifestChecksum: await fileHash(join(root, 'public', 'build', 'manifest.json')),
        composerLockChecksum: await fileHash(join(root, 'composer.lock')),
        npmLockChecksum: await fileHash(join(root, 'package-lock.json')),
        protectedStorefrontManifestChecksum: await fileHash(join(root, 'docs', 'phase-0', 'template-sha256.txt')),
        factoryProtectionManifestChecksum: await fileHash(join(root, 'scripts', 'validation', 'protected-php-baseline.json')),
        approvedBaselineRootChecksum: await directoryChecksum(
            join(root, 'tests', 'Baselines', 'william-taylor-template'),
        ),
        willy: {
            size: willyStat.size,
            sha256: await fileHash(willyPath),
        },
        routeCount,
        permissionCount: counts.permissionCount,
        roleCount: counts.roleCount,
        schedulerCount,
        viewports: {
            homepage: [
                '375x812',
                '639x900',
                '640x900',
                '767x900',
                '768x900',
                '768x1024',
                '1023x900',
                '1024x900',
                '1279x900',
                '1280x900',
                '1440x900',
            ],
            standard: [
                '375x812',
                '768x1024',
                '1023x900',
                '1024x900',
                '1279x900',
                '1280x900',
                '1440x900',
            ],
        },
        readiness: READINESS_CONFIG,
        motionNormalizationChecksum: sha256(MOTION_NORMALIZATION_CSS),
        visualNormalizationChecksum: sha256(VISUAL_NORMALIZATION_CSS),
        browserLaunchArgsChecksum: sha256(BROWSER_LAUNCH_ARGS.join('\n')),
        staticRouterNormalizationChecksum: sha256(JSON.stringify(STATIC_ROUTER_NORMALIZATION)),
        decorativeMediaAllowlistChecksum: sha256(JSON.stringify(DECORATIVE_MEDIA_ALLOWLIST)),
        browserProfileIsolation: 'stage-scoped-TEMP/TMP-with-Playwright-owned-per-process-user-data-directories',
        isolatedDatabaseProcedureChecksum: sha256(DATABASE_PROCEDURE),
        stages: ['prepare', ...stages],
    };
}

async function writeJson(path, value, options = {}) {
    await writeFile(path, `${JSON.stringify(value, null, 2)}\n`, options);
}

async function pathExists(path) {
    return await access(path, fsConstants.F_OK)
        .then(() => true)
        .catch(() => false);
}

async function writeJsonAtomic(path, value) {
    const temporaryPath = `${path}.${process.pid}.${Date.now()}.tmp`;
    await writeJson(temporaryPath, value, { flag: 'wx' });
    try {
        await rename(temporaryPath, path);
    } finally {
        await rm(temporaryPath, { force: true }).catch(() => {});
    }
}

async function writeTextAtomic(path, value) {
    const temporaryPath = `${path}.${process.pid}.${Date.now()}.tmp`;
    await writeFile(temporaryPath, value, { flag: 'wx' });
    try {
        await rename(temporaryPath, path);
    } finally {
        await rm(temporaryPath, { force: true }).catch(() => {});
    }
}

async function allFiles(directory) {
    const files = [];
    for (const name of await readdir(directory)) {
        const path = join(directory, name);
        const metadata = await stat(path);
        if (metadata.isDirectory()) files.push(...await allFiles(path));
        else files.push(path);
    }
    return files;
}

async function directoryChecksum(directory) {
    const rows = [];
    for (const path of (await allFiles(directory)).sort()) {
        rows.push(`${relative(directory, path).replaceAll('\\', '/')}:${await fileHash(path)}`);
    }
    return sha256(rows.join('\n'));
}

async function approvedBaselineValidationRoots(manifest) {
    const approvedRoot = join(root, 'tests', 'Baselines', 'william-taylor-template');
    return [{
        root: approvedRoot,
        beforeChecksum: manifest.approvedBaselineRootChecksum,
        afterChecksum: await directoryChecksum(approvedRoot),
    }];
}

async function prepare() {
    const base = await inventory();
    const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    const runId = `be6a1-${stamp}-${base.commit.slice(0, 8)}`;
    const directory = join(evidenceBase, runId);
    await mkdir(join(directory, 'prepare'), { recursive: true });
    const selectedPorts = {};
    const allocatedPorts = new Set();
    const takePort = async () => {
        let port;
        do port = await availablePort(); while (allocatedPorts.has(port));
        allocatedPorts.add(port);
        return port;
    };
    for (const stage of stages) {
        selectedPorts[stage] = {
            static: await takePort(),
            laravel: await takePort(),
        };
    }
    const browserProfileRoots = Object.fromEntries(stages.map((stage) => [
        stage,
        relative(
            root,
            join(root, 'storage', 'app', 'test-runtime', 'browser', runId, stage, 'playwright-profile-root'),
        ).replaceAll('\\', '/'),
    ]));
    const manifest = {
        runId,
        generatedAt: new Date().toISOString(),
        ...base,
        selectedPorts,
        browserProfileRoots,
    };
    const manifestPath = join(directory, 'run-manifest.json');
    await writeJson(manifestPath, manifest, { flag: 'wx' });
    const manifestChecksum = await fileHash(manifestPath);
    await writeFile(join(directory, 'run-manifest.sha256'), `${manifestChecksum}  run-manifest.json\n`, { flag: 'wx' });
    const prepareResult = {
        runId,
        stage: 'prepare',
        passed: true,
        rootManifestChecksum: manifestChecksum,
        selectedPorts,
        browserProfileRoots,
        generatedAt: manifest.generatedAt,
    };
    await writeJson(join(directory, 'prepare', 'stage-result.json'), prepareResult, { flag: 'wx' });
    await writeJson(join(directory, 'prepare', 'stage-complete.json'), {
        runId,
        stage: 'prepare',
        passed: true,
        stageResultChecksum: await fileHash(join(directory, 'prepare', 'stage-result.json')),
        completedAt: new Date().toISOString(),
    }, { flag: 'wx' });
    const initialJournal = [{
        stage: 'prepare',
        startedAt: manifest.generatedAt,
        finishedAt: new Date().toISOString(),
        exitCode: 0,
        timedOut: false,
        passed: true,
        rootManifestChecksum: manifestChecksum,
    }];
    await writeJson(join(directory, 'orchestration-journal.json'), initialJournal, { flag: 'wx' });
    await writeJsonAtomic(join(directory, 'final-result.json'), openAggregate({
        runId,
        rootManifestChecksum: manifestChecksum,
        stages: { prepare: prepareResult },
        cleanupResults: {},
        orchestrationOutcomes: initialJournal,
    }, 'Canonical stage execution has not completed.', {
        phase: 'prepared',
        approvedBaselineWrites: [],
        formalApprovalState: 'not-requested',
    }));
    await writeTextAtomic(join(evidenceBase, 'current-run-id.txt'), `${runId}\n`);
    return { manifest, manifestChecksum };
}

async function currentManifest() {
    const runId = (await readFile(join(evidenceBase, 'current-run-id.txt'), 'utf8')).trim();
    const path = join(evidenceBase, runId, 'run-manifest.json');
    const bytes = await readFile(path);
    const sidecar = (await readFile(join(evidenceBase, runId, 'run-manifest.sha256'), 'utf8')).trim().split(/\s+/)[0];
    const checksum = sha256(bytes);
    if (checksum !== sidecar) throw new Error('Immutable root manifest checksum sidecar mismatch.');
    return {
        manifest: JSON.parse(bytes),
        manifestChecksum: checksum,
    };
}

async function verifyImmutable(manifest, expectedManifestChecksum) {
    const manifestPath = join(evidenceBase, manifest.runId, 'run-manifest.json');
    const currentManifestChecksum = await fileHash(manifestPath);
    if (currentManifestChecksum !== expectedManifestChecksum) {
        throw new Error('Immutable root manifest file changed between stages.');
    }
    const current = await inventory();
    const keys = [
        'branch',
        'commit',
        'workingTreeChecksum',
        'workingTreeDiffChecksum',
        'phpVersion',
        'nodeVersion',
        'playwrightVersion',
        'chromiumVersion',
        'laravelEnvironment',
        'viteManifestChecksum',
        'composerLockChecksum',
        'npmLockChecksum',
        'protectedStorefrontManifestChecksum',
        'factoryProtectionManifestChecksum',
        'approvedBaselineRootChecksum',
        'routeCount',
        'permissionCount',
        'roleCount',
        'schedulerCount',
        'viewports',
        'readiness',
        'motionNormalizationChecksum',
        'visualNormalizationChecksum',
        'browserLaunchArgsChecksum',
        'staticRouterNormalizationChecksum',
        'decorativeMediaAllowlistChecksum',
        'browserProfileIsolation',
        'isolatedDatabaseProcedureChecksum',
        'stages',
    ];
    const changed = keys.filter((key) => JSON.stringify(manifest[key]) !== JSON.stringify(current[key]));
    if (JSON.stringify(manifest.willy) !== JSON.stringify(current.willy)) changed.push('willy');
    const ports = Object.values(manifest.selectedPorts ?? {}).flatMap((value) => Object.values(value));
    if (
        ports.length !== stages.length * 2
        || new Set(ports).size !== ports.length
        || ports.some((port) => !Number.isInteger(port) || port < 1 || port > 65_535)
    ) changed.push('selectedPorts');
    const expectedProfileRoots = Object.fromEntries(stages.map((stage) => [
        stage,
        relative(
            root,
            join(root, 'storage', 'app', 'test-runtime', 'browser', manifest.runId, stage, 'playwright-profile-root'),
        ).replaceAll('\\', '/'),
    ]));
    if (JSON.stringify(manifest.browserProfileRoots) !== JSON.stringify(expectedProfileRoots)) {
        changed.push('browserProfileRoots');
    }
    if (changed.length) throw new Error(`Immutable run manifest changed: ${changed.join(', ')}`);
}

async function appendJournal(manifest, entry) {
    const runRoot = join(evidenceBase, manifest.runId);
    const path = join(runRoot, 'orchestration-journal.json');
    const lockPath = join(runRoot, '.orchestration-journal-lock');
    const deadline = Date.now() + 30_000;
    while (true) {
        try {
            await mkdir(lockPath);
            await writeJson(join(lockPath, 'owner.json'), {
                runId: manifest.runId,
                pid: process.pid,
                acquiredAt: new Date().toISOString(),
            }, { flag: 'wx' });
            break;
        } catch (error) {
            const metadata = await stat(lockPath).catch(() => null);
            if (metadata && Date.now() - metadata.mtimeMs > 60_000) {
                const stalePath = `${lockPath}.stale.${Date.now()}.${process.pid}`;
                await rename(lockPath, stalePath).catch(() => {});
                continue;
            }
            if (Date.now() >= deadline) {
                const lockError = new Error(
                    `Timed out acquiring orchestration journal lock: ${error.message}`,
                );
                lockError.code = 'orchestration-journal-lock-timeout';
                throw lockError;
            }
            await new Promise((resolveWait) => setTimeout(resolveWait, 50));
        }
    }
    try {
        const journal = JSON.parse(await readFile(path, 'utf8'));
        journal.push(entry);
        await writeJsonAtomic(path, journal);
    } finally {
        await rm(lockPath, { recursive: true, force: true }).catch(() => {});
    }
}

function parseWindowsProcessSnapshot(output) {
    const lines = output
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
    const headerIndex = lines.findIndex((line) =>
        line.includes('CreationDate')
        && line.includes('ParentProcessId')
        && line.includes('ProcessId'));
    if (headerIndex < 0) throw new Error('WMIC process snapshot omitted its CSV header.');
    const headers = lines[headerIndex].split(',');
    const index = Object.fromEntries(headers.map((header, position) => [header, position]));
    const rows = new Map();
    for (const line of lines.slice(headerIndex + 1)) {
        const values = line.split(',');
        const pid = Number(values[index.ProcessId]);
        const parentPid = Number(values[index.ParentProcessId]);
        if (!Number.isInteger(pid) || !Number.isInteger(parentPid)) continue;
        rows.set(pid, {
            pid,
            parentPid,
            creationTime: values[index.CreationDate] || null,
            executableName: values[index.Name] || null,
        });
    }
    return rows;
}

function parseWindowsCimSnapshot(output) {
    const decoded = JSON.parse(output || '[]');
    const items = Array.isArray(decoded) ? decoded : [decoded];
    const rows = new Map();
    for (const item of items) {
        const pid = Number(item.ProcessId);
        const parentPid = Number(item.ParentProcessId);
        if (!Number.isInteger(pid) || !Number.isInteger(parentPid)) continue;
        rows.set(pid, {
            pid,
            parentPid,
            creationTime: item.CreationDate || null,
            executableName: item.Name || null,
        });
    }
    return rows;
}

function processSnapshot() {
    if (process.platform === 'win32') {
        try {
            return {
                provider: 'wmic-win32-process-creation-identity',
                rows: parseWindowsProcessSnapshot(run(
                    'wmic.exe',
                    ['process', 'get', 'ProcessId,ParentProcessId,CreationDate,Name', '/format:csv'],
                    { timeout: 20_000 },
                )),
            };
        } catch (wmicError) {
            const script = [
                'Get-CimInstance Win32_Process',
                '| Select-Object ProcessId,ParentProcessId,CreationDate,Name',
                '| ConvertTo-Json -Compress',
            ].join(' ');
            try {
                return {
                    provider: 'powershell-cim-win32-process-creation-identity',
                    rows: parseWindowsCimSnapshot(run(
                        'powershell.exe',
                        ['-NoProfile', '-Command', script],
                        { timeout: 30_000 },
                    )),
                };
            } catch (cimError) {
                const error = new Error(
                    `Windows process snapshot failed via WMIC (${wmicError.message}) and CIM (${cimError.message}).`,
                );
                error.cause = { wmic: wmicError.message, cim: cimError.message };
                throw error;
            }
        }
    }
    const rows = new Map();
    const output = run('ps', ['-eo', 'pid=,ppid=,lstart=,comm='], { timeout: 20_000 });
    for (const line of output.split(/\r?\n/)) {
        const match = line.match(
            /^\s*(\d+)\s+(\d+)\s+(\S+\s+\S+\s+\d+\s+\d{2}:\d{2}:\d{2}\s+\d{4})\s+(.+?)\s*$/,
        );
        if (!match) continue;
        const pid = Number(match[1]);
        rows.set(pid, {
            pid,
            parentPid: Number(match[2]),
            creationTime: match[3],
            executableName: match[4],
        });
    }
    return {
        provider: 'ps-pid-lstart-creation-identity',
        rows,
    };
}

function identityMatches(record, row) {
    if (!row || record.pid !== row.pid) return false;
    if (record.creationTime) {
        const recordedCreation = normalizeProcessCreationTime(record.creationTime);
        const currentCreation = normalizeProcessCreationTime(row.creationTime);
        return Boolean(recordedCreation && currentCreation && recordedCreation === currentCreation);
    }
    if (record.observed === true && record.executableName) {
        return normalizeExecutableName(record.executableName)
            === normalizeExecutableName(row.executableName);
    }
    return false;
}

function createOwnedProcessMonitor(stage, rootPid) {
    const records = new Map();
    const snapshotErrors = [];
    let provider = process.platform === 'win32'
        ? 'wmic-win32-process-creation-identity'
        : 'ps-pid-lstart-creation-identity';
    let latestRows = new Map();
    let successfulSamples = 0;
    let sampling = false;
    let stopped = false;

    const observe = (row, source, descendantOfPid = null) => {
        const identityMaterial = [
            row.pid,
            normalizeProcessCreationTime(row.creationTime)
                ?? 'creation-time-unavailable',
            normalizeExecutableName(row.executableName)
                ?? 'executable-name-unavailable',
        ].join('|');
        const identity = sha256(identityMaterial);
        let record = records.get(identity);
        if (!record) {
            record = {
                identity,
                pid: row.pid,
                parentPid: row.parentPid,
                creationTime: row.creationTime,
                executableName: row.executableName,
                identityBasis: row.creationTime
                    ? 'pid-plus-windows-creation-date'
                    : 'pid-plus-parent-and-executable-observation',
                observed: true,
                descendantOfPid,
                firstObservedAt: new Date().toISOString(),
                lastObservedAt: new Date().toISOString(),
                sources: [],
            };
            records.set(identity, record);
        }
        record.lastObservedAt = new Date().toISOString();
        if (!record.sources.includes(source)) record.sources.push(source);
        if (record.descendantOfPid === null && descendantOfPid !== null) {
            record.descendantOfPid = descendantOfPid;
        }
        return record;
    };

    const sample = async (reason) => {
        if (sampling || stopped) return false;
        sampling = true;
        try {
            const snapshot = processSnapshot();
            provider = snapshot.provider;
            latestRows = snapshot.rows;
            successfulSamples += 1;
            const activeOwnedPids = new Set();
            const rootRow = Number.isInteger(rootPid) ? latestRows.get(rootPid) : null;
            if (rootRow) {
                observe(rootRow, `orchestrator:${stage}:root:${reason}`, null);
                activeOwnedPids.add(rootPid);
            }
            for (const record of records.values()) {
                if (identityMatches(record, latestRows.get(record.pid))) {
                    activeOwnedPids.add(record.pid);
                }
            }
            let changed = true;
            while (changed) {
                changed = false;
                for (const row of latestRows.values()) {
                    if (!activeOwnedPids.has(row.parentPid) || activeOwnedPids.has(row.pid)) continue;
                    activeOwnedPids.add(row.pid);
                    observe(row, `orchestrator:${stage}:descendant:${reason}`, row.parentPid);
                    changed = true;
                }
            }
            return true;
        } catch (error) {
            snapshotErrors.push({
                at: new Date().toISOString(),
                reason,
                message: error.message,
            });
            return false;
        } finally {
            sampling = false;
        }
    };

    const samplingIntervalMs = process.platform === 'win32' ? 1_000 : 500;
    const interval = setInterval(() => {
        void sample('interval');
    }, samplingIntervalMs);
    interval.unref();

    const registerDeclaredPids = async (declared) => {
        await sample('stage-result-registration');
        for (const [pid, sources] of declared) {
            const matches = [...records.values()].filter((record) => record.pid === pid);
            if (matches.length) {
                for (const record of matches) {
                    for (const source of sources) {
                        if (!record.sources.includes(source)) record.sources.push(source);
                    }
                }
                continue;
            }
            const current = latestRows.get(pid);
            const identity = sha256(`${pid}|declared-after-exit|${stage}`);
            records.set(identity, {
                identity,
                pid,
                parentPid: current?.parentPid ?? null,
                creationTime: null,
                executableName: current?.executableName ?? null,
                identityBasis: current
                    ? 'declared-pid-currently-reused-or-unproven'
                    : 'declared-pid-absent-before-identity-observation',
                observed: false,
                descendantOfPid: null,
                firstObservedAt: null,
                lastObservedAt: null,
                sources: [...sources],
            });
        }
    };

    const activeOwnedRecords = async (reason) => {
        await sample(reason);
        return [...records.values()]
            .filter((record) => record.observed && identityMatches(record, latestRows.get(record.pid)))
            .map((record) => ({
                ...record,
                sources: [...record.sources],
            }));
    };

    const currentSurvivors = () => {
        const survivors = [];
        for (const record of records.values()) {
            const current = latestRows.get(record.pid);
            const alive = record.observed
                ? identityMatches(record, current)
                : Boolean(current);
            if (alive) {
                survivors.push({
                    identity: record.identity,
                    pid: record.pid,
                    creationTime: record.creationTime,
                    currentCreationTime: current?.creationTime ?? null,
                    executableName: record.executableName,
                    reason: record.observed
                        ? 'recorded-owned-identity-still-alive'
                        : 'declared-owned-pid-is-present-without-capturable-creation-identity',
                });
            }
        }
        return survivors;
    };

    const waitForAbsence = async (timeoutMs) => {
        const started = Date.now();
        do {
            await sample('post-exit-absence-proof');
            if (currentSurvivors().length === 0) return true;
            await new Promise((resolveWait) => setTimeout(resolveWait, 250));
        } while (Date.now() - started < timeoutMs);
        await sample('post-exit-absence-proof-final');
        return currentSurvivors().length === 0;
    };

    const finalize = async (absenceTimeoutMs) => {
        const absenceConfirmed = await waitForAbsence(absenceTimeoutMs);
        clearInterval(interval);
        await sample('final-proof');
        stopped = true;
        const survivors = currentSurvivors();
        const serialized = [...records.values()]
            .map((record) => ({
                ...record,
                sources: [...record.sources].sort(),
                absentAtProof: !survivors.some((item) => item.identity === record.identity),
            }))
            .sort((left, right) => left.pid - right.pid || left.identity.localeCompare(right.identity));
        const rootObserved = serialized.some((record) => record.pid === rootPid && record.observed);
        return {
            stage,
            rootPid,
            provider,
            samplingIntervalMs,
            successfulSamples,
            snapshotErrors,
            rootIdentityObserved: rootObserved,
            recordedProcessCount: serialized.length,
            recordedOwnedProcesses: serialized,
            survivors,
            absenceConfirmed,
            rootProcessExited: !serialized.some((record) =>
                record.pid === rootPid && !record.absentAtProof),
            passed: Boolean(
                successfulSamples > 0
                && rootObserved
                && absenceConfirmed
                && survivors.length === 0
            ),
            provedAt: new Date().toISOString(),
        };
    };

    return {
        sample,
        registerDeclaredPids,
        activeOwnedRecords,
        finalize,
    };
}

function recordedStagePids(stageResult, childPid) {
    const declared = new Map();
    const add = (pid, source) => {
        if (!Number.isInteger(pid) || pid <= 0) return;
        if (!declared.has(pid)) declared.set(pid, []);
        if (!declared.get(pid).includes(source)) declared.get(pid).push(source);
    };
    add(childPid, 'orchestrator-child-pid');
    for (const [index, item] of (stageResult?.processInventory ?? []).entries()) {
        add(item?.pid, `stage-result.processInventory[${index}]`);
    }
    for (const [index, item] of (stageResult?.cleanup ?? []).entries()) {
        add(item?.pid, `stage-result.cleanup[${index}]`);
    }
    for (const [index, item] of (stageResult?.payload?.fidelity?.captures ?? []).entries()) {
        add(item?.browserPid, `stage-result.payload.fidelity.captures[${index}].browserPid`);
    }
    return declared;
}

async function awaitChildExit(child, timeoutMs) {
    if (!child || child.exitCode !== null || child.signalCode !== null) {
        return {
            exited: true,
            exitCode: child?.exitCode ?? null,
            signalCode: child?.signalCode ?? null,
            spawnError: null,
        };
    }
    return await new Promise((resolveExit) => {
        const finish = (value) => {
            clearTimeout(timer);
            child.removeListener('exit', onExit);
            child.removeListener('error', onError);
            resolveExit(value);
        };
        const onExit = (exitCode, signalCode) => finish({
            exited: true,
            exitCode,
            signalCode,
            spawnError: null,
        });
        const onError = (error) => finish({
            exited: true,
            exitCode: 1,
            signalCode: null,
            spawnError: error.message,
        });
        const timer = setTimeout(() => finish({
            exited: false,
            exitCode: child.exitCode,
            signalCode: child.signalCode,
            spawnError: null,
        }), timeoutMs);
        child.once('exit', onExit);
        child.once('error', onError);
    });
}

function terminateExactTree(record) {
    if (!record?.observed || !Number.isInteger(record.pid) || record.pid <= 0) {
        return {
            issued: false,
            identityConfirmed: false,
            error: 'missing-observed-owned-process-identity',
        };
    }
    let snapshot;
    try {
        snapshot = processSnapshot();
    } catch (error) {
        return {
            issued: false,
            identityConfirmed: false,
            identity: record.identity,
            pid: record.pid,
            error: `pre-termination process snapshot failed: ${error.message}`,
        };
    }
    const current = snapshot.rows.get(record.pid);
    const identityConfirmed = identityMatches(record, current);
    if (!identityConfirmed) {
        return {
            issued: false,
            identityConfirmed: false,
            identity: record.identity,
            pid: record.pid,
            expectedCreationTime: record.creationTime,
            currentCreationTime: current?.creationTime ?? null,
            expectedExecutableName: record.executableName,
            currentExecutableName: current?.executableName ?? null,
            error: 'owned-process creation identity was absent or changed before termination',
        };
    }
    if (process.platform === 'win32') {
        try {
            execFileSync('taskkill', ['/PID', String(record.pid), '/T', '/F'], {
                windowsHide: true,
                stdio: 'ignore',
                timeout: 30_000,
            });
            return {
                issued: true,
                identityConfirmed: true,
                identity: record.identity,
                pid: record.pid,
                creationTime: record.creationTime,
                command: 'taskkill /PID <creation-identified-owned> /T /F',
                error: null,
            };
        } catch (error) {
            return {
                issued: false,
                identityConfirmed: true,
                identity: record.identity,
                pid: record.pid,
                creationTime: record.creationTime,
                command: 'taskkill /PID <creation-identified-owned> /T /F',
                error: error.message,
            };
        }
    }
    try {
        process.kill(record.pid, 'SIGKILL');
        return {
            issued: true,
            identityConfirmed: true,
            identity: record.identity,
            pid: record.pid,
            command: 'SIGKILL exact observed owned root',
            error: null,
        };
    } catch (error) {
        return {
            issued: false,
            identityConfirmed: true,
            identity: record.identity,
            pid: record.pid,
            command: 'SIGKILL exact observed owned root',
            error: error.message,
        };
    }
}

async function executeStage(stage, manifest, manifestChecksum) {
    await verifyImmutable(manifest, manifestChecksum);
    const startedAt = new Date().toISOString();
    const directory = join(evidenceBase, manifest.runId, stage);
    const child = spawn(process.execPath, [join(root, 'scripts', 'evidence', 'be6a1-browser.mjs')], {
        cwd: root,
        env: {
            ...process.env,
            BE6A1_RUN_ID: manifest.runId,
            BE6A1_STAGE: stage,
            BE6A1_MANIFEST_CHECKSUM: manifestChecksum,
        },
        windowsHide: true,
        stdio: 'inherit',
    });
    const processMonitor = createOwnedProcessMonitor(stage, child.pid);
    await processMonitor.sample('immediately-after-spawn');
    const timeoutMs = stage === 'visual' ? 7_200_000 : 600_000;
    let exit = await awaitChildExit(child, timeoutMs);
    let timedOut = false;
    let forcedTreeTermination = false;
    let termination = null;
    if (!exit.exited) {
        timedOut = true;
        forcedTreeTermination = true;
        const activeAtTimeout = await processMonitor.activeOwnedRecords('timeout-before-taskkill');
        const rootRecord = activeAtTimeout.find((record) => record.pid === child.pid) ?? null;
        termination = {
            ...terminateExactTree(rootRecord),
            requestedAt: new Date().toISOString(),
            rootIdentity: rootRecord ? {
                identity: rootRecord.identity,
                pid: rootRecord.pid,
                creationTime: rootRecord.creationTime,
                executableName: rootRecord.executableName,
            } : null,
            descendantTerminationAttempts: [],
        };
        exit = await awaitChildExit(child, 5_000);
        const activeAfterRootTermination = await processMonitor.activeOwnedRecords(
            'timeout-after-root-taskkill',
        );
        for (const record of activeAfterRootTermination) {
            if (record.pid === child.pid) continue;
            termination.descendantTerminationAttempts.push(terminateExactTree(record));
        }
        if (!exit.exited) exit = await awaitChildExit(child, 25_000);
        termination.childExitConfirmed = exit.exited;
        termination.childExitCode = exit.exitCode;
        termination.childSignalCode = exit.signalCode;
    }

    let immutableAfterPassed = true;
    let immutableAfterError = null;
    try {
        await verifyImmutable(manifest, manifestChecksum);
    } catch (error) {
        immutableAfterPassed = false;
        immutableAfterError = error.message;
    }
    const resultPath = join(directory, 'stage-result.json');
    let stageResult = null;
    let resultManifestPresent = true;
    try {
        stageResult = JSON.parse(await readFile(resultPath, 'utf8'));
    } catch {
        resultManifestPresent = false;
    }
    await processMonitor.registerDeclaredPids(recordedStagePids(stageResult, child.pid));
    const activeAfterChildExit = await processMonitor.activeOwnedRecords(
        'post-child-exit-owned-descendant-cleanup',
    );
    const postExitTerminationAttempts = activeAfterChildExit
        .filter((record) => record.pid !== child.pid || exit.exited)
        .map((record) => terminateExactTree(record));
    if (postExitTerminationAttempts.some((attempt) => attempt.issued)) {
        forcedTreeTermination = true;
    }
    const processProof = await processMonitor.finalize(
        timedOut || postExitTerminationAttempts.length > 0 ? 30_000 : 10_000,
    );
    const postExitTermination = {
        attempted: postExitTerminationAttempts.length > 0,
        attempts: postExitTerminationAttempts,
        descendantAbsenceConfirmed: processProof.absenceConfirmed,
        survivors: processProof.survivors,
        passed: processProof.absenceConfirmed && processProof.survivors.length === 0,
    };
    if (termination) {
        termination.descendantAbsenceConfirmed = processProof.absenceConfirmed;
        termination.confirmed = Boolean(
            termination.issued
            && termination.identityConfirmed
            && termination.childExitConfirmed
            && termination.descendantAbsenceConfirmed
            && processProof.survivors.length === 0
        );
    }

    const completionPath = join(directory, 'stage-complete.json');
    const completion = await readFile(completionPath, 'utf8')
        .then(JSON.parse)
        .catch(() => null);
    const resultChecksum = resultManifestPresent ? await fileHash(resultPath) : null;
    const completionMarkerPassed = Boolean(
        completion
        && completion.runId === manifest.runId
        && completion.stage === stage
        && completion.stageResultChecksum === resultChecksum
        && completion.passed === stageResult?.passed
    );
    const runnerFailurePresent = await pathExists(join(directory, 'runner-failure.json'));
    const childExited = Boolean(exit.exited && processProof.rootProcessExited);
    const processExitMarker = {
        runId: manifest.runId,
        stage,
        rootManifestChecksum: manifestChecksum,
        childPid: child.pid,
        childExited,
        exitCode: timedOut ? 124 : (exit.exitCode ?? 1),
        signal: exit.signalCode ?? null,
        timedOut,
        forcedTreeTermination,
        termination,
        postExitTermination,
        processProof,
        passed: Boolean(childExited && processProof.passed && !timedOut),
        completedAt: new Date().toISOString(),
    };
    await mkdir(directory, { recursive: true });
    const processExitMarkerPath = join(directory, 'stage-process-exit.json');
    await writeJson(processExitMarkerPath, processExitMarker);
    if (!timedOut && childExited) {
        await rm(join(directory, 'stage-in-progress.json'), { force: true });
    }
    const inProgressMarkerAbsent = !await pathExists(join(directory, 'stage-in-progress.json'));
    const processExitMarkerChecksum = await fileHash(processExitMarkerPath);
    const exitCode = timedOut ? 124 : (exit.exitCode ?? 1);
    const orchestrationResult = {
        runId: manifest.runId,
        stage,
        childPid: child.pid,
        startedAt,
        finishedAt: new Date().toISOString(),
        timeoutMs,
        exitCode,
        signal: exit.signalCode ?? null,
        spawnError: exit.spawnError,
        timedOut,
        forcedTreeTermination,
        termination,
        postExitTermination,
        resultManifestPresent,
        resultChecksum,
        completionMarkerPassed,
        processExitMarkerChecksum,
        processProof,
        inProgressMarkerAbsent,
        runnerFailurePresent,
        immutableAfterPassed,
        immutableAfterError,
        passed: Boolean(
            exitCode === 0
            && !timedOut
            && resultManifestPresent
            && stageResult?.runId === manifest.runId
            && stageResult?.stage === stage
            && stageResult?.passed === true
            && completion?.passed === true
            && completionMarkerPassed
            && processExitMarker.passed
            && inProgressMarkerAbsent
            && !runnerFailurePresent
            && immutableAfterPassed
            && stageResult?.rootManifestChecksum === manifestChecksum
        ),
    };
    await writeJson(join(directory, 'orchestration-result.json'), orchestrationResult);
    await appendJournal(manifest, orchestrationResult);
    if (!orchestrationResult.passed) {
        throw new Error(
            `${stage} failed (exit ${exitCode}, timeout ${timedOut}, result ${resultManifestPresent}, completion ${completionMarkerPassed}, process ${processProof.passed}, immutable ${immutableAfterPassed})`,
        );
    }
    if (stage === 'verify') {
        await finalizeRun(manifest, manifestChecksum, stageResult);
    }
    return orchestrationResult;
}

async function profileReleaseEvidence(manifest) {
    const results = [];
    for (const [stage, portablePath] of Object.entries(manifest.browserProfileRoots)) {
        const path = resolve(root, portablePath);
        const released = await access(path, fsConstants.F_OK)
            .then(() => false)
            .catch(() => true);
        results.push({ stage, path: portablePath, released });
    }
    return {
        passed: results.every((item) => item.released),
        profiles: results,
    };
}

async function candidateFileChecksums(temporaryRoot) {
    const rows = {};
    for (const path of (await allFiles(temporaryRoot)).sort()) {
        const portablePath = relative(temporaryRoot, path).replaceAll('\\', '/');
        if (portablePath === 'manifests/candidate-manifest.json') continue;
        rows[portablePath] = await fileHash(path);
    }
    return rows;
}

function candidatePackageChecksum(fileChecksums) {
    return sha256(Object.entries(fileChecksums)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([path, checksum]) => `${path}:${checksum}`)
        .join('\n'));
}

async function buildCandidate(manifest, aggregate, finalizationProof) {
    const temporaryRoot = join(
        root,
        'storage',
        'app',
        'evidence',
        `be6a1-baseline-candidate.${manifest.runId}.tmp`,
    );
    const runRoot = join(evidenceBase, manifest.runId);
    await rm(temporaryRoot, { recursive: true, force: true });
    await Promise.all([
        mkdir(join(temporaryRoot, 'manifests', 'protected-source'), { recursive: true }),
        mkdir(join(temporaryRoot, 'results', 'orchestration'), { recursive: true }),
        mkdir(join(temporaryRoot, 'results'), { recursive: true }),
        mkdir(join(temporaryRoot, 'screenshots'), { recursive: true }),
        mkdir(join(temporaryRoot, 'comparisons'), { recursive: true }),
    ]);
    await Promise.all([
        cp(join(runRoot, 'visual', 'screenshots'), join(temporaryRoot, 'screenshots'), { recursive: true }),
        cp(join(runRoot, 'visual', 'comparisons'), join(temporaryRoot, 'comparisons'), { recursive: true }),
        cp(join(root, 'docs', 'phase-0', 'template-sha256.txt'), join(temporaryRoot, 'manifests', 'protected-source', 'template-sha256.txt')),
        cp(join(root, 'scripts', 'validation', 'protected-php-baseline.json'), join(temporaryRoot, 'manifests', 'protected-source', 'protected-php-baseline.json')),
        cp(join(runRoot, 'run-manifest.json'), join(temporaryRoot, 'manifests', 'run-manifest.json')),
        cp(join(runRoot, 'run-manifest.sha256'), join(temporaryRoot, 'manifests', 'run-manifest.sha256')),
        cp(join(runRoot, 'orchestration-journal.json'), join(temporaryRoot, 'manifests', 'orchestration-journal.json')),
        cp(join(runRoot, 'verify'), join(temporaryRoot, 'results', 'verify'), { recursive: true }),
    ]);
    const resultNames = [
        'mime-preflight.json',
        'semantic-readiness-summary.json',
        'visual-stability-summary.json',
        'fidelity-repeatability-summary.json',
        'static-reference-checksums.json',
        'laravel-capture-checksums.json',
        'fidelity-diff-summary.json',
        'console-network-summary.json',
        'stage-result.json',
        'stage-complete.json',
        'stage-process-exit.json',
        'orchestration-result.json',
        'cleanup-result.json',
        'lifecycle-events.json',
    ];
    for (const name of resultNames) {
        await cp(join(runRoot, 'visual', name), join(temporaryRoot, 'results', name));
    }
    for (const stage of ['security', 'preview']) {
        await cp(join(runRoot, stage), join(temporaryRoot, 'results', stage), { recursive: true });
    }
    for (const stage of ['prepare', ...stages]) {
        for (const name of stage === 'prepare'
            ? ['stage-result.json', 'stage-complete.json']
            : ['stage-result.json', 'stage-complete.json', 'stage-process-exit.json', 'orchestration-result.json']) {
            await cp(
                join(runRoot, stage, name),
                join(temporaryRoot, 'results', 'orchestration', `${stage}-${name}`),
            );
        }
    }
    await writeJson(join(temporaryRoot, 'manifests', 'browser-manifest.json'), {
        playwrightVersion: manifest.playwrightVersion,
        chromiumVersion: manifest.chromiumVersion,
        launchArgsChecksum: manifest.browserLaunchArgsChecksum,
        launchArgs: BROWSER_LAUNCH_ARGS,
        motionNormalizationChecksum: manifest.motionNormalizationChecksum,
        visualNormalizationChecksum: manifest.visualNormalizationChecksum,
        visualNormalizationCss: VISUAL_NORMALIZATION_CSS,
        profileIsolation: manifest.browserProfileIsolation,
        staticRouterNormalizationChecksum: manifest.staticRouterNormalizationChecksum,
        staticRouterNormalization: STATIC_ROUTER_NORMALIZATION,
        decorativeMediaAllowlistChecksum: manifest.decorativeMediaAllowlistChecksum,
        decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST,
    });
    await writeJson(join(temporaryRoot, 'manifests', 'environment-manifest.json'), {
        runId: manifest.runId,
        rootManifestChecksum: finalizationProof.rootManifestChecksum,
        gitCommit: manifest.commit,
        workingTreeChecksum: manifest.workingTreeChecksum,
        workingTreeDiffChecksum: manifest.workingTreeDiffChecksum,
        nodeVersion: manifest.nodeVersion,
        phpVersion: manifest.phpVersion,
        composerLockChecksum: manifest.composerLockChecksum,
        npmLockChecksum: manifest.npmLockChecksum,
        viteManifestChecksum: manifest.viteManifestChecksum,
        protectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
        factoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
        staticRouterNormalizationChecksum: manifest.staticRouterNormalizationChecksum,
        decorativeMediaAllowlistChecksum: manifest.decorativeMediaAllowlistChecksum,
        motionNormalizationChecksum: manifest.motionNormalizationChecksum,
        visualNormalizationChecksum: manifest.visualNormalizationChecksum,
        approvedBaselineRootChecksum: manifest.approvedBaselineRootChecksum,
    });
    await writeJson(join(temporaryRoot, 'manifests', 'viewport-manifest.json'), manifest.viewports);
    await writeJson(join(temporaryRoot, 'manifests', 'finalization-proof.json'), finalizationProof);
    await writeFile(
        join(temporaryRoot, 'README.md'),
        [
            '# BE-6A.1 baseline candidates',
            '',
            '> Baseline candidates generated from the checksum-protected static source. Not yet formally approved.',
            '',
            `Run ID: \`${manifest.runId}\``,
            '',
            `Static-router normalization checksum: \`${manifest.staticRouterNormalizationChecksum}\``,
            `Decorative-media allowlist checksum: \`${manifest.decorativeMediaAllowlistChecksum}\``,
            `Motion-normalization checksum: \`${manifest.motionNormalizationChecksum}\``,
            `Visual-normalization checksum: \`${manifest.visualNormalizationChecksum}\``,
            `Approved immutable baseline root checksum: \`${manifest.approvedBaselineRootChecksum}\``,
            '',
            `Static-router normalization: \`${JSON.stringify(STATIC_ROUTER_NORMALIZATION)}\``,
            `Decorative-media allowlist: \`${JSON.stringify(DECORATIVE_MEDIA_ALLOWLIST)}\``,
            '',
            'These files are evidence candidates only. They were not copied to an approved baseline directory.',
            '',
        ].join('\n'),
    );
    await writeFile(
        join(temporaryRoot, 'ADMIN_HREF_EXCEPTION.md'),
        [
            '# Admin href non-visual exception',
            '',
            'The protected homepage source retains its imported `html/admin.html` href; other protected static pages may already use `/admin`.',
            'The Laravel public output uses the same-origin protected `/admin` destination, which may be serialized as an absolute URL.',
            'The fidelity record classifies this href-only security correction separately and does not alter the protected source.',
            'The static router does not expose `/admin`; only Laravel owns and protects that route.',
            '',
        ].join('\n'),
    );
    await writeFile(
        join(temporaryRoot, 'HUMAN_REVIEW_CHECKLIST.md'),
        [
            '# Human review checklist',
            '',
            '- [ ] Review all 102 page/viewport groups.',
            '- [ ] Review every non-zero deterministic subpixel classification.',
            '- [ ] Confirm the Admin href-only exception.',
            '- [ ] Confirm protected checksum manifests match the run manifest.',
            '- [ ] Record formal approval separately before BE-6A.2.',
            '',
            '**No item in this package is automatically approved.**',
            '',
        ].join('\n'),
    );
    const fileChecksums = await candidateFileChecksums(temporaryRoot);
    const packageChecksum = candidatePackageChecksum(fileChecksums);
    const candidateManifest = {
        runId: manifest.runId,
        approvalState: 'unapproved-baseline-candidate',
        candidateRootRole: 'unapproved-baseline-candidate',
        formalApprovalState: 'not-requested',
        approvedBaselineWrites: [],
        autoApprovalProhibited: true,
        source: 'checksum-protected-static-source-and-same-run-laravel-captures',
        rootManifestChecksum: finalizationProof.rootManifestChecksum,
        workingTreeChecksum: manifest.workingTreeChecksum,
        protectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
        factoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
        staticRouterNormalizationChecksum: manifest.staticRouterNormalizationChecksum,
        decorativeMediaAllowlistChecksum: manifest.decorativeMediaAllowlistChecksum,
        motionNormalizationChecksum: manifest.motionNormalizationChecksum,
        visualNormalizationChecksum: manifest.visualNormalizationChecksum,
        approvedBaselineRootChecksum: manifest.approvedBaselineRootChecksum,
        screenshotCount: aggregate.matrixTotals.captures,
        comparisonCount: aggregate.matrixTotals.comparisons,
        repeatabilityGroups: aggregate.repeatabilityTotals.present,
        fileCount: Object.keys(fileChecksums).length,
        fileChecksums,
        packageChecksum,
        createdAt: new Date().toISOString(),
    };
    const manifestPath = join(temporaryRoot, 'manifests', 'candidate-manifest.json');
    await writeJson(manifestPath, candidateManifest, { flag: 'wx' });
    const manifestChecksum = await fileHash(manifestPath);
    const validation = await validateBaselineCandidate({
        workspaceRoot: root,
        candidateRoot: temporaryRoot,
        expectedRunId: manifest.runId,
        expectedRootManifestChecksum: finalizationProof.rootManifestChecksum,
        expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
        expectedProtectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
        expectedFactoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
        approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
    });
    if (!validation.passed || validation.packageChecksum !== packageChecksum) {
        const error = new Error(
            `Temporary baseline candidate validation failed: ${validation.failures.map((item) => item.code).join(', ') || 'package-checksum-mismatch'}`,
        );
        error.code = 'candidate-preinstall-validation-failed';
        error.validation = validation;
        error.temporaryRoot = temporaryRoot;
        throw error;
    }
    return {
        temporaryRoot,
        candidateManifest,
        manifestChecksum,
        packageChecksum,
        preInstallValidation: validation,
    };
}

async function finalOwnedProcessProof(journal) {
    const stageProofPassed = stages.every((stage) => {
        const result = journal.find((item) => item.stage === stage);
        return result?.processProof?.passed === true;
    });
    let snapshot;
    try {
        snapshot = processSnapshot();
    } catch (error) {
        return {
            passed: false,
            stageProofPassed,
            provider: process.platform === 'win32'
                ? 'wmic-win32-process-creation-identity'
                : 'ps-pid-lstart-creation-identity',
            records: [],
            survivors: [],
            snapshotError: error.message,
            provedAt: new Date().toISOString(),
        };
    }
    const records = [];
    const survivors = [];
    for (const outcome of journal.filter((item) => stages.includes(item.stage))) {
        for (const record of outcome.processProof?.recordedOwnedProcesses ?? []) {
            const current = snapshot.rows.get(record.pid);
            const alive = record.observed
                ? identityMatches(record, current)
                : Boolean(current);
            const proof = {
                stage: outcome.stage,
                identity: record.identity,
                pid: record.pid,
                creationTime: record.creationTime,
                executableName: record.executableName,
                absent: !alive,
            };
            records.push(proof);
            if (alive) {
                survivors.push({
                    ...proof,
                    currentCreationTime: current?.creationTime ?? null,
                    currentExecutableName: current?.executableName ?? null,
                });
            }
        }
    }
    records.sort((left, right) =>
        left.stage.localeCompare(right.stage)
        || left.pid - right.pid
        || left.identity.localeCompare(right.identity));
    return {
        passed: stageProofPassed && records.length >= stages.length && survivors.length === 0,
        stageProofPassed,
        provider: snapshot.provider,
        records,
        survivors,
        snapshotError: null,
        provedAt: new Date().toISOString(),
    };
}

async function stageMarkerEvidence(manifest) {
    const runRoot = join(evidenceBase, manifest.runId);
    const results = [];
    for (const stage of ['prepare', ...stages]) {
        const directory = join(runRoot, stage);
        const expected = stage === 'prepare'
            ? ['stage-complete.json', 'stage-result.json']
            : [
                'orchestration-result.json',
                'stage-complete.json',
                'stage-process-exit.json',
                'stage-result.json',
            ];
        const actual = (await readdir(directory).catch(() => []))
            .filter((name) => /^(?:stage|orchestration|runner)-.+\.json$/.test(name))
            .sort();
        const resultPath = join(directory, 'stage-result.json');
        const completePath = join(directory, 'stage-complete.json');
        const stageResult = await readFile(resultPath, 'utf8').then(JSON.parse).catch(() => null);
        const complete = await readFile(completePath, 'utf8').then(JSON.parse).catch(() => null);
        const resultChecksum = await fileHash(resultPath);
        const orchestration = stage === 'prepare'
            ? null
            : await readFile(join(directory, 'orchestration-result.json'), 'utf8')
                .then(JSON.parse)
                .catch(() => null);
        const exitMarkerPath = join(directory, 'stage-process-exit.json');
        const exitMarker = stage === 'prepare'
            ? null
            : await readFile(exitMarkerPath, 'utf8')
                .then(JSON.parse)
                .catch(() => null);
        const exitMarkerChecksum = stage === 'prepare' ? null : await fileHash(exitMarkerPath);
        const exactMarkers = JSON.stringify(actual) === JSON.stringify(expected);
        const passed = Boolean(
            exactMarkers
            && stageResult?.runId === manifest.runId
            && stageResult?.stage === stage
            && stageResult?.passed === true
            && complete?.runId === manifest.runId
            && complete?.stage === stage
            && complete?.passed === stageResult.passed
            && complete?.stageResultChecksum === resultChecksum
            && (
                stage === 'prepare'
                || (
                    orchestration?.runId === manifest.runId
                    && orchestration?.stage === stage
                    && orchestration?.passed === stageResult.passed
                    && orchestration?.completionMarkerPassed === true
                    && orchestration?.inProgressMarkerAbsent === true
                    && orchestration?.runnerFailurePresent === false
                    && orchestration?.processProof?.passed === true
                    && orchestration?.processExitMarkerChecksum === exitMarkerChecksum
                    && exitMarker?.runId === manifest.runId
                    && exitMarker?.stage === stage
                    && exitMarker?.rootManifestChecksum === await fileHash(
                        join(runRoot, 'run-manifest.json'),
                    )
                    && exitMarker?.childPid === orchestration.childPid
                    && exitMarker?.processProof?.rootPid === orchestration.childPid
                    && exitMarker?.passed === true
                    && exitMarker?.processProof?.passed === true
                    && JSON.stringify(orchestration.processProof) === JSON.stringify(exitMarker.processProof)
                )
            )
        );
        results.push({
            stage,
            expected,
            actual,
            exactMarkers,
            stageResultChecksum: resultChecksum,
            completeStageResultChecksum: complete?.stageResultChecksum ?? null,
            passed,
        });
    }
    return {
        passed: results.length === stages.length + 1 && results.every((item) => item.passed),
        stages: results,
        inProgressMarkers: results.flatMap((item) =>
            item.actual.includes('stage-in-progress.json') ? [item.stage] : []),
        failureMarkers: results.flatMap((item) =>
            item.actual.includes('runner-failure.json') ? [item.stage] : []),
    };
}

function openAggregate(base, blocker, finalization = {}) {
    const aggregate = {
        ...base,
        passed: false,
        remainingBlocker: blocker,
        closureRecommendation: 'BE-6A.1 REMAINS OPEN.',
        finalizationState: 'open',
        candidateFinalization: {
            passed: false,
            ...finalization,
        },
    };
    delete aggregate.baselineCandidate;
    delete aggregate.behavioralAggregateValidation;
    delete aggregate.finalizationTransaction;
    return aggregate;
}

async function readJsonOptional(path) {
    return await readFile(path, 'utf8')
        .then(JSON.parse)
        .catch(() => null);
}

async function persistOpenRunFailure(manifest, manifestChecksum, error, failedStage) {
    const runRoot = join(evidenceBase, manifest.runId);
    const finalPath = join(runRoot, 'final-result.json');
    const existing = await readJsonOptional(finalPath) ?? {};
    const journal = await readJsonOptional(join(runRoot, 'orchestration-journal.json')) ?? [];
    const stageResults = {};
    const cleanupResults = {};
    for (const stage of ['prepare', ...stages]) {
        const result = await readJsonOptional(join(runRoot, stage, 'stage-result.json'));
        if (!result) continue;
        stageResults[stage] = result;
        if (stage !== 'prepare') cleanupResults[stage] = result.cleanup ?? [];
    }
    const markerEvidence = await stageMarkerEvidence(manifest);
    const failure = {
        stage: failedStage,
        code: error.code ?? 'stage-orchestration-failed',
        message: error.message,
        recordedAt: new Date().toISOString(),
    };
    const existingFinalizationFailed = existing.candidateFinalization?.phase === 'failed';
    const blocker = existingFinalizationFailed
        ? existing.remainingBlocker
        : `Canonical orchestration failed at ${failedStage ?? 'unknown'}: ${error.message}`;
    const aggregate = openAggregate({
        ...existing,
        runId: manifest.runId,
        rootManifestChecksum: manifestChecksum,
        stages: stageResults,
        cleanupResults,
        orchestrationOutcomes: journal,
        stageMarkerEvidence: markerEvidence,
        orchestrationFailure: failure,
    }, blocker, existingFinalizationFailed
        ? existing.candidateFinalization
        : {
            phase: 'stage-failure',
            failureCode: failure.code,
            failureMessage: failure.message,
            approvedBaselineWrites: [],
            formalApprovalState: 'not-requested',
        });
    await writeJsonAtomic(finalPath, aggregate);
    return aggregate;
}

async function validateCanonicalAggregate(
    manifest,
    runRoot,
    aggregatePath,
    finalizationMode = 'closed',
) {
    return await validateEvidenceAggregate({
        workspaceRoot: root,
        runRoot,
        aggregatePath,
        expectedRunId: manifest.runId,
        expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
        approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
        finalizationMode,
    });
}

async function validateTransactionCanonicalAggregate(state) {
    const transactionRunRoot = join(evidenceBase, state.runId);
    const transactionManifest = JSON.parse(
        await readFile(join(transactionRunRoot, 'run-manifest.json'), 'utf8'),
    );
    return await validateCanonicalAggregate(
        transactionManifest,
        transactionRunRoot,
        resolve(root, state.paths.finalPath),
        'commitpoint',
    );
}

async function finalizeRun(manifest, manifestChecksum, verifyStageResult) {
    const runRoot = join(evidenceBase, manifest.runId);
    const finalPath = join(runRoot, 'final-result.json');
    const pendingFinalPath = join(runRoot, 'final-result.pending.json');
    const portEvidence = await probeSelectedPorts(manifest.selectedPorts);
    const profileEvidence = await profileReleaseEvidence(manifest);
    const stageResults = {};
    const cleanupResults = {};
    let ownedCleanupPassed = true;
    for (const stage of stages) {
        const result = JSON.parse(await readFile(join(runRoot, stage, 'stage-result.json'), 'utf8'));
        stageResults[stage] = result;
        cleanupResults[stage] = result.cleanup ?? [];
        if ((result.cleanup ?? []).some((item) => item.passed === false)) ownedCleanupPassed = false;
    }
    const journal = JSON.parse(await readFile(join(runRoot, 'orchestration-journal.json'), 'utf8'));
    const processProof = await finalOwnedProcessProof(journal);
    const markerEvidence = await stageMarkerEvidence(manifest);
    const orphanProcessResult = {
        passed: Boolean(
            portEvidence.passed
            && profileEvidence.passed
            && ownedCleanupPassed
            && processProof.passed
        ),
        selectedPortsReleased: portEvidence.selectedPortsReleased,
        browserProfilesReleased: profileEvidence.profiles,
        ownedProcessCleanupPassed: ownedCleanupPassed,
        stageProcessProofPassed: processProof.stageProofPassed,
        recordedOwnedProcessProof: processProof,
        unrelatedProcessPolicy: 'Only exact stage ChildProcess roots and creation-identified observed descendants are monitored or eligible for termination.',
    };
    const verificationPayload = verifyStageResult.payload;
    const baseAggregate = {
        ...verificationPayload,
        runId: manifest.runId,
        rootManifestChecksum: manifestChecksum,
        stages: stageResults,
        cleanupResults,
        orchestrationOutcomes: journal,
        orphanProcessResult,
        stageMarkerEvidence: markerEvidence,
    };
    const preconditionsPassed = Boolean(
        verificationPayload.passed
        && orphanProcessResult.passed
        && markerEvidence.passed
    );
    const preconditionBlocker = [
        verificationPayload.remainingBlocker,
        orphanProcessResult.passed ? null : 'Post-exit owned-process, port, profile, or cleanup proof failed.',
        markerEvidence.passed ? null : 'Exact stage completion/process-exit marker proof failed.',
    ].filter(Boolean).join('; ') || 'Finalization transaction has not completed.';
    const aggregateValidationPath = join(runRoot, 'final-result.validation.json');
    let built = null;
    let transaction = null;
    let lock = null;
    let canonicalCommitReached = false;
    const releaseCommittedPublication = async (
        currentState,
        priorValidationPasses,
        { recovered = false, recoveredFromError = null } = {},
    ) => {
        const acceptedPriorPasses = (priorValidationPasses ?? []).slice(0, 2);
        if (
            acceptedPriorPasses.length !== 2
            || acceptedPriorPasses.some((result) => result?.passed !== true)
        ) {
            const error = new Error(
                'Committed publication is missing its two accepted pre-publication validation passes.',
            );
            error.code = 'candidate-prepublication-proof-missing';
            error.canonicalCommitReached = true;
            throw error;
        }
        let acceptanceEvidence = null;
        const release = await candidateTransactions.releaseLock(
            lock,
            currentState,
            {
                beforeRemoval: async (terminalState) => {
                    const releasePreparedValidation = await validateCanonicalAggregate(
                        manifest,
                        runRoot,
                        finalPath,
                        'postcommit',
                    );
                    if (!releasePreparedValidation.passed) {
                        const error = new Error(
                            `Release-prepared aggregate validation failed: ${releasePreparedValidation.failures.map((item) => item.code).join(', ')}`,
                        );
                        error.code = 'candidate-release-prepared-validation-failed';
                        error.validation = releasePreparedValidation;
                        error.canonicalCommitReached = true;
                        throw error;
                    }
                    acceptanceEvidence = {
                        runId: manifest.runId,
                        aggregateChecksum: await fileHash(finalPath),
                        phase: 'canonical-final-acceptance-prepared',
                        transactionId: terminalState.transactionId,
                        transaction: terminalState,
                        recovered,
                        recoveredFromError,
                        validationPasses: [
                            ...acceptedPriorPasses,
                            releasePreparedValidation,
                        ],
                        releasePreparedValidation,
                        closureBoundary: {
                            candidateLockRemovalIsFinalMutation: true,
                            candidateLockPath: terminalState.paths.lockRoot,
                            noPostReleaseWrites: true,
                        },
                        passed: true,
                    };
                    await writeJsonAtomic(aggregateValidationPath, acceptanceEvidence);
                },
            },
        );
        transaction = release.state;
        lock = null;
        const closed = await candidateTransactions.validateClosed(transaction, {
            validateCanonicalFinal: async (state) => await validateCanonicalAggregate(
                manifest,
                runRoot,
                resolve(root, state.paths.finalPath),
                'postcommit',
            ),
        });
        if (!closed.passed) {
            const error = new Error(
                `Canonical finalization validation failed: ${closed.failures.join(', ')}`,
            );
            error.code = recovered
                ? 'candidate-recovered-closure-validation-failed'
                : 'candidate-postpublication-validation-failed';
            error.validation = closed;
            error.canonicalCommitReached = true;
            throw error;
        }
        const strictClosedValidation = await validateCanonicalAggregate(
            manifest,
            runRoot,
            finalPath,
            'closed',
        );
        if (!strictClosedValidation.passed) {
            const error = new Error(
                `Canonical final failed strict closure validation: ${strictClosedValidation.failures.map((item) => item.code).join(', ')}`,
            );
            error.code = recovered
                ? 'candidate-recovered-strict-closure-validation-failed'
                : 'candidate-strict-closure-validation-failed';
            error.validation = strictClosedValidation;
            error.canonicalCommitReached = true;
            throw error;
        }
        return {
            acceptanceEvidence,
            closed,
            strictClosedValidation,
        };
    };
    try {
        lock = await candidateTransactions.acquireLock(manifest.runId);
        const reconciliation = await candidateTransactions.reconcile(lock, {
            validateCanonicalFinal: validateTransactionCanonicalAggregate,
        });
        if (
            reconciliation.outcome === 'committed'
            && reconciliation.state?.runId === manifest.runId
        ) {
            const previousValidationEvidence = await readJsonOptional(aggregateValidationPath);
            canonicalCommitReached = true;
            await releaseCommittedPublication(
                reconciliation.state,
                previousValidationEvidence?.validationPasses,
                { recovered: true },
            );
            return JSON.parse(await readFile(finalPath, 'utf8'));
        }
        if (!preconditionsPassed) {
            const error = new Error(preconditionBlocker);
            error.code = 'finalization-precondition-failed';
            throw error;
        }
        await writeJsonAtomic(
            finalPath,
            openAggregate(
                baseAggregate,
                'Candidate assembly and publication transaction is in progress.',
                {
                    phase: 'candidate-assembly',
                    reconciliation: {
                        outcome: reconciliation.outcome,
                        recovered: reconciliation.recovered,
                    },
                    approvedBaselineWrites: [],
                    formalApprovalState: 'not-requested',
                },
            ),
        );
        await rm(pendingFinalPath, { force: true });
        const finalizationProof = {
            runId: manifest.runId,
            rootManifestChecksum: manifestChecksum,
            generatedAt: new Date().toISOString(),
            ports: portEvidence,
            profiles: profileEvidence,
            ownedProcesses: processProof,
            stageMarkers: markerEvidence,
            ownedCleanupPassed,
            passed: true,
        };
        built = await buildCandidate(manifest, baseAggregate, finalizationProof);
        const replacementIdentity = await physicalTreeIdentity(built.temporaryRoot);
        transaction = await candidateTransactions.begin(lock, {
            runId: manifest.runId,
            temporaryRoot: built.temporaryRoot,
            replacementIdentity,
            packageChecksum: built.packageChecksum,
            candidateManifestChecksum: built.manifestChecksum,
            pendingFinalPath,
            finalPath,
        });
        transaction = await candidateTransactions.install(lock, transaction);
        const postInstallValidation = await validateBaselineCandidate({
            workspaceRoot: root,
            candidateRoot,
            expectedRunId: manifest.runId,
            expectedRootManifestChecksum: manifestChecksum,
            expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
            expectedProtectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
            expectedFactoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
            approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
        });
        if (
            !postInstallValidation.passed
            || postInstallValidation.packageChecksum !== built.packageChecksum
        ) {
            const error = new Error(
                `Installed baseline candidate validation failed: ${postInstallValidation.failures.map((item) => item.code).join(', ') || 'package-checksum-mismatch'}`,
            );
            error.code = 'candidate-postinstall-validation-failed';
            error.validation = postInstallValidation;
            throw error;
        }
        transaction = await candidateTransactions.markInstalledValidated(
            lock,
            transaction,
            {
                packageChecksum: postInstallValidation.packageChecksum,
                failureCount: postInstallValidation.failures.length,
            },
        );
        const candidatePath = relative(root, candidateRoot).replaceAll('\\', '/');
        const passingAggregate = {
            ...baseAggregate,
            passed: true,
            remainingBlocker: null,
            closureRecommendation: 'Close BE-6A.1; baseline candidates remain unapproved.',
            finalizationState: 'closed-transactionally',
            baselineCandidate: {
                approvalState: 'unapproved',
                autoApproved: false,
                path: candidatePath,
                validationPassed: true,
                packageChecksum: built.packageChecksum,
                manifestChecksum: built.manifestChecksum,
                physicalTreeChecksum: replacementIdentity.treeChecksum,
                preInstallValidation: built.preInstallValidation,
                postInstallValidation,
                installedAtomically: true,
            },
            candidateFinalization: {
                passed: true,
                phase: 'canonical-final-publication',
                transactionId: transaction.transactionId,
                transactionSchema: transaction.schema,
                commitPoint: transaction.commitPoint,
                durableStatePath: transaction.paths.runStatePath,
                approvedBaselineWrites: [],
                formalApprovalState: 'not-requested',
            },
            behavioralAggregateValidation: {
                passed: true,
                failures: [],
                validator: 'validateEvidenceAggregate',
                validationPass: 'pre-publication-pending-aggregate',
            },
            finalizationTransaction: {
                transactionId: transaction.transactionId,
                schema: transaction.schema,
                candidateAssembledBeforeCanonicalPublication: true,
                candidateChecksummedBeforeCanonicalPublication: true,
                candidateValidatedBeforeInstall: true,
                candidateInstalledBySameVolumeRename: true,
                candidateRevalidatedAfterInstall: true,
                candidateCommitPreparedBeforeCanonicalPublication: true,
                candidateLockHeldThroughCanonicalPublication: true,
                priorCandidateRetainedUntilCanonicalCommit: true,
                canonicalFinalRenameIsCommitPoint: true,
                candidateLockReleaseRequiredAfterCanonicalPublication: true,
                aggregateValidationPasses: 3,
                validationEvidencePath: 'final-result.validation.json',
            },
        };
        await writeJsonAtomic(pendingFinalPath, passingAggregate);
        await writeJsonAtomic(aggregateValidationPath, {
            runId: manifest.runId,
            aggregateChecksum: await fileHash(pendingFinalPath),
            phase: 'first-validation-pending',
            validationPasses: [],
        });
        const firstValidation = await validateEvidenceAggregate({
            workspaceRoot: root,
            runRoot,
            aggregatePath: pendingFinalPath,
            expectedRunId: manifest.runId,
            expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
            approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
            finalizationMode: 'prepublication',
        });
        if (!firstValidation.passed) {
            const error = new Error(
                `Pre-publication aggregate validation failed: ${firstValidation.failures.map((item) => item.code).join(', ')}`,
            );
            error.code = 'aggregate-prepublication-validation-failed';
            error.validation = firstValidation;
            throw error;
        }
        passingAggregate.behavioralAggregateValidation = firstValidation;
        await writeJsonAtomic(pendingFinalPath, passingAggregate);
        const publicationValidation = await validateEvidenceAggregate({
            workspaceRoot: root,
            runRoot,
            aggregatePath: pendingFinalPath,
            expectedRunId: manifest.runId,
            expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
            approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
            finalizationMode: 'prepublication',
        });
        if (!publicationValidation.passed) {
            const error = new Error(
                `Exact pending aggregate validation failed: ${publicationValidation.failures.map((item) => item.code).join(', ')}`,
            );
            error.code = 'aggregate-exact-publication-validation-failed';
            error.validation = publicationValidation;
            throw error;
        }
        const pendingAggregateChecksum = await fileHash(pendingFinalPath);
        await writeJsonAtomic(aggregateValidationPath, {
            runId: manifest.runId,
            aggregateChecksum: pendingAggregateChecksum,
            phase: 'pending-aggregate-accepted',
            transactionId: transaction.transactionId,
            validationPasses: [firstValidation, publicationValidation],
        });
        transaction = await candidateTransactions.prepareCommit(lock, transaction, {
            pendingAggregateChecksum,
        });
        const finalCandidateValidation = await validateBaselineCandidate({
            workspaceRoot: root,
            candidateRoot,
            expectedRunId: manifest.runId,
            expectedRootManifestChecksum: manifestChecksum,
            expectedWorkingTreeChecksum: manifest.workingTreeChecksum,
            expectedProtectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
            expectedFactoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
            approvedBaselineRoots: await approvedBaselineValidationRoots(manifest),
        });
        if (
            !finalCandidateValidation.passed
            || finalCandidateValidation.packageChecksum !== built.packageChecksum
        ) {
            const error = new Error('Final installed candidate validation failed before publication.');
            error.code = 'candidate-final-prepublication-validation-failed';
            error.validation = finalCandidateValidation;
            throw error;
        }

        transaction = await candidateTransactions.publishCanonical(lock, transaction);
        canonicalCommitReached = true;
        transaction = await candidateTransactions.completeCommit(lock, transaction);
        await releaseCommittedPublication(
            transaction,
            [firstValidation, publicationValidation],
        );
        return JSON.parse(await readFile(finalPath, 'utf8'));
    } catch (error) {
        error.suppressOpenAggregatePersistence = true;
        let rollback = null;
        let currentState = transaction;
        if (lock && !currentState) {
            const durableState = await candidateTransactions.loadState().catch(() => null);
            if (durableState?.runId === manifest.runId) currentState = durableState;
        }
        if (lock && currentState) {
            try {
                const latest = await candidateTransactions.loadState();
                const physicallyCommitted = Boolean(
                    latest?.pendingAggregateChecksum
                    && await fileHash(finalPath) === latest.pendingAggregateChecksum
                );
                if (
                    canonicalCommitReached
                    || latest?.commitPointReached === true
                    || physicallyCommitted
                ) {
                    canonicalCommitReached = true;
                    const reconciliation = await candidateTransactions.reconcile(lock, {
                        validateCanonicalFinal: validateTransactionCanonicalAggregate,
                    });
                    currentState = reconciliation.state;
                    error.canonicalCommitReached = true;
                } else {
                    currentState = await candidateTransactions.rollback(
                        lock,
                        latest ?? currentState,
                        `${error.code ?? 'unclassified'}: ${error.message}`,
                    );
                    rollback = {
                        passed: true,
                        outcome: currentState.outcome,
                        transactionId: currentState.transactionId,
                    };
                }
            } catch (recoveryError) {
                error.recoveryError = {
                    code: recoveryError.code ?? 'unclassified',
                    message: recoveryError.message,
                };
                if (canonicalCommitReached) error.canonicalCommitReached = true;
            }
        }

        if (canonicalCommitReached && lock && currentState) {
            try {
                const previousValidationEvidence = await readJsonOptional(aggregateValidationPath);
                await releaseCommittedPublication(
                    currentState,
                    previousValidationEvidence?.validationPasses,
                    {
                        recovered: true,
                        recoveredFromError: {
                            code: error.code ?? 'unclassified',
                            message: error.message,
                        },
                    },
                );
                return JSON.parse(await readFile(finalPath, 'utf8'));
            } catch (closureError) {
                error.closureRecoveryError = {
                    code: closureError.code ?? 'unclassified',
                    message: closureError.message,
                };
            }
        }

        if (!canonicalCommitReached) {
            if (lock && !currentState && built?.temporaryRoot) {
                await rm(built.temporaryRoot, { recursive: true, force: true }).catch(() => {});
            }
            const blocker = `Finalization failed (${error.code ?? 'unclassified'}): ${error.message}`;
            const aggregate = openAggregate(baseAggregate, blocker, {
                phase: 'failed',
                failureCode: error.code ?? 'unclassified',
                failureMessage: error.message,
                recoveryError: error.recoveryError ?? null,
                lockReleaseError: null,
                rollback,
                transactionId: currentState?.runId === manifest.runId
                    ? currentState.transactionId
                    : null,
                approvedBaselineWrites: [],
                formalApprovalState: 'not-requested',
            });
            if (lock) {
                try {
                    await candidateTransactions.assertLock(lock);
                    await writeJsonAtomic(finalPath, aggregate);
                    await writeJsonAtomic(aggregateValidationPath, {
                        runId: manifest.runId,
                        aggregateChecksum: await fileHash(finalPath),
                        phase: 'finalization-failed-open',
                        transaction: currentState,
                        failureCode: error.code ?? 'unclassified',
                        failureMessage: error.message,
                        recoveryError: error.recoveryError ?? null,
                        rollback,
                        passed: false,
                    });
                    error.finalizationFailurePersisted = true;
                } catch (persistenceError) {
                    error.openAggregateFailure = persistenceError.message;
                }
                try {
                    const latest = await candidateTransactions.loadState();
                    await candidateTransactions.releaseLock(lock, latest ?? currentState);
                    lock = null;
                } catch (releaseError) {
                    error.lockReleaseError = {
                        code: releaseError.code ?? 'unclassified',
                        message: releaseError.message,
                    };
                }
            }
        } else {
            error.canonicalCommitReached = true;
        }
        throw error;
    }
}

async function validateCurrentFinalization(manifest) {
    const runRoot = join(evidenceBase, manifest.runId);
    const finalPath = join(runRoot, 'final-result.json');
    const state = await candidateTransactions.loadState();
    if (state?.runId !== manifest.runId) {
        const error = new Error(
            'Current run does not own the durable candidate transaction snapshot.',
        );
        error.code = 'candidate-validation-run-mismatch';
        throw error;
    }
    const result = await candidateTransactions.validateClosed(state, {
        validateCanonicalFinal: async (current) => await validateCanonicalAggregate(
            manifest,
            runRoot,
            resolve(root, current.paths.finalPath),
            'postcommit',
        ),
    });
    if (!result.passed) {
        const error = new Error(
            `Current finalization validation failed: ${result.failures.join(', ')}`,
        );
        error.code = 'candidate-read-only-validation-failed';
        error.validation = result;
        throw error;
    }
    const strictClosedValidation = await validateCanonicalAggregate(
        manifest,
        runRoot,
        finalPath,
        'closed',
    );
    if (!strictClosedValidation.passed) {
        const error = new Error(
            `Current strict closure validation failed: ${strictClosedValidation.failures.map((item) => item.code).join(', ')}`,
        );
        error.code = 'candidate-read-only-strict-validation-failed';
        error.validation = strictClosedValidation;
        throw error;
    }
    return {
        runId: manifest.runId,
        finalPath: relative(root, finalPath).replaceAll('\\', '/'),
        aggregateChecksum: await fileHash(finalPath),
        transactionId: state.transactionId,
        transactionPhase: state.phase,
        passed: true,
        validation: result,
        strictClosedValidation,
    };
}

async function main() {
    const validationLock = join(root, 'storage', 'framework', 'validation-run.lock');
    try {
        await mkdir(validationLock);
    } catch (error) {
        throw new Error('Another validation run owns storage/framework/validation-run.lock. Wait for completion; investigate stale ownership before removal.', { cause: error });
    }
    try {
        await writeFile(join(validationLock, 'owner.json'), JSON.stringify({ tool: 'fidelity', pid: process.pid }));
        await runMain();
    } finally {
        await rm(validationLock, { recursive: true, force: true });
    }
}

async function runMain() {
    if (!['all', 'prepare', 'finalize', 'validate', ...stages].includes(command)) {
        throw new Error(`Unknown BE-6A.1 stage: ${command}`);
    }
    if (command === 'prepare') {
        const prepared = await prepare();
        console.log(JSON.stringify(prepared.manifest, null, 2));
        return;
    }
    let prepared = null;
    let activeStage = null;
    try {
        prepared = command === 'all' ? await prepare() : await currentManifest();
        const { manifest, manifestChecksum } = prepared;
        if (command === 'validate') {
            activeStage = 'validate';
            await verifyImmutable(manifest, manifestChecksum);
            console.log(JSON.stringify(await validateCurrentFinalization(manifest), null, 2));
            return;
        }
        if (command === 'finalize') {
            activeStage = 'finalize';
            await verifyImmutable(manifest, manifestChecksum);
            const verifyStageResult = JSON.parse(
                await readFile(join(evidenceBase, manifest.runId, 'verify', 'stage-result.json'), 'utf8'),
            );
            console.log(JSON.stringify(
                await finalizeRun(manifest, manifestChecksum, verifyStageResult),
                null,
                2,
            ));
            return;
        }
        if (command === 'all') {
            for (const stage of stages) {
                activeStage = stage;
                await executeStage(stage, manifest, manifestChecksum);
            }
            console.log(JSON.stringify(JSON.parse(
                await readFile(join(evidenceBase, manifest.runId, 'final-result.json'), 'utf8'),
            )));
            return;
        }
        activeStage = command;
        await executeStage(command, manifest, manifestChecksum);
    } catch (error) {
        if (
            prepared?.manifest
            && prepared?.manifestChecksum
            && error.canonicalCommitReached !== true
            && error.finalizationFailurePersisted !== true
            && error.suppressOpenAggregatePersistence !== true
            && activeStage !== 'validate'
        ) {
            try {
                await persistOpenRunFailure(
                    prepared.manifest,
                    prepared.manifestChecksum,
                    error,
                    activeStage,
                );
            } catch (openError) {
                error.openAggregateFailure = openError.message;
            }
        }
        throw error;
    }
}

main().catch((error) => {
    console.error(error.stack ?? error.message);
    process.exitCode = 1;
});
