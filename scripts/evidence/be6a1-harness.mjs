import { constants as fsConstants } from 'node:fs';
import { fork } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
    access,
    readFile,
    readdir,
    stat,
} from 'node:fs/promises';
import { createServer } from 'node:net';
import {
    dirname,
    isAbsolute,
    join,
    relative,
    resolve,
    sep,
} from 'node:path';
import { fileURLToPath } from 'node:url';
import { PNG } from 'pngjs';
import {
    analyzeSubpixelMorphology,
    canonicalPng,
    compareRasterBuffers,
    isAdminHrefException,
} from './be6a1-fidelity-contract.mjs';
import {
    CANDIDATE_TRANSACTION_SCHEMA,
    physicalTreeIdentity,
    samePhysicalTreeIdentity,
    validateCandidateTransactionState,
} from './be6a1-candidate-transaction.mjs';

export const REQUIRED_STAGES = ['security', 'preview', 'visual', 'verify'];

export const REQUIRED_VISUAL_SUMMARIES = [
    'mime-preflight.json',
    'semantic-readiness-summary.json',
    'visual-stability-summary.json',
    'fidelity-repeatability-summary.json',
    'static-reference-checksums.json',
    'laravel-capture-checksums.json',
    'fidelity-diff-summary.json',
    'console-network-summary.json',
];

export const CANONICAL_PAGE_KEYS = [
    'homepage',
    'about',
    'collections',
    'shop',
    'preorder',
    'limited-edition',
    'gift-cards',
    'login',
    'wishlist',
    'taylor-oxford-shirt',
    'mercerized-cotton-polo',
    'dar-es-salaam-linen-suit',
    'slim-tapered-chinos',
    'executive-overcoat',
];

export const HOMEPAGE_VIEWPORTS = [
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
];

export const STANDARD_VIEWPORTS = [
    '375x812',
    '768x1024',
    '1023x900',
    '1024x900',
    '1279x900',
    '1280x900',
    '1440x900',
];

export const CANONICAL_GROUP_KEYS = CANONICAL_PAGE_KEYS.flatMap((page) =>
    (page === 'homepage' ? HOMEPAGE_VIEWPORTS : STANDARD_VIEWPORTS)
        .map((viewport) => `${page}|${viewport}`));

export const CANONICAL_CAPTURE_KEYS = CANONICAL_GROUP_KEYS.flatMap((group) =>
    [1, 2, 3].flatMap((run) => ['static', 'laravel'].map((side) => `${group}|${run}|${side}`)));

export const CANONICAL_DIFF_KEYS = CANONICAL_GROUP_KEYS.flatMap((group) =>
    [1, 2, 3].map((run) => `${group}|${run}`));

export const CANONICAL_READINESS_KEYS = CANONICAL_CAPTURE_KEYS.map((key) => {
    const [page, viewport, run, side] = key.split('|');
    return `${page}:${viewport}:run${run}:${side}`;
});

export const GEOMETRY_BOX_NAMES = Object.freeze([
    'header',
    'main',
    'footer',
    'desktop-navigation',
    'mobile-navigation',
]);

export function expectedCaptureContract(page, viewport, side) {
    const allowedViewports = page === 'homepage' ? HOMEPAGE_VIEWPORTS : STANDARD_VIEWPORTS;
    const width = Number(String(viewport).split('x')[0]);
    if (
        !CANONICAL_PAGE_KEYS.includes(page)
        || !allowedViewports.includes(viewport)
        || !['static', 'laravel'].includes(side)
        || !Number.isFinite(width)
    ) {
        return null;
    }
    const login = page === 'login';
    const serverOwnedLaravelLogin = login && side === 'laravel';
    return {
        requiresScript: !serverOwnedLaravelLogin,
        requiresSpa: !serverOwnedLaravelLogin,
        requiresChrome: !login,
        responsiveMode: login
            ? 'not-applicable'
            : width >= 1024
                ? 'desktop'
                : 'mobile',
    };
}

export function hasCanonicalGeometryBoxes(boxes, contract) {
    if (
        !contract
        || !Array.isArray(boxes)
        || boxes.length !== GEOMETRY_BOX_NAMES.length
        || boxes.some((entry, index) =>
            !Array.isArray(entry)
            || entry.length !== 2
            || entry[0] !== GEOMETRY_BOX_NAMES[index]
            || (
                entry[1] !== null
                && (
                    !Array.isArray(entry[1])
                    || entry[1].length !== 4
                    || entry[1].some((value) => !Number.isFinite(value))
                    || entry[1][2] <= 0
                    || entry[1][3] <= 0
                )
            ))
    ) {
        return false;
    }
    const values = Object.fromEntries(boxes);
    if (!contract.requiresChrome) {
        return GEOMETRY_BOX_NAMES.every((name) => values[name] === null);
    }
    if (['header', 'main', 'footer'].some((name) => values[name] === null)) {
        return false;
    }
    return contract.responsiveMode === 'desktop'
        ? values['desktop-navigation'] !== null && values['mobile-navigation'] === null
        : contract.responsiveMode === 'mobile'
            ? values['desktop-navigation'] === null && values['mobile-navigation'] !== null
            : false;
}

const childFixture = resolve(dirname(fileURLToPath(import.meta.url)), 'be6a1-harness-child.mjs');
const HASH_PATTERN = /^[a-f0-9]{64}$/i;
const REQUIRED_MANIFEST_HASHES = [
    'workingTreeChecksum',
    'workingTreeDiffChecksum',
    'viteManifestChecksum',
    'composerLockChecksum',
    'npmLockChecksum',
    'protectedStorefrontManifestChecksum',
    'factoryProtectionManifestChecksum',
    'motionNormalizationChecksum',
    'visualNormalizationChecksum',
    'browserLaunchArgsChecksum',
    'staticRouterNormalizationChecksum',
    'decorativeMediaAllowlistChecksum',
    'approvedBaselineRootChecksum',
    'isolatedDatabaseProcedureChecksum',
];
const EXPECTED_PREVIEW_CASES = [
    'valid-authorized',
    'missing-signature',
    'expired-signature',
    'changed-resource',
    'changed-revision',
    'unverified',
    'unauthorized',
];
const ADMIN_ROUTES = [
    '/admin',
    '/admin/access/users',
    '/admin/access/roles',
    '/admin/audit',
    '/admin/settings',
    '/admin/content/pages',
    '/admin/content/navigation',
    '/admin/content/announcements',
    '/admin/media',
];
const ADMIN_STATES = ['guest', 'unverified', 'ordinary', 'adminOnly', 'super'];
const EXPECTED_ADMIN_KEYS = ADMIN_ROUTES.flatMap((path) =>
    ADMIN_STATES.map((state) => `admin:${state}:${path}`));
const EXPECTED_ABOUT_MODES = [
    'static',
    'shadow',
    'enabled-isolated',
    'emergency-disabled',
    'global-kill',
];
const PHYSICAL_PNG_CACHE = new Map();
const PHYSICAL_RASTER_CACHE = new Map();

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

function canonical(value) {
    if (Array.isArray(value)) return value.map(canonical);
    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value)
                .sort(([left], [right]) => left.localeCompare(right))
                .map(([key, item]) => [key, canonical(item)]),
        );
    }
    return value;
}

function same(left, right) {
    return JSON.stringify(canonical(left)) === JSON.stringify(canonical(right));
}

function portable(workspaceRoot, path) {
    return relative(workspaceRoot, path).replaceAll('\\', '/');
}

function isNonEmptyString(value) {
    return typeof value === 'string' && value.trim().length > 0;
}

function isTimestamp(value) {
    return isNonEmptyString(value) && Number.isFinite(Date.parse(value));
}

function isPositiveInteger(value) {
    return Number.isInteger(value) && value > 0;
}

function isInside(root, path) {
    const absoluteRoot = resolve(root);
    const absolutePath = resolve(path);
    return absolutePath === absoluteRoot || absolutePath.startsWith(`${absoluteRoot}${sep}`);
}

export function fidelityComparisonFingerprint(item) {
    return sha256(JSON.stringify({
        rawDifferentPixels: item.rawDifferentPixels,
        perceptualDifferentPixels: item.perceptualDifferentPixels,
        materialDifferentPixels: item.materialDifferentPixels,
        maxChannelDelta: item.maxChannelDelta,
        differenceBounds: item.differenceBounds,
        semanticMatch: item.semanticMatch,
        semanticMismatchFields: item.semanticMismatchFields,
        boxesMatch: item.boxesMatch,
        responsiveModeMatch: item.responsiveModeMatch,
        adminHrefException: item.adminHrefException,
        rawDiffHash: item.rawDiffHash,
        subpixelProof: item.subpixelProof,
    }));
}

function issue(failures, code, path, message) {
    failures.push({ code, path, message });
}

async function readJson(path, failures, code) {
    try {
        return JSON.parse(await readFile(path, 'utf8'));
    } catch (error) {
        issue(failures, code, path, error instanceof SyntaxError ? 'invalid JSON' : 'missing or unreadable file');
        return null;
    }
}

async function readRequiredFile(path, failures, code) {
    try {
        await access(path, fsConstants.R_OK);
        const metadata = await stat(path);
        if (!metadata.isFile() || metadata.size === 0) {
            issue(failures, code, path, 'file is empty or is not a regular file');
            return null;
        }
        return await readFile(path);
    } catch {
        issue(failures, code, path, 'missing or unreadable file');
        return null;
    }
}

function checkCount(failures, actual, expected, path, label) {
    if (actual !== expected) {
        issue(failures, 'count-mismatch', path, `${label}: expected ${expected}, received ${actual}`);
    }
}

function exactKeySet(failures, actual, expected, path, label) {
    const actualCounts = new Map();
    for (const key of actual) actualCounts.set(key, (actualCounts.get(key) ?? 0) + 1);
    const actualSet = new Set(actual);
    const expectedSet = new Set(expected);
    const duplicates = [...actualCounts].filter(([, count]) => count > 1).map(([key]) => key);
    const missing = expected.filter((key) => !actualSet.has(key));
    const unexpected = actual.filter((key) => !expectedSet.has(key));
    if (duplicates.length) issue(failures, `${label}-duplicate`, path, `duplicate keys: ${duplicates.join(', ')}`);
    if (missing.length) issue(failures, `${label}-missing`, path, `missing keys: ${missing.join(', ')}`);
    if (unexpected.length) {
        issue(failures, `${label}-unexpected`, path, `unexpected keys: ${[...new Set(unexpected)].join(', ')}`);
    }
}

async function listFiles(directory) {
    const files = [];
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const path = join(directory, entry.name);
        if (entry.isDirectory()) files.push(...await listFiles(path));
        else if (entry.isFile()) files.push(path);
    }
    return files;
}

function expectedRepeatabilityClassification(diffs) {
    const rawCounts = diffs.map((item) => item.rawDifferentPixels);
    const materialCounts = diffs.map((item) => item.materialDifferentPixels);
    const adminHrefException = diffs.every((item) => item.adminHrefException);
    if (rawCounts.every((value) => value === 0)) {
        return adminHrefException ? 'approved-non-visual-admin-href-change' : 'exact';
    }
    if (rawCounts.some((value) => value > 0) && materialCounts.every((value) => value === 0)) {
        return 'deterministic-subpixel-rasterization';
    }
    return 'unresolved-material-visual-difference';
}

function validateManifestSchema(failures, manifest, path, expectedRunId, expectedWorkingTreeChecksum) {
    if (!manifest || typeof manifest !== 'object') return;
    if (manifest.runId !== expectedRunId) {
        issue(failures, 'run-id-mismatch', path, `expected ${expectedRunId}, received ${manifest.runId}`);
    }
    if (manifest.workingTreeChecksum !== expectedWorkingTreeChecksum) {
        issue(
            failures,
            'checksum-mismatch',
            path,
            `expected ${expectedWorkingTreeChecksum}, received ${manifest.workingTreeChecksum}`,
        );
    }
    for (const field of REQUIRED_MANIFEST_HASHES) {
        if (!HASH_PATTERN.test(manifest[field] ?? '')) {
            issue(failures, 'manifest-schema-failure', path, `${field} is not a SHA-256 checksum`);
        }
    }
    for (const field of [
        'branch',
        'commit',
        'phpVersion',
        'nodeVersion',
        'playwrightVersion',
        'chromiumVersion',
        'laravelEnvironment',
        'browserProfileIsolation',
    ]) {
        if (!isNonEmptyString(manifest[field])) {
            issue(failures, 'manifest-schema-failure', path, `${field} is missing or empty`);
        }
    }
    if (!isTimestamp(manifest.generatedAt)) {
        issue(failures, 'manifest-schema-failure', path, 'generatedAt is not a timestamp');
    }
    if (!/^[a-f0-9]{7,40}$/i.test(manifest.commit ?? '')) {
        issue(failures, 'manifest-schema-failure', path, 'commit is not a Git commit identifier');
    }
    if (manifest.laravelEnvironment !== 'testing') {
        issue(failures, 'manifest-schema-failure', path, 'Laravel environment is not testing');
    }
    if (!isPositiveInteger(manifest.willy?.size) || !HASH_PATTERN.test(manifest.willy?.sha256 ?? '')) {
        issue(failures, 'manifest-schema-failure', path, 'willy size/checksum evidence is incomplete');
    }
    for (const field of ['routeCount', 'permissionCount', 'roleCount', 'schedulerCount']) {
        if (!isPositiveInteger(manifest[field])) {
            issue(failures, 'manifest-schema-failure', path, `${field} is not a positive integer`);
        }
    }
    if (!same(manifest.stages, ['prepare', ...REQUIRED_STAGES])) {
        issue(failures, 'stage-inventory-mismatch', path, 'manifest stage inventory is incomplete');
    }
    if (!same(manifest.viewports?.homepage, HOMEPAGE_VIEWPORTS)
        || !same(manifest.viewports?.standard, STANDARD_VIEWPORTS)) {
        issue(failures, 'viewport-manifest-mismatch', path, 'manifest viewports differ from the canonical matrix');
    }
    const readiness = manifest.readiness;
    if (
        !isPositiveInteger(readiness?.attempts)
        || !isPositiveInteger(readiness?.frameIntervalMs)
        || !isPositiveInteger(readiness?.stableFrames)
        || !isPositiveInteger(readiness?.assetTimeoutMs)
        || !isPositiveInteger(readiness?.screenshotTimeoutMs)
    ) {
        issue(failures, 'manifest-schema-failure', path, 'readiness policy is incomplete');
    }
    const ports = Object.values(manifest.selectedPorts ?? {}).flatMap((value) => Object.values(value ?? {}));
    if (
        !same(Object.keys(manifest.selectedPorts ?? {}), REQUIRED_STAGES)
        || ports.length !== 8
        || new Set(ports).size !== 8
        || ports.some((port) => !Number.isInteger(port) || port < 1 || port > 65_535)
    ) {
        issue(failures, 'manifest-schema-failure', path, 'selected-port inventory is incomplete or invalid');
    }
    const expectedProfileRoots = Object.fromEntries(REQUIRED_STAGES.map((stage) => [
        stage,
        `storage/app/test-runtime/browser/${expectedRunId}/${stage}/playwright-profile-root`,
    ]));
    if (!same(manifest.browserProfileRoots, expectedProfileRoots)) {
        issue(failures, 'manifest-schema-failure', path, 'browser profile roots are not run/stage scoped');
    }
}

