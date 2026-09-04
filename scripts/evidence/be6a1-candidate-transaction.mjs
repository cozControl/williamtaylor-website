import { createHash, randomBytes } from 'node:crypto';
import {
    access,
    mkdir,
    readFile,
    readdir,
    rename,
    rm,
    lstat,
    writeFile,
} from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import {
    basename,
    dirname,
    isAbsolute,
    join,
    relative,
    resolve,
} from 'node:path';

export const CANDIDATE_TRANSACTION_SCHEMA = 'be6a1-candidate-finalization-transaction-v2';
export const CANDIDATE_PHYSICAL_IDENTITY_SCHEMA = 'be6a1-physical-tree-identity-v2';

const CANDIDATE_PHYSICAL_IDENTITY_VERSION = 2;
const HASH_PATTERN = /^[a-f0-9]{64}$/;
const SAFE_RUN_ID_PATTERN = /^[a-zA-Z0-9](?:[a-zA-Z0-9._-]{0,190}[a-zA-Z0-9_-])?$/;

function transactionResolved(state) {
    return Boolean(
        state
        && (
            state.phase === 'committed'
            || state.phase === 'rolled-back'
            || (
                state.phase === 'lock-released'
                && ['committed', 'rolled-back'].includes(state.outcome)
            )
        )
    );
}

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

function timestamp(now) {
    return now().toISOString();
}

function errorWithCode(code, message, details = {}) {
    const error = new Error(message);
    error.code = code;
    Object.assign(error, details);
    return error;
}

async function exists(path) {
    try {
        await access(path, fsConstants.F_OK);
        return true;
    } catch (error) {
        if (error.code === 'ENOENT') return false;
        throw errorWithCode(
            'candidate-filesystem-access-failed',
            `Cannot inspect candidate transaction path: ${path}`,
            { path, causeCode: error.code },
        );
    }
}

async function fileChecksum(path) {
    try {
        return sha256(await readFile(path));
    } catch (error) {
        if (error.code === 'ENOENT') return null;
        throw errorWithCode(
            'candidate-checksum-read-failed',
            `Cannot checksum candidate transaction file: ${path}`,
            { path, causeCode: error.code },
        );
    }
}

async function readJsonOptional(path, {
    invalidCode = 'candidate-json-invalid',
    readCode = 'candidate-json-read-failed',
    label = 'Candidate transaction JSON',
} = {}) {
    let source;
    try {
        source = await readFile(path, 'utf8');
    } catch (error) {
        if (error.code === 'ENOENT') return null;
        throw errorWithCode(
            readCode,
            `${label} could not be read: ${path}`,
            { path, causeCode: error.code },
        );
    }
    try {
        return JSON.parse(source);
    } catch (error) {
        throw errorWithCode(
            invalidCode,
            `${label} is malformed JSON: ${path}`,
            { path, parseMessage: error.message },
        );
    }
}

async function writeJsonAtomic(path, value) {
    await mkdir(dirname(path), { recursive: true });
    const temporaryPath = `${path}.${process.pid}.${Date.now()}.${randomBytes(6).toString('hex')}.tmp`;
    await writeFile(temporaryPath, `${JSON.stringify(value, null, 2)}\n`, { flag: 'wx' });
    try {
        await rename(temporaryPath, path);
    } finally {
        await rm(temporaryPath, { force: true }).catch(() => {});
    }
}

function isPlainObject(value) {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

function canonicalize(value) {
    if (Array.isArray(value)) return value.map(canonicalize);
    if (!isPlainObject(value)) return value;
    return Object.fromEntries(
        Object.entries(value)
            .sort(([left], [right]) => left.localeCompare(right))
            .map(([key, item]) => [key, canonicalize(item)]),
    );
}

function canonicalJson(value) {
    return JSON.stringify(canonicalize(value));
}

function isHash(value) {
    return typeof value === 'string' && HASH_PATTERN.test(value);
}

function isTimestamp(value) {
    return typeof value === 'string' && Number.isFinite(Date.parse(value));
}

function isSafeRunId(value) {
    return typeof value === 'string'
        && SAFE_RUN_ID_PATTERN.test(value)
        && !value.includes('..');
}

function requireSafeRunId(value, label = 'run ID') {
    if (!isSafeRunId(value)) {
        throw errorWithCode(
            'candidate-run-id-invalid',
            `Candidate transaction ${label} is not a safe path component: ${value}`,
            { runId: value },
        );
    }
    return value;
}

async function physicalTreeRows(root, directory = root, rows = []) {
    let entries;
    try {
        entries = await readdir(directory, { withFileTypes: true });
    } catch (error) {
        throw errorWithCode(
            'candidate-identity-enumeration-failed',
            `Candidate identity directory could not be enumerated: ${directory}`,
            { path: directory, causeCode: error.code },
        );
    }
    entries.sort((left, right) => left.name.localeCompare(right.name));
    for (const entry of entries) {
        const path = join(directory, entry.name);
        const portable = relative(root, path).replaceAll('\\', '/');
        let metadata;
        try {
            metadata = await lstat(path);
        } catch (error) {
            throw errorWithCode(
                'candidate-identity-entry-stat-failed',
                `Candidate identity entry could not be inspected: ${portable}`,
                { path, causeCode: error.code },
            );
        }
        if (metadata.isSymbolicLink()) {
            throw errorWithCode(
                'candidate-identity-link-prohibited',
                `Candidate identity cannot traverse a symbolic link or junction: ${portable}`,
                { path },
            );
        }
        if (metadata.isDirectory()) {
            rows.push({ path: portable, type: 'directory' });
            await physicalTreeRows(root, path, rows);
            continue;
        }
        if (metadata.isFile()) {
            const checksum = await fileChecksum(path);
            if (!checksum) {
                throw errorWithCode(
                    'candidate-identity-file-disappeared',
                    `Candidate identity file disappeared while being checksummed: ${portable}`,
                    { path },
                );
            }
            rows.push({ path: portable, type: 'file', checksum });
            continue;
        }
        throw errorWithCode(
            'candidate-identity-special-entry-prohibited',
            `Candidate identity contains an unsupported filesystem entry: ${portable}`,
            { path },
        );
    }
    return rows;
}

/**
 * Return an exact physical identity for any candidate directory, including
 * legacy candidates which predate candidate-manifest.json.
 */
export async function physicalTreeIdentity(directory) {
    const root = resolve(directory);
    let metadata;
    try {
        metadata = await lstat(root);
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw errorWithCode(
                'candidate-identity-root-stat-failed',
                `Candidate identity root could not be inspected: ${root}`,
                { path: root, causeCode: error.code },
            );
        }
        return {
            identitySchema: CANDIDATE_PHYSICAL_IDENTITY_SCHEMA,
            identityVersion: CANDIDATE_PHYSICAL_IDENTITY_VERSION,
            present: false,
            treeChecksum: null,
            fileCount: 0,
            directoryCount: 0,
            entryCount: 0,
            runId: null,
            packageChecksum: null,
            candidateManifestChecksum: null,
            runManifestChecksum: null,
        };
    }
    if (metadata.isSymbolicLink()) {
        throw errorWithCode(
            'candidate-identity-link-prohibited',
            `Candidate identity root cannot be a symbolic link or junction: ${root}`,
        );
    }
    if (!metadata.isDirectory()) {
        throw errorWithCode(
            'candidate-identity-not-directory',
            `Candidate identity path is not a directory: ${root}`,
        );
    }
    const rows = await physicalTreeRows(root);
    const candidateManifestPath = join(root, 'manifests', 'candidate-manifest.json');
    const runManifestPath = join(root, 'manifests', 'run-manifest.json');
    const manifestReadOptions = {
        invalidCode: 'candidate-identity-manifest-invalid',
        readCode: 'candidate-identity-manifest-read-failed',
        label: 'Candidate identity manifest',
    };
    const candidateManifest = await readJsonOptional(candidateManifestPath, manifestReadOptions);
    const runManifest = await readJsonOptional(runManifestPath, manifestReadOptions);
    if (candidateManifest !== null && !isPlainObject(candidateManifest)) {
        throw errorWithCode(
            'candidate-identity-manifest-invalid',
            `Candidate manifest is not a JSON object: ${candidateManifestPath}`,
        );
    }
    if (runManifest !== null && !isPlainObject(runManifest)) {
        throw errorWithCode(
            'candidate-identity-manifest-invalid',
            `Run manifest is not a JSON object: ${runManifestPath}`,
        );
    }
    const fileCount = rows.filter((row) => row.type === 'file').length;
    const directoryCount = rows.filter((row) => row.type === 'directory').length;
    return {
        identitySchema: CANDIDATE_PHYSICAL_IDENTITY_SCHEMA,
        identityVersion: CANDIDATE_PHYSICAL_IDENTITY_VERSION,
        present: true,
        treeChecksum: sha256(canonicalJson(rows)),
        fileCount,
        directoryCount,
        entryCount: rows.length,
        runId: candidateManifest?.runId ?? runManifest?.runId ?? null,
        packageChecksum: candidateManifest?.packageChecksum ?? null,
        candidateManifestChecksum: await fileChecksum(candidateManifestPath),
        runManifestChecksum: await fileChecksum(runManifestPath),
    };
}

export function samePhysicalTreeIdentity(left, right) {
    return Boolean(
        left?.present
        && right?.present
        && left.identitySchema === CANDIDATE_PHYSICAL_IDENTITY_SCHEMA
        && right.identitySchema === CANDIDATE_PHYSICAL_IDENTITY_SCHEMA
        && left.identityVersion === CANDIDATE_PHYSICAL_IDENTITY_VERSION
        && right.identityVersion === CANDIDATE_PHYSICAL_IDENTITY_VERSION
        && typeof left.treeChecksum === 'string'
        && left.treeChecksum.length === 64
        && left.treeChecksum === right.treeChecksum
        && Number.isInteger(left.fileCount)
        && left.fileCount === right.fileCount
        && Number.isInteger(left.directoryCount)
        && left.directoryCount === right.directoryCount
        && Number.isInteger(left.entryCount)
        && left.entryCount === right.entryCount
    );
}

