import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import {
    access,
    mkdir,
    mkdtemp,
    readFile,
    readdir,
    rm,
    writeFile,
} from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { tmpdir } from 'node:os';
import {
    dirname,
    join,
    relative,
    resolve,
} from 'node:path';

import {
    createCandidateTransactionCoordinator,
    exactProcessIdentityStatus,
    physicalTreeIdentity,
    samePhysicalTreeIdentity,
} from './be6a1-candidate-transaction.mjs';

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

async function exists(path) {
    return await access(path, fsConstants.F_OK)
        .then(() => true)
        .catch(() => false);
}

async function checksumFile(path) {
    return sha256(await readFile(path));
}

async function writeJson(path, value) {
    await mkdir(dirname(path), { recursive: true });
    await writeFile(path, `${JSON.stringify(value, null, 2)}\n`);
}

function deterministicClock(start = '2026-07-28T00:00:00.000Z') {
    let tick = 0;
    const epoch = Date.parse(start);
    return () => {
        const current = new Date(epoch + (tick * 1000));
        tick += 1;
        return current;
    };
}

function processRow(pid, creationTime, executableName = 'node.exe') {
    return { pid, creationTime, executableName };
}

function processProvider(rows) {
    const indexed = Object.fromEntries(rows.map((row) => [String(row.pid), row]));
    return () => ({
        provider: 'be6a1-candidate-transaction-self-check',
        rows: indexed,
    });
}

async function captureError(action) {
    try {
        await action();
    } catch (error) {
        return error;
    }
    assert.fail('Expected the operation to throw.');
}

async function filesystemSnapshot(root) {
    const rows = [];

    async function visit(directory) {
        const entries = await readdir(directory, { withFileTypes: true })
            .catch((error) => {
                if (error.code === 'ENOENT') return [];
                throw error;
            });

        for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
            const path = join(directory, entry.name);
            const portable = relative(root, path).replaceAll('\\', '/');
            if (entry.isDirectory()) {
                rows.push({ path: portable, type: 'directory' });
                await visit(path);
            } else if (entry.isFile()) {
                rows.push({
                    path: portable,
                    type: 'file',
                    checksum: await checksumFile(path),
                });
            } else {
                rows.push({ path: portable, type: 'other' });
            }
        }
    }

    await visit(root);
    return rows;
}

async function createFixture(suiteRoot, label) {
    const workspaceRoot = join(suiteRoot, label);
    const evidenceRoot = join(workspaceRoot, 'storage', 'app', 'evidence');
    const evidenceBase = join(evidenceRoot, 'be6a1');
    const candidateRoot = join(evidenceRoot, 'be6a1-baseline-candidate');
    await mkdir(evidenceBase, { recursive: true });

    return {
        workspaceRoot,
        evidenceRoot,
        evidenceBase,
        candidateRoot,
        candidateLockRoot: `${candidateRoot}.finalization-lock`,
        activeStatePath: `${candidateRoot}.finalization-transaction.json`,
        clock: deterministicClock(),
    };
}

async function writeLegacyCandidate(candidateRoot, runId) {
    await writeJson(join(candidateRoot, 'manifests', 'run-manifest.json'), {
        schema: 'be6a1-legacy-run-manifest-self-check',
        runId,
    });
    await writeFile(
        join(candidateRoot, 'legacy-browser-evidence.txt'),
        `immutable legacy evidence for ${runId}\n`,
    );
    return await physicalTreeIdentity(candidateRoot);
}