function validateSemanticSchema(failures, semantic, path, label) {
    if (!semantic || typeof semantic !== 'object') {
        issue(failures, 'semantic-schema-failure', path, `${label}: semantic object is missing`);
        return false;
    }
    let passed = true;
    for (const [entriesField, checksumField] of [
        ['visibleTextEntries', 'visibleTextChecksum'],
        ['headingStructureEntries', 'headingStructureChecksum'],
        ['imageIdentityEntries', 'imageIdentityChecksum'],
        ['keyStyleEntries', 'keyStyleChecksum'],
        ['interactiveStateEntries', 'interactiveStateChecksum'],
        ['rasterSensitiveRegionEntries', 'rasterSensitiveRegionChecksum'],
    ]) {
        const entries = semantic[entriesField];
        if (
            !Array.isArray(entries)
            || entries.length === 0
            || entries.some((entry) => !isNonEmptyString(entry))
            || !HASH_PATTERN.test(semantic[checksumField] ?? '')
            || sha256(entries?.join('|') ?? '') !== semantic[checksumField]
        ) {
            issue(
                failures,
                'semantic-schema-failure',
                path,
                `${label}: ${entriesField}/${checksumField} is absent, empty, or inconsistent`,
            );
            passed = false;
        }
    }
    if (!isNonEmptyString(semantic.adminHref) || !isNonEmptyString(semantic.laravelAdminUrl)) {
        issue(failures, 'semantic-admin-failure', path, `${label}: Admin href evidence must be non-null`);
        passed = false;
    } else {
        try {
            if (new URL(semantic.laravelAdminUrl).pathname !== '/admin') throw new Error('wrong path');
        } catch {
            issue(failures, 'semantic-admin-failure', path, `${label}: Laravel Admin URL is invalid`);
            passed = false;
        }
    }
    if (
        !Array.isArray(semantic.rasterSensitiveRegions)
        || semantic.rasterSensitiveRegions.length === 0
        || semantic.rasterSensitiveRegions.some((region) =>
            !Number.isFinite(region?.x)
            || !Number.isFinite(region?.y)
            || !Number.isFinite(region?.width)
            || !Number.isFinite(region?.height)
            || region.width <= 0
            || region.height <= 0
            || !isNonEmptyString(region.type)
            || !isNonEmptyString(region.identity))
    ) {
        issue(failures, 'semantic-schema-failure', path, `${label}: raster-sensitive region evidence is absent`);
        passed = false;
    }
    return passed;
}

function networkGatePassed(failures, stageResult, path, requireEvents) {
    const gate = stageResult?.payload?.networkGate;
    const evidence = gate?.evidence;
    const rawCounts = gate?.rawCounts;
    const optional = gate?.optionalServiceContract;
    const expectedProtectedSpaCaptures = stageResult?.stage === 'visual' ? 591 : 0;
    const expectedServerRenderedCaptures = stageResult?.stage === 'visual' ? 21 : 0;
    const arraysPresent = (
        Array.isArray(gate?.blocking)
        && Array.isArray(gate?.requiredLocalAssetFailures)
        && Array.isArray(gate?.moduleMimeRefusals)
        && Array.isArray(evidence?.console)
        && Array.isArray(evidence?.failedRequests)
        && Array.isArray(evidence?.errorResponses)
    );
    const rawCountsMatch = arraysPresent && (
        rawCounts?.console === evidence.console.length
        && rawCounts?.failedRequests === evidence.failedRequests.length
        && rawCounts?.errorResponses === evidence.errorResponses.length
    );
    const events = arraysPresent
        ? [...evidence.console, ...evidence.failedRequests, ...evidence.errorResponses]
        : [];
    const classificationsMatch = (
        gate?.classifiedCounts
        && Object.values(gate.classifiedCounts).every((count) => Number.isInteger(count) && count >= 0)
        && Object.values(gate.classifiedCounts).reduce((sum, count) => sum + count, 0) === events.length
    );
    const eventSchemaPassed = events.every((event) =>
        isNonEmptyString(event.classification) && typeof event.allowed === 'boolean');
    const passed = (
        gate?.passed === true
        && arraysPresent
        && rawCountsMatch
        && classificationsMatch
        && eventSchemaPassed
        && gate.blocking.length === 0
        && gate.requiredLocalAssetFailures.length === 0
        && gate.moduleMimeRefusals.length === 0
        && (!requireEvents || events.length > 0)
        && gate.correlations?.genericCorrelationPassed === true
        && gate.correlations?.appStateCorrelationPassed === true
        && optional?.scope === 'captures-explicitly-requiring-protected-spa'
        && optional?.expectedCallsPerProtectedSpaCapture === 4
        && optional?.expectedConsoleErrorsPerProtectedSpaCapture === 5
        && optional?.protectedSpaCaptureCount === expectedProtectedSpaCaptures
        && optional?.serverRenderedCaptureCount === expectedServerRenderedCaptures
        && Array.isArray(optional?.failures)
        && optional.failures.length === 0
    );
    if (!passed) {
        issue(
            failures,
            requireEvents ? 'empty-network-evidence' : 'network-gate-failure',
            path,
            `${stageResult?.stage ?? 'unknown'} network gate is incomplete or not clean`,
        );
    }
    return passed;
}

function validateMimeEvidence(failures, mime, path) {
    const checks = Array.isArray(mime?.checks) ? mime.checks : [];
    const keys = checks.map((item) => `${item.origin}|${item.kind}|${item.path ?? item.disposition ?? ''}`);
    const requiredKinds = ['javascript', 'css', 'image', 'font', 'router-fallback'];
    let substantive = (
        mime?.passed === true
        && mime?.failFast === true
        && checks.length >= 10
        && new Set(keys).size === keys.length
        && checks.every((item) => item.passed === true)
        && mime?.totals?.expected === checks.length
        && mime?.totals?.passed === checks.length
        && mime?.totals?.failed === 0
    );
    for (const origin of ['static', 'laravel']) {
        for (const kind of requiredKinds) {
            if (!checks.some((item) => item.origin === origin && item.kind === kind)) substantive = false;
        }
    }
    for (const item of checks) {
        if (!['static', 'laravel'].includes(item.origin) || !requiredKinds.includes(item.kind)) substantive = false;
        if (item.kind === 'router-fallback') {
            if (item.status !== 404 || item.htmlFallback !== false) substantive = false;
        } else if (item.kind === 'font' && item.disposition === 'not-applicable-no-required-local-font-assets') {
            if (item.path !== null) substantive = false;
        } else if (
            !isNonEmptyString(item.path)
            || !Number.isInteger(item.status)
            || item.status < 200
            || item.status >= 300
            || !isNonEmptyString(item.contentType)
            || item.htmlFallback === true
        ) {
            substantive = false;
        }
    }
    if (!substantive) {
        issue(failures, 'mime-schema-failure', path, 'MIME evidence is empty, incomplete, or internally inconsistent');
    }
}

function validateSecurityPayload(failures, result, path) {
    const payload = result?.payload;
    const admin = payload?.admin;
    const projected = payload?.projected;
    const aboutModes = payload?.aboutModes;
    const routeMatrix = Array.isArray(admin?.routeMatrix) ? admin.routeMatrix : [];
    const footer = Array.isArray(admin?.footer) ? admin.footer : [];
    const projectedResults = Array.isArray(projected?.results) ? projected.results : [];
    const aboutResults = Array.isArray(aboutModes?.results) ? aboutModes.results : [];
    exactKeySet(failures, routeMatrix.map((item) => item.label), EXPECTED_ADMIN_KEYS, path, 'security-route-matrix');
    let passed = routeMatrix.length === 45 && routeMatrix.every((item) => {
        if (item.passed !== true || !['login', 'verification', 'forbidden', 'allowed'].includes(item.expected)) return false;
        if (item.expected === 'login') return item.finalPath === '/login' && item.adminBodyVisible === false;
        if (item.expected === 'verification') return item.finalPath === '/email/verify' && item.verificationVisible === true && item.adminBodyVisible === false;
        if (item.expected === 'forbidden') return item.status === 403 && item.adminBodyVisible === false;
        return item.status === 200 && item.adminBodyVisible === true;
    });
    passed = passed && (
        footer.length === 2
        && same(footer.map((item) => `${item.width}x${item.height}`).sort(), ['1440x900', '375x812'])
        && footer.every((item) =>
            item.passed === true
            && isNonEmptyString(item.href)
            && item.href.endsWith('/admin')
            && item.legacyCount === 0
            && item.clickFinalPath === '/login'
            && HASH_PATTERN.test(item.stableHash ?? '')
            && isNonEmptyString(item.screenshot))
    );
    passed = passed && (
        admin?.logout?.passed === true
        && admin.logout.finalPath === '/login'
        && admin.logout.adminBodyVisible === false
        && Number.isInteger(admin.logout.directStatus)
    );
    passed = passed && (
        projectedResults.length === 2
        && same(projectedResults.map((item) => `${item.width}x${item.height}`).sort(), ['1440x900', '375x812'])
        && projectedResults.every((item) =>
            item.passed === true
            && isNonEmptyString(item.href)
            && item.href.endsWith('/admin')
            && item.unsafe === 0
            && isNonEmptyString(item.screenshot))
    );
    exactKeySet(failures, aboutResults.map((item) => item.name), EXPECTED_ABOUT_MODES, path, 'about-mode-matrix');
    passed = passed && (
        aboutResults.length === 5
        && aboutResults.every((item) =>
            item.passed === true
            && item.status === 200
            && typeof item.expectedProjected === 'boolean'
            && item.projected === item.expectedProjected
            && item.governedFixtureAvailable === true
            && item.resourceKey === 'page'
            && isNonEmptyString(item.screenshot))
    );
    const totals = payload?.totals;
    passed = passed && (
        totals?.privileged?.expected === 45
        && totals.privileged.present === 45
        && totals.privileged.passed === 45
        && totals?.staticFooter?.expected === 2
        && totals.staticFooter.present === 2
        && totals.staticFooter.passed === 2
        && totals?.projectedFooter?.expected === 2
        && totals.projectedFooter.present === 2
        && totals.projectedFooter.passed === 2
        && totals?.logout === true
        && totals?.aboutModes?.expected === 5
        && totals.aboutModes.present === 5
        && totals.aboutModes.passed === 5
        && Array.isArray(admin?.requestLog)
        && admin.requestLog.length > 0
        && Array.isArray(projected?.requestLog)
        && projected.requestLog.length > 0
    );
    if (!passed) issue(failures, 'security-payload-failure', path, 'security payload is empty, incomplete, or internally inconsistent');
}

function validatePreviewPayload(failures, result, path) {
    const preview = result?.payload?.preview;
    const results = Array.isArray(preview?.results) ? preview.results : [];
    exactKeySet(failures, results.map((item) => item.name), EXPECTED_PREVIEW_CASES, path, 'preview-case-matrix');
    const valid = results.find((item) => item.name === 'valid-authorized');
    const unverified = results.find((item) => item.name === 'unverified');
    const substantive = (
        results.length === 7
        && results.every((item) =>
            item.passed === true
            && Number.isInteger(item.status)
            && isNonEmptyString(item.finalPath)
            && typeof item.permissionLeak === 'boolean'
            && typeof item.publicNavigationExposure === 'boolean'
            && typeof item.publicCacheEntry === 'boolean')
        && valid?.status === 200
        && valid.marker === true
        && valid.robots === 'noindex,nofollow'
        && valid.xRobots === 'noindex, nofollow'
        && valid.cacheControl?.includes('no-store')
        && valid.permissionLeak === false
        && valid.publicNavigationExposure === false
        && valid.publicCacheEntry === false
        && unverified?.finalPath === '/email/verify'
        && Array.isArray(preview?.requestLog)
        && preview.requestLog.length > 0
        && result?.payload?.totals?.expected === 7
        && result.payload.totals.present === 7
        && result.payload.totals.passed === 7
    );
    if (!substantive) issue(failures, 'preview-payload-failure', path, 'preview payload is empty, incomplete, or inconsistent');
}