function normalizedEpochNanoseconds(year, month, day, hour, minute, second, nanoseconds, offsetMinutes) {
    const localMilliseconds = Date.UTC(year, month - 1, day, hour, minute, second, 0);
    const local = new Date(localMilliseconds);
    if (
        local.getUTCFullYear() !== year
        || local.getUTCMonth() !== month - 1
        || local.getUTCDate() !== day
        || local.getUTCHours() !== hour
        || local.getUTCMinutes() !== minute
        || local.getUTCSeconds() !== second
    ) return null;
    const utcMilliseconds = localMilliseconds - (offsetMinutes * 60_000);
    return `unix-ns:${(BigInt(utcMilliseconds) * 1_000_000n) + BigInt(nanoseconds)}`;
}

/** Normalize WMIC DMTF, ISO/RFC3339, PowerShell /Date(...)/, and ps date strings. */
export function normalizeProcessCreationTime(value) {
    if (typeof value !== 'string' || value.trim() === '') return null;
    const text = value.trim().replaceAll('\\/', '/');
    const powershell = text.match(/^\/Date\((-?\d+)(?:[+-]\d{4})?\)\/$/);
    if (powershell) {
        try {
            return `unix-ns:${BigInt(powershell[1]) * 1_000_000n}`;
        } catch {
            return null;
        }
    }
    const dmtf = text.match(
        /^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.(\d{6})([+-])(\d{3})$/,
    );
    if (dmtf) {
        const offset = Number(dmtf[9]) * (dmtf[8] === '-' ? -1 : 1);
        return normalizedEpochNanoseconds(
            Number(dmtf[1]),
            Number(dmtf[2]),
            Number(dmtf[3]),
            Number(dmtf[4]),
            Number(dmtf[5]),
            Number(dmtf[6]),
            Number(dmtf[7]) * 1_000,
            offset,
        );
    }
    const iso = text.match(
        /^(\d{4})-(\d{2})-(\d{2})[Tt](\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,9}))?([Zz]|[+-]\d{2}:\d{2})$/,
    );
    if (iso) {
        const fraction = (iso[7] ?? '').padEnd(9, '0');
        const offset = iso[8].toUpperCase() === 'Z'
            ? 0
            : (() => {
                const sign = iso[8][0] === '-' ? -1 : 1;
                return sign * ((Number(iso[8].slice(1, 3)) * 60) + Number(iso[8].slice(4, 6)));
            })();
        return normalizedEpochNanoseconds(
            Number(iso[1]),
            Number(iso[2]),
            Number(iso[3]),
            Number(iso[4]),
            Number(iso[5]),
            Number(iso[6]),
            Number(fraction),
            offset,
        );
    }
    const parsed = Date.parse(text);
    return Number.isFinite(parsed) ? `unix-ns:${BigInt(parsed) * 1_000_000n}` : null;
}

export function normalizeExecutableName(value) {
    if (typeof value !== 'string' || value.trim() === '') return null;
    return value.trim().split(/[\\/]/).at(-1).toLocaleLowerCase('en-US');
}

/**
 * Classify an owner against one current process row. "dead" is returned only
 * when a creation-identified owner is provably absent or the PID was reused.
 */
export function exactProcessIdentityStatus(owner, currentRow) {
    const identity = owner?.processIdentity ?? owner;
    if (!identity || !Number.isInteger(identity.pid)) return 'ownerless';
    const ownerCreationTime = identity.creationTimeNormalized
        ?? normalizeProcessCreationTime(identity.creationTime);
    if (!ownerCreationTime) return 'indeterminate';
    if (!currentRow) return 'dead';
    if (Number(currentRow.pid) !== identity.pid) return 'dead';
    const currentCreationTime = currentRow.creationTimeNormalized
        ?? normalizeProcessCreationTime(currentRow.creationTime);
    if (!currentCreationTime) return 'indeterminate';
    if (currentCreationTime !== ownerCreationTime) return 'dead';
    const ownerExecutable = normalizeExecutableName(identity.executableName);
    const currentExecutable = normalizeExecutableName(currentRow.executableName);
    if (
        ownerExecutable
        && currentExecutable
        && ownerExecutable !== currentExecutable
    ) return 'dead';
    return 'live';
}

function rowFromSnapshot(snapshot, pid) {
    if (!snapshot?.rows) return null;
    if (snapshot.rows instanceof Map) return snapshot.rows.get(pid) ?? null;
    return snapshot.rows[pid] ?? snapshot.rows[String(pid)] ?? null;
}

function portablePath(workspaceRoot, path) {
    return relative(workspaceRoot, resolve(path)).replaceAll('\\', '/');
}

function resolveOwnedPath(workspaceRoot, evidenceRoot, portable) {
    if (typeof portable !== 'string' || portable === '' || isAbsolute(portable)) {
        throw errorWithCode('candidate-path-invalid', `Transaction path is not portable: ${portable}`);
    }
    const path = resolve(workspaceRoot, portable);
    const relation = relative(evidenceRoot, path);
    if (relation === '' || (!relation.startsWith('..') && !isAbsolute(relation))) return path;
    throw errorWithCode(
        'candidate-path-escaped-evidence-root',
        `Transaction path escaped the evidence root: ${portable}`,
    );
}

