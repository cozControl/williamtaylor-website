import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import {
    copyFile,
    mkdir,
    mkdtemp,
    readFile,
    readdir,
    rename,
    rm,
    unlink,
    writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { PNG } from 'pngjs';
import {
    canonicalPng,
    classifyNetworkEvidence,
    compareRasterBuffers,
    compareSemantics,
    isAdminHrefException,
} from './be6a1-fidelity-contract.mjs';
import {
    CANONICAL_CAPTURE_KEYS,
    CANONICAL_DIFF_KEYS,
    CANONICAL_GROUP_KEYS,
    CANONICAL_READINESS_KEYS,
    HOMEPAGE_VIEWPORTS,
    REQUIRED_STAGES,
    STANDARD_VIEWPORTS,
    allocateLoopbackPort,
    childAlive,
    expectedCaptureContract,
    fidelityComparisonFingerprint,
    hasCanonicalGeometryBoxes,
    portReleased,
    probeSelectedPorts,
    startHarnessChild,
    terminateOwnedChildren,
    validateBaselineCandidate,
    validateEvidenceAggregate,
} from './be6a1-harness.mjs';
import {
    createCandidateTransactionCoordinator,
    physicalTreeIdentity,
} from './be6a1-candidate-transaction.mjs';

const RUN_ID = 'be6a1-behavioral-fixture';
const WORKING_TREE_CHECKSUM = '1'.repeat(64);
const CHROMIUM_VERSION = '123.0.0.0';
const GENERATED_AT = '2026-07-27T00:00:00.000Z';
const BROWSER_LAUNCH_ARGS_FIXTURE = ['--headless', '--disable-gpu'];
const VISUAL_NORMALIZATION_CSS_FIXTURE = 'html{font-synthesis:none;text-rendering:geometricPrecision}';
const STATIC_ROUTER_NORMALIZATION_FIXTURE = {
    id: 'be6a1-static-router-normalization-v1',
    routes: ['/'],
};
const DECORATIVE_MEDIA_ALLOWLIST_FIXTURE = {
    policy: 'decorative-media-only',
    sources: ['/images/decorative-fixture.png'],
};
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
const PREVIEW_CASES = [
    ['valid-authorized', 200, '/preview/page/about/revision/revision-1'],
    ['missing-signature', 403, '/preview/page/about/revision/revision-1'],
    ['expired-signature', 403, '/preview/page/about/revision/revision-1'],
    ['changed-resource', 404, '/preview/page/other/revision/revision-1'],
    ['changed-revision', 404, '/preview/page/about/revision/other'],
    ['unverified', 200, '/email/verify'],
    ['unauthorized', 403, '/preview/page/about/revision/revision-1'],
];
const PNG_BY_VIEWPORT = new Map();
const ARTIFACTS_BY_VIEWPORT = new Map();

let assertionCount = 0;
const liveChildren = new Set();

function check(condition, message) {
    assert.ok(condition, message);
    assertionCount += 1;
}

function hasFailure(result, code) {
    return result.failures.some((failure) => failure.code === code);
}

function serialized(value) {
    return `${JSON.stringify(value, null, 2)}\n`;
}

function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

async function json(path, value) {
    await mkdir(dirname(path), { recursive: true });
    await writeFile(path, serialized(value));
}

function portable(workspaceRoot, path) {
    return relative(workspaceRoot, path).replaceAll('\\', '/');
}

async function filesUnder(directory) {
    const files = [];
    for (const entry of await readdir(directory, { withFileTypes: true }).catch(() => [])) {
        const path = join(directory, entry.name);
        if (entry.isDirectory()) files.push(...await filesUnder(path));
        else if (entry.isFile()) files.push(path);
    }
    return files;
}

async function directoryChecksum(directory) {
    const rows = [];
    for (const path of (await filesUnder(directory)).sort()) {
        rows.push(`${portable(directory, path)}:${sha256(await readFile(path))}`);
    }
    return sha256(rows.join('\n'));
}

function fixtureProcessProof(stage, rootPid) {
    const identity = sha256(`${rootPid}|fixture-creation-time|node.exe`);
    return {
        stage,
        rootPid,
        provider: 'fixture-creation-identity-provider',
        samplingIntervalMs: 2_000,
        successfulSamples: 2,
        snapshotErrors: [],
        rootIdentityObserved: true,
        recordedProcessCount: 1,
        recordedOwnedProcesses: [{
            identity,
            pid: rootPid,
            parentPid: 1,
            creationTime: 'fixture-creation-time',
            executableName: 'node.exe',
            identityBasis: 'pid-plus-windows-creation-date',
            observed: true,
            descendantOfPid: null,
            firstObservedAt: GENERATED_AT,
            lastObservedAt: GENERATED_AT,
            sources: [`orchestrator:${stage}:root:fixture`],
            absentAtProof: true,
        }],
        survivors: [],
        absenceConfirmed: true,
        rootProcessExited: true,
        passed: true,
        provedAt: GENERATED_AT,
    };
}

function selectedPorts() {
    return Object.fromEntries(
        REQUIRED_STAGES.map((stage, index) => [
            stage,
            { static: 31_000 + (index * 2), laravel: 31_001 + (index * 2) },
        ]),
    );
}

function profileRoots() {
    return Object.fromEntries(REQUIRED_STAGES.map((stage) => [
        stage,
        `storage/app/test-runtime/browser/${RUN_ID}/${stage}/playwright-profile-root`,
    ]));
}

function groupParts(group) {
    const [page, viewport] = group.split('|');
    return { page, viewport };
}

function pngForViewport(viewport) {
    if (PNG_BY_VIEWPORT.has(viewport)) return PNG_BY_VIEWPORT.get(viewport);
    const [width, height] = viewport.split('x').map(Number);
    const png = new PNG({ width, height });
    for (let index = 0; index < png.data.length; index += 4) {
        png.data[index] = 32;
        png.data[index + 1] = 64;
        png.data[index + 2] = 96;
        png.data[index + 3] = 255;
    }
    const buffer = canonicalPng(PNG.sync.write(png));
    PNG_BY_VIEWPORT.set(viewport, buffer);
    return buffer;
}

function artifactsForViewport(viewport) {
    if (ARTIFACTS_BY_VIEWPORT.has(viewport)) return ARTIFACTS_BY_VIEWPORT.get(viewport);
    const capture = pngForViewport(viewport);
    const source = PNG.sync.read(capture);
    const raster = compareRasterBuffers(capture, capture);
    const overlay = new PNG({ width: source.width, height: source.height });
    for (let index = 0; index < overlay.data.length; index += 4) {
        overlay.data[index] = source.data[index];
        overlay.data[index + 1] = source.data[index + 1];
        overlay.data[index + 2] = source.data[index + 2];
        overlay.data[index + 3] = 255;
    }
    const side = new PNG({ width: source.width * 2, height: source.height });
    PNG.bitblt(source, side, 0, 0, source.width, source.height, 0, 0);
    PNG.bitblt(source, side, 0, 0, source.width, source.height, source.width, 0);
    const result = {
        raster,
        diff: raster.rawDiffBuffer,
        overlay: PNG.sync.write(overlay),
        side: PNG.sync.write(side),
    };
    ARTIFACTS_BY_VIEWPORT.set(viewport, result);
    return result;
}

function semanticFor(group, side) {
    const { page } = groupParts(group);
    const visibleTextEntries = [`H1:${group}`, 'A:Admin'];
    const headingStructureEntries = [`H1:${group}`];
    const imageIdentityEntries = ['logo.png:William Taylor'];
    const keyStyleEntries = ['HEADER:block|static|100px|80px'];
    const interactiveStateEntries = ['A:Admin:/admin:enabled'];
    const rasterSensitiveRegionEntries = [`text:${group}:0:0:200:80`];
    const rasterSensitiveRegions = [{
        x: 0,
        y: 0,
        width: 200,
        height: 80,
        type: 'text',
        identity: `${group}:heading`,
    }];
    const adminHref = page === 'login'
        ? 'not-applicable:no-admin-footer-link'
        : side === 'laravel'
            ? 'http://127.0.0.1:31000/admin'
            : ['homepage', 'about'].includes(page)
                ? 'html/admin.html'
                : '/admin';
    return {
        visibleTextChecksum: sha256(visibleTextEntries.join('|')),
        headingStructureChecksum: sha256(headingStructureEntries.join('|')),
        imageIdentityChecksum: sha256(imageIdentityEntries.join('|')),
        visibleTextEntries,
        headingStructureEntries,
        imageIdentityEntries,
        keyStyleChecksum: sha256(keyStyleEntries.join('|')),
        keyStyleEntries,
        interactiveStateChecksum: sha256(interactiveStateEntries.join('|')),
        interactiveStateEntries,
        rasterSensitiveRegionChecksum: sha256(rasterSensitiveRegionEntries.join('|')),
        rasterSensitiveRegionEntries,
        rasterSensitiveRegions,
        adminHref,
        laravelAdminUrl: 'http://127.0.0.1:31000/admin',
    };
}

function cleanNetworkGate(stage) {
    const event = {
        at: GENERATED_AT,
        label: `${stage}:fixture`,
        phase: 'cleanup-after-evidence',
        type: 'error',
        text: 'expected fixture event',
        classification: 'expected-fixture-event',
        allowed: true,
    };
    return {
        passed: true,
        rawCounts: { console: 1, failedRequests: 0, errorResponses: 0 },
        classifiedCounts: { 'expected-fixture-event': 1 },
        optionalServiceContract: {
            scope: 'captures-explicitly-requiring-protected-spa',
            expectedCallsPerProtectedSpaCapture: 4,
            expectedConsoleErrorsPerProtectedSpaCapture: 5,
            protectedSpaCaptureCount: stage === 'visual' ? 591 : 0,
            serverRenderedCaptureCount: stage === 'visual' ? 21 : 0,
            failures: [],
        },
        correlations: {
            generic404ConsoleCount: 0,
            optionalOrNegative404Count: 0,
            genericCorrelationPassed: true,
            appStateConsoleCount: 0,
            settings404Count: 0,
            appStateCorrelationPassed: true,
        },
        requiredLocalAssetFailures: [],
        moduleMimeRefusals: [],
        blocking: [],
        evidence: { console: [event], failedRequests: [], errorResponses: [] },
    };
}

function securityPayload() {
    const routeMatrix = [];
    for (const path of ADMIN_ROUTES) {
        for (const state of ADMIN_STATES) {
            const expected =
                state === 'guest' ? 'login'
                : state === 'unverified' ? 'verification'
                : state === 'ordinary' ? 'forbidden'
                : state === 'adminOnly' ? (path === '/admin' ? 'allowed' : 'forbidden')
                : 'allowed';
            routeMatrix.push({
                label: `admin:${state}:${path}`,
                requestedPath: path,
                status: expected === 'forbidden' ? 403 : 200,
                finalPath: expected === 'login' ? '/login' : expected === 'verification' ? '/email/verify' : path,
                title: 'Fixture',
                adminBodyVisible: expected === 'allowed',
                verificationVisible: expected === 'verification',
                forbiddenVisible: expected === 'forbidden',
                cacheControl: 'no-store',
                expected,
                passed: true,
            });
        }
    }
    const footer = [375, 1440].map((width) => ({
        width,
        height: width === 375 ? 812 : 900,
        href: 'http://127.0.0.1:31000/admin',
        legacyCount: 0,
        clickFinalPath: '/login',
        stableHash: '2'.repeat(64),
        screenshot: `security/screenshots/footer/static/${width}x${width === 375 ? 812 : 900}.png`,
        passed: true,
    }));
    const projected = [375, 1440].map((width) => ({
        width,
        height: width === 375 ? 812 : 900,
        href: 'http://127.0.0.1:31000/admin',
        unsafe: 0,
        screenshot: `security/screenshots/footer/projected/${width}x${width === 375 ? 812 : 900}.png`,
        passed: true,
    }));
    const aboutModes = [
        ['static', false, true],
        ['shadow', false, true],
        ['enabled-isolated', true, false],
        ['emergency-disabled', false, true],
        ['global-kill', false, true],
    ].map(([name, expectedProjected, protectedScript]) => ({
        name,
        status: 200,
        configuredMode: name === 'enabled-isolated' ? 'enabled' : name,
        globalEnabled: name !== 'global-kill',
        expectedProjected,
        projected: expectedProjected,
        protectedScript,
        governedHeading: expectedProjected,
        governedFixtureAvailable: true,
        resourceKey: 'page',
        screenshot: `security/screenshots/about-modes/${name}.png`,
        passed: true,
    }));
    return {
        admin: {
            routeMatrix,
            footer,
            logout: { finalPath: '/login', directStatus: 200, adminBodyVisible: false, passed: true },
            requestLog: [{ url: '/admin' }],
        },
        projected: { results: projected, requestLog: [{ url: '/' }] },
        aboutModes: {
            results: aboutModes,
            totals: { expected: 5, present: 5, passed: 5 },
        },
        totals: {
            privileged: { expected: 45, present: 45, passed: 45 },
            staticFooter: { expected: 2, present: 2, passed: 2 },
            projectedFooter: { expected: 2, present: 2, passed: 2 },
            logout: true,
            aboutModes: { expected: 5, present: 5, passed: 5 },
        },
        networkGate: cleanNetworkGate('security'),
    };
}

function previewPayload() {
    const results = PREVIEW_CASES.map(([name, status, finalPath]) => ({
        name,
        status,
        finalPath,
        marker: name === 'valid-authorized',
        robots: name === 'valid-authorized' ? 'noindex,nofollow' : null,
        xRobots: name === 'valid-authorized' ? 'noindex, nofollow' : null,
        cacheControl: name === 'valid-authorized' ? 'private, no-store' : 'no-store',
        permissionLeak: false,
        publicNavigationExposure: false,
        publicCacheEntry: false,
        passed: true,
    }));
    return {
        preview: { results, requestLog: [{ url: '/preview/page/about/revision/revision-1' }] },
        totals: { expected: 7, present: 7, passed: 7 },
        networkGate: cleanNetworkGate('preview'),
    };
}

function mimeEvidence() {
    const checks = [];
    for (const origin of ['static', 'laravel']) {
        const prefix = origin === 'laravel' ? '/website' : '';
        checks.push(
            { origin, kind: 'javascript', path: `${prefix}/js/app.js`, status: 200, contentType: 'application/javascript', htmlFallback: false, passed: true },
            { origin, kind: 'css', path: `${prefix}/css/app.css`, status: 200, contentType: 'text/css', htmlFallback: false, passed: true },
            { origin, kind: 'image', path: `${prefix}/images/logo.png`, status: 200, contentType: 'image/png', htmlFallback: false, passed: true },
            { origin, kind: 'font', path: null, status: null, contentType: null, disposition: 'not-applicable-no-required-local-font-assets', passed: true },
            { origin, kind: 'router-fallback', path: `${prefix}/js/missing.mjs`, status: 404, contentType: 'text/plain', htmlFallback: false, passed: true },
        );
    }
    return {
        passed: true,
        failFast: true,
        checks,
        totals: { expected: checks.length, passed: checks.length, failed: 0 },
    };
}

function finalSnapshot(group, viewport, side, buffer) {
    const [width, height] = viewport.split('x').map(Number);
    const { page } = groupParts(group);
    const contract = expectedCaptureContract(page, viewport, side);
    assert.ok(contract);
    const semantic = semanticFor(group, side);
    const landmark = (box) => contract.requiresChrome ? box : null;
    const boxes = [
        ['header', landmark([0, 0, width, 80])],
        ['main', landmark([0, 80, width, Math.max(height - 160, 1)])],
        ['footer', landmark([0, Math.max(height - 80, 0), width, Math.min(height, 80)])],
        ['desktop-navigation', contract.responsiveMode === 'desktop'
            ? [Math.max(width - 400, 0), 0, Math.min(width, 400), 80]
            : null],
        ['mobile-navigation', contract.responsiveMode === 'mobile'
            ? [0, Math.max(height - 64, 0), width, Math.min(height, 64)]
            : null],
    ];
    return {
        readyState: 'complete',
        pathname: '/',
        bodyChildren: contract.requiresChrome ? 3 : 1,
        rootChildren: contract.requiresChrome ? 3 : 1,
        rootHtmlLength: 1_000,
        bodyHeight: height,
        rootHeight: height,
        mainHeight: contract.requiresChrome ? Math.max(height - 160, 1) : 0,
        scrollHeight: height,
        visibleHeadings: 1,
        headingMatched: true,
        routeLandmarkMatched: true,
        visibleImages: 1,
        loadedImages: 1,
        pendingImages: [],
        failedImages: [],
        visibleVideos: 0,
        visibleVideosReady: true,
        videoSources: [],
        readyVideoSources: [],
        fontsReady: true,
        stylesheetLoaded: true,
        scriptLoaded: contract.requiresScript,
        requiresScript: contract.requiresScript,
        requiresChrome: contract.requiresChrome,
        requiresSpa: contract.requiresSpa,
        headerVisible: contract.requiresChrome,
        mainVisible: contract.requiresChrome,
        footerVisible: contract.requiresChrome,
        rootVisible: true,
        rootDisplay: 'block',
        rootVisibility: 'visible',
        rootOpacity: '1',
        bodyBackground: 'rgb(32, 64, 96)',
        responsiveMode: contract.responsiveMode,
        expectedResponsiveMode: contract.responsiveMode,
        activeAnimations: 0,
        applicationInitialized: contract.requiresSpa,
        spaInitialized: contract.requiresSpa,
        observedRequestCount: contract.requiresSpa ? 4 : 0,
        completedRequestCount: contract.requiresSpa ? 4 : 0,
        outstandingRequests: 0,
        scrollPosition: [0, 0],
        visibleSections: contract.requiresChrome ? 1 : 0,
        imageState: [{
            source: '/images/logo.png',
            srcset: '',
            complete: true,
            naturalWidth: 100,
            naturalHeight: 50,
            renderedWidth: 100,
            renderedHeight: 50,
            visible: true,
        }],
        videoState: [],
        domChecksum: '3'.repeat(64),
        computedStyleChecksum: '4'.repeat(64),
        ...semantic,
        boxes,
        routeBootstrapPaths: contract.requiresSpa ? ['/fixture-route'] : [],
        expectedPath: '/',
        minimumHeight: height,
        atMs: 750,
        semanticFailures: [],
        screenshotHash: sha256(buffer),
        screenshotBytes: buffer.length,
        rasterDeltaPixels: 0,
        exactRasterStable: true,
        stableFrameCount: 3,
        serverStaticShellOnly: false,
        fullyHydrated: true,
        visuallyStable: true,
        intermediateFrame: false,
        renderState: 'fully-hydrated-and-visually-stable',
        side,
    };
}

function readinessTimeline(final) {
    const common = {
        fullyHydrated: true,
        semanticFailures: [],
        screenshotHash: final.screenshotHash,
        screenshotBytes: final.screenshotBytes,
        requiresScript: final.requiresScript,
        requiresSpa: final.requiresSpa,
        requiresChrome: final.requiresChrome,
        responsiveMode: final.responsiveMode,
        expectedResponsiveMode: final.expectedResponsiveMode,
        boxes: final.boxes,
    };
    return [
        {
            ...common,
            atMs: 250,
            rasterDeltaPixels: null,
            exactRasterStable: false,
            stableFrameCount: 1,
            visuallyStable: false,
            renderState: 'fully-hydrated-awaiting-exact-stability',
        },
        {
            ...common,
            atMs: 500,
            rasterDeltaPixels: 0,
            exactRasterStable: true,
            stableFrameCount: 2,
            visuallyStable: false,
            renderState: 'fully-hydrated-awaiting-exact-stability',
        },
        {
            ...common,
            atMs: 750,
            rasterDeltaPixels: 0,
            exactRasterStable: true,
            stableFrameCount: 3,
            visuallyStable: true,
            renderState: 'fully-hydrated-and-visually-stable',
        },
    ];
}

async function buildVisual(workspaceRoot, runRoot) {
    const captures = [];
    const readiness = [];
    const diffs = [];
    const repeatability = [];
    for (const group of CANONICAL_GROUP_KEYS) {
        const { page, viewport } = groupParts(group);
        const buffer = pngForViewport(viewport);
        const hash = sha256(buffer);
        const groupDiffs = [];
        for (const run of [1, 2, 3]) {
            for (const side of ['static', 'laravel']) {
                const path = join(
                    runRoot,
                    'visual',
                    'screenshots',
                    'fidelity',
                    page,
                    viewport,
                    `run-${run}`,
                    `${side}.png`,
                );
                await mkdir(dirname(path), { recursive: true });
                await writeFile(path, buffer);
                const final = finalSnapshot(group, viewport, side, buffer);
                const capture = {
                    page,
                    viewport,
                    run,
                    side,
                    status: 200,
                    hash,
                    bytes: buffer.length,
                    responsiveMode: final.responsiveMode,
                    boxes: final.boxes,
                    scrollHeight: final.scrollHeight,
                    renderState: final.renderState,
                    intermediateCapture: false,
                    routeBootstrapPaths: final.routeBootstrapPaths,
                    normalization: {
                        id: side === 'static' ? STATIC_ROUTER_NORMALIZATION_FIXTURE.id : null,
                        expected: side === 'static',
                        passed: true,
                    },
                    semantic: semanticFor(group, side),
                    path: portable(workspaceRoot, path),
                    optionalRequestTreatment: [{ classification: 'expected-fixture-event', allowed: true }],
                    browserPid: 42_000,
                    captureOrder: captures.length + 1,
                };
                captures.push(capture);
                readiness.push({
                    label: `${page}:${viewport}:run${run}:${side}`,
                    passed: true,
                    renderState: final.renderState,
                    final,
                    timeline: readinessTimeline(final),
                });
            }
            const artifacts = artifactsForViewport(viewport);
            const semantic = compareSemantics(
                semanticFor(group, 'static'),
                semanticFor(group, 'laravel'),
            );
            const directory = join(runRoot, 'visual', 'comparisons', page, viewport, `run-${run}`);
            await mkdir(directory, { recursive: true });
            const diffPath = join(directory, 'diff.png');
            const overlayPath = join(directory, 'overlay.png');
            const sidePath = join(directory, 'side-by-side.png');
            await Promise.all([
                writeFile(diffPath, artifacts.diff),
                writeFile(overlayPath, artifacts.overlay),
                writeFile(sidePath, artifacts.side),
            ]);
            const comparison = {
                page,
                viewport,
                run,
                differentPixels: 0,
                differencePercent: 0,
                rawDifferentPixels: 0,
                rawDifferencePercent: 0,
                perceptualDifferentPixels: 0,
                perceptualDifferencePercent: 0,
                materialDifferentPixels: 0,
                totalPixels: artifacts.raster.totalPixels,
                maxChannelDelta: 0,
                differenceBounds: null,
                rawDiffHash: sha256(artifacts.diff),
                staticHash: hash,
                laravelHash: hash,
                responsiveModeMatch: true,
                boxesMatch: true,
                semanticMatch: semantic.passed,
                semanticMismatchFields: semantic.mismatchFields,
                semanticVisibleTextDifference: semantic.visibleTextDifference,
                adminHrefException: semantic.adminHrefException,
                staticAdminHref: semantic.staticAdminHref,
                laravelAdminHref: semantic.laravelAdminHref,
                subpixelProof: null,
                maxHorizontalRun: 0,
                maxVerticalRun: 0,
                diffPath: portable(workspaceRoot, diffPath),
                overlayPath: portable(workspaceRoot, overlayPath),
                sideBySidePath: portable(workspaceRoot, sidePath),
            };
            diffs.push(comparison);
            groupDiffs.push(comparison);
        }
        const adminHrefException = groupDiffs.every((item) => item.adminHrefException);
        repeatability.push({
            page,
            viewport,
            runs: 3,
            staticHashes: [hash],
            laravelHashes: [hash],
            crossRunDeltas: { static: [0, 0], laravel: [0, 0] },
            comparisonFingerprints: [fidelityComparisonFingerprint(groupDiffs[0])],
            exactSideRepeatability: true,
            deterministicComparisons: true,
            semanticParity: true,
            geometryParity: true,
            rawDifferentPixels: [0, 0, 0],
            perceptualDifferentPixels: [0, 0, 0],
            materialDifferentPixels: [0, 0, 0],
            passed: true,
            classification: adminHrefException
                ? 'approved-non-visual-admin-href-change'
                : 'exact',
            classified: true,
        });
    }
    return {
        mime: mimeEvidence(),
        fidelity: { captures, diffs, repeatability, readiness, diagnostic: false },
        totals: { captures: 612, comparisons: 306, repeatability: 102 },
        networkGate: cleanNetworkGate('visual'),
    };
}

function manifestFixture(protectedStorefrontHash, protectedFactoryHash, approvedBaselineRootChecksum) {
    return {
        runId: RUN_ID,
        generatedAt: GENERATED_AT,
        branch: 'main',
        commit: 'abcdef123456',
        workingTreeChecksum: WORKING_TREE_CHECKSUM,
        workingTreeDiffChecksum: '5'.repeat(64),
        phpVersion: '8.4.0',
        nodeVersion: 'v24.0.0',
        playwrightVersion: '1.61.1',
        chromiumVersion: CHROMIUM_VERSION,
        laravelEnvironment: 'testing',
        viteManifestChecksum: '6'.repeat(64),
        composerLockChecksum: '7'.repeat(64),
        npmLockChecksum: '8'.repeat(64),
        protectedStorefrontManifestChecksum: protectedStorefrontHash,
        factoryProtectionManifestChecksum: protectedFactoryHash,
        approvedBaselineRootChecksum,
        willy: { size: 401_408, sha256: '9'.repeat(64) },
        routeCount: 40,
        permissionCount: 56,
        roleCount: 4,
        schedulerCount: 2,
        viewports: { homepage: HOMEPAGE_VIEWPORTS, standard: STANDARD_VIEWPORTS },
        readiness: {
            attempts: 48,
            frameIntervalMs: 250,
            stableFrames: 3,
            assetTimeoutMs: 2_000,
            screenshotTimeoutMs: 15_000,
        },
        motionNormalizationChecksum: 'a'.repeat(64),
        visualNormalizationChecksum: sha256(VISUAL_NORMALIZATION_CSS_FIXTURE),
        browserLaunchArgsChecksum: sha256(BROWSER_LAUNCH_ARGS_FIXTURE.join('\n')),
        staticRouterNormalizationChecksum: sha256(JSON.stringify(STATIC_ROUTER_NORMALIZATION_FIXTURE)),
        decorativeMediaAllowlistChecksum: sha256(JSON.stringify(DECORATIVE_MEDIA_ALLOWLIST_FIXTURE)),
        browserProfileIsolation: 'stage-scoped-TEMP/TMP-with-Playwright-owned-per-process-user-data-directories',
        isolatedDatabaseProcedureChecksum: 'c'.repeat(64),
        stages: ['prepare', ...REQUIRED_STAGES],
        selectedPorts: selectedPorts(),
        browserProfileRoots: profileRoots(),
    };
}

async function copyTree(source, destination) {
    for (const path of await filesUnder(source)) {
        const target = join(destination, relative(source, path));
        await mkdir(dirname(target), { recursive: true });
        await copyFile(path, target);
    }
}

async function buildCandidate(
    workspaceRoot,
    runRoot,
    candidateRoot,
    manifest,
    rootManifestChecksum,
    protectedStorefrontBytes,
    protectedFactoryBytes,
) {
    await rm(candidateRoot, { recursive: true, force: true });
    await Promise.all([
        copyTree(join(runRoot, 'visual', 'screenshots'), join(candidateRoot, 'screenshots')),
        copyTree(join(runRoot, 'visual', 'comparisons'), join(candidateRoot, 'comparisons')),
    ]);
    const visualResults = [
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
    for (const name of visualResults) {
        const target = join(candidateRoot, 'results', name);
        await mkdir(dirname(target), { recursive: true });
        await copyFile(join(runRoot, 'visual', name), target);
    }
    for (const stage of ['security', 'preview']) {
        await copyTree(join(runRoot, stage), join(candidateRoot, 'results', stage));
    }
    await copyTree(join(runRoot, 'verify'), join(candidateRoot, 'results', 'verify'));
    for (const stage of ['prepare', ...REQUIRED_STAGES]) {
        const names = stage === 'prepare'
            ? ['stage-result.json', 'stage-complete.json']
            : [
                'stage-result.json',
                'stage-complete.json',
                'stage-process-exit.json',
                'orchestration-result.json',
            ];
        for (const name of names) {
            const target = join(candidateRoot, 'results', 'orchestration', `${stage}-${name}`);
            await mkdir(dirname(target), { recursive: true });
            await copyFile(join(runRoot, stage, name), target);
        }
    }
    await mkdir(join(candidateRoot, 'manifests', 'protected-source'), { recursive: true });
    await Promise.all([
        copyFile(join(runRoot, 'run-manifest.json'), join(candidateRoot, 'manifests', 'run-manifest.json')),
        copyFile(join(runRoot, 'run-manifest.sha256'), join(candidateRoot, 'manifests', 'run-manifest.sha256')),
        copyFile(join(runRoot, 'orchestration-journal.json'), join(candidateRoot, 'manifests', 'orchestration-journal.json')),
        writeFile(join(candidateRoot, 'manifests', 'protected-source', 'template-sha256.txt'), protectedStorefrontBytes),
        writeFile(join(candidateRoot, 'manifests', 'protected-source', 'protected-php-baseline.json'), protectedFactoryBytes),
        json(join(candidateRoot, 'manifests', 'browser-manifest.json'), {
            playwrightVersion: manifest.playwrightVersion,
            chromiumVersion: manifest.chromiumVersion,
            launchArgsChecksum: manifest.browserLaunchArgsChecksum,
            launchArgs: BROWSER_LAUNCH_ARGS_FIXTURE,
            motionNormalizationChecksum: manifest.motionNormalizationChecksum,
            visualNormalizationChecksum: manifest.visualNormalizationChecksum,
            visualNormalizationCss: VISUAL_NORMALIZATION_CSS_FIXTURE,
            profileIsolation: manifest.browserProfileIsolation,
            staticRouterNormalizationChecksum: manifest.staticRouterNormalizationChecksum,
            staticRouterNormalization: STATIC_ROUTER_NORMALIZATION_FIXTURE,
            decorativeMediaAllowlistChecksum: manifest.decorativeMediaAllowlistChecksum,
            decorativeMediaAllowlist: DECORATIVE_MEDIA_ALLOWLIST_FIXTURE,
        }),
        json(join(candidateRoot, 'manifests', 'environment-manifest.json'), {
            runId: manifest.runId,
            rootManifestChecksum,
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
        }),
        json(join(candidateRoot, 'manifests', 'viewport-manifest.json'), manifest.viewports),
        json(join(candidateRoot, 'manifests', 'finalization-proof.json'), {
            runId: manifest.runId,
            rootManifestChecksum,
            generatedAt: GENERATED_AT,
            ports: { passed: true },
            profiles: { passed: true },
            ownedProcesses: { passed: true },
            stageMarkers: { passed: true },
            ownedCleanupPassed: true,
            passed: true,
        }),
        writeFile(join(candidateRoot, 'README.md'), '# BE-6A.1 baseline candidates\n\nNot yet formally approved.\n'),
        writeFile(join(candidateRoot, 'ADMIN_HREF_EXCEPTION.md'), '# Admin href exception\n\nNo visual approval is implied.\n'),
        writeFile(join(candidateRoot, 'HUMAN_REVIEW_CHECKLIST.md'), '# Human review\n\n**No item is automatically approved.**\n'),
    ]);
    const files = (await filesUnder(candidateRoot))
        .map((path) => portable(candidateRoot, path))
        .filter((path) => path !== 'manifests/candidate-manifest.json')
        .sort();
    const fileChecksums = {};
    for (const path of files) fileChecksums[path] = sha256(await readFile(join(candidateRoot, path)));
    const packageChecksum = sha256(
        Object.entries(fileChecksums).map(([path, hash]) => `${path}:${hash}`).join('\n'),
    );
    await json(join(candidateRoot, 'manifests', 'candidate-manifest.json'), {
        runId: RUN_ID,
        approvalState: 'unapproved-baseline-candidate',
        candidateRootRole: 'unapproved-baseline-candidate',
        formalApprovalState: 'not-requested',
        approvedBaselineWrites: [],
        autoApprovalProhibited: true,
        rootManifestChecksum,
        workingTreeChecksum: WORKING_TREE_CHECKSUM,
        protectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
        factoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
        staticRouterNormalizationChecksum: manifest.staticRouterNormalizationChecksum,
        decorativeMediaAllowlistChecksum: manifest.decorativeMediaAllowlistChecksum,
        motionNormalizationChecksum: manifest.motionNormalizationChecksum,
        visualNormalizationChecksum: manifest.visualNormalizationChecksum,
        approvedBaselineRootChecksum: manifest.approvedBaselineRootChecksum,
        screenshotCount: 612,
        comparisonCount: 306,
        repeatabilityGroups: 102,
        source: 'checksum-protected-static-source-and-same-run-laravel-captures',
        createdAt: GENERATED_AT,
        fileCount: Object.keys(fileChecksums).length,
        fileChecksums,
        packageChecksum,
    });
    return candidateRoot;
}

async function createFixture(workspaceRoot) {
    const evidenceBase = join(workspaceRoot, 'storage', 'app', 'evidence', 'be6a1-browser');
    const runRoot = join(evidenceBase, RUN_ID);
    await mkdir(runRoot, { recursive: true });
    const protectedStorefrontBytes = Buffer.from('fixture protected storefront manifest\n');
    const protectedFactoryBytes = Buffer.from('{"fixture":"protected factory manifest"}\n');
    const protectedStorefrontHash = sha256(protectedStorefrontBytes);
    const protectedFactoryHash = sha256(protectedFactoryBytes);
    const approvedBaselineRoot = join(
        workspaceRoot,
        'tests',
        'Baselines',
        'william-taylor-template',
    );
    await mkdir(approvedBaselineRoot, { recursive: true });
    await writeFile(
        join(approvedBaselineRoot, 'approved-baseline-attestation.txt'),
        'immutable approved fixture baseline\n',
    );
    const approvedBaselineRootChecksum = await directoryChecksum(approvedBaselineRoot);
    const approvedBaselineRoots = [{
        root: approvedBaselineRoot,
        beforeChecksum: approvedBaselineRootChecksum,
        afterChecksum: approvedBaselineRootChecksum,
    }];
    const manifest = manifestFixture(
        protectedStorefrontHash,
        protectedFactoryHash,
        approvedBaselineRootChecksum,
    );
    const manifestText = serialized(manifest);
    const rootManifestChecksum = sha256(manifestText);
    await Promise.all([
        writeFile(join(runRoot, 'run-manifest.json'), manifestText),
        writeFile(join(runRoot, 'run-manifest.sha256'), `${rootManifestChecksum}  run-manifest.json\n`),
        writeFile(join(evidenceBase, 'current-run-id.txt'), `${RUN_ID}\n`),
    ]);

    const prepare = {
        runId: RUN_ID,
        stage: 'prepare',
        passed: true,
        rootManifestChecksum,
        selectedPorts: manifest.selectedPorts,
        browserProfileRoots: manifest.browserProfileRoots,
        generatedAt: manifest.generatedAt,
    };
    await json(join(runRoot, 'prepare', 'stage-result.json'), prepare);
    const prepareBytes = await readFile(join(runRoot, 'prepare', 'stage-result.json'));
    await json(join(runRoot, 'prepare', 'stage-complete.json'), {
        runId: RUN_ID,
        stage: 'prepare',
        passed: true,
        stageResultChecksum: sha256(prepareBytes),
        completedAt: GENERATED_AT,
    });

    const visualPayload = await buildVisual(workspaceRoot, runRoot);
    const payloads = {
        security: securityPayload(),
        preview: previewPayload(),
        visual: visualPayload,
        verify: { totals: {}, networkGate: cleanNetworkGate('verify') },
    };
    const cleanup = [{
        label: 'owned-process-cleanup',
        pid: 42_000,
        graceful: true,
        forced: false,
        passed: true,
        at: GENERATED_AT,
    }];
    const immutableInventory = {
        commit: manifest.commit,
        workingTreeChecksum: manifest.workingTreeChecksum,
        composerLockChecksum: manifest.composerLockChecksum,
        npmLockChecksum: manifest.npmLockChecksum,
        viteManifestChecksum: manifest.viteManifestChecksum,
        protectedStorefrontManifestChecksum: manifest.protectedStorefrontManifestChecksum,
        factoryProtectionManifestChecksum: manifest.factoryProtectionManifestChecksum,
        chromiumVersion: manifest.chromiumVersion,
    };
    const stageResults = {};
    const orchestrationResults = {};
    const journal = [{
        stage: 'prepare',
        startedAt: GENERATED_AT,
        finishedAt: GENERATED_AT,
        exitCode: 0,
        timedOut: false,
        passed: true,
        rootManifestChecksum,
    }];
    for (const [index, stage] of REQUIRED_STAGES.entries()) {
        const stageRoot = join(runRoot, stage);
        await mkdir(stageRoot, { recursive: true });
        const stageResult = {
            runId: RUN_ID,
            stage,
            diagnostic: false,
            passed: true,
            failures: [],
            browserVersion: stage === 'verify' ? null : CHROMIUM_VERSION,
            rootManifestChecksum,
            immutableInventory,
            ports: manifest.selectedPorts[stage],
            profileRoot: manifest.browserProfileRoots[stage],
            processInventory: cleanup,
            payload: payloads[stage],
            cleanup,
            executionError: null,
            requestedExitCode: 0,
        };
        stageResults[stage] = stageResult;
        await Promise.all([
            json(join(stageRoot, 'fixture-summary.json'), {
                databaseProcedure: 'fixture',
                actors: { admin: 1 },
                resources: { about: 1 },
            }),
            json(join(stageRoot, 'console-network-summary.json'), stageResult.payload.networkGate),
            json(join(stageRoot, 'active-handle-diagnostic.json'), { beforeCleanup: ['fixture'], afterCleanup: [] }),
            json(join(stageRoot, 'lifecycle-events.json'), [
                { event: 'stage-start', at: GENERATED_AT },
                { event: 'cleanup-finished', at: GENERATED_AT },
            ]),
            json(join(stageRoot, 'cleanup-result.json'), cleanup),
            json(join(stageRoot, 'stage-result.json'), stageResult),
        ]);
        const stageBytes = await readFile(join(stageRoot, 'stage-result.json'));
        await json(join(stageRoot, 'stage-complete.json'), {
            runId: RUN_ID,
            stage,
            passed: true,
            stageResultChecksum: sha256(stageBytes),
            completedAt: GENERATED_AT,
        });
        const childPid = 50_000 + index;
        const processProof = fixtureProcessProof(stage, childPid);
        const processExit = {
            runId: RUN_ID,
            stage,
            rootManifestChecksum,
            childPid,
            childExited: true,
            exitCode: 0,
            signal: null,
            timedOut: false,
            forcedTreeTermination: false,
            termination: null,
            processProof,
            passed: true,
            completedAt: GENERATED_AT,
        };
        const processExitPath = join(stageRoot, 'stage-process-exit.json');
        await json(processExitPath, processExit);
        const processExitBytes = await readFile(processExitPath);
        const orchestration = {
            runId: RUN_ID,
            stage,
            childPid,
            startedAt: GENERATED_AT,
            finishedAt: GENERATED_AT,
            timeoutMs: stage === 'visual' ? 7_200_000 : 600_000,
            exitCode: 0,
            signal: null,
            spawnError: null,
            timedOut: false,
            forcedTreeTermination: false,
            termination: null,
            resultManifestPresent: true,
            resultChecksum: sha256(stageBytes),
            completionMarkerPassed: true,
            processExitMarkerChecksum: sha256(processExitBytes),
            processProof,
            inProgressMarkerAbsent: true,
            runnerFailurePresent: false,
            immutableAfterPassed: true,
            immutableAfterError: null,
            passed: true,
        };
        orchestrationResults[stage] = orchestration;
        await json(join(stageRoot, 'orchestration-result.json'), orchestration);
        journal.push(orchestration);
    }

    await Promise.all([
        json(join(runRoot, 'security', 'admin-access-matrix.json'), {
            routes: payloads.security.admin.routeMatrix,
            staticFooter: payloads.security.admin.footer,
            projectedFooter: payloads.security.projected.results,
            logout: payloads.security.admin.logout,
            aboutModes: payloads.security.aboutModes.results,
        }),
        json(join(runRoot, 'preview', 'preview-browser-matrix.json'), payloads.preview.preview.results),
        json(join(runRoot, 'preview', 'preview-assertion-result.json'), {
            runId: RUN_ID,
            assertionsComplete: true,
            passed: true,
            cases: 7,
            persistedAt: GENERATED_AT,
        }),
        json(join(runRoot, 'visual', 'mime-preflight.json'), visualPayload.mime),
        json(join(runRoot, 'visual', 'semantic-readiness-summary.json'), visualPayload.fidelity.readiness),
        json(join(runRoot, 'visual', 'visual-stability-summary.json'), visualPayload.fidelity.readiness.map((item) => ({
            label: item.label,
            passed: item.passed,
            renderState: item.renderState,
            final: item.final,
            frames: item.timeline.length,
        }))),
        json(join(runRoot, 'visual', 'fidelity-repeatability-summary.json'), visualPayload.fidelity.repeatability),
        json(join(runRoot, 'visual', 'static-reference-checksums.json'), visualPayload.fidelity.captures.filter((item) => item.side === 'static')),
        json(join(runRoot, 'visual', 'laravel-capture-checksums.json'), visualPayload.fidelity.captures.filter((item) => item.side === 'laravel')),
        json(join(runRoot, 'visual', 'fidelity-diff-summary.json'), visualPayload.fidelity.diffs),
        json(join(runRoot, 'orchestration-journal.json'), journal),
    ]);

    for (const item of payloads.security.admin.footer) {
        const path = join(workspaceRoot, item.screenshot);
        await mkdir(dirname(path), { recursive: true });
        await writeFile(path, pngForViewport(`${item.width}x${item.height}`));
    }
    for (const item of payloads.security.projected.results) {
        const path = join(workspaceRoot, item.screenshot);
        await mkdir(dirname(path), { recursive: true });
        await writeFile(path, pngForViewport(`${item.width}x${item.height}`));
    }
    for (const item of payloads.security.aboutModes.results) {
        const path = join(workspaceRoot, item.screenshot);
        await mkdir(dirname(path), { recursive: true });
        await writeFile(path, pngForViewport('1440x900'));
    }
    await mkdir(join(runRoot, 'preview', 'screenshots', 'preview'), { recursive: true });
    await writeFile(
        join(runRoot, 'preview', 'screenshots', 'preview', 'valid-authorized.png'),
        pngForViewport('1440x900'),
    );

    const stageMarkerStages = [{
        stage: 'prepare',
        expected: ['stage-complete.json', 'stage-result.json'],
        actual: ['stage-complete.json', 'stage-result.json'],
        exactMarkers: true,
        stageResultChecksum: sha256(prepareBytes),
        completeStageResultChecksum: sha256(prepareBytes),
        passed: true,
    }];
    for (const stage of REQUIRED_STAGES) {
        const stageResultBytes = await readFile(join(runRoot, stage, 'stage-result.json'));
        stageMarkerStages.push({
            stage,
            expected: [
                'orchestration-result.json',
                'stage-complete.json',
                'stage-process-exit.json',
                'stage-result.json',
            ],
            actual: [
                'orchestration-result.json',
                'stage-complete.json',
                'stage-process-exit.json',
                'stage-result.json',
            ],
            exactMarkers: true,
            stageResultChecksum: sha256(stageResultBytes),
            completeStageResultChecksum: sha256(stageResultBytes),
            passed: true,
        });
    }
    const stageMarkerEvidence = {
        passed: true,
        stages: stageMarkerStages,
        inProgressMarkers: [],
        failureMarkers: [],
    };

    const releasedPorts = Object.entries(manifest.selectedPorts).flatMap(([stage, ports]) =>
        Object.entries(ports).map(([kind, port]) => ({ stage, kind, port, released: true })));
    const releasedProfiles = Object.entries(manifest.browserProfileRoots)
        .map(([stage, path]) => ({ stage, path, released: true }));
    const aggregate = {
        runId: RUN_ID,
        rootManifestChecksum,
        passed: true,
        failures: [],
        stages: stageResults,
        cleanupResults: Object.fromEntries(REQUIRED_STAGES.map((stage) => [stage, cleanup])),
        orchestrationOutcomes: journal,
        stageMarkerEvidence,
        matrixTotals: { captures: 612, comparisons: 306, repeatability: 102 },
        repeatabilityTotals: {
            expected: 102,
            present: 102,
            passed: 102,
            exactSideRepeatability: 102,
        },
        securityTotals: {
            privileged: { expected: 45, present: 45, passed: 45 },
            staticFooter: { expected: 2, present: 2, passed: 2 },
            projectedFooter: { expected: 2, present: 2, passed: 2 },
            logout: true,
        },
        previewTotals: { expected: 7, present: 7, passed: 7 },
        fidelityTotals: {
            captures: 612,
            comparisons: 306,
            repeatability: 102,
            semanticPassed: 306,
            exactRasterComparisons: 306,
            hydratedCaptures: 612,
            visuallyStableCaptures: 612,
            intermediateCaptures: 0,
            mimePassed: true,
            requiredLocalAssetFailures: 0,
            blockingNetworkEvents: 0,
            classifications: {
                exact: 7,
                'approved-non-visual-admin-href-change': 95,
            },
        },
        remainingBlocker: null,
        closureRecommendation: 'Close BE-6A.1; baseline candidates remain unapproved.',
        orphanProcessResult: {
            passed: true,
            selectedPortsReleased: releasedPorts,
            browserProfilesReleased: releasedProfiles,
            ownedProcessCleanupPassed: true,
            stageProcessProofPassed: true,
            recordedOwnedProcessProof: {
                passed: true,
                stageProofPassed: true,
                provider: 'fixture-creation-identity-provider',
                records: REQUIRED_STAGES.map((stage, index) => ({
                    stage,
                    identity: fixtureProcessProof(stage, 50_000 + index).recordedOwnedProcesses[0].identity,
                    pid: 50_000 + index,
                    alive: false,
                })),
                survivors: [],
                snapshotError: null,
                provedAt: GENERATED_AT,
            },
            unrelatedProcessPolicy: 'Only exact owned handles may be terminated.',
        },
    };

    const candidateRoot = join(
        workspaceRoot,
        'storage',
        'app',
        'evidence',
        'be6a1-baseline-candidate',
    );
    const temporaryCandidateRoot = `${candidateRoot}.${RUN_ID}.tmp`;
    await buildCandidate(
        workspaceRoot,
        runRoot,
        temporaryCandidateRoot,
        manifest,
        rootManifestChecksum,
        protectedStorefrontBytes,
        protectedFactoryBytes,
    );
    const preInstallValidation = await validateBaselineCandidate({
        workspaceRoot,
        candidateRoot: temporaryCandidateRoot,
        expectedRunId: RUN_ID,
        expectedRootManifestChecksum: rootManifestChecksum,
        expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
        expectedProtectedStorefrontManifestChecksum: protectedStorefrontHash,
        expectedFactoryProtectionManifestChecksum: protectedFactoryHash,
        approvedBaselineRoots,
    });
    if (!preInstallValidation.passed) {
        throw new Error(
            `Fixture candidate pre-install validation failed: ${JSON.stringify(preInstallValidation.failures)}`,
        );
    }
    const replacementIdentity = await physicalTreeIdentity(temporaryCandidateRoot);
    if (
        replacementIdentity.packageChecksum !== preInstallValidation.packageChecksum
        || replacementIdentity.candidateManifestChecksum !== preInstallValidation.manifestChecksum
    ) {
        throw new Error('Fixture candidate physical identity differs from its validated package.');
    }

    const transactionPid = 61_001;
    const transactionCreationTime = '2026-07-27T00:00:00.000000Z';
    let transactionClockTick = 0;
    const candidateTransactions = createCandidateTransactionCoordinator({
        workspaceRoot,
        evidenceBase,
        candidateRoot,
        processSnapshot: () => ({
            provider: 'fixture-exact-process-identity-provider',
            rows: {
                [transactionPid]: {
                    pid: transactionPid,
                    creationTime: transactionCreationTime,
                    executableName: 'node.exe',
                },
            },
        }),
        now: () => {
            const at = new Date(Date.parse(GENERATED_AT) + (transactionClockTick * 1000));
            transactionClockTick += 1;
            return at;
        },
        pid: transactionPid,
        executablePath: 'node.exe',
    });
    const pendingFinalPath = join(runRoot, 'final-result.pending.json');
    const finalPath = join(runRoot, 'final-result.json');
    const validationEvidencePath = join(runRoot, 'final-result.validation.json');
    const lock = await candidateTransactions.acquireLock(RUN_ID);
    let transaction = await candidateTransactions.begin(lock, {
        runId: RUN_ID,
        temporaryRoot: temporaryCandidateRoot,
        replacementIdentity,
        packageChecksum: replacementIdentity.packageChecksum,
        candidateManifestChecksum: replacementIdentity.candidateManifestChecksum,
        pendingFinalPath,
        finalPath,
    });
    transaction = await candidateTransactions.install(lock, transaction);
    const postInstallValidation = await validateBaselineCandidate({
        workspaceRoot,
        candidateRoot,
        expectedRunId: RUN_ID,
        expectedRootManifestChecksum: rootManifestChecksum,
        expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
        expectedProtectedStorefrontManifestChecksum: protectedStorefrontHash,
        expectedFactoryProtectionManifestChecksum: protectedFactoryHash,
        approvedBaselineRoots,
    });
    if (
        !postInstallValidation.passed
        || postInstallValidation.packageChecksum !== replacementIdentity.packageChecksum
        || postInstallValidation.manifestChecksum
            !== replacementIdentity.candidateManifestChecksum
    ) {
        throw new Error(
            `Fixture candidate post-install validation failed: ${JSON.stringify(postInstallValidation.failures)}`,
        );
    }
    transaction = await candidateTransactions.markInstalledValidated(
        lock,
        transaction,
        {
            packageChecksum: postInstallValidation.packageChecksum,
            failureCount: postInstallValidation.failures.length,
        },
    );

    aggregate.baselineCandidate = {
        approvalState: 'unapproved',
        autoApproved: false,
        path: portable(workspaceRoot, candidateRoot),
        validationPassed: true,
        packageChecksum: replacementIdentity.packageChecksum,
        manifestChecksum: replacementIdentity.candidateManifestChecksum,
        physicalTreeChecksum: replacementIdentity.treeChecksum,
        preInstallValidation,
        postInstallValidation,
        installedAtomically: true,
    };
    aggregate.finalizationState = 'closed-transactionally';
    aggregate.candidateFinalization = {
        passed: true,
        phase: 'canonical-final-publication',
        transactionId: transaction.transactionId,
        transactionSchema: transaction.schema,
        commitPoint: transaction.commitPoint,
        durableStatePath: transaction.paths.runStatePath,
        approvedBaselineWrites: [],
        formalApprovalState: 'not-requested',
    };
    aggregate.behavioralAggregateValidation = {
        passed: true,
        failures: [],
        validator: 'validateEvidenceAggregate',
        validationPass: 'pre-publication-pending-aggregate',
    };
    aggregate.finalizationTransaction = {
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
    };
    await json(pendingFinalPath, aggregate);
    const firstValidation = await validateEvidenceAggregate({
        workspaceRoot,
        runRoot,
        aggregatePath: pendingFinalPath,
        expectedRunId: RUN_ID,
        expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
        approvedBaselineRoots,
        finalizationMode: 'prepublication',
    });
    if (!firstValidation.passed) {
        throw new Error(
            `Fixture first pre-publication validation failed: ${JSON.stringify(firstValidation.failures)}`,
        );
    }
    aggregate.behavioralAggregateValidation = firstValidation;
    await json(pendingFinalPath, aggregate);
    const publicationValidation = await validateEvidenceAggregate({
        workspaceRoot,
        runRoot,
        aggregatePath: pendingFinalPath,
        expectedRunId: RUN_ID,
        expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
        approvedBaselineRoots,
        finalizationMode: 'prepublication',
    });
    if (!publicationValidation.passed) {
        throw new Error(
            `Fixture exact pre-publication validation failed: ${JSON.stringify(publicationValidation.failures)}`,
        );
    }
    transaction = await candidateTransactions.prepareCommit(lock, transaction, {
        pendingAggregateChecksum: sha256(await readFile(pendingFinalPath)),
    });
    transaction = await candidateTransactions.publishCanonical(lock, transaction);
    transaction = await candidateTransactions.completeCommit(lock, transaction);

    let releasePreparedValidation = null;
    const release = await candidateTransactions.releaseLock(
        lock,
        transaction,
        {
            beforeRemoval: async (terminalState) => {
                releasePreparedValidation = await validateEvidenceAggregate({
                    workspaceRoot,
                    runRoot,
                    expectedRunId: RUN_ID,
                    expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
                    approvedBaselineRoots,
                    finalizationMode: 'postcommit',
                });
                if (!releasePreparedValidation.passed) {
                    throw new Error(
                        `Fixture release-prepared validation failed: ${JSON.stringify(releasePreparedValidation.failures)}`,
                    );
                }
                await json(validationEvidencePath, {
                    runId: RUN_ID,
                    aggregateChecksum: sha256(await readFile(finalPath)),
                    phase: 'canonical-final-acceptance-prepared',
                    transactionId: terminalState.transactionId,
                    transaction: terminalState,
                    validationPasses: [
                        firstValidation,
                        publicationValidation,
                        releasePreparedValidation,
                    ],
                    releasePreparedValidation,
                    closureBoundary: {
                        candidateLockRemovalIsFinalMutation: true,
                        candidateLockPath: terminalState.paths.lockRoot,
                        noPostReleaseWrites: true,
                    },
                    passed: true,
                });
            },
        },
    );
    transaction = release.state;
    if (!releasePreparedValidation?.passed) {
        throw new Error('Fixture release callback did not create passing validation evidence.');
    }
    return {
        workspaceRoot,
        runRoot,
        candidateRoot,
        manifest,
        aggregate,
        rootManifestChecksum,
        protectedStorefrontHash,
        protectedFactoryHash,
        approvedBaselineRoots,
        transaction,
        activeStatePath: candidateTransactions.paths.activeStatePath,
        transactionStatePath: join(runRoot, 'finalization-transaction.json'),
        validationEvidencePath,
        candidateLockRoot: candidateTransactions.paths.candidateLockRoot,
    };
}

async function validateFixture(fixture, overrides = {}) {
    return await validateEvidenceAggregate({
        workspaceRoot: fixture.workspaceRoot,
        runRoot: fixture.runRoot,
        expectedRunId: RUN_ID,
        expectedWorkingTreeChecksum: WORKING_TREE_CHECKSUM,
        approvedBaselineRoots: fixture.approvedBaselineRoots,
        ...overrides,
    });
}

async function mutateJsonFile(path, mutation) {
    const original = await readFile(path);
    const value = JSON.parse(original);
    mutation(value);
    await writeFile(path, serialized(value));
    return async () => writeFile(path, original);
}

async function mutateFile(path, replacement) {
    const original = await readFile(path);
    await writeFile(path, replacement);
    return async () => writeFile(path, original);
}

async function uniquePorts(count) {
    const ports = new Set();
    while (ports.size < count) ports.add(await allocateLoopbackPort());
    return [...ports];
}

async function main() {
    const workspaceRoot = await mkdtemp(join(tmpdir(), 'be6a1-harness-strict-'));
    try {
        const fixture = await createFixture(workspaceRoot);
        const valid = await validateFixture(fixture);
        check(valid.passed, `strict valid fixture failed: ${JSON.stringify(valid.failures.slice(0, 20))}`);
        check(
            valid.totals.stages === 4
            && valid.totals.groups === 102
            && valid.totals.captures === 612
            && valid.totals.comparisons === 306
            && valid.totals.readiness === 612,
            'strict valid fixture totals differ',
        );
        check(
            CANONICAL_GROUP_KEYS.length === 102
            && CANONICAL_CAPTURE_KEYS.length === 612
            && CANONICAL_DIFF_KEYS.length === 306
            && CANONICAL_READINESS_KEYS.length === 612,
            'canonical matrix constants have incorrect cardinality',
        );
        check(
            isAdminHrefException('/admin', 'http://127.0.0.1:31000/admin', 'http://127.0.0.1:31000/admin'),
            'static /admin href representation was not accepted',
        );
        check(
            isAdminHrefException('html/admin.html', 'http://127.0.0.1:31000/admin', 'http://127.0.0.1:31000/admin'),
            'legacy static Admin href representation was not accepted',
        );
        check(
            !isAdminHrefException('/admin', '/admin', 'http://127.0.0.1:31000/admin'),
            'equal Admin hrefs were incorrectly classified as an exception',
        );
        check(
            !isAdminHrefException('/account', 'http://127.0.0.1:31000/admin', 'http://127.0.0.1:31000/admin'),
            'off-path static href was incorrectly classified as an Admin exception',
        );
        check(
            !isAdminHrefException('/admin', 'https://attacker.invalid/admin', 'http://127.0.0.1:31000/admin'),
            'cross-origin Laravel Admin href was incorrectly classified as an exception',
        );
        check(
            JSON.stringify(expectedCaptureContract('login', '375x812', 'static')) === JSON.stringify({
                requiresScript: true,
                requiresSpa: true,
                requiresChrome: false,
                responsiveMode: 'not-applicable',
            }),
            'static login capture contract is not SPA-strict and chrome-free',
        );
        check(
            JSON.stringify(expectedCaptureContract('login', '375x812', 'laravel')) === JSON.stringify({
                requiresScript: false,
                requiresSpa: false,
                requiresChrome: false,
                responsiveMode: 'not-applicable',
            }),
            'Laravel login capture contract is not server-owned and chrome-free',
        );
        const desktopFixture = finalSnapshot('homepage|1024x900', '1024x900', 'static', pngForViewport('1024x900'));
        check(hasCanonicalGeometryBoxes(
            desktopFixture.boxes,
            expectedCaptureContract('homepage', '1024x900', 'static'),
        ), 'five-box desktop geometry fixture was rejected');
        check(!hasCanonicalGeometryBoxes(
            desktopFixture.boxes.slice(0, 4),
            expectedCaptureContract('homepage', '1024x900', 'static'),
        ), 'incomplete four-box geometry fixture was accepted');
        const serverRenderedNetwork = classifyNetworkEvidence(
            { console: [], failedRequests: [], errorResponses: [] },
            { 'login:375x812:run1:laravel': { screenshotPersisted: true, protectedSpaExpected: false } },
            'visual',
        );
        check(
            serverRenderedNetwork.passed
            && serverRenderedNetwork.optionalServiceContract.serverRenderedCaptureCount === 1
            && serverRenderedNetwork.optionalServiceContract.protectedSpaCaptureCount === 0,
            'server-rendered Laravel login was forced through the protected-SPA network contract',
        );
        const missingSpaExpectation = classifyNetworkEvidence(
            { console: [], failedRequests: [], errorResponses: [] },
            { 'homepage:375x812:run1:static': { screenshotPersisted: true } },
            'visual',
        );
        check(
            !missingSpaExpectation.passed
            && missingSpaExpectation.blocking.some((item) =>
                item.classification === 'protected-spa-expectation-missing'),
            'missing protected-SPA network expectation was accepted',
        );

        const restoreActiveSnapshot = await mutateJsonFile(
            fixture.activeStatePath,
            (state) => {
                state.runId = 'be6a1-different-active-snapshot';
            },
        );
        check(
            hasFailure(
                await validateFixture(fixture),
                'finalization-active-state-failure',
            ),
            'mismatched run-scoped and active transaction snapshots were accepted',
        );
        await restoreActiveSnapshot();

        const terminalPhaseRestores = [];
        for (const statePath of [
            fixture.transactionStatePath,
            fixture.activeStatePath,
        ]) {
            terminalPhaseRestores.push(await mutateJsonFile(statePath, (state) => {
                state.phase = 'committed';
            }));
        }
        terminalPhaseRestores.push(await mutateJsonFile(
            fixture.validationEvidencePath,
            (evidence) => {
                evidence.transaction.phase = 'committed';
            },
        ));
        const terminalPhaseFailure = await validateFixture(fixture);
        check(
            !terminalPhaseFailure.passed
            && hasFailure(
                terminalPhaseFailure,
                'finalization-transaction-state-failure',
            ),
            'terminal transaction phase drift bypassed signed-state validation',
        );
        for (const restoreTerminalPhase of terminalPhaseRestores.reverse()) {
            await restoreTerminalPhase();
        }

        await json(join(fixture.candidateLockRoot, 'owner.json'), fixture.transaction.owner);
        check(
            hasFailure(
                await validateFixture(fixture),
                'finalization-terminal-state-failure',
            ),
            'retained canonical candidate lock was accepted as closed',
        );
        await rm(fixture.candidateLockRoot, { recursive: true, force: true });

        const restoreValidationEvidence = await mutateJsonFile(
            fixture.validationEvidencePath,
            (evidence) => {
                evidence.aggregateChecksum = '0'.repeat(64);
                evidence.transaction.transactionId = 'drifted-sidecar-transaction';
            },
        );
        check(
            hasFailure(
                await validateFixture(fixture),
                'finalization-validation-evidence-failure',
            ),
            'validation sidecar checksum and transaction drift were accepted',
        );
        await restoreValidationEvidence();

        if (process.env.BE6A1_SELF_CHECK_VALID_ONLY === '1') {
            console.log(`BE-6A.1 strict behavioral harness valid-only check passed: ${assertionCount}/${assertionCount}`);
            return;
        }

        const manifestPath = join(fixture.runRoot, 'run-manifest.json');
        let restore = await mutateJsonFile(manifestPath, (manifest) => {
            delete manifest.phpVersion;
            manifest.readiness = {};
        });
        check(hasFailure(await validateFixture(fixture), 'manifest-schema-failure'), 'minimal manifest was accepted');
        await restore();

        const visualStagePath = join(fixture.runRoot, 'visual', 'stage-result.json');
        restore = await mutateJsonFile(visualStagePath, (stage) => {
            const semantic = stage.payload.fidelity.captures[0].semantic;
            semantic.visibleTextEntries = [];
            semantic.visibleTextChecksum = sha256('');
            semantic.adminHref = null;
        });
        const semanticFailure = await validateFixture(fixture);
        check(hasFailure(semanticFailure, 'semantic-schema-failure'), 'empty semantic entries were accepted');
        check(hasFailure(semanticFailure, 'semantic-admin-failure'), 'null Admin semantic evidence was accepted');
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            stage.payload.fidelity.captures[0].boxes.pop();
        });
        check(
            hasFailure(await validateFixture(fixture), 'capture-schema-failure'),
            'incomplete geometry capture was accepted',
        );
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            const capture = stage.payload.fidelity.captures.find((item) =>
                item.page === 'login' && item.side === 'laravel');
            const readiness = stage.payload.fidelity.readiness.find((item) =>
                item.label === `${capture.page}:${capture.viewport}:run${capture.run}:${capture.side}`);
            capture.responsiveMode = 'mobile';
            readiness.final.responsiveMode = 'mobile';
            readiness.final.expectedResponsiveMode = 'mobile';
            for (const frame of readiness.timeline) {
                frame.responsiveMode = 'mobile';
                frame.expectedResponsiveMode = 'mobile';
            }
        });
        const loginModeFailure = await validateFixture(fixture);
        check(hasFailure(loginModeFailure, 'capture-schema-failure'), 'mobile mode was accepted for chrome-free login');
        check(hasFailure(loginModeFailure, 'readiness-schema-failure'), 'login responsive contract drift was accepted');
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            const readiness = stage.payload.fidelity.readiness.find((item) =>
                item.label.startsWith('login:') && item.label.endsWith(':laravel'));
            Object.assign(readiness.final, {
                requiresScript: true,
                requiresSpa: true,
                scriptLoaded: true,
                applicationInitialized: true,
                spaInitialized: true,
            });
        });
        check(
            hasFailure(await validateFixture(fixture), 'readiness-schema-failure'),
            'Laravel login was allowed to claim protected-SPA initialization',
        );
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            const readiness = stage.payload.fidelity.readiness.find((item) =>
                item.label.startsWith('homepage:') && item.label.endsWith(':static'));
            Object.assign(readiness.final, {
                requiresSpa: false,
                applicationInitialized: false,
                spaInitialized: false,
            });
        });
        check(
            hasFailure(await validateFixture(fixture), 'readiness-schema-failure'),
            'protected static route was allowed to opt out of SPA initialization',
        );
        await restore();

        const firstCapture = fixture.aggregate.stages.visual.payload.fidelity.captures[0];
        const firstScreenshot = join(workspaceRoot, firstCapture.path);
        const onePixel = new PNG({ width: 1, height: 1 });
        onePixel.data.set([255, 0, 0, 255]);
        restore = await mutateFile(firstScreenshot, canonicalPng(PNG.sync.write(onePixel)));
        check(hasFailure(await validateFixture(fixture), 'physical-screenshot-failure'), 'wrong-dimension physical screenshot was accepted');
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            stage.payload.fidelity.captures[0].hash = 'f'.repeat(64);
            stage.payload.fidelity.captures[1].bytes += 1;
            stage.payload.fidelity.diffs[0].rawDifferentPixels = 99;
            stage.payload.fidelity.diffs[0].differentPixels = 99;
        });
        const physicalRecordFailure = await validateFixture(fixture);
        check(hasFailure(physicalRecordFailure, 'physical-screenshot-failure'), 'forged capture hash/bytes were accepted');
        check(hasFailure(physicalRecordFailure, 'raster-metric-mismatch'), 'forged raster metrics were accepted');
        await restore();

        const firstDiff = fixture.aggregate.stages.visual.payload.fidelity.diffs[0];
        const diffPath = join(workspaceRoot, firstDiff.diffPath);
        const [diffWidth, diffHeight] = firstDiff.viewport.split('x').map(Number);
        const wrongDiff = new PNG({ width: diffWidth, height: diffHeight });
        wrongDiff.data[0] = 255;
        wrongDiff.data[3] = 255;
        restore = await mutateFile(diffPath, PNG.sync.write(wrongDiff));
        check(hasFailure(await validateFixture(fixture), 'comparison-artifact-failure'), 'forged diff artifact was accepted');
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            stage.payload.fidelity.diffs[0].subpixelProof = {
                passed: true,
                outsideSensitivePixels: 0,
            };
        });
        check(hasFailure(await validateFixture(fixture), 'subpixel-proof-mismatch'), 'forged zero-diff subpixel proof was accepted');
        await restore();

        const securityPath = join(fixture.runRoot, 'security', 'stage-result.json');
        const previewPath = join(fixture.runRoot, 'preview', 'stage-result.json');
        const restores = [];
        restores.push(await mutateJsonFile(securityPath, (stage) => {
            stage.payload.admin = {};
        }));
        restores.push(await mutateJsonFile(previewPath, (stage) => {
            stage.payload.preview = {};
        }));
        restores.push(await mutateJsonFile(visualStagePath, (stage) => {
            stage.payload.mime = { passed: true, checks: [] };
            stage.payload.fidelity.readiness[0].timeline = [];
            stage.payload.networkGate = {
                passed: true,
                rawCounts: { console: 0, failedRequests: 0, errorResponses: 0 },
                classifiedCounts: {},
                correlations: { genericCorrelationPassed: true, appStateCorrelationPassed: true },
                requiredLocalAssetFailures: [],
                moduleMimeRefusals: [],
                blocking: [],
                evidence: { console: [], failedRequests: [], errorResponses: [] },
            };
        }));
        const attestationFailure = await validateFixture(fixture);
        check(hasFailure(attestationFailure, 'security-payload-failure'), 'empty security payload was accepted');
        check(hasFailure(attestationFailure, 'preview-payload-failure'), 'empty preview payload was accepted');
        check(hasFailure(attestationFailure, 'mime-schema-failure'), 'empty MIME checks were accepted');
        check(hasFailure(attestationFailure, 'readiness-schema-failure'), 'empty readiness timeline was accepted');
        check(hasFailure(attestationFailure, 'empty-network-evidence'), 'zero-event visual network evidence was accepted');
        for (const undo of restores.reverse()) await undo();

        const finalPath = join(fixture.runRoot, 'final-result.json');
        restore = await mutateJsonFile(finalPath, (aggregate) => {
            delete aggregate.stages.preview;
            delete aggregate.cleanupResults.security;
        });
        const mappingFailure = await validateFixture(fixture);
        check(hasFailure(mappingFailure, 'aggregate-stage-mapping-failure'), 'missing aggregate stage mapping was accepted');
        check(hasFailure(mappingFailure, 'aggregate-cleanup-mapping-failure'), 'missing aggregate cleanup mapping was accepted');
        await restore();

        const protocolRestores = [];
        protocolRestores.push(await mutateJsonFile(join(fixture.runRoot, 'prepare', 'stage-result.json'), (value) => {
            value.passed = false;
        }));
        protocolRestores.push(await mutateJsonFile(join(fixture.runRoot, 'orchestration-journal.json'), (journal) => {
            [journal[1], journal[2]] = [journal[2], journal[1]];
        }));
        protocolRestores.push(await mutateJsonFile(join(fixture.runRoot, 'security', 'orchestration-result.json'), (value) => {
            value.exitCode = 1;
            value.passed = false;
        }));
        protocolRestores.push(await mutateJsonFile(join(fixture.runRoot, 'preview', 'stage-complete.json'), (value) => {
            value.stageResultChecksum = '0'.repeat(64);
        }));
        protocolRestores.push(await mutateJsonFile(join(fixture.runRoot, 'verify', 'stage-process-exit.json'), (value) => {
            value.processProof.absenceConfirmed = false;
            value.processProof.passed = false;
            value.passed = false;
        }));
        await json(join(fixture.runRoot, 'visual', 'stage-in-progress.json'), {
            runId: RUN_ID,
            stage: 'visual',
            pid: 42_000,
            startedAt: GENERATED_AT,
        });
        const protocolFailure = await validateFixture(fixture);
        check(hasFailure(protocolFailure, 'prepare-result-failure'), 'failed prepare result was accepted');
        check(hasFailure(protocolFailure, 'orchestration-journal-order-failure'), 'out-of-order journal was accepted');
        check(hasFailure(protocolFailure, 'orchestration-result-failure'), 'failed orchestration result was accepted');
        check(hasFailure(protocolFailure, 'stage-completion-failure'), 'wrong completion checksum was accepted');
        check(hasFailure(protocolFailure, 'process-proof-failure'), 'failed owned-process identity/absence proof was accepted');
        check(hasFailure(protocolFailure, 'process-exit-marker-failure'), 'failed process-exit marker was accepted');
        check(hasFailure(protocolFailure, 'stage-marker-evidence-failure'), 'physical marker divergence was accepted');
        check(hasFailure(protocolFailure, 'stage-in-progress-present'), 'retained in-progress marker was accepted');
        await unlink(join(fixture.runRoot, 'visual', 'stage-in-progress.json'));
        for (const undo of protocolRestores.reverse()) await undo();

        const candidateManifestPath = join(fixture.candidateRoot, 'manifests', 'candidate-manifest.json');
        restore = await mutateJsonFile(candidateManifestPath, (manifest) => {
            manifest.runId = 'different-run';
            manifest.approvalState = 'approved';
            manifest.autoApprovalProhibited = false;
            manifest.fileCount += 1;
            manifest.packageChecksum = '0'.repeat(64);
        });
        const candidateManifestFailure = await validateFixture(fixture);
        check(hasFailure(candidateManifestFailure, 'candidate-manifest-failure'), 'approved/wrong-run candidate manifest was accepted');
        check(hasFailure(candidateManifestFailure, 'candidate-package-checksum-mismatch'), 'forged candidate fileCount/packageChecksum was accepted');
        await restore();

        const candidateProtected = join(
            fixture.candidateRoot,
            'manifests',
            'protected-source',
            'template-sha256.txt',
        );
        restore = await mutateFile(candidateProtected, Buffer.from('tampered protected manifest\n'));
        const candidateChecksumFailure = await validateFixture(fixture);
        check(hasFailure(candidateChecksumFailure, 'candidate-file-checksum-mismatch'), 'candidate file checksum drift was accepted');
        check(hasFailure(candidateChecksumFailure, 'candidate-protected-storefront-mismatch'), 'protected storefront drift was accepted');
        await restore();

        const candidateBackup = `${fixture.candidateRoot}.missing`;
        await rename(fixture.candidateRoot, candidateBackup);
        check(hasFailure(await validateFixture(fixture), 'candidate-manifest-missing'), 'missing candidate package was accepted');
        await rename(candidateBackup, fixture.candidateRoot);

        const approvedRoot = join(workspaceRoot, 'storage', 'app', 'evidence', 'approved-baseline');
        await mkdir(approvedRoot, { recursive: true });
        await writeFile(join(approvedRoot, 'forbidden.png'), pngForViewport('375x812'));
        check(
            hasFailure(
                await validateFixture(fixture, { approvedBaselineRoots: [approvedRoot] }),
                'approved-baseline-write-detected',
            ),
            'approved-baseline write was accepted',
        );
        await rm(approvedRoot, { recursive: true, force: true });

        restore = await mutateJsonFile(finalPath, (aggregate) => {
            aggregate.baselineCandidate.approvalState = 'approved';
            aggregate.baselineCandidate.autoApproved = true;
        });
        check(hasFailure(await validateFixture(fixture), 'candidate-aggregate-state-failure'), 'approved aggregate candidate state was accepted');
        await restore();

        restore = await mutateJsonFile(visualStagePath, (stage) => {
            const first = stage.payload.fidelity.captures[0];
            Object.assign(stage.payload.fidelity.captures.at(-1), {
                page: first.page,
                viewport: first.viewport,
                run: first.run,
                side: first.side,
            });
        });
        const matrixFailure = await validateFixture(fixture);
        check(hasFailure(matrixFailure, 'capture-matrix-duplicate'), 'duplicate capture matrix key was accepted');
        check(hasFailure(matrixFailure, 'capture-matrix-missing'), 'missing capture matrix key was accepted');
        await restore();

        const forcedStagePath = join(fixture.runRoot, 'verify', 'stage-result.json');
        restore = await mutateJsonFile(forcedStagePath, (stage) => {
            stage.cleanup[0].passed = false;
        });
        check(hasFailure(await validateFixture(fixture), 'stage-schema-failure'), 'failed forced cleanup was accepted');
        await restore();

        const [cooperativePort, stubbornPort, unrelatedPort] = await uniquePorts(3);
        const cooperative = await startHarnessChild({ label: 'owned-cooperative', port: cooperativePort });
        liveChildren.add(cooperative);
        const stubborn = await startHarnessChild({ label: 'owned-stubborn', port: stubbornPort, mode: 'stubborn' });
        liveChildren.add(stubborn);
        const unrelated = await startHarnessChild({ label: 'unrelated-control', port: unrelatedPort });
        liveChildren.add(unrelated);

        check(
            !(await probeSelectedPorts({
                behavioral: { cooperative: cooperativePort, stubborn: stubbornPort, unrelated: unrelatedPort },
            })).passed,
            'occupied child ports were reported as released',
        );
        const graceful = await terminateOwnedChildren([cooperative], { gracefulTimeoutMs: 1_000 });
        liveChildren.delete(cooperative);
        check(graceful[0].passed && !graceful[0].forced, 'cooperative owned child did not stop gracefully');
        check(await portReleased(cooperativePort), 'cooperative child port remains occupied');
        check(childAlive(unrelated.child) && !await portReleased(unrelatedPort), 'owned cleanup touched unrelated child');
        const forced = await terminateOwnedChildren([stubborn], { gracefulTimeoutMs: 150, forceTimeoutMs: 3_000 });
        liveChildren.delete(stubborn);
        check(forced[0].passed && forced[0].forced, 'stubborn owned child did not exercise forced cleanup');
        check(await portReleased(stubbornPort), 'forced child port remains occupied');
        check(childAlive(unrelated.child) && !await portReleased(unrelatedPort), 'forced cleanup touched unrelated child');
        const control = await terminateOwnedChildren([unrelated], { gracefulTimeoutMs: 1_000 });
        liveChildren.delete(unrelated);
        check(control[0].passed && !control[0].forced, 'control child did not stop gracefully');
        check(await portReleased(unrelatedPort), 'control child port remains occupied');

        console.log(`BE-6A.1 strict behavioral harness self-check passed: ${assertionCount}/${assertionCount}`);
    } finally {
        if (liveChildren.size) {
            await terminateOwnedChildren([...liveChildren], {
                gracefulTimeoutMs: 250,
                forceTimeoutMs: 3_000,
            }).catch(() => {});
        }
        await rm(workspaceRoot, { recursive: true, force: true });
    }
}

main().catch((error) => {
    console.error(error.stack ?? error.message);
    process.exitCode = 1;
});