function validateReadinessAndCapture(failures, readiness, capture, manifest, path) {
    const final = readiness?.final;
    const timeline = readiness?.timeline;
    const contract = expectedCaptureContract(capture?.page, capture?.viewport, capture?.side);
    let passed = (
        contract !== null
        &&
        readiness?.passed === true
        && readiness?.renderState === 'fully-hydrated-and-visually-stable'
        && final?.fullyHydrated === true
        && final?.visuallyStable === true
        && final?.intermediateFrame === false
        && final?.renderState === 'fully-hydrated-and-visually-stable'
        && final?.serverStaticShellOnly === false
        && Array.isArray(final?.semanticFailures)
        && final.semanticFailures.length === 0
        && final?.exactRasterStable === true
        && final?.rasterDeltaPixels === 0
        && final?.stableFrameCount >= manifest.readiness.stableFrames
        && HASH_PATTERN.test(final?.screenshotHash ?? '')
        && isPositiveInteger(final?.screenshotBytes)
        && Array.isArray(timeline)
        && timeline.length >= manifest.readiness.stableFrames
    );
    const requiredStringFields = [
        'readyState',
        'pathname',
        'rootDisplay',
        'rootVisibility',
        'rootOpacity',
        'bodyBackground',
        'responsiveMode',
        'expectedResponsiveMode',
        'domChecksum',
        'computedStyleChecksum',
        'expectedPath',
    ];
    const requiredBooleanFields = [
        'headingMatched',
        'routeLandmarkMatched',
        'visibleVideosReady',
        'fontsReady',
        'stylesheetLoaded',
        'scriptLoaded',
        'headerVisible',
        'mainVisible',
        'footerVisible',
        'rootVisible',
        'applicationInitialized',
        'spaInitialized',
        'fullyHydrated',
        'visuallyStable',
        'intermediateFrame',
        'exactRasterStable',
        'requiresScript',
        'requiresChrome',
        'requiresSpa',
    ];
    const requiredNumberFields = [
        'bodyChildren',
        'rootChildren',
        'rootHtmlLength',
        'bodyHeight',
        'rootHeight',
        'mainHeight',
        'scrollHeight',
        'visibleHeadings',
        'visibleImages',
        'loadedImages',
        'visibleVideos',
        'activeAnimations',
        'observedRequestCount',
        'completedRequestCount',
        'outstandingRequests',
        'visibleSections',
        'minimumHeight',
        'stableFrameCount',
        'atMs',
    ];
    passed = passed
        && requiredStringFields.every((field) => isNonEmptyString(final?.[field]))
        && requiredBooleanFields.every((field) => typeof final?.[field] === 'boolean')
        && requiredNumberFields.every((field) => Number.isFinite(final?.[field]))
        && final.readyState === 'complete'
        && final.pathname === final.expectedPath
        && final.requiresScript === contract.requiresScript
        && final.requiresSpa === contract.requiresSpa
        && final.requiresChrome === contract.requiresChrome
        && final.scriptLoaded === contract.requiresScript
        && final.applicationInitialized === contract.requiresSpa
        && final.spaInitialized === contract.requiresSpa
        && final.headerVisible === contract.requiresChrome
        && final.mainVisible === contract.requiresChrome
        && final.footerVisible === contract.requiresChrome
        && final.responsiveMode === contract.responsiveMode
        && final.expectedResponsiveMode === contract.responsiveMode
        && final.fullyHydrated === true
        && final.visuallyStable === true
        && final.intermediateFrame === false
        && final.exactRasterStable === true
        && final.outstandingRequests === 0
        && final.activeAnimations === 0
        && final.scrollHeight >= final.minimumHeight
        && final.visibleImages === final.loadedImages
        && HASH_PATTERN.test(final.screenshotHash ?? '')
        && isPositiveInteger(final.screenshotBytes)
        && final.stableFrameCount >= manifest.readiness.stableFrames
        && Array.isArray(final.pendingImages)
        && final.pendingImages.length === 0
        && Array.isArray(final.failedImages)
        && final.failedImages.length === 0
        && Array.isArray(final.videoSources)
        && Array.isArray(final.readyVideoSources)
        && same(final.videoSources, final.readyVideoSources)
        && final.visibleVideos === final.videoSources.length
        && Array.isArray(final.scrollPosition)
        && final.scrollPosition.length === 2
        && Array.isArray(final.imageState)
        && final.imageState.length > 0
        && final.imageState.every((item) =>
            isNonEmptyString(item.source)
            && typeof item.visible === 'boolean'
            && (
                item.visible === false
                || (
                    item.complete === true
                    && item.naturalWidth > 0
                    && item.naturalHeight > 0
                )
            ))
        && Array.isArray(final.videoState)
        && final.videoState.every((item) =>
            typeof item.visible === 'boolean'
            && Number.isFinite(item.readyState)
            && Number.isFinite(item.videoWidth)
            && Number.isFinite(item.videoHeight)
            && (item.visible === false || isNonEmptyString(item.source)))
        && hasCanonicalGeometryBoxes(final.boxes, contract)
        && Array.isArray(final.routeBootstrapPaths)
        && capture?.normalization?.passed === true
        && typeof capture?.normalization?.expected === 'boolean'
        && (
            capture.normalization.expected
                ? isNonEmptyString(capture.normalization.id)
                : capture.normalization.id === null
        );

    const stableFrames = Array.isArray(timeline)
        ? timeline.slice(-manifest.readiness.stableFrames)
        : [];
    passed = passed && stableFrames.length === manifest.readiness.stableFrames
        && stableFrames.every((frame, index) =>
            frame.fullyHydrated === true
            && (index === 0
                ? (frame.rasterDeltaPixels === null || frame.rasterDeltaPixels === 0)
                : frame.exactRasterStable === true && frame.rasterDeltaPixels === 0)
            && Array.isArray(frame.semanticFailures)
            && frame.semanticFailures.length === 0
            && frame.requiresScript === contract.requiresScript
            && frame.requiresSpa === contract.requiresSpa
            && frame.requiresChrome === contract.requiresChrome
            && frame.responsiveMode === contract.responsiveMode
            && frame.expectedResponsiveMode === contract.responsiveMode
            && same(frame.boxes, final.boxes)
            && HASH_PATTERN.test(frame.screenshotHash ?? '')
            && isPositiveInteger(frame.screenshotBytes)
            && Number.isFinite(frame.atMs))
        && stableFrames.at(-1)?.screenshotHash === final?.screenshotHash
        && stableFrames.at(-1)?.screenshotBytes === final?.screenshotBytes
        && stableFrames.at(-1)?.renderState === final?.renderState
        && stableFrames.at(-1)?.stableFrameCount === final?.stableFrameCount;

    const semanticPassed = validateSemanticSchema(failures, final, path, readiness?.label ?? 'readiness');
    passed = passed && semanticPassed;
    passed = passed && (
        capture?.renderState === final?.renderState
        && capture?.intermediateCapture === false
        && capture?.hash === final?.screenshotHash
        && capture?.bytes === final?.screenshotBytes
        && capture?.responsiveMode === final?.responsiveMode
        && same(capture?.boxes, final?.boxes)
        && capture?.scrollHeight === final?.scrollHeight
        && same(capture?.routeBootstrapPaths, final?.routeBootstrapPaths)
        && same(capture?.semantic, {
            visibleTextChecksum: final?.visibleTextChecksum,
            headingStructureChecksum: final?.headingStructureChecksum,
            imageIdentityChecksum: final?.imageIdentityChecksum,
            visibleTextEntries: final?.visibleTextEntries,
            headingStructureEntries: final?.headingStructureEntries,
            imageIdentityEntries: final?.imageIdentityEntries,
            keyStyleChecksum: final?.keyStyleChecksum,
            keyStyleEntries: final?.keyStyleEntries,
            interactiveStateChecksum: final?.interactiveStateChecksum,
            interactiveStateEntries: final?.interactiveStateEntries,
            rasterSensitiveRegionChecksum: final?.rasterSensitiveRegionChecksum,
            rasterSensitiveRegionEntries: final?.rasterSensitiveRegionEntries,
            rasterSensitiveRegions: final?.rasterSensitiveRegions,
            adminHref: final?.adminHref,
            laravelAdminUrl: final?.laravelAdminUrl,
        })
    );
    if (!passed) {
        issue(failures, 'readiness-schema-failure', path, `${readiness?.label ?? 'unknown'} readiness/timeline is incomplete`);
    }
}

async function loadPhysicalPng(failures, path, expectedWidth, expectedHeight, code, cache) {
    const buffer = await readRequiredFile(path, failures, code);
    if (!buffer) return null;
    const encodedHash = sha256(buffer);
    let decoded = cache.get(encodedHash);
    if (!decoded) {
        try {
            const png = PNG.sync.read(buffer);
            const canonicalBuffer = canonicalPng(buffer);
            decoded = { png, canonicalBuffer, canonicalHash: sha256(canonicalBuffer) };
            cache.set(encodedHash, decoded);
        } catch {
            issue(failures, code, path, 'PNG decode failed');
            return null;
        }
    }
    if (decoded.png.width !== expectedWidth || decoded.png.height !== expectedHeight) {
        issue(
            failures,
            code,
            path,
            `PNG dimensions ${decoded.png.width}x${decoded.png.height}, expected ${expectedWidth}x${expectedHeight}`,
        );
    }
    return { ...decoded, buffer, encodedHash };
}

function diffMorphology(rawDiffBuffer) {
    const diff = PNG.sync.read(rawDiffBuffer);
    let maxHorizontalRun = 0;
    let maxVerticalRun = 0;
    const verticalRuns = new Uint32Array(diff.width);
    for (let y = 0; y < diff.height; y++) {
        let horizontalRun = 0;
        for (let x = 0; x < diff.width; x++) {
            const offset = ((y * diff.width) + x) * 4;
            const changed = diff.data[offset + 3] !== 0;
            horizontalRun = changed ? horizontalRun + 1 : 0;
            verticalRuns[x] = changed ? verticalRuns[x] + 1 : 0;
            maxHorizontalRun = Math.max(maxHorizontalRun, horizontalRun);
            maxVerticalRun = Math.max(maxVerticalRun, verticalRuns[x]);
        }
    }
    return { maxHorizontalRun, maxVerticalRun };
}

function overlayPng(staticPng, laravelPng) {
    const overlay = new PNG({ width: staticPng.width, height: staticPng.height });
    for (let index = 0; index < overlay.data.length; index += 4) {
        overlay.data[index] = Math.round((staticPng.data[index] + laravelPng.data[index]) / 2);
        overlay.data[index + 1] = Math.round((staticPng.data[index + 1] + laravelPng.data[index + 1]) / 2);
        overlay.data[index + 2] = Math.round((staticPng.data[index + 2] + laravelPng.data[index + 2]) / 2);
        overlay.data[index + 3] = 255;
    }
    return overlay;
}

function sideBySidePng(staticPng, laravelPng) {
    const sideBySide = new PNG({ width: staticPng.width * 2, height: staticPng.height });
    PNG.bitblt(staticPng, sideBySide, 0, 0, staticPng.width, staticPng.height, 0, 0);
    PNG.bitblt(laravelPng, sideBySide, 0, 0, laravelPng.width, laravelPng.height, staticPng.width, 0);
    return sideBySide;
}

function validateStrictRepeatability(failures, visual, path) {
    const diffsByGroup = new Map(CANONICAL_GROUP_KEYS.map((key) => [key, []]));
    for (const diff of visual.diffs) {
        const key = `${diff.page}|${diff.viewport}`;
        if (diffsByGroup.has(key)) diffsByGroup.get(key).push(diff);
    }
    const repeatabilityByGroup = new Map(
        visual.repeatability.map((item) => [`${item.page}|${item.viewport}`, item]),
    );
    for (const group of CANONICAL_GROUP_KEYS) {
        const diffs = (diffsByGroup.get(group) ?? []).sort((left, right) => left.run - right.run);
        const item = repeatabilityByGroup.get(group);
        if (!item || diffs.length !== 3) continue;
        const staticHashes = [...new Set(diffs.map((diff) => diff.staticHash))];
        const laravelHashes = [...new Set(diffs.map((diff) => diff.laravelHash))];
        const fingerprints = [...new Set(diffs.map(fidelityComparisonFingerprint))];
        const rawCounts = diffs.map((diff) => diff.rawDifferentPixels);
        const perceptualCounts = diffs.map((diff) => diff.perceptualDifferentPixels);
        const materialCounts = diffs.map((diff) => diff.materialDifferentPixels);
        const expectedClassification = expectedRepeatabilityClassification(diffs);
        const strict = (
            item.runs === 3
            && item.passed === true
            && item.classified === true
            && item.exactSideRepeatability === true
            && item.deterministicComparisons === true
            && item.semanticParity === true
            && item.geometryParity === true
            && same(item.staticHashes, staticHashes)
            && staticHashes.length === 1
            && same(item.laravelHashes, laravelHashes)
            && laravelHashes.length === 1
            && same(item.crossRunDeltas?.static, [0, 0])
            && same(item.crossRunDeltas?.laravel, [0, 0])
            && same(item.comparisonFingerprints, fingerprints)
            && fingerprints.length === 1
            && same(item.rawDifferentPixels, rawCounts)
            && same(item.perceptualDifferentPixels, perceptualCounts)
            && same(item.materialDifferentPixels, materialCounts)
            && item.classification === expectedClassification
            && expectedClassification !== 'unresolved-material-visual-difference'
        );
        if (!strict) {
            issue(failures, 'strict-repeatability-failure', path, `${group} is not exactly deterministic across three runs`);
        }
    }
}

async function validatePhysicalFidelity(
    failures,
    workspaceRoot,
    runRoot,
    captures,
    diffs,
) {
    const pngCache = PHYSICAL_PNG_CACHE;
    const rasterCache = PHYSICAL_RASTER_CACHE;
    const captureFiles = new Map();
    for (const capture of captures) {
        const [width, height] = String(capture.viewport).split('x').map(Number);
        const expectedPath = join(
            runRoot,
            'visual',
            'screenshots',
            'fidelity',
            capture.page,
            capture.viewport,
            `run-${capture.run}`,
            `${capture.side}.png`,
        );
        const actualPath = resolve(workspaceRoot, capture.path ?? '');
        if (
            !isInside(workspaceRoot, actualPath)
            || actualPath !== resolve(expectedPath)
            || portable(workspaceRoot, actualPath) !== capture.path
        ) {
            issue(failures, 'screenshot-path-mismatch', capture.path ?? '', 'capture path is non-canonical or escapes workspace');
            continue;
        }
        const physical = await loadPhysicalPng(
            failures,
            actualPath,
            width,
            height,
            'physical-screenshot-failure',
            pngCache,
        );
        if (!physical) continue;
        if (physical.png.width !== width || physical.png.height !== height) continue;
        if (
            !physical.buffer.equals(physical.canonicalBuffer)
            || capture.bytes !== physical.buffer.length
            || capture.hash !== physical.canonicalHash
        ) {
            issue(
                failures,
                'physical-screenshot-failure',
                actualPath,
                'capture bytes/hash/canonical encoding differs from physical PNG',
            );
        }
        captureFiles.set(
            `${capture.page}|${capture.viewport}|${capture.run}|${capture.side}`,
            { ...physical, capture },
        );
    }

    for (const comparison of diffs) {
        const groupRun = `${comparison.page}|${comparison.viewport}|${comparison.run}`;
        const staticPhysical = captureFiles.get(`${groupRun}|static`);
        const laravelPhysical = captureFiles.get(`${groupRun}|laravel`);
        if (!staticPhysical || !laravelPhysical) continue;
        const [width, height] = comparison.viewport.split('x').map(Number);
        const rasterKey = `${staticPhysical.canonicalHash}|${laravelPhysical.canonicalHash}`;
        let raster = rasterCache.get(rasterKey);
        if (!raster) {
            raster = compareRasterBuffers(staticPhysical.canonicalBuffer, laravelPhysical.canonicalBuffer);
            rasterCache.set(rasterKey, raster);
        }
        const morphology = diffMorphology(raster.rawDiffBuffer);
        const expectedMetrics = {
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
            staticHash: staticPhysical.canonicalHash,
            laravelHash: laravelPhysical.canonicalHash,
            maxHorizontalRun: morphology.maxHorizontalRun,
            maxVerticalRun: morphology.maxVerticalRun,
        };
        if (Object.entries(expectedMetrics).some(([field, value]) => !same(comparison[field], value))) {
            issue(failures, 'raster-metric-mismatch', groupRun, 'recorded raster/diff metrics differ from physical captures');
        }
        const rasterSensitiveRegions = [
            ...(staticPhysical.capture?.semantic?.rasterSensitiveRegions ?? []),
            ...(laravelPhysical.capture?.semantic?.rasterSensitiveRegions ?? []),
        ];
        const expectedSubpixelProof = raster.rawDifferentPixels === 0
            ? null
            : analyzeSubpixelMorphology(
                staticPhysical.canonicalBuffer,
                laravelPhysical.canonicalBuffer,
                rasterSensitiveRegions,
            );
        if (
            !same(comparison.subpixelProof, expectedSubpixelProof)
            || (raster.rawDifferentPixels > 0 && expectedSubpixelProof.passed !== true)
        ) {
            issue(failures, 'subpixel-proof-mismatch', groupRun, 'subpixel morphology proof differs from physical captures/regions');
        }

        const expectedDirectory = join(
            runRoot,
            'visual',
            'comparisons',
            comparison.page,
            comparison.viewport,
            `run-${comparison.run}`,
        );
        const artifactSpecifications = [
            ['diffPath', 'diff.png', width, raster.rawDiffBuffer, null],
            ['overlayPath', 'overlay.png', width, null, overlayPng(staticPhysical.png, laravelPhysical.png)],
            ['sideBySidePath', 'side-by-side.png', width * 2, null, sideBySidePng(staticPhysical.png, laravelPhysical.png)],
        ];
        for (const [field, filename, expectedWidth, expectedBuffer, expectedPng] of artifactSpecifications) {
            const expectedPath = resolve(expectedDirectory, filename);
            const actualPath = resolve(workspaceRoot, comparison[field] ?? '');
            if (
                actualPath !== expectedPath
                || !isInside(workspaceRoot, actualPath)
                || portable(workspaceRoot, actualPath) !== comparison[field]
            ) {
                issue(failures, 'comparison-artifact-failure', comparison[field] ?? '', `${field} path is non-canonical`);
                continue;
            }
            const physical = await loadPhysicalPng(
                failures,
                actualPath,
                expectedWidth,
                height,
                'comparison-artifact-failure',
                pngCache,
            );
            if (!physical) continue;
            const pixelsMatch = expectedPng
                ? physical.png.data.equals(expectedPng.data)
                : physical.buffer.equals(expectedBuffer);
            if (!pixelsMatch) {
                issue(failures, 'comparison-artifact-failure', actualPath, `${field} content differs from recomputed artifact`);
            }
        }
    }
}