async function writeReplacementCandidate(temporaryRoot, runId) {
    const packageChecksum = sha256(`self-check-package:${runId}`);
    await writeJson(join(temporaryRoot, 'manifests', 'candidate-manifest.json'), {
        schema: 'be6a1-candidate-manifest-self-check',
        runId,
        packageChecksum,
    });
    await writeJson(join(temporaryRoot, 'manifests', 'run-manifest.json'), {
        schema: 'be6a1-run-manifest-self-check',
        runId,
    });
    await writeFile(
        join(temporaryRoot, 'replacement-browser-evidence.txt'),
        `replacement evidence for ${runId}\n`,
    );

    const identity = await physicalTreeIdentity(temporaryRoot);
    assert.equal(identity.runId, runId);
    assert.equal(identity.packageChecksum, packageChecksum);
    assert.match(identity.candidateManifestChecksum, /^[a-f0-9]{64}$/);
    return { identity, packageChecksum };
}

function coordinator(fixture, {
    pid,
    rows,
    checkpoint = async () => {},
}) {
    return createCandidateTransactionCoordinator({
        workspaceRoot: fixture.workspaceRoot,
        evidenceBase: fixture.evidenceBase,
        candidateRoot: fixture.candidateRoot,
        candidateLockRoot: fixture.candidateLockRoot,
        activeStatePath: fixture.activeStatePath,
        processSnapshot: processProvider(rows),
        now: fixture.clock,
        pid,
        executablePath: 'node.exe',
        checkpoint,
    });
}

function absoluteStatePath(fixture, portablePath) {
    return resolve(fixture.workspaceRoot, portablePath);
}

async function assertExactProcessIdentityClassification() {
    const owner = {
        processIdentity: processRow(
            4101,
            '2026-07-28T01:02:03.000Z',
            'node.exe',
        ),
    };
    const matching = processRow(
        4101,
        '2026-07-28T01:02:03.000Z',
        'node.exe',
    );
    const reusedPid = processRow(
        4101,
        '2026-07-28T04:05:06.000Z',
        'node.exe',
    );

    assert.equal(exactProcessIdentityStatus(owner, matching), 'live');
    assert.equal(exactProcessIdentityStatus(owner, null), 'dead');
    assert.equal(exactProcessIdentityStatus(owner, reusedPid), 'dead');
    assert.equal(
        exactProcessIdentityStatus(
            { processIdentity: { pid: 4101, executableName: 'node.exe' } },
            matching,
        ),
        'indeterminate',
    );
    assert.equal(
        exactProcessIdentityStatus(
            owner,
            { ...matching, executableName: 'another-node.exe' },
        ),
        'dead',
    );
}

async function assertLegacyManifestlessPhysicalIdentity(suiteRoot) {
    const fixture = await createFixture(suiteRoot, 'legacy-physical-identity');
    const legacyRunId = 'be6a1-legacy-manifestless';
    const identity = await writeLegacyCandidate(fixture.candidateRoot, legacyRunId);
    const repeated = await physicalTreeIdentity(fixture.candidateRoot);

    assert.equal(identity.present, true);
    assert.equal(identity.runId, legacyRunId);
    assert.equal(identity.fileCount, 2);
    assert.match(identity.treeChecksum, /^[a-f0-9]{64}$/);
    assert.equal(identity.packageChecksum, null);
    assert.equal(identity.candidateManifestChecksum, null);
    assert.match(identity.runManifestChecksum, /^[a-f0-9]{64}$/);
    assert.equal(samePhysicalTreeIdentity(identity, repeated), true);
}

async function assertLiveLockContentionHasNoWrites(suiteRoot) {
    const fixture = await createFixture(suiteRoot, 'live-lock-contention');
    const firstPid = 4201;
    const secondPid = 4202;
    const rows = [
        processRow(firstPid, '2026-07-28T10:00:01.000Z'),
        processRow(secondPid, '2026-07-28T10:00:02.000Z'),
    ];
    const first = coordinator(fixture, { pid: firstPid, rows });
    const contenderCheckpoints = [];
    const contender = coordinator(fixture, {
        pid: secondPid,
        rows,
        checkpoint: async (name) => contenderCheckpoints.push(name),
    });
    const lock = await first.acquireLock('be6a1-live-lock-owner');
    const before = await filesystemSnapshot(fixture.evidenceRoot);

    const error = await captureError(() =>
        contender.acquireLock('be6a1-live-lock-contender'));
    assert.equal(error.code, 'candidate-finalization-lock-live');

    const after = await filesystemSnapshot(fixture.evidenceRoot);
    assert.deepEqual(after, before);
    assert.deepEqual(contenderCheckpoints, []);
    assert.equal(await exists(fixture.activeStatePath), false);
    assert.equal(
        (await readdir(fixture.evidenceRoot))
            .some((name) => name.includes('.acquiring.')),
        false,
    );

    await first.releaseLock(lock);
    assert.equal(await exists(fixture.candidateLockRoot), false);
}