function safePathPart(value) {
    const normalized = String(value ?? 'legacy')
        .replace(/[^a-zA-Z0-9._-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return normalized || 'legacy';
}

function remnantPathNameMatches(lockRoot, path) {
    const lockName = basename(lockRoot);
    const name = basename(path);
    if (name.startsWith(`${lockName}.acquiring.`)) {
        return /^\d+\.[a-f0-9]{12}\.\d+$/.test(
            name.slice(`${lockName}.acquiring.`.length),
        );
    }
    if (name.startsWith(`${lockName}.stale.`)) {
        return /^\d+\.\d+\.[a-f0-9]{10}$/.test(
            name.slice(`${lockName}.stale.`.length),
        );
    }
    return false;
}

function validationPassed(result) {
    return result === true || result?.passed === true;
}

function validatePhysicalIdentityRecord(identity, label, { requiredPresent = null } = {}) {
    const allowedKeys = new Set([
        'identitySchema',
        'identityVersion',
        'present',
        'treeChecksum',
        'fileCount',
        'directoryCount',
        'entryCount',
        'runId',
        'packageChecksum',
        'candidateManifestChecksum',
        'runManifestChecksum',
    ]);
    if (!isPlainObject(identity) || Object.keys(identity).some((key) => !allowedKeys.has(key))) {
        throw errorWithCode(
            'candidate-transaction-identity-invalid',
            `${label} has an unknown physical identity schema.`,
            { identity },
        );
    }
    if (
        identity.identitySchema !== CANDIDATE_PHYSICAL_IDENTITY_SCHEMA
        || identity.identityVersion !== CANDIDATE_PHYSICAL_IDENTITY_VERSION
        || typeof identity.present !== 'boolean'
        || (requiredPresent !== null && identity.present !== requiredPresent)
        || !Number.isInteger(identity.fileCount)
        || identity.fileCount < 0
        || !Number.isInteger(identity.directoryCount)
        || identity.directoryCount < 0
        || !Number.isInteger(identity.entryCount)
        || identity.entryCount !== identity.fileCount + identity.directoryCount
    ) {
        throw errorWithCode(
            'candidate-transaction-identity-invalid',
            `${label} has an incomplete physical identity.`,
            { identity },
        );
    }
    if (!identity.present) {
        if (
            identity.treeChecksum !== null
            || identity.fileCount !== 0
            || identity.directoryCount !== 0
            || identity.entryCount !== 0
            || identity.runId !== null
            || identity.packageChecksum !== null
            || identity.candidateManifestChecksum !== null
            || identity.runManifestChecksum !== null
        ) {
            throw errorWithCode(
                'candidate-transaction-identity-invalid',
                `${label} records metadata for an absent tree.`,
                { identity },
            );
        }
        return identity;
    }
    if (
        !isHash(identity.treeChecksum)
        || (identity.runId !== null && !isSafeRunId(identity.runId))
        || (identity.packageChecksum !== null && !isHash(identity.packageChecksum))
        || (identity.candidateManifestChecksum !== null && !isHash(identity.candidateManifestChecksum))
        || (identity.runManifestChecksum !== null && !isHash(identity.runManifestChecksum))
    ) {
        throw errorWithCode(
            'candidate-transaction-identity-invalid',
            `${label} contains an invalid tree or manifest checksum.`,
            { identity },
        );
    }
    return identity;
}

function validateLockOwnerRecord(owner, label = 'Candidate lock owner') {
    const allowedOwnerKeys = new Set([
        'schema',
        'token',
        'runId',
        'acquiredAt',
        'processIdentity',
        'releaseCommittedAt',
    ]);
    const allowedIdentityKeys = new Set([
        'pid',
        'creationTime',
        'creationTimeNormalized',
        'executableName',
        'provider',
    ]);
    if (
        !isPlainObject(owner)
        || Object.keys(owner).some((key) => !allowedOwnerKeys.has(key))
        || owner.schema !== CANDIDATE_TRANSACTION_SCHEMA
        || !isHash(owner.token)
        || !isSafeRunId(owner.runId)
        || !isTimestamp(owner.acquiredAt)
        || (owner.releaseCommittedAt !== undefined && !isTimestamp(owner.releaseCommittedAt))
        || !isPlainObject(owner.processIdentity)
        || Object.keys(owner.processIdentity).some((key) => !allowedIdentityKeys.has(key))
        || !Number.isInteger(owner.processIdentity.pid)
        || owner.processIdentity.pid <= 0
        || typeof owner.processIdentity.creationTime !== 'string'
        || typeof owner.processIdentity.executableName !== 'string'
        || owner.processIdentity.executableName.trim() === ''
        || typeof owner.processIdentity.provider !== 'string'
        || owner.processIdentity.provider.trim() === ''
    ) {
        throw errorWithCode(
            'candidate-lock-owner-invalid',
            `${label} is malformed or lacks exact process identity.`,
            { owner },
        );
    }
    const normalized = normalizeProcessCreationTime(owner.processIdentity.creationTime);
    if (
        !normalized
        || (
            owner.processIdentity.creationTimeNormalized !== undefined
            && owner.processIdentity.creationTimeNormalized !== normalized
        )
    ) {
        throw errorWithCode(
            'candidate-lock-owner-invalid',
            `${label} has an unnormalizable or contradictory creation time.`,
            { owner },
        );
    }
    return owner;
}

function stateSnapshotChecksum(state) {
    const { snapshotChecksum: ignored, ...unsigned } = state;
    return sha256(canonicalJson(unsigned));
}

const TRANSACTION_PHASES = new Set([
    'transaction-started',
    'previous-candidate-backed-up',
    'candidate-installed',
    'candidate-installed-validated',
    'commit-prepared',
    'canonical-final-published',
    'committed',
    'rolled-back',
    'recovery-started',
    'recovery-blocked',
    'lock-released',
]);

const TRANSACTION_PHASE_TRANSITIONS = new Map([
    ['transaction-started', new Set(['previous-candidate-backed-up', 'candidate-installed', 'recovery-started', 'rolled-back', 'recovery-blocked'])],
    ['previous-candidate-backed-up', new Set(['candidate-installed', 'recovery-started', 'rolled-back', 'recovery-blocked'])],
    ['candidate-installed', new Set(['candidate-installed-validated', 'recovery-started', 'rolled-back', 'recovery-blocked'])],
    ['candidate-installed-validated', new Set(['commit-prepared', 'recovery-started', 'rolled-back', 'recovery-blocked'])],
    ['commit-prepared', new Set(['canonical-final-published', 'recovery-started', 'rolled-back', 'recovery-blocked'])],
    ['canonical-final-published', new Set(['committed', 'recovery-started'])],
    ['committed', new Set(['lock-released', 'recovery-started'])],
    ['rolled-back', new Set(['lock-released', 'recovery-started'])],
    ['recovery-started', new Set(['recovery-started', 'committed', 'rolled-back', 'recovery-blocked'])],
    ['recovery-blocked', new Set(['recovery-started'])],
    ['lock-released', new Set(['lock-released'])],
]);

/**
 * Pure schema and deterministic-path assertion shared by the coordinator and
 * aggregate validators. Returns the input state or throws a coded error.
 */
export function validateCandidateTransactionState(state, {
    workspaceRoot,
    evidenceBase,
    candidateRoot,
    candidateLockRoot = `${candidateRoot}.finalization-lock`,
    activeStatePath = `${candidateRoot}.finalization-transaction.json`,
}) {
    const allowedStateKeys = new Set([
        'schema', 'version', 'transactionId', 'runId', 'sequence', 'phase', 'outcome',
        'commitPoint', 'commitPointReached', 'startedAt', 'updatedAt', 'owner', 'paths',
        'previousCandidateIdentity', 'replacementCandidateIdentity', 'packageChecksum',
        'candidateManifestChecksum', 'pendingAggregateChecksum', 'canonicalFinalChecksum',
        'previousCandidateArchive', 'recovery', 'cleanup', 'events',
        'previousSnapshotChecksum', 'snapshotChecksum', 'installedAt', 'installedValidation',
        'commitPreparedAt', 'canonicalPublishedAt', 'committedAt', 'rolledBackAt',
        'rollbackReason', 'lockRelease',
    ]);
    if (
        !isPlainObject(state)
        || Object.keys(state).some((key) => !allowedStateKeys.has(key))
        || state.schema !== CANDIDATE_TRANSACTION_SCHEMA
        || state.version !== 2
        || !isHash(state.transactionId)
        || !isSafeRunId(state.runId)
        || !Number.isInteger(state.sequence)
        || state.sequence < 1
        || !TRANSACTION_PHASES.has(state.phase)
        || ![null, 'committed', 'rolled-back'].includes(state.outcome)
        || state.commitPoint !== 'canonical-final-rename'
        || typeof state.commitPointReached !== 'boolean'
        || !isTimestamp(state.startedAt)
        || !isTimestamp(state.updatedAt)
        || !isHash(state.snapshotChecksum)
        || state.snapshotChecksum !== stateSnapshotChecksum(state)
        || (
            state.sequence === 1
                ? state.previousSnapshotChecksum !== null
                : !isHash(state.previousSnapshotChecksum)
        )
    ) {
        throw errorWithCode(
            'candidate-transaction-state-invalid',
            'The active candidate transaction snapshot has an unknown, incomplete, or corrupt schema.',
            { state },
        );
    }
    validateLockOwnerRecord(state.owner, 'Candidate transaction owner');
    validatePhysicalIdentityRecord(state.previousCandidateIdentity, 'Previous candidate identity');
    validatePhysicalIdentityRecord(
        state.replacementCandidateIdentity,
        'Replacement candidate identity',
        { requiredPresent: true },
    );
    if (
        state.replacementCandidateIdentity.runId !== state.runId
        || !isHash(state.packageChecksum)
        || state.replacementCandidateIdentity.packageChecksum !== state.packageChecksum
        || !isHash(state.candidateManifestChecksum)
        || state.replacementCandidateIdentity.candidateManifestChecksum !== state.candidateManifestChecksum
        || (state.pendingAggregateChecksum !== null && !isHash(state.pendingAggregateChecksum))
        || (state.canonicalFinalChecksum !== null && !isHash(state.canonicalFinalChecksum))
    ) {
        throw errorWithCode(
            'candidate-transaction-state-coherence-invalid',
            'Candidate transaction checksums and replacement identity are incoherent.',
            { state },
        );
    }
    if (!Array.isArray(state.events) || state.events.length !== state.sequence) {
        throw errorWithCode(
            'candidate-transaction-events-invalid',
            'Candidate transaction events are not contiguous with the durable sequence.',
            { events: state.events, sequence: state.sequence },
        );
    }
    for (let index = 0; index < state.events.length; index += 1) {
        const event = state.events[index];
        const previous = state.events[index - 1];
        if (
            !isPlainObject(event)
            || event.sequence !== index + 1
            || !TRANSACTION_PHASES.has(event.phase)
            || !isTimestamp(event.at)
            || (previous && Date.parse(event.at) < Date.parse(previous.at))
            || (
                previous
                && !TRANSACTION_PHASE_TRANSITIONS.get(previous.phase)?.has(event.phase)
            )
        ) {
            throw errorWithCode(
                'candidate-transaction-events-invalid',
                'Candidate transaction event sequence contains an impossible transition.',
                { event, previous },
            );
        }
    }
    if (
        state.events[0].phase !== 'transaction-started'
        || state.events.at(-1).phase !== state.phase
        || state.events.at(-1).at !== state.updatedAt
    ) {
        throw errorWithCode(
            'candidate-transaction-events-invalid',
            'Candidate transaction event head or tail does not match the durable state.',
            { state },
        );
    }
    if (
        (state.phase === 'committed' && state.outcome !== 'committed')
        || (state.phase === 'rolled-back' && state.outcome !== 'rolled-back')
        || (
            state.phase === 'lock-released'
            && !['committed', 'rolled-back'].includes(state.outcome)
        )
        || (
            state.outcome === 'committed'
            && (
                state.commitPointReached !== true
                || !isHash(state.pendingAggregateChecksum)
                || state.canonicalFinalChecksum !== state.pendingAggregateChecksum
            )
        )
        || (state.outcome === 'rolled-back' && state.commitPointReached !== false)
        || (
            ['canonical-final-published', 'committed'].includes(state.phase)
            && (
                state.commitPointReached !== true
                || state.canonicalFinalChecksum !== state.pendingAggregateChecksum
            )
        )
    ) {
        throw errorWithCode(
            'candidate-transaction-outcome-invalid',
            'Candidate transaction phase, outcome, and commit-point evidence are incoherent.',
            { state },
        );
    }
    if (
        !isPlainObject(state.recovery)
        || !Array.isArray(state.recovery.adoptedStaleLocks)
        || !Array.isArray(state.recovery.attempts)
        || typeof state.recovery.blocked !== 'boolean'
    ) {
        throw errorWithCode(
            'candidate-transaction-recovery-invalid',
            'Candidate transaction recovery journal is malformed.',
            { recovery: state.recovery },
        );
    }
    if (
        !isPlainObject(state.cleanup)
        || typeof state.cleanup.temporaryRemoved !== 'boolean'
        || typeof state.cleanup.backupRemoved !== 'boolean'
        || typeof state.cleanup.pendingRemoved !== 'boolean'
        || !Array.isArray(state.cleanup.staleLocksRemoved)
        || state.cleanup.staleLocksRemoved.some((path) => typeof path !== 'string')
        || (
            state.cleanup.staleLocksExpectedAbsent !== undefined
            && (
                !Array.isArray(state.cleanup.staleLocksExpectedAbsent)
                || state.cleanup.staleLocksExpectedAbsent.some((path) => typeof path !== 'string')
            )
        )
        || (
            state.cleanup.replacementRemoved !== undefined
            && typeof state.cleanup.replacementRemoved !== 'boolean'
        )
    ) {
        throw errorWithCode(
            'candidate-transaction-cleanup-invalid',
            'Candidate transaction cleanup journal is malformed.',
            { cleanup: state.cleanup },
        );
    }
    for (const key of [
        'installedAt',
        'commitPreparedAt',
        'canonicalPublishedAt',
        'committedAt',
        'rolledBackAt',
    ]) {
        if (state[key] !== undefined && !isTimestamp(state[key])) {
            throw errorWithCode(
                'candidate-transaction-timestamp-invalid',
                `Candidate transaction ${key} is not a timestamp.`,
                { state },
            );
        }
    }
    if (
        state.installedValidation !== undefined
        && (
            !isPlainObject(state.installedValidation)
            || state.installedValidation.passed !== true
            || !isTimestamp(state.installedValidation.recordedAt)
        )
    ) {
        throw errorWithCode(
            'candidate-transaction-installed-validation-invalid',
            'Installed-candidate validation metadata is malformed.',
            { state },
        );
    }
    if (
        state.phase === 'lock-released'
        && (
            !isPlainObject(state.lockRelease)
            || !isTimestamp(state.lockRelease.committedAt)
            || state.lockRelease.canonicalRemovalRequired !== true
            || !Array.isArray(state.lockRelease.staleLockCleanupPaths)
        )
    ) {
        throw errorWithCode(
            'candidate-transaction-lock-release-invalid',
            'Lock-release commitment metadata is malformed.',
            { state },
        );
    }

    const workspace = resolve(workspaceRoot);
    const evidenceBaseRoot = resolve(evidenceBase);
    const evidenceRoot = resolve(evidenceBaseRoot, '..');
    const candidate = resolve(candidateRoot);
    const lockRoot = resolve(candidateLockRoot);
    const globalStatePath = resolve(activeStatePath);
    const previousLabel = safePathPart(
        state.previousCandidateIdentity.runId
        ?? `legacy-${state.previousCandidateIdentity.treeChecksum?.slice(0, 12) ?? 'absent'}`,
    );
    const expectedPaths = {
        candidateRoot: portablePath(workspace, candidate),
        temporaryRoot: portablePath(workspace, `${candidate}.${state.runId}.tmp`),
        backupRoot: portablePath(
            workspace,
            `${candidate}.rollback.${state.runId}.${state.transactionId.slice(0, 12)}`,
        ),
        archiveRoot: portablePath(
            workspace,
            join(
                dirname(candidate),
                'be6a1-baseline-candidate-archive',
                `${previousLabel}-replaced-by-${safePathPart(state.runId)}-${state.transactionId.slice(0, 12)}`,
            ),
        ),
        pendingFinalPath: portablePath(
            workspace,
            join(evidenceBaseRoot, state.runId, 'final-result.pending.json'),
        ),
        finalPath: portablePath(
            workspace,
            join(evidenceBaseRoot, state.runId, 'final-result.json'),
        ),
        lockRoot: portablePath(workspace, lockRoot),
        activeStatePath: portablePath(workspace, globalStatePath),
        runStatePath: portablePath(
            workspace,
            join(evidenceBaseRoot, state.runId, 'finalization-transaction.json'),
        ),
    };
    if (
        !isPlainObject(state.paths)
        || canonicalJson(Object.keys(state.paths).sort()) !== canonicalJson(Object.keys(expectedPaths).sort())
        || Object.entries(expectedPaths).some(([key, expected]) => state.paths[key] !== expected)
    ) {
        throw errorWithCode(
            'candidate-transaction-paths-invalid',
            'Candidate transaction paths do not match the deterministic coordinator paths.',
            { expectedPaths, actualPaths: state.paths },
        );
    }
    for (const portable of Object.values(state.paths)) {
        resolveOwnedPath(workspace, evidenceRoot, portable);
    }
    for (const portable of [
        ...(state.cleanup.staleLocksRemoved ?? []),
        ...(state.cleanup.staleLocksExpectedAbsent ?? []),
    ]) {
        const path = resolveOwnedPath(workspace, evidenceRoot, portable);
        if (dirname(path) !== dirname(lockRoot) || !remnantPathNameMatches(lockRoot, path)) {
            throw errorWithCode(
                'candidate-transaction-stale-lock-path-invalid',
                `Candidate transaction cleanup contains a non-lock-remnant path: ${portable}`,
                { state },
            );
        }
    }
    if (
        state.previousCandidateArchive !== null
        && state.previousCandidateArchive !== expectedPaths.archiveRoot
    ) {
        throw errorWithCode(
            'candidate-transaction-archive-path-invalid',
            'Candidate transaction archive path differs from its deterministic recovery path.',
            { state },
        );
    }
    return state;
}

export function createCandidateTransactionCoordinator({
    workspaceRoot,
    evidenceBase,
    candidateRoot,
    candidateLockRoot = `${candidateRoot}.finalization-lock`,
    activeStatePath = `${candidateRoot}.finalization-transaction.json`,
    processSnapshot,
    now = () => new Date(),
    pid = process.pid,
    executablePath = process.execPath,
    checkpoint = async () => {},
}) {
    const workspace = resolve(workspaceRoot);
    const evidenceBaseRoot = resolve(evidenceBase);
    const evidence = resolve(evidenceBaseRoot, '..');
    const candidate = resolve(candidateRoot);
    const lockRoot = resolve(candidateLockRoot);
    const globalStatePath = resolve(activeStatePath);
    const stateValidationConfig = {
        workspaceRoot: workspace,
        evidenceBase: evidenceBaseRoot,
        candidateRoot: candidate,
        candidateLockRoot: lockRoot,
        activeStatePath: globalStatePath,
    };

    for (const path of [candidate, lockRoot, globalStatePath]) {
        const relation = relative(evidence, path);
        if (relation.startsWith('..') || isAbsolute(relation)) {
            throw errorWithCode(
                'candidate-coordinator-path-invalid',
                `Candidate transaction path is outside the evidence root: ${path}`,
            );
        }
    }

    const runStatePath = (runId) => join(
        evidenceBaseRoot,
        requireSafeRunId(runId),
        'finalization-transaction.json',
    );
    const ownerPath = (root = lockRoot) => join(root, 'owner.json');

    async function readStateSnapshot(path, label) {
        const state = await readJsonOptional(path, {
            invalidCode: 'candidate-transaction-state-json-invalid',
            readCode: 'candidate-transaction-state-read-failed',
            label,
        });
        return state === null
            ? null
            : validateCandidateTransactionState(state, stateValidationConfig);
    }

    async function runSnapshotPaths() {
        let entries;
        try {
            entries = await readdir(evidenceBaseRoot, { withFileTypes: true });
        } catch (error) {
            if (error.code === 'ENOENT') return [];
            throw errorWithCode(
                'candidate-transaction-run-scan-failed',
                'Run transaction snapshots could not be enumerated.',
                { path: evidenceBaseRoot, causeCode: error.code },
            );
        }
        const paths = [];
        for (const entry of entries) {
            if (!entry.isDirectory()) continue;
            const path = join(evidenceBaseRoot, entry.name, 'finalization-transaction.json');
            if (await exists(path)) paths.push(path);
        }
        return paths.sort();
    }

    async function persistState(state) {
        validateCandidateTransactionState(state, stateValidationConfig);
        await writeJsonAtomic(globalStatePath, state);
        await checkpoint('after-global-state-write-before-run-snapshot', state);
        await writeJsonAtomic(runStatePath(state.runId), state);
        return state;
    }

    async function transition(state, phase, patch = {}, details = {}) {
        const at = timestamp(now);
        const sequence = (state.sequence ?? 0) + 1;
        const { snapshotChecksum: previousSnapshotChecksum = null, ...base } = state;
        const next = {
            ...base,
            ...patch,
            schema: CANDIDATE_TRANSACTION_SCHEMA,
            version: 2,
            sequence,
            phase,
            updatedAt: at,
            previousSnapshotChecksum,
            events: [
                ...(state.events ?? []),
                {
                    sequence,
                    phase,
                    at,
                    ...details,
                },
            ],
        };
        next.snapshotChecksum = stateSnapshotChecksum(next);
        await persistState(next);
        await checkpoint(`phase:${phase}`, next);
        return next;
    }

    async function loadState({
        lock = null,
        repairRunSnapshot = false,
        requireMirrored = false,
    } = {}) {
        const state = await readStateSnapshot(
            globalStatePath,
            'Active candidate transaction snapshot',
        );
        if (!state) {
            const orphanedRunSnapshots = await runSnapshotPaths();
            if (orphanedRunSnapshots.length) {
                throw errorWithCode(
                    'candidate-transaction-global-state-missing',
                    'Run transaction snapshots exist without the authoritative active snapshot.',
                    { runSnapshotPaths: orphanedRunSnapshots },
                );
            }
            return null;
        }
        const perRunPath = runStatePath(state.runId);
        const perRun = await readStateSnapshot(
            perRunPath,
            'Per-run candidate transaction snapshot',
        );
        if (perRun && canonicalJson(perRun) === canonicalJson(state)) return state;

        const provableOneWriteTear = Boolean(
            perRun
            && perRun.transactionId === state.transactionId
            && perRun.runId === state.runId
            && state.sequence === perRun.sequence + 1
            && state.previousSnapshotChecksum === perRun.snapshotChecksum
        );
        if (!perRun || provableOneWriteTear) {
            if (repairRunSnapshot && lock) {
                await assertLock(lock);
                await writeJsonAtomic(perRunPath, state);
                return state;
            }
            if (requireMirrored) {
                throw errorWithCode(
                    'candidate-transaction-run-snapshot-torn',
                    'The authoritative active state is ahead of its per-run snapshot.',
                    { state, perRun, perRunPath },
                );
            }
            return state;
        }
        throw errorWithCode(
            'candidate-transaction-snapshots-diverged',
            'Active and per-run transaction snapshots diverged without a provable global-first write tear.',
            { state, perRun },
        );
    }

    async function readLockOwner(root = lockRoot, label = 'Candidate lock owner') {
        const owner = await readJsonOptional(ownerPath(root), {
            invalidCode: 'candidate-lock-owner-json-invalid',
            readCode: 'candidate-lock-owner-read-failed',
            label,
        });
        if (!owner) {
            throw errorWithCode(
                'candidate-lock-owner-missing',
                `${label} is missing; preserving the lock directory.`,
                { path: ownerPath(root) },
            );
        }
        return validateLockOwnerRecord(owner, label);
    }

    async function assertLock(lock) {
        if (!isHash(lock?.token) || !isSafeRunId(lock?.runId)) {
            throw errorWithCode('candidate-lock-handle-invalid', 'Candidate lock handle is incomplete.');
        }
        const owner = await readLockOwner();
        if (
            owner.token !== lock.token
            || owner.runId !== lock.runId
            || (lock.owner && canonicalJson(owner) !== canonicalJson(lock.owner))
        ) {
            throw errorWithCode(
                'candidate-lock-ownership-lost',
                'Candidate finalization lock ownership changed or disappeared.',
                { owner, lock },
            );
        }
        return owner;
    }

    function snapshotRow(snapshot, targetPid) {
        const row = rowFromSnapshot(snapshot, targetPid);
        return row
            ? { ...row, provider: snapshot?.provider ?? 'injected-process-snapshot' }
            : null;
    }

    function captureProcessSnapshot(message) {
        try {
            return processSnapshot();
        } catch (error) {
            throw errorWithCode(
                'candidate-lock-owner-indeterminate',
                `${message}: ${error.message}`,
            );
        }
    }

    async function currentIdentity() {
        const snapshot = captureProcessSnapshot('Cannot establish the finalizer process identity');
        const row = snapshotRow(snapshot, pid);
        if (!row) {
            throw errorWithCode(
                'candidate-process-identity-unavailable',
                `The finalizer PID ${pid} was absent from the process identity snapshot.`,
            );
        }
        const creationTimeNormalized = normalizeProcessCreationTime(row.creationTime);
        if (!creationTimeNormalized) {
            throw errorWithCode(
                'candidate-process-identity-inexact',
                'The process provider did not supply a normalizable creation identity; stale-lock recovery must fail closed.',
            );
        }
        return {
            pid,
            creationTime: row.creationTime,
            creationTimeNormalized,
            executableName: row.executableName ?? basename(executablePath),
            provider: snapshot.provider ?? 'injected-process-snapshot',
        };
    }

    function remnantDescriptor(name) {
        const acquiringPrefix = `${basename(lockRoot)}.acquiring.`;
        const stalePrefix = `${basename(lockRoot)}.stale.`;
        if (name.startsWith(acquiringPrefix)) {
            const suffix = name.slice(acquiringPrefix.length);
            const match = suffix.match(/^(\d+)\.([a-f0-9]{12})\.(\d+)$/);
            return match
                ? { kind: 'acquiring', pid: Number(match[1]), tokenPrefix: match[2] }
                : { kind: 'acquiring', malformed: true };
        }
        if (name.startsWith(stalePrefix)) {
            const suffix = name.slice(stalePrefix.length);
            return /^\d+\.\d+\.[a-f0-9]{10}$/.test(suffix)
                ? { kind: 'stale' }
                : { kind: 'stale', malformed: true };
        }
        return null;
    }

    async function lockRemnantRoots() {
        let entries;
        try {
            entries = await readdir(dirname(lockRoot), { withFileTypes: true });
        } catch (error) {
            throw errorWithCode(
                'candidate-lock-remnant-scan-failed',
                'Candidate lock sibling remnants could not be enumerated.',
                { path: dirname(lockRoot), causeCode: error.code },
            );
        }
        return entries
            .map((entry) => ({
                entry,
                descriptor: remnantDescriptor(entry.name),
                path: join(dirname(lockRoot), entry.name),
            }))
            .filter((item) => item.descriptor)
            .sort((left, right) => left.entry.name.localeCompare(right.entry.name));
    }

    async function classifyExactOwner(owner, message) {
        const snapshot = captureProcessSnapshot(message);
        return exactProcessIdentityStatus(
            owner,
            snapshotRow(snapshot, owner.processIdentity.pid),
        );
    }

    async function adoptLockRemnants(lock) {
        await assertLock(lock);
        for (const remnant of await lockRemnantRoots()) {
            if (remnant.descriptor.malformed) {
                throw errorWithCode(
                    'candidate-lock-remnant-name-invalid',
                    `Candidate lock remnant has a malformed owned name: ${remnant.entry.name}`,
                );
            }
            let metadata;
            try {
                metadata = await lstat(remnant.path);
            } catch (error) {
                if (error.code === 'ENOENT') continue;
                throw error;
            }
            if (metadata.isSymbolicLink() || !metadata.isDirectory()) {
                throw errorWithCode(
                    'candidate-lock-remnant-type-invalid',
                    `Candidate lock remnant is not an owned physical directory: ${remnant.path}`,
                );
            }
            const owner = await readLockOwner(remnant.path, 'Candidate lock remnant owner');
            if (
                remnant.descriptor.kind === 'acquiring'
                && (
                    owner.processIdentity.pid !== remnant.descriptor.pid
                    || !owner.token.startsWith(remnant.descriptor.tokenPrefix)
                )
            ) {
                throw errorWithCode(
                    'candidate-lock-remnant-owner-mismatch',
                    `Acquiring remnant name and owner identity disagree: ${remnant.path}`,
                    { owner },
                );
            }
            const status = await classifyExactOwner(
                owner,
                'Cannot inspect a candidate lock remnant owner',
            );
            if (status !== 'dead') {
                throw errorWithCode(
                    status === 'live'
                        ? 'candidate-lock-remnant-owner-live'
                        : 'candidate-lock-remnant-owner-indeterminate',
                    `Candidate ${remnant.descriptor.kind} remnant is not provably dead.`,
                    { path: remnant.path, owner, ownerStatus: status },
                );
            }
            const portable = portablePath(workspace, remnant.path);
            const existing = lock.staleLocks.find((item) => item.path === portable);
            const adopted = {
                path: portable,
                owner,
                ownerStatus: 'dead',
                remnantKind: remnant.descriptor.kind,
                adoptedAt: timestamp(now),
            };
            if (existing) Object.assign(existing, adopted);
            else lock.staleLocks.push(adopted);
        }
        return lock.staleLocks;
    }

    async function acquireLock(runId) {
        requireSafeRunId(runId);
        const token = sha256(`${runId}|${pid}|${timestamp(now)}|${randomBytes(16).toString('hex')}`);
        const staleLocks = [];
        const processIdentity = await currentIdentity();
        const owner = {
            schema: CANDIDATE_TRANSACTION_SCHEMA,
            token,
            runId,
            acquiredAt: timestamp(now),
            processIdentity,
        };
        validateLockOwnerRecord(owner);
        for (let attempt = 0; attempt < 20; attempt += 1) {
            const preparedLockRoot = `${lockRoot}.acquiring.${pid}.${token.slice(0, 12)}.${attempt}`;
            let preparedCreated = false;
            let acquired = false;
            try {
                await mkdir(preparedLockRoot);
                preparedCreated = true;
                await writeJsonAtomic(join(preparedLockRoot, 'owner.json'), owner);
                await rename(preparedLockRoot, lockRoot);
                acquired = true;
            } catch (error) {
                if (preparedCreated && await exists(preparedLockRoot)) {
                    const preparedOwner = await readLockOwner(
                        preparedLockRoot,
                        'Prepared candidate lock owner',
                    );
                    if (canonicalJson(preparedOwner) !== canonicalJson(owner)) {
                        throw errorWithCode(
                            'candidate-prepared-lock-ownership-lost',
                            'Prepared candidate lock owner changed; preserving it.',
                            { preparedOwner, owner },
                        );
                    }
                    await rm(preparedLockRoot, { recursive: true, force: false });
                }
                if (!await exists(lockRoot)) throw error;
                const existingOwner = await readLockOwner();
                const status = exactProcessIdentityStatus(
                    existingOwner,
                    snapshotRow(
                        captureProcessSnapshot('Cannot inspect the existing candidate lock owner'),
                        existingOwner.processIdentity.pid,
                    ),
                );
                if (status === 'live') {
                    throw errorWithCode(
                        'candidate-finalization-lock-live',
                        `Candidate finalization is owned by live PID ${existingOwner.processIdentity.pid}.`,
                        { owner: existingOwner },
                    );
                }
                if (status !== 'dead') {
                    throw errorWithCode(
                        'candidate-lock-owner-indeterminate',
                        'Existing candidate lock lacks exact creation identity; refusing stale takeover.',
                        { owner: existingOwner, ownerStatus: status },
                    );
                }
                const quarantine = `${lockRoot}.stale.${Date.now()}.${pid}.${randomBytes(5).toString('hex')}`;
                const recheckedOwner = await readLockOwner();
                if (canonicalJson(recheckedOwner) !== canonicalJson(existingOwner)) {
                    throw errorWithCode(
                        'candidate-lock-owner-changed-before-quarantine',
                        'Candidate lock owner changed before stale quarantine; preserving it.',
                        { existingOwner, recheckedOwner },
                    );
                }
                await rename(lockRoot, quarantine);
                staleLocks.push({
                    path: portablePath(workspace, quarantine),
                    owner: existingOwner,
                    ownerStatus: 'dead',
                    remnantKind: 'stale',
                    quarantinedAt: timestamp(now),
                });
                continue;
            }
            if (acquired) {
                const lock = {
                    token,
                    runId,
                    owner,
                    staleLocks,
                    acquired: true,
                };
                try {
                    await adoptLockRemnants(lock);
                } catch (error) {
                    await assertLock(lock);
                    await rm(lockRoot, { recursive: true, force: false });
                    throw error;
                }
                await checkpoint('lock-acquired', lock);
                return lock;
            }
        }
        throw errorWithCode(
            'candidate-finalization-lock-contention',
            'Could not acquire candidate finalization lock after bounded stale-owner reconciliation.',
        );
    }

    function pathsFromState(state) {
        validateCandidateTransactionState(state, stateValidationConfig);
        const paths = {};
        for (const [name, portable] of Object.entries(state.paths ?? {})) {
            paths[name] = resolveOwnedPath(workspace, evidence, portable);
        }
        return paths;
    }

    async function requireCurrent(lock, expected) {
        await assertLock(lock);
        const current = await loadState({ lock, repairRunSnapshot: true });
        if (
            !current
            || current.transactionId !== expected.transactionId
            || current.runId !== expected.runId
            || current.sequence !== expected.sequence
            || current.snapshotChecksum !== expected.snapshotChecksum
        ) {
            throw errorWithCode(
                'candidate-transaction-state-changed',
                'Candidate transaction state changed while the lock was held.',
                { current, expected },
            );
        }
        return current;
    }

    async function ownedRemnantsWithoutState() {
        const parent = dirname(candidate);
        const name = basename(candidate);
        const remnants = [];
        let candidateSiblings;
        let runs;
        try {
            candidateSiblings = await readdir(parent, { withFileTypes: true });
            runs = await readdir(evidenceBaseRoot, { withFileTypes: true });
        } catch (error) {
            throw errorWithCode(
                'candidate-remnant-scan-failed',
                'Candidate transaction remnants could not be enumerated safely.',
                { causeCode: error.code },
            );
        }
        for (const entry of candidateSiblings) {
            if (
                entry.name.startsWith(`${name}.rollback.`)
                || (entry.name.startsWith(`${name}.`) && entry.name.endsWith('.tmp'))
            ) remnants.push(portablePath(workspace, join(parent, entry.name)));
        }
        for (const run of runs) {
            if (!run.isDirectory() || !run.name.startsWith('be6a1-')) continue;
            const pending = join(evidenceBaseRoot, run.name, 'final-result.pending.json');
            if (await exists(pending)) remnants.push(portablePath(workspace, pending));
        }
        return remnants.sort();
    }

    async function begin(lock, {
        runId,
        temporaryRoot,
        replacementIdentity,
        packageChecksum,
        candidateManifestChecksum,
        pendingFinalPath,
        finalPath,
    }) {
        await assertLock(lock);
        requireSafeRunId(runId);
        if (lock.runId !== runId) {
            throw errorWithCode('candidate-lock-run-mismatch', 'Lock run ID differs from transaction run ID.');
        }
        const expectedTemporaryRoot = `${candidate}.${runId}.tmp`;
        const expectedPendingFinalPath = join(
            evidenceBaseRoot,
            runId,
            'final-result.pending.json',
        );
        const expectedFinalPath = join(evidenceBaseRoot, runId, 'final-result.json');
        if (
            resolve(temporaryRoot) !== expectedTemporaryRoot
            || resolve(pendingFinalPath) !== expectedPendingFinalPath
            || resolve(finalPath) !== expectedFinalPath
        ) {
            throw errorWithCode(
                'candidate-transaction-begin-path-invalid',
                'Candidate transaction begin paths differ from the deterministic run paths.',
                {
                    expectedTemporaryRoot,
                    expectedPendingFinalPath,
                    expectedFinalPath,
                    temporaryRoot: resolve(temporaryRoot),
                    pendingFinalPath: resolve(pendingFinalPath),
                    finalPath: resolve(finalPath),
                },
            );
        }
        validatePhysicalIdentityRecord(
            replacementIdentity,
            'Replacement candidate identity',
            { requiredPresent: true },
        );
        const existing = await loadState({ lock, repairRunSnapshot: true });
        if (existing && !transactionResolved(existing)) {
            throw errorWithCode(
                'candidate-active-transaction-present',
                `Active candidate transaction ${existing.transactionId} must be reconciled first.`,
                { existing },
            );
        }
        const temporaryIdentity = await physicalTreeIdentity(temporaryRoot);
        if (!samePhysicalTreeIdentity(temporaryIdentity, replacementIdentity)) {
            throw errorWithCode(
                'candidate-temporary-identity-mismatch',
                'Built candidate identity changed before the transaction began.',
            );
        }
        if (
            replacementIdentity.runId !== runId
            || replacementIdentity.packageChecksum !== packageChecksum
            || replacementIdentity.candidateManifestChecksum !== candidateManifestChecksum
        ) {
            throw errorWithCode(
                'candidate-replacement-manifest-mismatch',
                'Replacement candidate manifest identity does not match the transaction.',
            );
        }
        const previousCandidateIdentity = await physicalTreeIdentity(candidate);
        const transactionId = sha256(
            `${runId}|${lock.token}|${replacementIdentity.treeChecksum}|${timestamp(now)}`,
        );
        const backupRoot = `${candidate}.rollback.${runId}.${transactionId.slice(0, 12)}`;
        const previousLabel = safePathPart(
            previousCandidateIdentity.runId
            ?? `legacy-${previousCandidateIdentity.treeChecksum?.slice(0, 12) ?? 'absent'}`,
        );
        const archiveRoot = join(
            dirname(candidate),
            'be6a1-baseline-candidate-archive',
            `${previousLabel}-replaced-by-${safePathPart(runId)}-${transactionId.slice(0, 12)}`,
        );
        for (const occupied of [backupRoot, archiveRoot]) {
            if (await exists(occupied)) {
                throw errorWithCode(
                    'candidate-recovery-path-occupied',
                    `Candidate transaction recovery path already exists: ${portablePath(workspace, occupied)}`,
                );
            }
        }
        const at = timestamp(now);
        const state = {
            schema: CANDIDATE_TRANSACTION_SCHEMA,
            version: 2,
            transactionId,
            runId,
            sequence: 0,
            phase: 'initializing',
            outcome: null,
            commitPoint: 'canonical-final-rename',
            commitPointReached: false,
            startedAt: at,
            updatedAt: at,
            owner: lock.owner,
            paths: {
                candidateRoot: portablePath(workspace, candidate),
                temporaryRoot: portablePath(workspace, temporaryRoot),
                backupRoot: portablePath(workspace, backupRoot),
                archiveRoot: portablePath(workspace, archiveRoot),
                pendingFinalPath: portablePath(workspace, pendingFinalPath),
                finalPath: portablePath(workspace, finalPath),
                lockRoot: portablePath(workspace, lockRoot),
                activeStatePath: portablePath(workspace, globalStatePath),
                runStatePath: portablePath(workspace, runStatePath(runId)),
            },
            previousCandidateIdentity,
            replacementCandidateIdentity: replacementIdentity,
            packageChecksum,
            candidateManifestChecksum,
            pendingAggregateChecksum: null,
            canonicalFinalChecksum: null,
            previousCandidateArchive: null,
            recovery: {
                adoptedStaleLocks: lock.staleLocks,
                attempts: [],
                blocked: false,
            },
            cleanup: {
                temporaryRemoved: false,
                backupRemoved: false,
                pendingRemoved: false,
                staleLocksRemoved: [],
            },
            events: [],
        };
        return await transition(state, 'transaction-started', {}, {
            previousCandidateIdentity,
            replacementCandidateIdentity: replacementIdentity,
        });
    }

    async function install(lock, expected) {
        let state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        let candidateIdentity = await physicalTreeIdentity(paths.candidateRoot);
        let backupIdentity = await physicalTreeIdentity(paths.backupRoot);

        if (state.previousCandidateIdentity.present) {
            if (
                samePhysicalTreeIdentity(candidateIdentity, state.previousCandidateIdentity)
                && !backupIdentity.present
            ) {
                await rename(paths.candidateRoot, paths.backupRoot);
                await checkpoint('after-previous-candidate-backup-rename-before-journal', state);
                backupIdentity = await physicalTreeIdentity(paths.backupRoot);
                candidateIdentity = await physicalTreeIdentity(paths.candidateRoot);
            }
            if (
                !samePhysicalTreeIdentity(backupIdentity, state.previousCandidateIdentity)
                || candidateIdentity.present
            ) {
                throw errorWithCode(
                    'candidate-previous-backup-ambiguous',
                    'Previous candidate was not backed up with its exact physical identity.',
                );
            }
            state = await transition(state, 'previous-candidate-backed-up');
        } else if (candidateIdentity.present) {
            if (!samePhysicalTreeIdentity(candidateIdentity, state.replacementCandidateIdentity)) {
                throw errorWithCode(
                    'candidate-root-unexpected-before-install',
                    'Candidate root is occupied by an unknown physical identity.',
                );
            }
        }

        candidateIdentity = await physicalTreeIdentity(paths.candidateRoot);
        if (!candidateIdentity.present) {
            const temporaryIdentity = await physicalTreeIdentity(paths.temporaryRoot);
            if (!samePhysicalTreeIdentity(temporaryIdentity, state.replacementCandidateIdentity)) {
                throw errorWithCode(
                    'candidate-temporary-identity-changed',
                    'Temporary candidate changed before installation.',
                );
            }
            await rename(paths.temporaryRoot, paths.candidateRoot);
            await checkpoint('after-candidate-install-rename-before-journal', state);
            candidateIdentity = await physicalTreeIdentity(paths.candidateRoot);
        }
        if (!samePhysicalTreeIdentity(candidateIdentity, state.replacementCandidateIdentity)) {
            throw errorWithCode(
                'candidate-installed-identity-mismatch',
                'Installed candidate differs from the validated replacement package.',
            );
        }
        return await transition(state, 'candidate-installed', {
            installedAt: timestamp(now),
        });
    }

    async function markInstalledValidated(lock, expected, details = {}) {
        const state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        if (!samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)) {
            throw errorWithCode(
                'candidate-installed-validation-identity-mismatch',
                'Installed candidate changed before validation was recorded.',
            );
        }
        return await transition(state, 'candidate-installed-validated', {
            installedValidation: {
                passed: true,
                recordedAt: timestamp(now),
                ...details,
            },
        });
    }

    async function prepareCommit(lock, expected, { pendingAggregateChecksum }) {
        const state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        if (state.phase !== 'candidate-installed-validated') {
            throw errorWithCode(
                'candidate-commit-phase-invalid',
                `Cannot prepare commit from phase ${state.phase}.`,
            );
        }
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        if (!samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)) {
            throw errorWithCode(
                'candidate-identity-changed-before-commit',
                'Installed candidate changed before commit preparation.',
            );
        }
        if (await fileChecksum(paths.pendingFinalPath) !== pendingAggregateChecksum) {
            throw errorWithCode(
                'candidate-pending-aggregate-checksum-mismatch',
                'Pending aggregate checksum changed before commit preparation.',
            );
        }
        if (state.previousCandidateIdentity.present) {
            const backup = await physicalTreeIdentity(paths.backupRoot);
            if (!samePhysicalTreeIdentity(backup, state.previousCandidateIdentity)) {
                throw errorWithCode(
                    'candidate-backup-identity-changed',
                    'Previous candidate backup changed before commit preparation.',
                );
            }
        }
        return await transition(state, 'commit-prepared', {
            pendingAggregateChecksum,
            commitPreparedAt: timestamp(now),
        });
    }

    async function publishCanonical(lock, expected) {
        let state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        if (state.phase !== 'commit-prepared') {
            throw errorWithCode(
                'candidate-publication-phase-invalid',
                `Cannot publish canonical aggregate from phase ${state.phase}.`,
            );
        }
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        if (!samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)) {
            throw errorWithCode(
                'candidate-final-prepublication-identity-mismatch',
                'Candidate changed immediately before canonical publication.',
            );
        }
        if (await fileChecksum(paths.pendingFinalPath) !== state.pendingAggregateChecksum) {
            throw errorWithCode(
                'candidate-final-prepublication-aggregate-mismatch',
                'Pending aggregate changed immediately before canonical publication.',
            );
        }
        await rename(paths.pendingFinalPath, paths.finalPath);
        await checkpoint('after-canonical-final-rename-before-journal', state);
        const canonicalFinalChecksum = await fileChecksum(paths.finalPath);
        if (canonicalFinalChecksum !== state.pendingAggregateChecksum) {
            throw errorWithCode(
                'candidate-canonical-final-checksum-mismatch',
                'Canonical final checksum differs after atomic publication.',
            );
        }
        state = await transition(state, 'canonical-final-published', {
            commitPointReached: true,
            canonicalFinalChecksum,
            canonicalPublishedAt: timestamp(now),
        });
        return state;
    }

    async function removeOwnedTemporary(path, expectedIdentity, label) {
        const identity = await physicalTreeIdentity(path);
        if (!identity.present) return false;
        if (!samePhysicalTreeIdentity(identity, expectedIdentity)) {
            throw errorWithCode(
                'candidate-owned-cleanup-identity-mismatch',
                `${label} has an unexpected physical identity; preserving it.`,
            );
        }
        await rm(path, { recursive: true, force: false });
        return true;
    }

    async function pendingAggregateOwnedByState(state, path) {
        if (!await exists(path)) return true;
        if (
            state.pendingAggregateChecksum
            && await fileChecksum(path) === state.pendingAggregateChecksum
        ) return true;
        const aggregate = await readJsonOptional(path);
        return Boolean(
            aggregate?.candidateFinalization?.transactionId === state.transactionId
            || aggregate?.finalizationTransaction?.transactionId === state.transactionId
        );
    }

    async function completeCommit(lock, expected) {
        let state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        const finalChecksum = await fileChecksum(paths.finalPath);
        if (
            !samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)
            || finalChecksum !== state.pendingAggregateChecksum
        ) {
            throw errorWithCode(
                'candidate-postcommit-state-ambiguous',
                'Canonical candidate/final aggregate does not prove the commit point.',
            );
        }
        let previousCandidateArchive = state.previousCandidateArchive;
        if (state.previousCandidateIdentity.present) {
            const backup = await physicalTreeIdentity(paths.backupRoot);
            const archive = await physicalTreeIdentity(paths.archiveRoot);
            if (backup.present && archive.present) {
                if (
                    !samePhysicalTreeIdentity(backup, state.previousCandidateIdentity)
                    || !samePhysicalTreeIdentity(archive, state.previousCandidateIdentity)
                ) {
                    throw errorWithCode(
                        'candidate-postcommit-archive-ambiguous',
                        'Backup/archive collision contains an unexpected candidate identity.',
                    );
                }
                await rm(paths.backupRoot, { recursive: true, force: false });
            } else if (backup.present) {
                if (!samePhysicalTreeIdentity(backup, state.previousCandidateIdentity)) {
                    throw errorWithCode(
                        'candidate-postcommit-backup-identity-mismatch',
                        'Previous candidate backup changed after commit.',
                    );
                }
                await mkdir(dirname(paths.archiveRoot), { recursive: true });
                await rename(paths.backupRoot, paths.archiveRoot);
                await checkpoint('after-previous-candidate-archive-rename-before-journal', state);
            } else if (!samePhysicalTreeIdentity(archive, state.previousCandidateIdentity)) {
                throw errorWithCode(
                    'candidate-postcommit-archive-missing',
                    'Committed transaction lost the previous candidate recovery package.',
                );
            }
            previousCandidateArchive = portablePath(workspace, paths.archiveRoot);
        }

        const temporaryRemoved = await removeOwnedTemporary(
            paths.temporaryRoot,
            state.replacementCandidateIdentity,
            'Temporary candidate',
        );
        let pendingRemoved = false;
        if (await exists(paths.pendingFinalPath)) {
            if (await fileChecksum(paths.pendingFinalPath) !== state.pendingAggregateChecksum) {
                throw errorWithCode(
                    'candidate-pending-cleanup-checksum-mismatch',
                    'Unexpected pending aggregate was preserved after commit.',
                );
            }
            await rm(paths.pendingFinalPath, { force: false });
            pendingRemoved = true;
        }
        state = await transition(state, 'committed', {
            outcome: 'committed',
            commitPointReached: true,
            canonicalFinalChecksum: finalChecksum,
            previousCandidateArchive,
            committedAt: timestamp(now),
            cleanup: {
                ...state.cleanup,
                temporaryRemoved: state.cleanup?.temporaryRemoved || temporaryRemoved,
                backupRemoved: !await exists(paths.backupRoot),
                pendingRemoved: state.cleanup?.pendingRemoved || pendingRemoved,
            },
        });
        return state;
    }

    async function rollback(lock, expected, reason = 'pre-commit failure') {
        let state = await requireCurrent(lock, expected);
        const paths = pathsFromState(state);
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        const finalChecksum = await fileChecksum(paths.finalPath);
        if (
            state.pendingAggregateChecksum
            && finalChecksum === state.pendingAggregateChecksum
            && samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)
        ) {
            throw errorWithCode(
                'candidate-rollback-after-commit-prohibited',
                'Canonical final is physically committed; recovery must roll forward.',
            );
        }
        try {
            let replacementRemoved = false;
            let current = installed;
            if (samePhysicalTreeIdentity(current, state.replacementCandidateIdentity)) {
                await rm(paths.candidateRoot, { recursive: true, force: false });
                replacementRemoved = true;
                await checkpoint('after-installed-candidate-removal-before-restore', state);
                current = await physicalTreeIdentity(paths.candidateRoot);
            }
            let previousRestored = false;
            if (state.previousCandidateIdentity.present) {
                if (samePhysicalTreeIdentity(current, state.previousCandidateIdentity)) {
                    previousRestored = true;
                } else if (!current.present) {
                    const backup = await physicalTreeIdentity(paths.backupRoot);
                    const archive = await physicalTreeIdentity(paths.archiveRoot);
                    const recoveryPath = samePhysicalTreeIdentity(
                        backup,
                        state.previousCandidateIdentity,
                    )
                        ? paths.backupRoot
                        : (samePhysicalTreeIdentity(archive, state.previousCandidateIdentity)
                            ? paths.archiveRoot
                            : null);
                    if (!recoveryPath) {
                        throw errorWithCode(
                            'candidate-rollback-recovery-package-missing',
                            'Exact previous candidate backup/archive is unavailable.',
                        );
                    }
                    await rename(recoveryPath, paths.candidateRoot);
                    await checkpoint('after-previous-candidate-restore-before-journal', state);
                    previousRestored = samePhysicalTreeIdentity(
                        await physicalTreeIdentity(paths.candidateRoot),
                        state.previousCandidateIdentity,
                    );
                } else {
                    throw errorWithCode(
                        'candidate-rollback-root-occupied',
                        'Candidate root contains an unknown identity; rollback preserved it.',
                    );
                }
                if (!previousRestored) {
                    throw errorWithCode(
                        'candidate-rollback-restore-failed',
                        'Previous candidate was not restored exactly.',
                    );
                }
            } else if (current.present) {
                throw errorWithCode(
                    'candidate-rollback-unexpected-root',
                    'Candidate root remains occupied although no previous candidate existed.',
                );
            }

            const temporaryRemoved = await removeOwnedTemporary(
                paths.temporaryRoot,
                state.replacementCandidateIdentity,
                'Temporary candidate',
            );
            let pendingRemoved = false;
            if (await exists(paths.pendingFinalPath)) {
                if (!await pendingAggregateOwnedByState(state, paths.pendingFinalPath)) {
                    throw errorWithCode(
                        'candidate-rollback-pending-identity-mismatch',
                        'Unknown pending aggregate was preserved during rollback.',
                    );
                }
                await rm(paths.pendingFinalPath, { force: false });
                pendingRemoved = true;
            }
            state = await transition(state, 'rolled-back', {
                outcome: 'rolled-back',
                commitPointReached: false,
                rolledBackAt: timestamp(now),
                rollbackReason: reason,
                cleanup: {
                    ...state.cleanup,
                    temporaryRemoved: state.cleanup?.temporaryRemoved || temporaryRemoved,
                    backupRemoved: !await exists(paths.backupRoot),
                    pendingRemoved: state.cleanup?.pendingRemoved || pendingRemoved,
                    replacementRemoved,
                },
            });
            return state;
        } catch (error) {
            state = await transition(state, 'recovery-blocked', {
                outcome: null,
                recovery: {
                    ...state.recovery,
                    blocked: true,
                    blockedAt: timestamp(now),
                    blockCode: error.code ?? 'candidate-recovery-unclassified',
                    blockMessage: error.message,
                },
            }).catch(() => state);
            error.transactionState = state;
            throw error;
        }
    }

    async function reconcile(lock, { validateCanonicalFinal = async () => true } = {}) {
        await assertLock(lock);
        let state = await loadState({ lock, repairRunSnapshot: true });
        if (!state) {
            const remnants = await ownedRemnantsWithoutState();
            const staleRunIds = new Set(
                (lock.staleLocks ?? [])
                    .map((item) => item.owner?.runId)
                    .filter((runId) => typeof runId === 'string' && runId !== ''),
            );
            const removableTemporaryPaths = new Set(
                [...staleRunIds].map((runId) =>
                    portablePath(workspace, `${candidate}.${runId}.tmp`)),
            );
            const cleanedOwnedTemporaryPaths = [];
            const unresolvedRemnants = [];
            for (const portable of remnants) {
                if (removableTemporaryPaths.has(portable)) {
                    const path = resolveOwnedPath(workspace, evidence, portable);
                    await rm(path, { recursive: true, force: false });
                    cleanedOwnedTemporaryPaths.push(portable);
                } else {
                    unresolvedRemnants.push(portable);
                }
            }
            if (unresolvedRemnants.length) {
                throw errorWithCode(
                    'candidate-orphaned-transaction-remnants',
                    'Candidate transaction remnants exist without a durable transaction snapshot.',
                    {
                        remnants: unresolvedRemnants,
                        cleanedOwnedTemporaryPaths,
                    },
                );
            }
            return {
                outcome: 'clean',
                state: null,
                recovered: cleanedOwnedTemporaryPaths.length > 0,
                cleanedOwnedTemporaryPaths,
            };
        }
        if (state.phase === 'lock-released' && state.outcome === 'committed') {
            const paths = pathsFromState(state);
            const installed = await physicalTreeIdentity(paths.candidateRoot);
            const archive = state.previousCandidateIdentity.present
                ? await physicalTreeIdentity(paths.archiveRoot)
                : { present: false };
            const finalChecksum = await fileChecksum(paths.finalPath);
            const validation = await validateCanonicalFinal(state);
            const clean = Boolean(
                samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)
                && finalChecksum === state.pendingAggregateChecksum
                && !await exists(paths.temporaryRoot)
                && !await exists(paths.backupRoot)
                && !await exists(paths.pendingFinalPath)
                && (
                    !state.previousCandidateIdentity.present
                    || samePhysicalTreeIdentity(archive, state.previousCandidateIdentity)
                )
                && validationPassed(validation)
            );
            if (!clean) {
                throw errorWithCode(
                    'candidate-terminal-commit-physical-mismatch',
                    'Previously committed candidate transaction no longer matches physical evidence.',
                    { validation },
                );
            }
            return { outcome: 'committed', state, recovered: false, validation };
        }
        if (state.phase === 'lock-released' && state.outcome === 'rolled-back') {
            const paths = pathsFromState(state);
            const installed = await physicalTreeIdentity(paths.candidateRoot);
            const restored = state.previousCandidateIdentity.present
                ? samePhysicalTreeIdentity(installed, state.previousCandidateIdentity)
                : !installed.present;
            if (
                !restored
                || await exists(paths.temporaryRoot)
                || await exists(paths.backupRoot)
                || await exists(paths.pendingFinalPath)
            ) {
                throw errorWithCode(
                    'candidate-terminal-rollback-physical-mismatch',
                    'Previously rolled-back transaction retained or changed owned physical state.',
                );
            }
            return { outcome: 'rolled-back', state, recovered: false };
        }
        state = await transition(state, 'recovery-started', {
            owner: lock.owner,
            recovery: {
                ...state.recovery,
                attempts: [
                    ...(state.recovery?.attempts ?? []),
                    {
                        at: timestamp(now),
                        recoveringRunId: lock.runId,
                        staleLocks: lock.staleLocks,
                    },
                ],
            },
        });
        const paths = pathsFromState(state);
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        const finalChecksum = await fileChecksum(paths.finalPath);
        const physicalCommit = Boolean(
            state.pendingAggregateChecksum
            && finalChecksum === state.pendingAggregateChecksum
            && samePhysicalTreeIdentity(installed, state.replacementCandidateIdentity)
        );
        if (physicalCommit || state.commitPointReached) {
            if (!physicalCommit) {
                throw errorWithCode(
                    'candidate-recorded-commit-physical-mismatch',
                    'Transaction records a commit point but canonical files do not prove it.',
                );
            }
            const validation = await validateCanonicalFinal(state);
            if (!validationPassed(validation)) {
                throw errorWithCode(
                    'candidate-recovered-final-validation-failed',
                    'Committed canonical final failed behavioral validation during recovery.',
                    { validation },
                );
            }
            state = await completeCommit(lock, state);
            return { outcome: 'committed', state, recovered: true, validation };
        }
        state = await rollback(lock, state, 'hard-crash reconciliation before canonical commit');
        return { outcome: 'rolled-back', state, recovered: true };
    }

    async function recheckAndRemoveLockRemnant(lock, stale) {
        const path = resolveOwnedPath(workspace, evidence, stale.path);
        const descriptor = dirname(path) === dirname(lockRoot)
            ? remnantDescriptor(basename(path))
            : null;
        if (!descriptor || descriptor.malformed) {
            throw errorWithCode(
                'candidate-lock-remnant-cleanup-path-invalid',
                `Refusing to remove a non-canonical lock remnant path: ${stale.path}`,
            );
        }
        if (!await exists(path)) return false;
        const owner = await readLockOwner(path, 'Candidate lock cleanup owner');
        if (canonicalJson(owner) !== canonicalJson(stale.owner)) {
            throw errorWithCode(
                'candidate-lock-remnant-owner-changed',
                `Candidate lock remnant owner changed before removal: ${stale.path}`,
                { expectedOwner: stale.owner, owner },
            );
        }
        const status = await classifyExactOwner(
            owner,
            'Cannot recheck a candidate lock remnant owner before removal',
        );
        if (status !== 'dead') {
            throw errorWithCode(
                status === 'live'
                    ? 'candidate-lock-remnant-owner-revived'
                    : 'candidate-lock-remnant-owner-indeterminate',
                `Candidate lock remnant is not provably dead immediately before removal: ${stale.path}`,
                { owner, ownerStatus: status },
            );
        }
        await assertLock(lock);
        const finalOwner = await readLockOwner(path, 'Candidate lock cleanup owner');
        if (canonicalJson(finalOwner) !== canonicalJson(owner)) {
            throw errorWithCode(
                'candidate-lock-remnant-owner-changed',
                `Candidate lock remnant owner changed during removal recheck: ${stale.path}`,
                { owner, finalOwner },
            );
        }
        await rm(path, { recursive: true, force: false });
        return true;
    }

    async function releaseLock(lock, expected = null, { beforeRemoval } = {}) {
        await assertLock(lock);
        if (beforeRemoval !== undefined && typeof beforeRemoval !== 'function') {
            throw errorWithCode(
                'candidate-lock-before-removal-invalid',
                'Candidate lock beforeRemoval hook must be a function.',
            );
        }
        await adoptLockRemnants(lock);
        let state = expected
            ? await requireCurrent(lock, expected)
            : await loadState({ lock, repairRunSnapshot: true });
        const staleRecords = [...new Map(
            (lock.staleLocks ?? []).map((item) => [item.path, item]),
        ).values()];
        const staleLockCleanupPaths = staleRecords.map((item) => item.path).sort();
        if (state) {
            const releaseCommittedAt = timestamp(now);
            state = await transition(state, 'lock-released', {
                owner: {
                    ...lock.owner,
                    releaseCommittedAt,
                },
                outcome: state.outcome,
                lockRelease: {
                    committedAt: releaseCommittedAt,
                    canonicalRemovalRequired: true,
                    staleLockCleanupPaths,
                },
                cleanup: {
                    ...state.cleanup,
                    staleLocksExpectedAbsent: [
                        ...(state.cleanup?.staleLocksExpectedAbsent ?? []),
                        ...staleLockCleanupPaths,
                    ],
                    staleLocksRemoved: [
                        ...(state.cleanup?.staleLocksRemoved ?? []),
                        ...staleLockCleanupPaths,
                    ],
                },
            });
        }
        const removed = [];
        for (const stale of staleRecords) {
            if (await recheckAndRemoveLockRemnant(lock, stale)) removed.push(stale.path);
        }
        await assertLock(lock);
        if (beforeRemoval) await beforeRemoval(state);
        await assertLock(lock);
        await rm(lockRoot, { recursive: true, force: false });
        return { passed: true, state, removedStaleLocks: removed };
    }

    async function validateClosed(
        state = null,
        { validateCanonicalFinal = async () => true } = {},
    ) {
        const failures = [];
        let durable;
        try {
            durable = await loadState({ requireMirrored: true });
        } catch (error) {
            return {
                passed: false,
                failures: [`durable transaction snapshots invalid: ${error.code ?? error.message}`],
                state: state ?? null,
                validation: null,
            };
        }
        const current = state ?? durable;
        if (!current) {
            failures.push('durable transaction snapshot missing');
            return { passed: false, failures, state: null };
        }
        try {
            validateCandidateTransactionState(current, stateValidationConfig);
        } catch (error) {
            failures.push(`transaction state invalid: ${error.code ?? error.message}`);
            return { passed: false, failures, state: current, validation: null };
        }
        if (!durable || canonicalJson(durable) !== canonicalJson(current)) {
            failures.push('supplied state differs from durable transaction snapshots');
        }
        if (
            current.schema !== CANDIDATE_TRANSACTION_SCHEMA
            || current.phase !== 'lock-released'
            || current.outcome !== 'committed'
            || current.commitPointReached !== true
        ) failures.push('transaction is not committed and lock-released');
        const paths = pathsFromState(current);
        const installed = await physicalTreeIdentity(paths.candidateRoot);
        if (!samePhysicalTreeIdentity(installed, current.replacementCandidateIdentity)) {
            failures.push('installed candidate identity differs');
        }
        const finalChecksum = await fileChecksum(paths.finalPath);
        if (
            !current.pendingAggregateChecksum
            || finalChecksum !== current.pendingAggregateChecksum
            || current.canonicalFinalChecksum !== current.pendingAggregateChecksum
        ) failures.push('canonical final checksum differs');
        if (await exists(paths.lockRoot)) failures.push('candidate finalization lock remains');
        for (const remnant of await lockRemnantRoots()) {
            failures.push(`candidate lock ${remnant.descriptor.kind} remnant remains: ${portablePath(workspace, remnant.path)}`);
        }
        if (await exists(paths.temporaryRoot)) failures.push('temporary candidate remains');
        if (await exists(paths.backupRoot)) failures.push('rollback candidate remains');
        if (await exists(paths.pendingFinalPath)) failures.push('pending aggregate remains');
        for (const portable of current.cleanup?.staleLocksExpectedAbsent ?? []) {
            const stalePath = resolveOwnedPath(workspace, evidence, portable);
            if (await exists(stalePath)) failures.push(`stale lock remains: ${portable}`);
        }
        if (current.previousCandidateIdentity.present) {
            const archive = await physicalTreeIdentity(paths.archiveRoot);
            if (!samePhysicalTreeIdentity(archive, current.previousCandidateIdentity)) {
                failures.push('previous candidate archive identity differs');
            }
        }
        const validation = await validateCanonicalFinal(current);
        if (!validationPassed(validation)) failures.push('canonical final behavioral validation failed');
        return {
            passed: failures.length === 0,
            failures,
            state: current,
            validation,
        };
    }

    return {
        paths: {
            workspaceRoot: workspace,
            evidenceRoot: evidence,
            evidenceBase: resolve(evidenceBase),
            candidateRoot: candidate,
            candidateLockRoot: lockRoot,
            activeStatePath: globalStatePath,
        },
        acquireLock,
        begin,
        install,
        markInstalledValidated,
        prepareCommit,
        publishCanonical,
        completeCommit,
        rollback,
        reconcile,
        releaseLock,
        validateClosed,
        loadState,
        assertLock,
    };
}