export async function validateBaselineCandidate({
    workspaceRoot,
    candidateRoot,
    expectedRunId,
    expectedRootManifestChecksum,
    expectedWorkingTreeChecksum,
    expectedProtectedStorefrontManifestChecksum,
    expectedFactoryProtectionManifestChecksum,
    approvedBaselineRoots = [],
}) {
    const failures = [];
    const candidate = resolve(candidateRoot);
    const manifestPath = join(candidate, 'manifests', 'candidate-manifest.json');
    if (!isInside(workspaceRoot, candidate)) {
        issue(failures, 'candidate-path-failure', candidate, 'candidate root escapes workspace');
    }
    const manifestBytes = await readRequiredFile(manifestPath, failures, 'candidate-manifest-missing');
    const manifest = manifestBytes
        ? await readJson(manifestPath, failures, 'candidate-manifest-missing')
        : null;
    const manifestChecksum = manifestBytes ? sha256(manifestBytes) : null;
    const fileChecksums = manifest?.fileChecksums;
    const manifestSchemaPassed = (
        manifest?.runId === expectedRunId
        && manifest?.approvalState === 'unapproved-baseline-candidate'
        && manifest?.candidateRootRole === 'unapproved-baseline-candidate'
        && manifest?.formalApprovalState === 'not-requested'
        && manifest?.autoApprovalProhibited === true
        && same(manifest?.approvedBaselineWrites, [])
        && manifest?.rootManifestChecksum === expectedRootManifestChecksum
        && manifest?.workingTreeChecksum === expectedWorkingTreeChecksum
        && manifest?.protectedStorefrontManifestChecksum === expectedProtectedStorefrontManifestChecksum
        && manifest?.factoryProtectionManifestChecksum === expectedFactoryProtectionManifestChecksum
        && [
            'motionNormalizationChecksum',
            'visualNormalizationChecksum',
            'staticRouterNormalizationChecksum',
            'decorativeMediaAllowlistChecksum',
            'approvedBaselineRootChecksum',
        ].every((field) => HASH_PATTERN.test(manifest?.[field] ?? ''))
        && manifest?.screenshotCount === 612
        && manifest?.comparisonCount === 306
        && manifest?.repeatabilityGroups === 102
        && isPositiveInteger(manifest?.fileCount)
        && HASH_PATTERN.test(manifest?.packageChecksum ?? '')
        && isTimestamp(manifest?.createdAt)
        && isNonEmptyString(manifest?.source)
        && fileChecksums
        && typeof fileChecksums === 'object'
        && !Array.isArray(fileChecksums)
    );
    if (!manifestSchemaPassed) {
        issue(failures, 'candidate-manifest-failure', manifestPath, 'candidate identity, checksum, count, or unapproved-state evidence is incomplete');
    }

    const requiredFiles = [
        'README.md',
        'ADMIN_HREF_EXCEPTION.md',
        'HUMAN_REVIEW_CHECKLIST.md',
        'manifests/run-manifest.json',
        'manifests/run-manifest.sha256',
        'manifests/orchestration-journal.json',
        'manifests/browser-manifest.json',
        'manifests/environment-manifest.json',
        'manifests/viewport-manifest.json',
        'manifests/finalization-proof.json',
        'manifests/protected-source/template-sha256.txt',
        'manifests/protected-source/protected-php-baseline.json',
        'results/mime-preflight.json',
        'results/semantic-readiness-summary.json',
        'results/visual-stability-summary.json',
        'results/fidelity-repeatability-summary.json',
        'results/static-reference-checksums.json',
        'results/laravel-capture-checksums.json',
        'results/fidelity-diff-summary.json',
        'results/console-network-summary.json',
        'results/stage-result.json',
        'results/stage-complete.json',
        'results/stage-process-exit.json',
        'results/orchestration-result.json',
        'results/cleanup-result.json',
        'results/lifecycle-events.json',
        'results/security/stage-result.json',
        'results/preview/stage-result.json',
        'results/verify/stage-result.json',
    ];
    for (const relativePath of requiredFiles) {
        await readRequiredFile(join(candidate, relativePath), failures, 'candidate-required-file-missing');
    }
    for (const stage of REQUIRED_STAGES) {
        const stagePrefix = stage === 'visual' ? 'results' : `results/${stage}`;
        for (const name of [
            'stage-result.json',
            'stage-complete.json',
            'stage-process-exit.json',
            'orchestration-result.json',
        ]) {
            await readRequiredFile(
                join(candidate, stagePrefix, name),
                failures,
                'candidate-required-file-missing',
            );
            await readRequiredFile(
                join(candidate, 'results', 'orchestration', `${stage}-${name}`),
                failures,
                'candidate-required-file-missing',
            );
        }
    }
    for (const name of ['stage-result.json', 'stage-complete.json']) {
        await readRequiredFile(
            join(candidate, 'results', 'orchestration', `prepare-${name}`),
            failures,
            'candidate-required-file-missing',
        );
    }

    const physicalCandidateFiles = await listFiles(candidate).catch((error) => {
        issue(
            failures,
            'candidate-file-inventory-unreadable',
            candidate,
            `candidate inventory could not be read: ${error.message}`,
        );
        return [];
    });
    const allFiles = physicalCandidateFiles
        .map((path) => portable(candidate, path))
        .sort();
    const checksumPaths = Object.keys(fileChecksums ?? {});
    const expectedChecksumPaths = allFiles.filter((path) => path !== 'manifests/candidate-manifest.json');
    if (!same(checksumPaths, [...checksumPaths].sort()) || !same(checksumPaths, expectedChecksumPaths)) {
        issue(failures, 'candidate-file-inventory-mismatch', manifestPath, 'candidate fileChecksums is unsorted or differs from physical package files');
    }
    for (const relativePath of expectedChecksumPaths) {
        const actual = sha256(await readFile(join(candidate, relativePath)));
        if (fileChecksums?.[relativePath] !== actual) {
            issue(failures, 'candidate-file-checksum-mismatch', join(candidate, relativePath), 'candidate file checksum differs');
        }
    }
    const packageChecksum = sha256(
        Object.entries(fileChecksums ?? {}).map(([path, hash]) => `${path}:${hash}`).join('\n'),
    );
    if (
        manifest?.fileCount !== expectedChecksumPaths.length
        || manifest?.packageChecksum !== packageChecksum
    ) {
        issue(
            failures,
            'candidate-package-checksum-mismatch',
            manifestPath,
            'candidate fileCount/packageChecksum differs from the complete physical inventory',
        );
    }

    const screenshotFiles = allFiles.filter((path) => path.startsWith('screenshots/') && path.endsWith('.png'));
    const comparisonFiles = allFiles.filter((path) => path.startsWith('comparisons/') && path.endsWith('.png'));
    if (screenshotFiles.length !== 612 || comparisonFiles.length !== 918) {
        issue(
            failures,
            'candidate-artifact-count-mismatch',
            candidate,
            `expected 612 screenshots and 918 comparison artifacts, received ${screenshotFiles.length}/${comparisonFiles.length}`,
        );
    }

    const candidateRunManifest = await readRequiredFile(
        join(candidate, 'manifests', 'run-manifest.json'),
        failures,
        'candidate-run-manifest-failure',
    );
    const sidecar = await readRequiredFile(
        join(candidate, 'manifests', 'run-manifest.sha256'),
        failures,
        'candidate-run-manifest-failure',
    );
    let candidateRunManifestObject = null;
    try {
        candidateRunManifestObject = candidateRunManifest
            ? JSON.parse(candidateRunManifest.toString('utf8'))
            : null;
    } catch {
        candidateRunManifestObject = null;
    }
    if (
        !candidateRunManifest
        || sha256(candidateRunManifest) !== expectedRootManifestChecksum
        || candidateRunManifestObject?.runId !== expectedRunId
        || sidecar?.toString('utf8') !== `${expectedRootManifestChecksum}  run-manifest.json\n`
    ) {
        issue(failures, 'candidate-run-manifest-failure', candidate, 'candidate run manifest/sidecar identity differs');
    }
    const browserManifestPath = join(candidate, 'manifests', 'browser-manifest.json');
    const environmentManifestPath = join(candidate, 'manifests', 'environment-manifest.json');
    const browserManifest = await readJson(
        browserManifestPath,
        failures,
        'candidate-browser-manifest-failure',
    );
    const environmentManifest = await readJson(
        environmentManifestPath,
        failures,
        'candidate-environment-manifest-failure',
    );
    for (const field of [
        'motionNormalizationChecksum',
        'visualNormalizationChecksum',
        'staticRouterNormalizationChecksum',
        'decorativeMediaAllowlistChecksum',
        'approvedBaselineRootChecksum',
    ]) {
        const values = [
            manifest?.[field],
            candidateRunManifestObject?.[field],
            environmentManifest?.[field],
            ...(field === 'approvedBaselineRootChecksum' ? [] : [browserManifest?.[field]]),
        ];
        if (
            values.some((value) => !HASH_PATTERN.test(value ?? ''))
            || values.some((value) => value !== values[0])
        ) {
            issue(
                failures,
                'candidate-normalization-manifest-mismatch',
                manifestPath,
                `${field} differs across candidate/run/browser/environment manifests`,
            );
        }
    }
    if (
        browserManifest?.launchArgsChecksum !== candidateRunManifestObject?.browserLaunchArgsChecksum
        || !Array.isArray(browserManifest?.launchArgs)
        || sha256(browserManifest.launchArgs?.join('\n') ?? '') !== browserManifest?.launchArgsChecksum
        || !isNonEmptyString(browserManifest?.visualNormalizationCss)
        || sha256(browserManifest?.visualNormalizationCss ?? '') !== browserManifest?.visualNormalizationChecksum
        || !browserManifest?.staticRouterNormalization
        || sha256(JSON.stringify(browserManifest?.staticRouterNormalization)) !== browserManifest?.staticRouterNormalizationChecksum
        || !browserManifest?.decorativeMediaAllowlist
        || sha256(JSON.stringify(browserManifest?.decorativeMediaAllowlist)) !== browserManifest?.decorativeMediaAllowlistChecksum
        || environmentManifest?.runId !== expectedRunId
        || environmentManifest?.rootManifestChecksum !== expectedRootManifestChecksum
    ) {
        issue(
            failures,
            'candidate-normalization-manifest-mismatch',
            browserManifestPath,
            'browser/environment normalization definitions or immutable identity differ',
        );
    }
    const finalizationProofPath = join(candidate, 'manifests', 'finalization-proof.json');
    const finalizationProof = await readJson(
        finalizationProofPath,
        failures,
        'candidate-finalization-proof-failure',
    );
    if (
        finalizationProof?.runId !== expectedRunId
        || finalizationProof?.rootManifestChecksum !== expectedRootManifestChecksum
        || !isTimestamp(finalizationProof?.generatedAt)
        || finalizationProof?.ports?.passed !== true
        || finalizationProof?.profiles?.passed !== true
        || finalizationProof?.ownedProcesses?.passed !== true
        || finalizationProof?.stageMarkers?.passed !== true
        || finalizationProof?.ownedCleanupPassed !== true
        || finalizationProof?.passed !== true
    ) {
        issue(
            failures,
            'candidate-finalization-proof-failure',
            finalizationProofPath,
            'candidate finalization proof is incomplete or failed',
        );
    }
    for (const [relativePath, expectedHash, code] of [
        ['manifests/protected-source/template-sha256.txt', expectedProtectedStorefrontManifestChecksum, 'candidate-protected-storefront-mismatch'],
        ['manifests/protected-source/protected-php-baseline.json', expectedFactoryProtectionManifestChecksum, 'candidate-protected-factory-mismatch'],
    ]) {
        const bytes = await readRequiredFile(join(candidate, relativePath), failures, code);
        if (bytes && sha256(bytes) !== expectedHash) {
            issue(failures, code, join(candidate, relativePath), 'protected manifest checksum differs');
        }
    }
    for (const relativePath of [
        'results/stage-result.json',
        'results/security/stage-result.json',
        'results/preview/stage-result.json',
        'results/verify/stage-result.json',
    ]) {
        const result = await readJson(join(candidate, relativePath), failures, 'candidate-result-failure');
        if (result?.runId !== expectedRunId || result?.passed !== true) {
            issue(failures, 'candidate-result-failure', join(candidate, relativePath), 'candidate contains a different or failed run result');
        }
    }
    for (const stage of REQUIRED_STAGES) {
        const stageDirectory = join(candidate, stage === 'visual' ? 'results' : `results/${stage}`);
        const stageResultPath = join(stageDirectory, 'stage-result.json');
        const stageResultBytes = await readRequiredFile(
            stageResultPath,
            failures,
            'candidate-result-failure',
        );
        const completion = await readJson(
            join(stageDirectory, 'stage-complete.json'),
            failures,
            'candidate-result-failure',
        );
        const processExit = await readJson(
            join(stageDirectory, 'stage-process-exit.json'),
            failures,
            'candidate-result-failure',
        );
        const orchestration = await readJson(
            join(stageDirectory, 'orchestration-result.json'),
            failures,
            'candidate-result-failure',
        );
        const resultChecksum = stageResultBytes ? sha256(stageResultBytes) : null;
        const processExitBytes = await readRequiredFile(
            join(stageDirectory, 'stage-process-exit.json'),
            failures,
            'candidate-result-failure',
        );
        const processProofPassed = validateProcessProof(
            failures,
            processExit?.processProof,
            join(stageDirectory, 'stage-process-exit.json'),
            stage,
            processExit?.childPid,
        );
        if (
            completion?.runId !== expectedRunId
            || completion?.stage !== stage
            || completion?.passed !== true
            || completion?.stageResultChecksum !== resultChecksum
            || processExit?.runId !== expectedRunId
            || processExit?.stage !== stage
            || processExit?.rootManifestChecksum !== expectedRootManifestChecksum
            || processExit?.childExited !== true
            || processExit?.exitCode !== 0
            || processExit?.timedOut !== false
            || processExit?.forcedTreeTermination !== false
            || processExit?.termination !== null
            || processExit?.passed !== true
            || !processProofPassed
            || orchestration?.runId !== expectedRunId
            || orchestration?.stage !== stage
            || orchestration?.childPid !== processExit?.childPid
            || orchestration?.exitCode !== 0
            || orchestration?.spawnError !== null
            || orchestration?.timedOut !== false
            || orchestration?.forcedTreeTermination !== false
            || orchestration?.termination !== null
            || orchestration?.resultChecksum !== resultChecksum
            || orchestration?.completionMarkerPassed !== true
            || orchestration?.processExitMarkerChecksum !== (processExitBytes ? sha256(processExitBytes) : null)
            || !same(orchestration?.processProof, processExit?.processProof)
            || orchestration?.inProgressMarkerAbsent !== true
            || orchestration?.runnerFailurePresent !== false
            || orchestration?.passed !== true
        ) {
            issue(
                failures,
                'candidate-result-failure',
                stageDirectory,
                `${stage} completion/process-exit/orchestration evidence is inconsistent`,
            );
        }
        for (const name of [
            'stage-result.json',
            'stage-complete.json',
            'stage-process-exit.json',
            'orchestration-result.json',
        ]) {
            const sourceBytes = await readRequiredFile(
                join(stageDirectory, name),
                failures,
                'candidate-result-failure',
            );
            const copyBytes = await readRequiredFile(
                join(candidate, 'results', 'orchestration', `${stage}-${name}`),
                failures,
                'candidate-result-failure',
            );
            if (sourceBytes && copyBytes && !sourceBytes.equals(copyBytes)) {
                issue(
                    failures,
                    'candidate-result-failure',
                    join(candidate, 'results', 'orchestration', `${stage}-${name}`),
                    `${stage} orchestration copy differs from the packaged stage artifact`,
                );
            }
        }
    }
    const prepareResultPath = join(candidate, 'results', 'orchestration', 'prepare-stage-result.json');
    const prepareResultBytes = await readRequiredFile(
        prepareResultPath,
        failures,
        'candidate-result-failure',
    );
    const prepareResult = await readJson(prepareResultPath, failures, 'candidate-result-failure');
    const prepareCompletion = await readJson(
        join(candidate, 'results', 'orchestration', 'prepare-stage-complete.json'),
        failures,
        'candidate-result-failure',
    );
    if (
        prepareResult?.runId !== expectedRunId
        || prepareResult?.stage !== 'prepare'
        || prepareResult?.passed !== true
        || prepareResult?.rootManifestChecksum !== expectedRootManifestChecksum
        || prepareCompletion?.runId !== expectedRunId
        || prepareCompletion?.stage !== 'prepare'
        || prepareCompletion?.passed !== true
        || prepareCompletion?.stageResultChecksum !== (prepareResultBytes ? sha256(prepareResultBytes) : null)
    ) {
        issue(failures, 'candidate-result-failure', prepareResultPath, 'packaged prepare markers are inconsistent');
    }
    const readme = await readFile(join(candidate, 'README.md'), 'utf8').catch(() => '');
    const checklist = await readFile(join(candidate, 'HUMAN_REVIEW_CHECKLIST.md'), 'utf8').catch(() => '');
    if (!/not yet formally approved|unapproved/i.test(readme) || !/No item.*automatically approved/is.test(checklist)) {
        issue(failures, 'candidate-approval-label-failure', candidate, 'candidate prose does not clearly prohibit automatic approval');
    }
    if (!Array.isArray(approvedBaselineRoots) || approvedBaselineRoots.length === 0) {
        issue(
            failures,
            'approved-baseline-proof-missing',
            candidate,
            'no immutable approved-baseline root was supplied for pre/post/current validation',
        );
    }
    for (const entry of approvedBaselineRoots) {
        const approved = resolve(typeof entry === 'string' ? entry : entry.root);
        if (!isInside(workspaceRoot, approved)) {
            issue(failures, 'approved-baseline-path-failure', approved, 'approved-baseline audit root escapes workspace');
            continue;
        }
        const files = await listFiles(approved)
            .then((items) => items.sort())
            .catch((error) => {
                issue(
                    failures,
                    'approved-baseline-inventory-unreadable',
                    approved,
                    `approved baseline inventory could not be read: ${error.message}`,
                );
                return [];
            });
        const rows = [];
        for (const file of files) rows.push(`${portable(approved, file)}:${sha256(await readFile(file))}`);
        const currentChecksum = sha256(rows.join('\n'));
        if (typeof entry === 'string') {
            if (files.length) {
                issue(failures, 'approved-baseline-write-detected', approved, 'approved baseline contains unexpected writes');
            }
        } else if (
            !HASH_PATTERN.test(entry.beforeChecksum ?? '')
            || !HASH_PATTERN.test(entry.afterChecksum ?? '')
            || entry.beforeChecksum !== manifest?.approvedBaselineRootChecksum
            || entry.beforeChecksum !== entry.afterChecksum
            || currentChecksum !== entry.afterChecksum
        ) {
            issue(failures, 'approved-baseline-write-detected', approved, 'approved baseline pre/post/current inventories differ');
        }
    }

    failures.sort((left, right) =>
        left.code.localeCompare(right.code)
        || left.path.localeCompare(right.path)
        || left.message.localeCompare(right.message),
    );
    return {
        passed: failures.length === 0,
        failures,
        totals: {
            files: allFiles.length,
            screenshots: screenshotFiles.length,
            comparisonArtifacts: comparisonFiles.length,
        },
        packageChecksum,
        manifestChecksum,
    };
}