async function assertPrecommitCrashRollsBack(suiteRoot) {
    const fixture = await createFixture(suiteRoot, 'precommit-hard-crash');
    const oldRunId = 'be6a1-legacy-before-precommit-crash';
    const runId = 'be6a1-replacement-precommit-crash';
    const recoveryRunId = 'be6a1-recovery-precommit-crash';
    const temporaryRoot = `${fixture.candidateRoot}.${runId}.tmp`;
    const pendingFinalPath = join(
        fixture.evidenceBase,
        runId,
        'final-result.pending.json',
    );
    const finalPath = join(fixture.evidenceBase, runId, 'final-result.json');
    const oldIdentity = await writeLegacyCandidate(fixture.candidateRoot, oldRunId);
    const replacement = await writeReplacementCandidate(temporaryRoot, runId);
    const crash = new Error('simulated hard crash after candidate install rename');
    const originalPid = 4301;
    const recoveryPid = 4302;
    const original = coordinator(fixture, {
        pid: originalPid,
        rows: [processRow(originalPid, '2026-07-28T11:00:01.000Z')],
        checkpoint: async (name) => {
            if (name === 'after-candidate-install-rename-before-journal') throw crash;
        },
    });

    const originalLock = await original.acquireLock(runId);
    const started = await original.begin(originalLock, {
        runId,
        temporaryRoot,
        replacementIdentity: replacement.identity,
        packageChecksum: replacement.packageChecksum,
        candidateManifestChecksum: replacement.identity.candidateManifestChecksum,
        pendingFinalPath,
        finalPath,
    });
    const crashError = await captureError(() => original.install(originalLock, started));
    assert.equal(crashError, crash);

    const durableAtCrash = await original.loadState();
    assert.equal(durableAtCrash.phase, 'previous-candidate-backed-up');
    assert.equal(
        samePhysicalTreeIdentity(
            await physicalTreeIdentity(fixture.candidateRoot),
            replacement.identity,
        ),
        true,
    );
    assert.equal(
        samePhysicalTreeIdentity(
            await physicalTreeIdentity(
                absoluteStatePath(fixture, durableAtCrash.paths.backupRoot),
            ),
            oldIdentity,
        ),
        true,
    );
    assert.equal(await exists(fixture.candidateLockRoot), true);

    const recovery = coordinator(fixture, {
        pid: recoveryPid,
        rows: [processRow(recoveryPid, '2026-07-28T11:00:02.000Z')],
    });
    const recoveryLock = await recovery.acquireLock(recoveryRunId);
    assert.equal(recoveryLock.staleLocks.length, 1);
    assert.equal(recoveryLock.staleLocks[0].ownerStatus, 'dead');
    assert.equal(recoveryLock.staleLocks[0].owner.processIdentity.pid, originalPid);

    const reconciled = await recovery.reconcile(recoveryLock);
    assert.equal(reconciled.outcome, 'rolled-back');
    assert.equal(reconciled.recovered, true);
    const released = await recovery.releaseLock(recoveryLock, reconciled.state);

    assert.equal(released.state.phase, 'lock-released');
    assert.equal(released.state.outcome, 'rolled-back');
    assert.equal(released.state.commitPointReached, false);
    assert.equal(
        samePhysicalTreeIdentity(
            await physicalTreeIdentity(fixture.candidateRoot),
            oldIdentity,
        ),
        true,
    );
    assert.equal(
        (await physicalTreeIdentity(fixture.candidateRoot)).candidateManifestChecksum,
        null,
    );
    assert.equal(
        await exists(absoluteStatePath(fixture, released.state.paths.backupRoot)),
        false,
    );
    assert.equal(await exists(temporaryRoot), false);
    assert.equal(await exists(pendingFinalPath), false);
    assert.equal(await exists(finalPath), false);
    assert.equal(await exists(fixture.candidateLockRoot), false);
    for (const stale of recoveryLock.staleLocks) {
        assert.equal(await exists(absoluteStatePath(fixture, stale.path)), false);
    }
}