function validateProcessProof(failures, proof, path, expectedStage, expectedRootPid) {
    const records = proof?.recordedOwnedProcesses;
    const recordsPassed = (
        Array.isArray(records)
        && records.length > 0
        && records.every((record) =>
            HASH_PATTERN.test(record?.identity ?? '')
            && isPositiveInteger(record?.pid)
            && Number.isInteger(record?.parentPid)
            && record.parentPid >= 0
            && (record.creationTime === null || isNonEmptyString(record.creationTime))
            && isNonEmptyString(record?.executableName)
            && [
                'pid-plus-windows-creation-date',
                'pid-plus-parent-and-executable-observation',
            ].includes(record?.identityBasis)
            && record.observed === true
            && (record.descendantOfPid === null || isPositiveInteger(record.descendantOfPid))
            && isTimestamp(record.firstObservedAt)
            && isTimestamp(record.lastObservedAt)
            && Array.isArray(record.sources)
            && record.sources.length > 0
            && record.sources.every(isNonEmptyString)
            && record.absentAtProof === true)
    );
    const passed = Boolean(
        proof
        && proof.stage === expectedStage
        && proof.rootPid === expectedRootPid
        && isNonEmptyString(proof.provider)
        && isPositiveInteger(proof.samplingIntervalMs)
        && isPositiveInteger(proof.successfulSamples)
        && Array.isArray(proof.snapshotErrors)
        && proof.snapshotErrors.length === 0
        && proof.rootIdentityObserved === true
        && proof.recordedProcessCount === records?.length
        && recordsPassed
        && records.some((record) => record.pid === expectedRootPid && record.observed === true)
        && Array.isArray(proof.survivors)
        && proof.survivors.length === 0
        && proof.absenceConfirmed === true
        && proof.rootProcessExited === true
        && proof.passed === true
        && isTimestamp(proof.provedAt)
    );
    if (!passed) {
        issue(
            failures,
            'process-proof-failure',
            path,
            `${expectedStage} owned-process identity/absence proof is incomplete or failed`,
        );
    }
    return passed;
}

async function physicalMarkerNames(directory, expected) {
    const markerNames = new Set([
        ...expected,
        'runner-failure.json',
        'stage-in-progress.json',
    ]);
    return (await readdir(directory).catch(() => []))
        .filter((name) => markerNames.has(name))
        .sort();
}

async function validateRunProtocol(
    failures,
    workspaceRoot,
    runRoot,
    manifest,
    rootManifestChecksum,
    expectedRunId,
    aggregate,
    stageResults,
) {
    const manifestSidecarPath = join(runRoot, 'run-manifest.sha256');
    const sidecar = await readRequiredFile(manifestSidecarPath, failures, 'manifest-sidecar-failure');
    if (sidecar?.toString('utf8') !== `${rootManifestChecksum}  run-manifest.json\n`) {
        issue(failures, 'manifest-sidecar-failure', manifestSidecarPath, 'manifest sidecar content differs');
    }
    const currentRunIdPath = join(dirname(runRoot), 'current-run-id.txt');
    const currentRunId = await readRequiredFile(currentRunIdPath, failures, 'current-run-id-failure');
    if (currentRunId?.toString('utf8') !== `${expectedRunId}\n`) {
        issue(failures, 'current-run-id-failure', currentRunIdPath, 'current run pointer differs');
    }

    const preparePath = join(runRoot, 'prepare', 'stage-result.json');
    const prepareBytes = await readRequiredFile(preparePath, failures, 'prepare-result-failure');
    const prepare = prepareBytes ? await readJson(preparePath, failures, 'prepare-result-failure') : null;
    if (
        prepare?.runId !== expectedRunId
        || prepare?.stage !== 'prepare'
        || prepare?.passed !== true
        || prepare?.rootManifestChecksum !== rootManifestChecksum
        || !same(prepare?.selectedPorts, manifest?.selectedPorts)
        || !same(prepare?.browserProfileRoots, manifest?.browserProfileRoots)
        || prepare?.generatedAt !== manifest?.generatedAt
    ) {
        issue(failures, 'prepare-result-failure', preparePath, 'prepare result is missing or inconsistent');
    }
    const prepareCompletionPath = join(runRoot, 'prepare', 'stage-complete.json');
    const prepareCompletion = await readJson(prepareCompletionPath, failures, 'stage-completion-failure');
    if (
        prepareCompletion?.runId !== expectedRunId
        || prepareCompletion?.stage !== 'prepare'
        || prepareCompletion?.passed !== true
        || prepareCompletion?.stageResultChecksum !== (prepareBytes ? sha256(prepareBytes) : null)
        || !isTimestamp(prepareCompletion?.completedAt)
    ) {
        issue(failures, 'stage-completion-failure', prepareCompletionPath, 'prepare completion marker is inconsistent');
    }

    const journalPath = join(runRoot, 'orchestration-journal.json');
    const journal = await readJson(journalPath, failures, 'orchestration-journal-failure');
    const expectedOrder = ['prepare', ...REQUIRED_STAGES];
    exactKeySet(
        failures,
        Array.isArray(journal) ? journal.map((entry) => entry.stage) : [],
        expectedOrder,
        journalPath,
        'orchestration-journal',
    );
    if (!Array.isArray(journal) || !same(journal.map((entry) => entry.stage), expectedOrder)) {
        issue(failures, 'orchestration-journal-order-failure', journalPath, 'journal stage order is not canonical');
    }
    const prepareJournal = journal?.[0];
    if (
        prepareJournal?.stage !== 'prepare'
        || prepareJournal?.exitCode !== 0
        || prepareJournal?.timedOut !== false
        || prepareJournal?.passed !== true
        || prepareJournal?.rootManifestChecksum !== rootManifestChecksum
        || !isTimestamp(prepareJournal?.startedAt)
        || !isTimestamp(prepareJournal?.finishedAt)
    ) {
        issue(failures, 'orchestration-journal-failure', journalPath, 'prepare journal event is incomplete');
    }

    if (!same(Object.keys(aggregate?.stages ?? {}), REQUIRED_STAGES)) {
        issue(failures, 'aggregate-stage-mapping-failure', join(runRoot, 'final-result.json'), 'aggregate stage mapping is not exact');
    }
    if (!same(Object.keys(aggregate?.cleanupResults ?? {}), REQUIRED_STAGES)) {
        issue(failures, 'aggregate-cleanup-mapping-failure', join(runRoot, 'final-result.json'), 'aggregate cleanup mapping is not exact');
    }

    const prepareExpectedMarkers = ['stage-complete.json', 'stage-result.json'];
    const prepareActualMarkers = await physicalMarkerNames(join(runRoot, 'prepare'), prepareExpectedMarkers);
    const physicalStageMarkerEvidence = [{
        stage: 'prepare',
        expected: prepareExpectedMarkers,
        actual: prepareActualMarkers,
        exactMarkers: same(prepareActualMarkers, prepareExpectedMarkers),
        stageResultChecksum: prepareBytes ? sha256(prepareBytes) : null,
        completeStageResultChecksum: prepareCompletion?.stageResultChecksum ?? null,
        passed: Boolean(
            same(prepareActualMarkers, prepareExpectedMarkers)
            && prepare?.passed === true
            && prepareCompletion?.passed === true
            && prepareCompletion?.stageResultChecksum === (prepareBytes ? sha256(prepareBytes) : null)
        ),
    }];

    for (const [index, stage] of REQUIRED_STAGES.entries()) {
        const stageRoot = join(runRoot, stage);
        const resultPath = join(stageRoot, 'stage-result.json');
        const resultBytes = await readRequiredFile(resultPath, failures, 'missing-stage-result');
        const result = stageResults[stage];
        const resultChecksum = resultBytes ? sha256(resultBytes) : null;
        const completionPath = join(stageRoot, 'stage-complete.json');
        const completion = await readJson(completionPath, failures, 'stage-completion-failure');
        if (
            completion?.runId !== expectedRunId
            || completion?.stage !== stage
            || completion?.passed !== true
            || completion?.stageResultChecksum !== resultChecksum
            || !isTimestamp(completion?.completedAt)
        ) {
            issue(failures, 'stage-completion-failure', completionPath, `${stage} completion marker is inconsistent`);
        }
        const inProgressPath = join(stageRoot, 'stage-in-progress.json');
        const inProgressPresent = await access(inProgressPath, fsConstants.F_OK)
            .then(() => true)
            .catch(() => false);
        if (inProgressPresent) {
            issue(failures, 'stage-in-progress-present', inProgressPath, `${stage} retained an incomplete-stage marker`);
        }
        const cleanup = await readJson(join(stageRoot, 'cleanup-result.json'), failures, 'cleanup-result-failure');
        if (!same(cleanup, result?.cleanup)) {
            issue(failures, 'cleanup-result-failure', stageRoot, `${stage} cleanup artifact differs from stage result`);
        }
        const network = await readJson(join(stageRoot, 'console-network-summary.json'), failures, 'network-summary-failure');
        if (!same(network, result?.payload?.networkGate)) {
            issue(failures, 'network-summary-failure', stageRoot, `${stage} network artifact differs from stage result`);
        }
        for (const commonFile of [
            'fixture-summary.json',
            'active-handle-diagnostic.json',
            'lifecycle-events.json',
        ]) {
            await readRequiredFile(join(stageRoot, commonFile), failures, 'stage-artifact-missing');
        }
        const orchestrationPath = join(stageRoot, 'orchestration-result.json');
        const orchestration = await readJson(orchestrationPath, failures, 'orchestration-result-failure');
        const processExitPath = join(stageRoot, 'stage-process-exit.json');
        const processExitBytes = await readRequiredFile(
            processExitPath,
            failures,
            'process-exit-marker-failure',
        );
        const processExit = processExitBytes
            ? await readJson(processExitPath, failures, 'process-exit-marker-failure')
            : null;
        const processProofPassed = validateProcessProof(
            failures,
            processExit?.processProof,
            processExitPath,
            stage,
            orchestration?.childPid,
        );
        const processExitPassed = Boolean(
            processExit?.runId === expectedRunId
            && processExit?.stage === stage
            && processExit?.rootManifestChecksum === rootManifestChecksum
            && processExit?.childPid === orchestration?.childPid
            && processExit?.childExited === true
            && processExit?.exitCode === 0
            && processExit?.signal === null
            && processExit?.timedOut === false
            && processExit?.forcedTreeTermination === false
            && processExit?.termination === null
            && processExit?.passed === true
            && isTimestamp(processExit?.completedAt)
            && processProofPassed
        );
        if (!processExitPassed) {
            issue(failures, 'process-exit-marker-failure', processExitPath, `${stage} process-exit marker is inconsistent`);
        }
        const orchestrationPassed = (
            orchestration?.runId === expectedRunId
            && orchestration?.stage === stage
            && isPositiveInteger(orchestration?.childPid)
            && isTimestamp(orchestration?.startedAt)
            && isTimestamp(orchestration?.finishedAt)
            && isPositiveInteger(orchestration?.timeoutMs)
            && orchestration?.exitCode === 0
            && orchestration?.signal === null
            && orchestration?.spawnError === null
            && orchestration?.timedOut === false
            && orchestration?.forcedTreeTermination === false
            && orchestration?.termination === null
            && orchestration?.resultManifestPresent === true
            && orchestration?.resultChecksum === resultChecksum
            && orchestration?.completionMarkerPassed === true
            && orchestration?.processExitMarkerChecksum === (processExitBytes ? sha256(processExitBytes) : null)
            && same(orchestration?.processProof, processExit?.processProof)
            && orchestration?.inProgressMarkerAbsent === true
            && orchestration?.runnerFailurePresent === false
            && orchestration?.immutableAfterPassed === true
            && orchestration?.immutableAfterError === null
            && orchestration?.passed === true
        );
        if (!orchestrationPassed) {
            issue(failures, 'orchestration-result-failure', orchestrationPath, `${stage} orchestration result is incomplete or failed`);
        }
        if (!same(journal?.[index + 1], orchestration)) {
            issue(failures, 'orchestration-journal-failure', journalPath, `${stage} journal entry differs from orchestration result`);
        }
        if (!same(aggregate?.stages?.[stage], result)) {
            issue(failures, 'aggregate-stage-mapping-failure', resultPath, `${stage} aggregate stage differs from physical result`);
        }
        if (!same(aggregate?.cleanupResults?.[stage], result?.cleanup)) {
            issue(failures, 'aggregate-cleanup-mapping-failure', resultPath, `${stage} aggregate cleanup differs from physical result`);
        }
        const expectedMarkers = [
            'orchestration-result.json',
            'stage-complete.json',
            'stage-process-exit.json',
            'stage-result.json',
        ];
        const actualMarkers = await physicalMarkerNames(stageRoot, expectedMarkers);
        physicalStageMarkerEvidence.push({
            stage,
            expected: expectedMarkers,
            actual: actualMarkers,
            exactMarkers: same(actualMarkers, expectedMarkers),
            stageResultChecksum: resultChecksum,
            completeStageResultChecksum: completion?.stageResultChecksum ?? null,
            passed: Boolean(
                same(actualMarkers, expectedMarkers)
                && result?.passed === true
                && completion?.passed === true
                && completion?.stageResultChecksum === resultChecksum
                && processExitPassed
                && orchestrationPassed
            ),
        });
    }
    const physicalMarkerEvidence = {
        passed: physicalStageMarkerEvidence.length === REQUIRED_STAGES.length + 1
            && physicalStageMarkerEvidence.every((item) => item.passed),
        stages: physicalStageMarkerEvidence,
        inProgressMarkers: physicalStageMarkerEvidence.flatMap((item) =>
            item.actual.includes('stage-in-progress.json') ? [item.stage] : []),
        failureMarkers: physicalStageMarkerEvidence.flatMap((item) =>
            item.actual.includes('runner-failure.json') ? [item.stage] : []),
    };
    if (!same(aggregate?.stageMarkerEvidence, physicalMarkerEvidence)) {
        issue(
            failures,
            'stage-marker-evidence-failure',
            join(runRoot, 'final-result.json'),
            'aggregate exact-marker evidence differs from physical stage markers',
        );
    }
    if (!same(aggregate?.orchestrationOutcomes, journal)) {
        issue(failures, 'orchestration-journal-failure', journalPath, 'aggregate orchestration outcomes differ from journal');
    }
}

async function pathExists(path) {
    return await access(path, fsConstants.F_OK)
        .then(() => true)
        .catch(() => false);
}

async function checksumOptional(path) {
    try {
        return sha256(await readFile(path));
    } catch {
        return null;
    }
}

function resolveTransactionPath(failures, workspaceRoot, evidenceRoot, portablePath, label) {
    if (
        !isNonEmptyString(portablePath)
        || isAbsolute(portablePath)
    ) {
        issue(
            failures,
            'finalization-transaction-path-failure',
            String(portablePath ?? label),
            `${label} must be a non-empty portable workspace path`,
        );
        return null;
    }
    const path = resolve(workspaceRoot, portablePath);
    if (!isInside(evidenceRoot, path)) {
        issue(
            failures,
            'finalization-transaction-path-failure',
            path,
            `${label} escapes the governed evidence root`,
        );
        return null;
    }
    return path;
}

async function validateFinalizationProof({
    failures,
    workspaceRoot,
    runRoot,
    aggregatePath,
    aggregate,
    expectedRunId,
    candidatePath,
    candidateState,
    mode,
}) {
    const evidenceRoot = join(workspaceRoot, 'storage', 'app', 'evidence');
    const metadata = aggregate?.candidateFinalization;
    const contract = aggregate?.finalizationTransaction;
    const aggregateContractPassed = Boolean(
        aggregate?.finalizationState === 'closed-transactionally'
        && metadata?.passed === true
        && metadata?.phase === 'canonical-final-publication'
        && isNonEmptyString(metadata?.transactionId)
        && metadata?.transactionSchema === CANDIDATE_TRANSACTION_SCHEMA
        && metadata?.commitPoint === 'canonical-final-rename'
        && isNonEmptyString(metadata?.durableStatePath)
        && Array.isArray(metadata?.approvedBaselineWrites)
        && metadata.approvedBaselineWrites.length === 0
        && metadata?.formalApprovalState === 'not-requested'
        && contract?.transactionId === metadata.transactionId
        && contract?.schema === CANDIDATE_TRANSACTION_SCHEMA
        && contract?.candidateAssembledBeforeCanonicalPublication === true
        && contract?.candidateChecksummedBeforeCanonicalPublication === true
        && contract?.candidateValidatedBeforeInstall === true
        && contract?.candidateInstalledBySameVolumeRename === true
        && contract?.candidateRevalidatedAfterInstall === true
        && contract?.candidateCommitPreparedBeforeCanonicalPublication === true
        && contract?.candidateLockHeldThroughCanonicalPublication === true
        && contract?.priorCandidateRetainedUntilCanonicalCommit === true
        && contract?.canonicalFinalRenameIsCommitPoint === true
        && contract?.candidateLockReleaseRequiredAfterCanonicalPublication === true
        && contract?.aggregateValidationPasses === 3
        && contract?.validationEvidencePath === 'final-result.validation.json'
        && aggregate?.behavioralAggregateValidation?.passed === true
        && Array.isArray(aggregate?.behavioralAggregateValidation?.failures)
        && aggregate.behavioralAggregateValidation.failures.length === 0
    );
    if (!aggregateContractPassed) {
        issue(
            failures,
            'finalization-aggregate-contract-failure',
            aggregatePath,
            'aggregate finalization metadata is missing, incomplete, or not transactionally closed',
        );
    }

    const expectedRunStatePath = join(runRoot, 'finalization-transaction.json');
    const durableStatePath = resolveTransactionPath(
        failures,
        workspaceRoot,
        evidenceRoot,
        metadata?.durableStatePath,
        'durable transaction state',
    );
    if (!durableStatePath || resolve(durableStatePath) !== resolve(expectedRunStatePath)) {
        issue(
            failures,
            'finalization-transaction-path-failure',
            durableStatePath ?? String(metadata?.durableStatePath ?? ''),
            'aggregate does not reference this run exact durable transaction state',
        );
        return;
    }
    const state = await readJson(
        durableStatePath,
        failures,
        'finalization-transaction-state-missing',
    );
    if (!state) return;
    try {
        validateCandidateTransactionState(state, {
            workspaceRoot,
            evidenceBase: dirname(runRoot),
            candidateRoot: candidatePath,
            candidateLockRoot: `${candidatePath}.finalization-lock`,
            activeStatePath: `${candidatePath}.finalization-transaction.json`,
        });
    } catch (error) {
        issue(
            failures,
            'finalization-transaction-state-failure',
            durableStatePath,
            `strict durable transaction state rejected: ${error.code ?? error.message}`,
        );
        return;
    }

    const statePaths = {};
    for (const [name, portablePath] of Object.entries(state.paths ?? {})) {
        statePaths[name] = resolveTransactionPath(
            failures,
            workspaceRoot,
            evidenceRoot,
            portablePath,
            `transaction path ${name}`,
        );
    }
    const expectedFinalPath = join(runRoot, 'final-result.json');
    const expectedPendingPath = join(runRoot, 'final-result.pending.json');
    const commonStatePassed = Boolean(
        state.schema === CANDIDATE_TRANSACTION_SCHEMA
        && state.version === 2
        && state.runId === expectedRunId
        && state.transactionId === metadata?.transactionId
        && state.commitPoint === 'canonical-final-rename'
        && Number.isInteger(state.sequence)
        && state.sequence > 0
        && Array.isArray(state.events)
        && state.events.length > 0
        && state.events.at(-1)?.sequence === state.sequence
        && state.owner?.runId === expectedRunId
        && isNonEmptyString(state.owner?.token)
        && Number.isInteger(state.owner?.processIdentity?.pid)
        && isNonEmptyString(state.owner?.processIdentity?.creationTime)
        && resolve(statePaths.runStatePath ?? '') === resolve(expectedRunStatePath)
        && resolve(statePaths.finalPath ?? '') === resolve(expectedFinalPath)
        && resolve(statePaths.pendingFinalPath ?? '') === resolve(expectedPendingPath)
        && candidatePath
        && resolve(statePaths.candidateRoot ?? '') === resolve(candidatePath)
        && state.packageChecksum === candidateState?.packageChecksum
        && state.candidateManifestChecksum === candidateState?.manifestChecksum
    );
    if (!commonStatePassed) {
        issue(
            failures,
            'finalization-transaction-state-failure',
            durableStatePath,
            'durable transaction identity, owner, paths, or checksums differ from the aggregate',
        );
    }

    const activeStatePath = statePaths.activeStatePath;
    if (!activeStatePath) {
        issue(
            failures,
            'finalization-active-state-failure',
            durableStatePath,
            'active transaction state path is missing',
        );
    } else {
        const activeState = await readJson(
            activeStatePath,
            failures,
            'finalization-active-state-failure',
        );
        if (!same(activeState, state)) {
            issue(
                failures,
                'finalization-active-state-failure',
                activeStatePath,
                'active and run-scoped durable transaction snapshots differ',
            );
        }
    }

    const installedIdentity = candidatePath
        ? await physicalTreeIdentity(candidatePath).catch(() => null)
        : null;
    if (
        !installedIdentity
        || !samePhysicalTreeIdentity(installedIdentity, state.replacementCandidateIdentity)
        || candidateState?.physicalTreeChecksum !== installedIdentity.treeChecksum
        || state.replacementCandidateIdentity?.packageChecksum !== candidateState?.packageChecksum
        || state.replacementCandidateIdentity?.candidateManifestChecksum !== candidateState?.manifestChecksum
    ) {
        issue(
            failures,
            'finalization-candidate-identity-failure',
            candidatePath ?? aggregatePath,
            'installed candidate physical identity differs from the aggregate or durable transaction',
        );
    }

    if (mode === 'commitpoint') {
        const finalChecksum = await checksumOptional(join(runRoot, 'final-result.json'));
        let previousCandidateRetained = true;
        if (state.previousCandidateIdentity?.present === true) {
            const backupIdentity = statePaths.backupRoot
                ? await physicalTreeIdentity(statePaths.backupRoot).catch(() => null)
                : null;
            const archiveIdentity = statePaths.archiveRoot
                ? await physicalTreeIdentity(statePaths.archiveRoot).catch(() => null)
                : null;
            previousCandidateRetained = Boolean(
                backupIdentity
                && samePhysicalTreeIdentity(
                    backupIdentity,
                    state.previousCandidateIdentity,
                )
                || archiveIdentity
                && samePhysicalTreeIdentity(
                    archiveIdentity,
                    state.previousCandidateIdentity,
                )
            );
        }
        if (
            ![
                'commit-prepared',
                'canonical-final-published',
                'recovery-started',
                'committed',
                'lock-released',
            ].includes(state.phase)
            || !isNonEmptyString(state.pendingAggregateChecksum)
            || finalChecksum !== state.pendingAggregateChecksum
            || resolve(aggregatePath) !== resolve(join(runRoot, 'final-result.json'))
            || !statePaths.lockRoot
            || !await pathExists(statePaths.lockRoot)
            || !statePaths.pendingFinalPath
            || await pathExists(statePaths.pendingFinalPath)
            || !statePaths.temporaryRoot
            || await pathExists(statePaths.temporaryRoot)
            || !previousCandidateRetained
        ) {
            issue(
                failures,
                'finalization-commitpoint-state-failure',
                durableStatePath,
                'physical canonical commit point is not coherently retained under the recovery lock',
            );
        }
        return;
    }

    if (mode === 'prepublication') {
        const lockOwnerPath = statePaths.lockRoot ? join(statePaths.lockRoot, 'owner.json') : null;
        const lockOwner = lockOwnerPath
            ? await readJson(lockOwnerPath, failures, 'finalization-lock-proof-failure')
            : null;
        if (
            state.phase !== 'candidate-installed-validated'
            || state.outcome !== null
            || state.commitPointReached !== false
            || state.pendingAggregateChecksum !== null
            || state.canonicalFinalChecksum !== null
            || resolve(aggregatePath) !== resolve(expectedPendingPath)
            || !same(lockOwner, state.owner)
        ) {
            issue(
                failures,
                'finalization-prepublication-state-failure',
                durableStatePath,
                'pending aggregate is not protected by the exact installed-and-validated transaction lock',
            );
        }
        return;
    }

    const finalChecksum = await checksumOptional(expectedFinalPath);
    const lockAbsent = Boolean(
        statePaths.lockRoot
        && !await pathExists(statePaths.lockRoot)
    );
    const terminalStatePassed = Boolean(
        state.phase === 'lock-released'
        && state.outcome === 'committed'
        && state.commitPointReached === true
        && state.canonicalFinalChecksum === state.pendingAggregateChecksum
        && state.pendingAggregateChecksum === finalChecksum
        && state.events.at(-1)?.phase === 'lock-released'
        && resolve(aggregatePath) === resolve(expectedFinalPath)
        && statePaths.lockRoot
        && (mode === 'postcommit' || lockAbsent)
        && statePaths.temporaryRoot
        && !await pathExists(statePaths.temporaryRoot)
        && statePaths.backupRoot
        && !await pathExists(statePaths.backupRoot)
        && statePaths.pendingFinalPath
        && !await pathExists(statePaths.pendingFinalPath)
    );
    if (!terminalStatePassed) {
        issue(
            failures,
            'finalization-terminal-state-failure',
            durableStatePath,
            'canonical final, terminal journal, lock, or owned transaction remnants are inconsistent',
        );
    }
    if (state.previousCandidateIdentity?.present === true) {
        const archiveIdentity = statePaths.archiveRoot
            ? await physicalTreeIdentity(statePaths.archiveRoot).catch(() => null)
            : null;
        if (!archiveIdentity || !samePhysicalTreeIdentity(
            archiveIdentity,
            state.previousCandidateIdentity,
        )) {
            issue(
                failures,
                'finalization-archive-identity-failure',
                statePaths.archiveRoot ?? durableStatePath,
                'previous candidate archive does not retain the exact pre-transaction identity',
            );
        }
    }

    if (mode !== 'closed') return;
    const validationEvidencePath = join(runRoot, contract?.validationEvidencePath ?? '');
    const validationEvidence = await readJson(
        validationEvidencePath,
        failures,
        'finalization-validation-evidence-missing',
    );
    const evidenceTransaction = validationEvidence?.transaction
        ?? validationEvidence?.physicalClosureValidation?.state;
    if (
        validationEvidence?.runId !== expectedRunId
        || validationEvidence?.aggregateChecksum !== finalChecksum
        || validationEvidence?.passed !== true
        || validationEvidence?.phase !== 'canonical-final-acceptance-prepared'
        || (
            validationEvidence?.transactionId
            ?? evidenceTransaction?.transactionId
        ) !== state.transactionId
        || !same(evidenceTransaction, state)
        || !Array.isArray(validationEvidence?.validationPasses)
        || validationEvidence.validationPasses.length !== contract?.aggregateValidationPasses
        || validationEvidence.validationPasses.some((result) => result?.passed !== true)
        || validationEvidence?.releasePreparedValidation?.passed !== true
        || validationEvidence?.closureBoundary?.candidateLockRemovalIsFinalMutation !== true
        || validationEvidence?.closureBoundary?.noPostReleaseWrites !== true
        || validationEvidence?.closureBoundary?.candidateLockPath !== state.paths.lockRoot
    ) {
        issue(
            failures,
            'finalization-validation-evidence-failure',
            validationEvidencePath,
            'canonical final validation sidecar does not prove the exact closed transaction',
        );
    }
}