async function assertPostcommitCrashRollsForward(suiteRoot) {
    const fixture = await createFixture(suiteRoot, 'postcommit-hard-crash');
    const oldRunId = 'be6a1-legacy-before-postcommit-crash';
    const runId = 'be6a1-replacement-postcommit-crash';
    const recoveryRunId = 'be6a1-recovery-postcommit-crash';
    const temporaryRoot = `${fixture.candidateRoot}.${runId}.tmp`;
    const pendingFinalPath = join(
        fixture.evidenceBase,
        runId,
        'final-result.pending.json',
    );
    const finalPath = join(fixture.evidenceBase, runId, 'final-result.json');
    const oldIdentity = await writeLegacyCandidate(fixture.candidateRoot, oldRunId);
    const replacement = await writeReplacementCandidate(temporaryRoot, runId);
    const crash = new Error('simulated hard crash after canonical final rename');
    const originalPid = 4401;
    const recoveryPid = 4402;
    const original = coordinator(fixture, {
        pid: originalPid,
        rows: [processRow(originalPid, '2026-07-28T12:00:01.000Z')],
        checkpoint: async (name) => {
            if (name === 'after-canonical-final-rename-before-journal') throw crash;
        },
    });

    const originalLock = await original.acquireLock(runId);
    let state = await original.begin(originalLock, {
        runId,
        temporaryRoot,
        replacementIdentity: replacement.identity,
        packageChecksum: replacement.packageChecksum,
        candidateManifestChecksum: replacement.identity.candidateManifestChecksum,
        pendingFinalPath,
        finalPath,
    });
    state = await original.install(originalLock, state);
    state = await original.markInstalledValidated(originalLock, state, {
        source: 'candidate-transaction-self-check',
    });
    await writeJson(pendingFinalPath, {
        schema: 'be6a1-final-result-self-check',
        runId,
        passed: true,
        candidateFinalization: {
            transactionId: state.transactionId,
        },
    });
    const pendingAggregateChecksum = await checksumFile(pendingFinalPath);
    state = await original.prepareCommit(originalLock, state, {
        pendingAggregateChecksum,
    });
    const crashError = await captureError(() =>
        original.publishCanonical(originalLock, state));
    assert.equal(crashError, crash);

    const durableAtCrash = await original.loadState();
    assert.equal(durableAtCrash.phase, 'commit-prepared');
    assert.equal(durableAtCrash.commitPointReached, false);
    assert.equal(await exists(pendingFinalPath), false);
    assert.equal(await checksumFile(finalPath), pendingAggregateChecksum);
    assert.equal(
        samePhysicalTreeIdentity(
            await physicalTreeIdentity(fixture.candidateRoot),
            replacement.identity,
        ),
        true,
    );
    assert.equal(
        samePhysicalTreeIdentity(
            await physicalTreeIdentity(
                absoluteStatePath(fixture, durableAtCrash.paths.backupRoot),
            ),
            oldIdentity,
        ),
        true,
    );

    const recovery = coordinator(fixture, {
        pid: recoveryPid,
        rows: [processRow(recoveryPid, '2026-07-28T12:00:02.000Z')],
    });
    const recoveryLock = await recovery.acquireLock(recoveryRunId);
    assert.equal(recoveryLock.staleLocks.length, 1);
    assert.equal(recoveryLock.staleLocks[0].ownerStatus, 'dead');
    assert.equal(recoveryLock.staleLocks[0].owner.processIdentity.pid, originalPid);

    let canonicalValidationCalls = 0;
    const validateCanonicalFinal = async (candidateState) => {
        canonicalValidationCalls += 1;
        assert.equal(await checksumFile(finalPath), candidateState.pendingAggregateChecksum);
        assert.equal(
            samePhysicalTreeIdentity(
                await physicalTreeIdentity(fixture.candidateRoot),
                candidateState.replacementCandidateIdentity,
            ),
            true,
        );
        return {
            passed: true,
            source: 'candidate-transaction-self-check',
        };
    };
    const reconciled = await recovery.reconcile(recoveryLock, {
        validateCanonicalFinal,
    });
    assert.equal(reconciled.outcome, 'committed');
    assert.equal(reconciled.recovered, true);
    assert.equal(reconciled.state.commitPointReached, true);

    const released = await recovery.releaseLock(recoveryLock, reconciled.state);
    const closed = await recovery.validateClosed(released.state, {
        validateCanonicalFinal,
    });
    assert.equal(closed.passed, true, closed.failures.join('; '));
    assert.deepEqual(closed.failures, []);
    assert.equal(canonicalValidationCalls, 2);

    const installed = await physicalTreeIdentity(fixture.candidateRoot);
    const archived = await physicalTreeIdentity(
        absoluteStatePath(fixture, released.state.paths.archiveRoot),
    );
    assert.equal(samePhysicalTreeIdentity(installed, replacement.identity), true);
    assert.equal(samePhysicalTreeIdentity(archived, oldIdentity), true);
    assert.equal(archived.candidateManifestChecksum, null);
    assert.equal(released.state.previousCandidateArchive, released.state.paths.archiveRoot);
    assert.equal(released.state.phase, 'lock-released');
    assert.equal(released.state.outcome, 'committed');
    assert.equal(released.state.canonicalFinalChecksum, pendingAggregateChecksum);
    assert.equal(await checksumFile(finalPath), pendingAggregateChecksum);
    assert.equal(await exists(temporaryRoot), false);
    assert.equal(
        await exists(absoluteStatePath(fixture, released.state.paths.backupRoot)),
        false,
    );
    assert.equal(await exists(pendingFinalPath), false);
    assert.equal(await exists(fixture.candidateLockRoot), false);
    for (const stale of recoveryLock.staleLocks) {
        assert.equal(await exists(absoluteStatePath(fixture, stale.path)), false);
    }
}

const suiteRoot = await mkdtemp(join(tmpdir(), 'be6a1-candidate-transaction-self-check-'));
const passed = [];

async function run(name, assertion) {
    await assertion();
    passed.push(name);
}

try {
    await run('exact process identity classification', assertExactProcessIdentityClassification);
    await run(
        'legacy manifest-less physical identity',
        () => assertLegacyManifestlessPhysicalIdentity(suiteRoot),
    );
    await run(
        'live-lock contention has no durable or physical writes',
        () => assertLiveLockContentionHasNoWrites(suiteRoot),
    );
    await run(
        'precommit install-rename crash rolls back exactly',
        () => assertPrecommitCrashRollsBack(suiteRoot),
    );
    await run(
        'postcommit canonical-rename crash rolls forward exactly',
        () => assertPostcommitCrashRollsForward(suiteRoot),
    );
} finally {
    await rm(suiteRoot, {
        recursive: true,
        force: true,
        maxRetries: 3,
        retryDelay: 25,
    });
}

console.log(JSON.stringify({
    passed: true,
    assertions: passed.length,
    scenarios: passed,
}, null, 2));