/**
 * Validate a physically materialized BE-6A.1 aggregate. Pending validation is
 * explicit; the default requires the closed transaction and validation sidecar.
 */
export async function validateEvidenceAggregate({
    workspaceRoot,
    runRoot,
    expectedRunId,
    expectedWorkingTreeChecksum,
    aggregatePath = join(runRoot, 'final-result.json'),
    approvedBaselineRoots = [],
    finalizationMode = 'closed',
}) {
    const failures = [];
    if (!['prepublication', 'commitpoint', 'postcommit', 'closed'].includes(finalizationMode)) {
        throw new Error(`Unknown BE-6A.1 finalization validation mode: ${finalizationMode}`);
    }
    const manifestPath = join(runRoot, 'run-manifest.json');
    const finalPath = resolve(aggregatePath);
    if (!isInside(runRoot, finalPath)) {
        issue(failures, 'aggregate-path-failure', finalPath, 'aggregate path escapes run root');
    }
    const manifestBytes = await readRequiredFile(manifestPath, failures, 'missing-manifest');
    const rootManifestChecksum = manifestBytes ? sha256(manifestBytes) : null;
    const manifest = manifestBytes ? await readJson(manifestPath, failures, 'missing-manifest') : null;
    const aggregate = await readJson(finalPath, failures, 'missing-final-result');
    validateManifestSchema(failures, manifest, manifestPath, expectedRunId, expectedWorkingTreeChecksum);

    if (aggregate?.runId !== expectedRunId) {
        issue(failures, 'run-id-mismatch', finalPath, `expected ${expectedRunId}, received ${aggregate?.runId ?? 'missing'}`);
    }
    if (aggregate?.rootManifestChecksum !== rootManifestChecksum) {
        issue(failures, 'root-manifest-checksum-mismatch', finalPath, 'aggregate root manifest checksum differs');
    }

    const stageResults = {};
    for (const stage of REQUIRED_STAGES) {
        const path = join(runRoot, stage, 'stage-result.json');
        const result = await readJson(path, failures, 'missing-stage-result');
        if (!result) continue;
        stageResults[stage] = result;
        const immutableExpected = {
            commit: manifest?.commit,
            workingTreeChecksum: manifest?.workingTreeChecksum,
            composerLockChecksum: manifest?.composerLockChecksum,
            npmLockChecksum: manifest?.npmLockChecksum,
            viteManifestChecksum: manifest?.viteManifestChecksum,
            protectedStorefrontManifestChecksum: manifest?.protectedStorefrontManifestChecksum,
            factoryProtectionManifestChecksum: manifest?.factoryProtectionManifestChecksum,
            chromiumVersion: manifest?.chromiumVersion,
        };
        const stageSchemaPassed = (
            result.runId === expectedRunId
            && result.stage === stage
            && result.diagnostic === false
            && result.passed === true
            && Array.isArray(result.failures)
            && result.failures.length === 0
            && result.browserVersion === (stage === 'verify' ? null : manifest?.chromiumVersion)
            && result.rootManifestChecksum === rootManifestChecksum
            && same(result.immutableInventory, immutableExpected)
            && same(result.ports, manifest?.selectedPorts?.[stage])
            && result.profileRoot === manifest?.browserProfileRoots?.[stage]
            && Array.isArray(result.processInventory)
            && result.processInventory.length > 0
            && Array.isArray(result.cleanup)
            && result.cleanup.length > 0
            && result.cleanup.every((item) =>
                item.passed === true
                && isNonEmptyString(item.label)
                && typeof item.forced === 'boolean')
            && result.executionError === null
            && result.requestedExitCode === 0
        );
        if (!stageSchemaPassed) {
            issue(failures, 'stage-schema-failure', path, `${stage} result is incomplete or inconsistent`);
        }
    }

    await validateRunProtocol(
        failures,
        workspaceRoot,
        runRoot,
        manifest,
        rootManifestChecksum,
        expectedRunId,
        aggregate,
        stageResults,
    );
    validateSecurityPayload(failures, stageResults.security, join(runRoot, 'security', 'stage-result.json'));
    validatePreviewPayload(failures, stageResults.preview, join(runRoot, 'preview', 'stage-result.json'));
    for (const stage of ['security', 'preview', 'visual']) {
        networkGatePassed(
            failures,
            stageResults[stage],
            join(runRoot, stage, 'stage-result.json'),
            true,
        );
    }

    const visualPath = join(runRoot, 'visual', 'stage-result.json');
    const visualPayload = stageResults.visual?.payload;
    const visual = visualPayload?.fidelity;
    validateMimeEvidence(failures, visualPayload?.mime, visualPath);
    if (!visual || typeof visual !== 'object' || visual.diagnostic !== false) {
        issue(failures, 'missing-fidelity-payload', visualPath, 'visual stage omitted a non-diagnostic fidelity payload');
    } else {
        const captures = Array.isArray(visual.captures) ? visual.captures : [];
        const diffs = Array.isArray(visual.diffs) ? visual.diffs : [];
        const repeatability = Array.isArray(visual.repeatability) ? visual.repeatability : [];
        const readiness = Array.isArray(visual.readiness) ? visual.readiness : [];
        exactKeySet(
            failures,
            captures.map((item) => `${item.page}|${item.viewport}|${item.run}|${item.side}`),
            CANONICAL_CAPTURE_KEYS,
            visualPath,
            'capture-matrix',
        );
        exactKeySet(
            failures,
            diffs.map((item) => `${item.page}|${item.viewport}|${item.run}`),
            CANONICAL_DIFF_KEYS,
            visualPath,
            'diff-matrix',
        );
        exactKeySet(
            failures,
            repeatability.map((item) => `${item.page}|${item.viewport}`),
            CANONICAL_GROUP_KEYS,
            visualPath,
            'repeatability-matrix',
        );
        exactKeySet(
            failures,
            readiness.map((item) => item.label),
            CANONICAL_READINESS_KEYS,
            visualPath,
            'readiness-matrix',
        );
        checkCount(failures, captures.length, 612, visualPath, 'captures');
        checkCount(failures, diffs.length, 306, visualPath, 'comparisons');
        checkCount(failures, repeatability.length, 102, visualPath, 'repeatability groups');
        checkCount(failures, readiness.length, 612, visualPath, 'readiness records');
        checkCount(failures, new Set(captures.map((item) => item.path)).size, 612, visualPath, 'unique screenshot paths');
        checkCount(failures, new Set(diffs.flatMap((item) => [item.diffPath, item.overlayPath, item.sideBySidePath])).size, 918, visualPath, 'unique comparison paths');

        const captureMap = new Map(captures.map((capture) => [
            `${capture.page}|${capture.viewport}|${capture.run}|${capture.side}`,
            capture,
        ]));
        const readinessMap = new Map(readiness.map((item) => [item.label, item]));
        for (const capture of captures) {
            const label = `${capture.page}:${capture.viewport}:run${capture.run}:${capture.side}`;
            const contract = expectedCaptureContract(capture.page, capture.viewport, capture.side);
            const captureSchemaPassed = (
                capture.status === 200
                && HASH_PATTERN.test(capture.hash ?? '')
                && isPositiveInteger(capture.bytes)
                && capture.responsiveMode === contract?.responsiveMode
                && hasCanonicalGeometryBoxes(capture.boxes, contract)
                && isPositiveInteger(capture.scrollHeight)
                && capture.renderState === 'fully-hydrated-and-visually-stable'
                && capture.intermediateCapture === false
                && Array.isArray(capture.routeBootstrapPaths)
                && Array.isArray(capture.optionalRequestTreatment)
                && isPositiveInteger(capture.browserPid)
                && isPositiveInteger(capture.captureOrder)
                && capture.captureOrder === captures.indexOf(capture) + 1
                && isNonEmptyString(capture.path)
                && validateSemanticSchema(failures, capture.semantic, visualPath, label)
            );
            if (!captureSchemaPassed) {
                issue(failures, 'capture-schema-failure', visualPath, `${label} capture record is incomplete`);
            }
            validateReadinessAndCapture(
                failures,
                readinessMap.get(label),
                capture,
                manifest,
                visualPath,
            );
        }

        for (const comparison of diffs) {
            const groupRun = `${comparison.page}|${comparison.viewport}|${comparison.run}`;
            const staticCapture = captureMap.get(`${groupRun}|static`);
            const laravelCapture = captureMap.get(`${groupRun}|laravel`);
            const staticHref = staticCapture?.semantic?.adminHref;
            const laravelHref = laravelCapture?.semantic?.adminHref;
            const laravelAdminUrl = laravelCapture?.semantic?.laravelAdminUrl;
            const expectedAdminException = isAdminHrefException(
                staticHref,
                laravelHref,
                laravelAdminUrl,
            );
            const semanticFieldsMatch = staticCapture && laravelCapture && [
                'visibleTextChecksum',
                'headingStructureChecksum',
                'imageIdentityChecksum',
                'visibleTextEntries',
                'headingStructureEntries',
                'imageIdentityEntries',
                'keyStyleChecksum',
                'keyStyleEntries',
                'interactiveStateChecksum',
                'interactiveStateEntries',
                'rasterSensitiveRegionChecksum',
                'rasterSensitiveRegionEntries',
                'rasterSensitiveRegions',
            ].every((field) => same(staticCapture.semantic[field], laravelCapture.semantic[field]));
            const semanticPassed = (
                comparison.semanticMatch === true
                && Array.isArray(comparison.semanticMismatchFields)
                && comparison.semanticMismatchFields.length === 0
                && Array.isArray(comparison.semanticVisibleTextDifference?.onlyStatic)
                && comparison.semanticVisibleTextDifference.onlyStatic.length === 0
                && Array.isArray(comparison.semanticVisibleTextDifference?.onlyLaravel)
                && comparison.semanticVisibleTextDifference.onlyLaravel.length === 0
                && isNonEmptyString(comparison.staticAdminHref)
                && isNonEmptyString(comparison.laravelAdminHref)
                && comparison.staticAdminHref === staticHref
                && comparison.laravelAdminHref === laravelHref
                && comparison.adminHrefException === expectedAdminException
                && (staticHref === laravelHref || expectedAdminException)
                && semanticFieldsMatch
            );
            if (!semanticPassed) {
                issue(failures, 'semantic-mismatch', visualPath, `${groupRun} semantic/Admin evidence is incomplete or differs`);
            }
            if (comparison.boxesMatch !== true || comparison.responsiveModeMatch !== true) {
                issue(failures, 'geometry-mismatch', visualPath, `${groupRun} geometry or responsive mode differs`);
            }
        }

        validateStrictRepeatability(failures, { captures, diffs, repeatability, readiness }, visualPath);
        await validatePhysicalFidelity(failures, workspaceRoot, runRoot, captures, diffs);

        const totals = visualPayload?.totals ?? {};
        checkCount(failures, totals.captures, captures.length, visualPath, 'visual total captures');
        checkCount(failures, totals.comparisons, diffs.length, visualPath, 'visual total comparisons');
        checkCount(failures, totals.repeatability, repeatability.length, visualPath, 'visual total repeatability');
        const aggregateTotals = aggregate?.matrixTotals ?? {};
        checkCount(failures, aggregateTotals.captures, captures.length, finalPath, 'aggregate captures');
        checkCount(failures, aggregateTotals.comparisons, diffs.length, finalPath, 'aggregate comparisons');
        checkCount(failures, aggregateTotals.repeatability, repeatability.length, finalPath, 'aggregate repeatability');
        for (const [field, expected] of Object.entries({
            expected: 102,
            present: repeatability.length,
            passed: repeatability.filter((item) => item.passed === true).length,
            exactSideRepeatability: repeatability.filter((item) => item.exactSideRepeatability === true).length,
        })) {
            checkCount(failures, aggregate?.repeatabilityTotals?.[field], expected, finalPath, `aggregate repeatability ${field}`);
        }
        const classifications = {};
        for (const item of repeatability) {
            classifications[item.classification] = (classifications[item.classification] ?? 0) + 1;
        }
        const expectedFidelityTotals = {
            captures: captures.length,
            comparisons: diffs.length,
            repeatability: repeatability.length,
            semanticPassed: diffs.filter((item) => item.semanticMatch === true).length,
            exactRasterComparisons: diffs.filter((item) => item.rawDifferentPixels === 0).length,
            hydratedCaptures: readiness.filter((item) => item.final?.fullyHydrated === true).length,
            visuallyStableCaptures: readiness.filter((item) => item.final?.visuallyStable === true).length,
            intermediateCaptures: captures.filter((item) => item.intermediateCapture === true).length,
            mimePassed: visualPayload?.mime?.passed === true,
            requiredLocalAssetFailures: visualPayload?.networkGate?.requiredLocalAssetFailures?.length ?? -1,
            blockingNetworkEvents: visualPayload?.networkGate?.blocking?.length ?? -1,
            classifications,
        };
        for (const [field, expected] of Object.entries(expectedFidelityTotals)) {
            if (!same(aggregate?.fidelityTotals?.[field], expected)) {
                issue(failures, 'fidelity-total-mismatch', finalPath, `${field} differs from the visual evidence`);
            }
        }

        const expectedSummaries = {
            'mime-preflight.json': visualPayload?.mime,
            'semantic-readiness-summary.json': readiness,
            'fidelity-repeatability-summary.json': repeatability,
            'static-reference-checksums.json': captures.filter((item) => item.side === 'static'),
            'laravel-capture-checksums.json': captures.filter((item) => item.side === 'laravel'),
            'fidelity-diff-summary.json': diffs,
            'console-network-summary.json': visualPayload?.networkGate,
        };
        for (const [name, expected] of Object.entries(expectedSummaries)) {
            const summaryPath = join(runRoot, 'visual', name);
            const summary = await readJson(summaryPath, failures, 'visual-summary-failure');
            if (!same(summary, expected)) {
                issue(failures, 'visual-summary-failure', summaryPath, 'summary differs from stage result');
            }
        }
        const stabilityPath = join(runRoot, 'visual', 'visual-stability-summary.json');
        const stability = await readJson(stabilityPath, failures, 'visual-summary-failure');
        if (
            !Array.isArray(stability)
            || stability.length !== 612
            || stability.some((item, index) =>
                item.label !== readiness[index]?.label
                || item.passed !== true
                || item.renderState !== 'fully-hydrated-and-visually-stable'
                || !same(item.final, readiness[index]?.final)
                || item.frames !== readiness[index]?.timeline?.length)
        ) {
            issue(failures, 'visual-summary-failure', stabilityPath, 'visual stability summary is incomplete or differs');
        }
    }

    for (const summary of REQUIRED_VISUAL_SUMMARIES) {
        await readRequiredFile(join(runRoot, 'visual', summary), failures, 'missing-visual-summary');
    }

    const securityTotals = aggregate?.securityTotals;
    if (
        securityTotals?.privileged?.expected !== 45
        || securityTotals.privileged.present !== 45
        || securityTotals.privileged.passed !== 45
        || securityTotals?.staticFooter?.expected !== 2
        || securityTotals.staticFooter.passed !== 2
        || securityTotals?.projectedFooter?.expected !== 2
        || securityTotals.projectedFooter.passed !== 2
        || securityTotals?.logout !== true
    ) {
        issue(failures, 'security-total-mismatch', finalPath, 'aggregate security totals are incomplete');
    }
    if (
        aggregate?.previewTotals?.expected !== 7
        || aggregate.previewTotals.present !== 7
        || aggregate.previewTotals.passed !== 7
    ) {
        issue(failures, 'preview-total-mismatch', finalPath, 'aggregate preview totals are incomplete');
    }

    const orphan = aggregate?.orphanProcessResult;
    const expectedPorts = manifest
        ? Object.entries(manifest.selectedPorts ?? {}).flatMap(([stage, ports]) =>
            Object.entries(ports).map(([kind, port]) => ({ stage, kind, port, released: true })))
        : [];
    const expectedProfiles = manifest
        ? Object.entries(manifest.browserProfileRoots ?? {})
            .map(([stage, path]) => ({ stage, path, released: true }))
        : [];
    const portEvidencePassed = expectedPorts.length === 8
        && same(orphan?.selectedPortsReleased, expectedPorts);
    const profileEvidencePassed = expectedProfiles.length === 4
        && same(orphan?.browserProfilesReleased, expectedProfiles);
    const orphanEvidencePassed = (
        orphan?.passed === true
        && orphan?.ownedProcessCleanupPassed === true
        && portEvidencePassed
        && profileEvidencePassed
        && isNonEmptyString(orphan?.unrelatedProcessPolicy)
    );
    if (!orphanEvidencePassed) {
        issue(failures, 'orphan-check-failure', finalPath, 'orphan, port, profile, or owned-process evidence did not pass');
    }
    let forcedCleanupPresent = false;
    for (const stage of REQUIRED_STAGES) {
        const events = aggregate?.cleanupResults?.[stage] ?? [];
        if (events.some((item) => item.forced === true)) forcedCleanupPresent = true;
        if (events.some((item) => item.passed !== true)) {
            issue(failures, 'cleanup-failure', finalPath, `${stage} cleanup contains a failed or unverified event`);
        }
    }
    if (forcedCleanupPresent && !orphanEvidencePassed) {
        issue(failures, 'forced-cleanup-unverified', finalPath, 'forced cleanup lacks passing orphan, port, and profile evidence');
    }

    if (aggregate?.passed !== true || aggregate?.remainingBlocker !== null) {
        issue(failures, 'aggregate-not-closed', finalPath, 'aggregate is not in a passing, blocker-free state');
    }
    const candidateState = aggregate?.baselineCandidate;
    const candidatePath = candidateState?.path
        ? resolve(workspaceRoot, candidateState.path)
        : null;
    if (
        candidateState?.approvalState !== 'unapproved'
        || candidateState?.autoApproved !== false
        || candidateState?.validationPassed !== true
        || candidateState?.installedAtomically !== true
        || !isNonEmptyString(candidateState?.packageChecksum)
        || !isNonEmptyString(candidateState?.manifestChecksum)
        || candidateState?.preInstallValidation?.passed !== true
        || candidateState?.postInstallValidation?.passed !== true
        || !candidatePath
        || !isInside(workspaceRoot, candidatePath)
        || /approved-baseline|baseline-approved/i.test(candidateState.path)
    ) {
        issue(failures, 'candidate-aggregate-state-failure', finalPath, 'aggregate candidate state is missing, approved, or incomplete');
    } else {
        const candidateValidation = await validateBaselineCandidate({
            workspaceRoot,
            candidateRoot: candidatePath,
            expectedRunId,
            expectedRootManifestChecksum: rootManifestChecksum,
            expectedWorkingTreeChecksum,
            expectedProtectedStorefrontManifestChecksum: manifest?.protectedStorefrontManifestChecksum,
            expectedFactoryProtectionManifestChecksum: manifest?.factoryProtectionManifestChecksum,
            approvedBaselineRoots,
        });
        if (!candidateValidation.passed) failures.push(...candidateValidation.failures);
        if (
            candidateState.packageChecksum !== candidateValidation.packageChecksum
            || candidateState.manifestChecksum !== candidateValidation.manifestChecksum
        ) {
            issue(failures, 'candidate-aggregate-state-failure', finalPath, 'aggregate candidate checksums differ from installed package');
        }
    }

    await validateFinalizationProof({
        failures,
        workspaceRoot,
        runRoot,
        aggregatePath: finalPath,
        aggregate,
        expectedRunId,
        candidatePath,
        candidateState,
        mode: finalizationMode,
    });

    failures.sort((left, right) =>
        left.code.localeCompare(right.code)
        || left.path.localeCompare(right.path)
        || left.message.localeCompare(right.message),
    );
    return {
        passed: failures.length === 0,
        failures,
        totals: {
            stages: Object.keys(stageResults).length,
            groups: visual?.repeatability?.length ?? 0,
            captures: visual?.captures?.length ?? 0,
            comparisons: visual?.diffs?.length ?? 0,
            readiness: visual?.readiness?.length ?? 0,
        },
    };
}

export async function allocateLoopbackPort() {
    return await new Promise((resolvePort, reject) => {
        const server = createServer();
        server.unref();
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : null;
            server.close(() => port ? resolvePort(port) : reject(new Error('Could not allocate a loopback port.')));
        });
    });
}

export async function portReleased(port) {
    return await new Promise((resolveReleased) => {
        const server = createServer();
        server.unref();
        server.once('error', () => resolveReleased(false));
        server.listen(port, '127.0.0.1', () => server.close(() => resolveReleased(true)));
    });
}

export async function probeSelectedPorts(selectedPorts) {
    const results = [];
    for (const [stage, ports] of Object.entries(selectedPorts)) {
        for (const [kind, port] of Object.entries(ports)) {
            results.push({ stage, kind, port, released: await portReleased(port) });
        }
    }
    return {
        passed: results.every((item) => item.released),
        selectedPortsReleased: results,
    };
}

export function childAlive(child) {
    return child.exitCode === null && child.signalCode === null;
}

export async function startHarnessChild({ label, port, mode = 'cooperative', readyTimeoutMs = 5_000 }) {
    const child = fork(childFixture, [], {
        env: {
            ...process.env,
            BE6A1_HARNESS_CHILD_LABEL: label,
            BE6A1_HARNESS_CHILD_MODE: mode,
            BE6A1_HARNESS_CHILD_PORT: String(port),
        },
        stdio: ['ignore', 'ignore', 'ignore', 'ipc'],
        windowsHide: true,
    });
    return await new Promise((resolveChild, reject) => {
        const onError = (error) => {
            clearTimeout(timer);
            child.removeListener('exit', onExit);
            reject(error);
        };
        const onExit = (code, signal) => {
            clearTimeout(timer);
            child.removeListener('error', onError);
            reject(new Error(`${label} exited before ready (code ${code}, signal ${signal})`));
        };
        const onMessage = (message) => {
            if (message?.type !== 'ready') return;
            clearTimeout(timer);
            child.removeListener('error', onError);
            child.removeListener('exit', onExit);
            child.removeListener('message', onMessage);
            resolveChild({ label, port, mode, child, pid: child.pid });
        };
        const timer = setTimeout(() => {
            child.removeListener('error', onError);
            child.removeListener('exit', onExit);
            child.removeListener('message', onMessage);
            child.kill('SIGKILL');
            reject(new Error(`${label} did not become ready within ${readyTimeoutMs}ms`));
        }, readyTimeoutMs);
        child.once('error', onError);
        child.once('exit', onExit);
        child.on('message', onMessage);
    });
}

function awaitExit(child, timeoutMs) {
    if (!childAlive(child)) {
        return Promise.resolve({ exited: true, exitCode: child.exitCode, signalCode: child.signalCode });
    }
    return new Promise((resolveExit) => {
        const timer = setTimeout(() => {
            child.removeListener('exit', onExit);
            resolveExit({ exited: false, exitCode: child.exitCode, signalCode: child.signalCode });
        }, timeoutMs);
        const onExit = (exitCode, signalCode) => {
            clearTimeout(timer);
            resolveExit({ exited: true, exitCode, signalCode });
        };
        child.once('exit', onExit);
    });
}

export async function terminateOwnedChildren(owned, {
    gracefulTimeoutMs = 1_000,
    forceTimeoutMs = 3_000,
} = {}) {
    const results = [];
    for (const entry of owned) {
        const { child, label } = entry;
        const startedAt = new Date().toISOString();
        if (childAlive(child) && child.connected) child.send({ type: 'shutdown' }, () => {});
        let outcome = await awaitExit(child, gracefulTimeoutMs);
        let forced = false;
        if (!outcome.exited) {
            forced = true;
            child.kill('SIGKILL');
            outcome = await awaitExit(child, forceTimeoutMs);
        }
        results.push({
            label,
            pid: child.pid,
            startedAt,
            finishedAt: new Date().toISOString(),
            forced,
            passed: outcome.exited,
            exitCode: outcome.exitCode,
            signalCode: outcome.signalCode,
        });
    }
    return results;
}
