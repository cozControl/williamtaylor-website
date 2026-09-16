import { createHash } from 'node:crypto';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

export const PROTECTED_APP_ID = '6a4d9ad469285a7e6df866f1';

export const DATABASE_PROCEDURE = 'sqlite-disposable:create-empty;migrate-fresh;fixture;stage-scoped-path;remove-runtime';

export const READINESS_CONFIG = Object.freeze({
    attempts: 48,
    frameIntervalMs: 250,
    stableFrames: 3,
    assetTimeoutMs: 2_000,
    screenshotTimeoutMs: 15_000,
});

export const MOTION_NORMALIZATION_CSS = [
    '*,*::before,*::after{',
    'animation-delay:0s!important;',
    'animation-duration:0s!important;',
    'animation-iteration-count:1!important;',
    'caret-color:transparent!important;',
    'cursor:auto!important;',
    'scroll-behavior:auto!important;',
    'transition-delay:0s!important;',
    'transition-duration:0s!important',
    '}',
].join('');

// These deterministic test-only rules are applied unchanged to both origins.
// They remove compositor blur as a source of full-width false deltas while
// retaining the protected color, and make scaled decorative JPEG tiling use
// integer dimensions and nearest-neighbour sampling.
export const VISUAL_NORMALIZATION_CSS = [
    '.frosted-nav{',
    '-webkit-backdrop-filter:none!important;',
    'backdrop-filter:none!important;',
    'background-color:#41171b!important',
    '}',
    '.wt-pattern-texture{image-rendering:pixelated!important}',
    '.wt-pattern-texture[style*="120px"]{background-size:120px 72px!important}',
    '.wt-pattern-texture[style*="300px"]{background-size:300px 179px!important}',
].join('');

export const STATIC_ROUTER_NORMALIZATION = Object.freeze({
    id: 'base-href-root-v1',
    injectedMarkup: '<base href="/">',
    routes: Object.freeze([
        '/collections/limited-edition',
        '/product/taylor-oxford-shirt',
        '/product/mercerized-cotton-polo',
        '/product/dar-es-salaam-linen-suit',
        '/product/slim-tapered-chinos',
        '/product/executive-overcoat',
    ]),
});

export const DECORATIVE_MEDIA_ALLOWLIST = Object.freeze([
    `/videos/public/${PROTECTED_APP_ID}/2eaa51040_3333.mp4`,
    `/videos/public/${PROTECTED_APP_ID}/eb1f94bda_SnapInsta-Ai_3923038057139474583_77059721635.mp4`,
]);

export const SUBPIXEL_MORPHOLOGY_LIMITS = Object.freeze({
    maxChannelDelta: 16,
    maxComponentWidth: 64,
    maxComponentHeight: 64,
    maxComponentPixels: 4096,
});

export const BROWSER_LAUNCH_ARGS = [
    // Chromium begin-frame control enabled by deterministic-mode deadlocks Playwright screenshots.
    // Determinism is verified by repeated raster captures below, not this incompatible flag.
    '--disable-gpu',
    '--disable-lcd-text',
    '--disable-skia-runtime-opts',
    '--disable-threaded-animation',
    '--disable-threaded-scrolling',
    '--font-render-hinting=none',
    '--force-color-profile=srgb',
    '--run-all-compositor-stages-before-draw',
];

export function sha256(value) {
    return createHash('sha256').update(value).digest('hex');
}

export function canonicalPng(buffer) {
    return PNG.sync.write(PNG.sync.read(buffer), {
        colorType: 6,
        inputColorType: 6,
        inputHasAlpha: true,
    });
}

function changedBounds(before, after, width, height) {
    let left = width;
    let top = height;
    let right = -1;
    let bottom = -1;
    let rawDifferentPixels = 0;
    let maxChannelDelta = 0;
    const diff = new PNG({ width, height });

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const offset = ((y * width) + x) * 4;
            let changed = false;
            for (let channel = 0; channel < 4; channel++) {
                const delta = Math.abs(before[offset + channel] - after[offset + channel]);
                maxChannelDelta = Math.max(maxChannelDelta, delta);
                if (delta !== 0) changed = true;
            }
            const value = changed ? [255, 0, 0, 255] : [0, 0, 0, 0];
            diff.data.set(value, offset);
            if (!changed) continue;
            rawDifferentPixels += 1;
            left = Math.min(left, x);
            top = Math.min(top, y);
            right = Math.max(right, x);
            bottom = Math.max(bottom, y);
        }
    }

    return {
        rawDifferentPixels,
        maxChannelDelta,
        differenceBounds: rawDifferentPixels === 0 ? null : {
            x: left,
            y: top,
            width: right - left + 1,
            height: bottom - top + 1,
        },
        rawDiffBuffer: PNG.sync.write(diff),
    };
}

export function compareRasterBuffers(leftBuffer, rightBuffer) {
    const left = PNG.sync.read(leftBuffer);
    const right = PNG.sync.read(rightBuffer);
    if (left.width !== right.width || left.height !== right.height) {
        throw new Error(`Raster dimensions differ: ${left.width}x${left.height} vs ${right.width}x${right.height}`);
    }
    const { rawDifferentPixels, maxChannelDelta, differenceBounds, rawDiffBuffer } = changedBounds(
        left.data,
        right.data,
        left.width,
        left.height,
    );
    const perceptualDifferentPixels = pixelmatch(
        left.data,
        right.data,
        null,
        left.width,
        left.height,
        { threshold: 0.1, includeAA: true },
    );
    const materialDifferentPixels = pixelmatch(
        left.data,
        right.data,
        null,
        left.width,
        left.height,
        { threshold: 0.1, includeAA: false },
    );
    return {
        width: left.width,
        height: left.height,
        totalPixels: left.width * left.height,
        rawDifferentPixels,
        rawDifferencePercent: rawDifferentPixels / (left.width * left.height) * 100,
        perceptualDifferentPixels,
        perceptualDifferencePercent: perceptualDifferentPixels / (left.width * left.height) * 100,
        materialDifferentPixels,
        maxChannelDelta,
        differenceBounds,
        rawDiffBuffer,
    };
}

export function analyzeSubpixelMorphology(leftBuffer, rightBuffer, sensitiveRegions = []) {
    const left = PNG.sync.read(leftBuffer);
    const right = PNG.sync.read(rightBuffer);
    if (left.width !== right.width || left.height !== right.height) {
        throw new Error(`Raster dimensions differ: ${left.width}x${left.height} vs ${right.width}x${right.height}`);
    }
    const raster = compareRasterBuffers(leftBuffer, rightBuffer);
    const pixelCount = left.width * left.height;
    const changed = new Uint8Array(pixelCount);
    const sensitive = new Uint8Array(pixelCount);
    const regions = sensitiveRegions
        .filter((region) => region && Number.isFinite(region.x) && Number.isFinite(region.y)
            && Number.isFinite(region.width) && Number.isFinite(region.height)
            && region.width > 0 && region.height > 0)
        .map((region) => ({
            x: Math.max(0, Math.floor(region.x) - 1),
            y: Math.max(0, Math.floor(region.y) - 1),
            right: Math.min(left.width, Math.ceil(region.x + region.width) + 1),
            bottom: Math.min(left.height, Math.ceil(region.y + region.height) + 1),
            type: String(region.type ?? 'unknown'),
            identity: String(region.identity ?? ''),
        }));
    for (const region of regions) {
        for (let y = region.y; y < region.bottom; y++) {
            sensitive.fill(1, (y * left.width) + region.x, (y * left.width) + region.right);
        }
    }

    let outsideSensitivePixels = 0;
    let changedPixels = 0;
    for (let pixel = 0; pixel < pixelCount; pixel++) {
        const offset = pixel * 4;
        const differs = (
            left.data[offset] !== right.data[offset]
            || left.data[offset + 1] !== right.data[offset + 1]
            || left.data[offset + 2] !== right.data[offset + 2]
            || left.data[offset + 3] !== right.data[offset + 3]
        );
        if (!differs) continue;
        changed[pixel] = 1;
        changedPixels += 1;
        if (!sensitive[pixel]) outsideSensitivePixels += 1;
    }

    const queue = new Int32Array(Math.max(changedPixels, 1));
    let componentCount = 0;
    let maxComponentWidth = 0;
    let maxComponentHeight = 0;
    let maxComponentPixels = 0;
    const neighbours = [
        [-1, -1], [0, -1], [1, -1],
        [-1, 0], [1, 0],
        [-1, 1], [0, 1], [1, 1],
    ];
    for (let seed = 0; seed < pixelCount; seed++) {
        if (!changed[seed]) continue;
        componentCount += 1;
        let head = 0;
        let tail = 0;
        queue[tail++] = seed;
        changed[seed] = 0;
        let componentPixels = 0;
        let leftBound = left.width;
        let topBound = left.height;
        let rightBound = -1;
        let bottomBound = -1;
        while (head < tail) {
            const pixel = queue[head++];
            componentPixels += 1;
            const x = pixel % left.width;
            const y = Math.floor(pixel / left.width);
            leftBound = Math.min(leftBound, x);
            topBound = Math.min(topBound, y);
            rightBound = Math.max(rightBound, x);
            bottomBound = Math.max(bottomBound, y);
            for (const [dx, dy] of neighbours) {
                const nextX = x + dx;
                const nextY = y + dy;
                if (nextX < 0 || nextX >= left.width || nextY < 0 || nextY >= left.height) continue;
                const next = (nextY * left.width) + nextX;
                if (!changed[next]) continue;
                changed[next] = 0;
                queue[tail++] = next;
            }
        }
        maxComponentWidth = Math.max(maxComponentWidth, rightBound - leftBound + 1);
        maxComponentHeight = Math.max(maxComponentHeight, bottomBound - topBound + 1);
        maxComponentPixels = Math.max(maxComponentPixels, componentPixels);
    }

    const passed = (
        raster.rawDifferentPixels > 0
        && raster.perceptualDifferentPixels === 0
        && raster.materialDifferentPixels === 0
        && raster.maxChannelDelta <= SUBPIXEL_MORPHOLOGY_LIMITS.maxChannelDelta
        && regions.length > 0
        && outsideSensitivePixels === 0
        && componentCount > 0
        && maxComponentWidth <= SUBPIXEL_MORPHOLOGY_LIMITS.maxComponentWidth
        && maxComponentHeight <= SUBPIXEL_MORPHOLOGY_LIMITS.maxComponentHeight
        && maxComponentPixels <= SUBPIXEL_MORPHOLOGY_LIMITS.maxComponentPixels
    );
    return {
        passed,
        limits: SUBPIXEL_MORPHOLOGY_LIMITS,
        sensitiveRegionCount: regions.length,
        outsideSensitivePixels,
        insideSensitivePixels: changedPixels - outsideSensitivePixels,
        componentCount,
        maxComponentWidth,
        maxComponentHeight,
        maxComponentPixels,
        rawDifferentPixels: raster.rawDifferentPixels,
        perceptualDifferentPixels: raster.perceptualDifferentPixels,
        materialDifferentPixels: raster.materialDifferentPixels,
        maxChannelDelta: raster.maxChannelDelta,
    };
}

function normalized(value) {
    return JSON.stringify(value);
}

function entryDifferences(left = [], right = []) {
    const leftCounts = new Map();
    const rightCounts = new Map();
    for (const value of left) leftCounts.set(value, (leftCounts.get(value) ?? 0) + 1);
    for (const value of right) rightCounts.set(value, (rightCounts.get(value) ?? 0) + 1);
    const onlyStatic = [];
    const onlyLaravel = [];
    for (const [value, count] of leftCounts) {
        for (let index = rightCounts.get(value) ?? 0; index < count; index++) onlyStatic.push(value);
    }
    for (const [value, count] of rightCounts) {
        for (let index = leftCounts.get(value) ?? 0; index < count; index++) onlyLaravel.push(value);
    }
    return { onlyStatic, onlyLaravel };
}

function adminPath(value, origin = 'http://be6a1.invalid') {
    if (typeof value !== 'string' || value.trim() === '') return null;
    try {
        const expectedOrigin = new URL(origin);
        const url = new URL(value, expectedOrigin);
        return ['http:', 'https:'].includes(url.protocol) && url.origin === expectedOrigin.origin
            ? url.pathname
            : null;
    } catch {
        return null;
    }
}

function laravelAdminUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') return null;
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && url.pathname === '/admin'
            ? url
            : null;
    } catch {
        return null;
    }
}

export function isAdminHrefException(staticHref, laravelHref, configuredLaravelAdminUrl) {
    const adminUrl = laravelAdminUrl(configuredLaravelAdminUrl);
    if (
        adminUrl === null
        || typeof staticHref !== 'string'
        || staticHref.trim() === ''
        || typeof laravelHref !== 'string'
        || laravelHref.trim() === ''
        || staticHref === laravelHref
    ) {
        return false;
    }

    return (
        ['/admin', '/html/admin.html'].includes(adminPath(staticHref))
        && adminPath(laravelHref, adminUrl.href) === '/admin'
    );
}

export function compareSemantics(staticSemantic, laravelSemantic) {
    const arrayFields = [
        'visibleTextEntries',
        'headingStructureEntries',
        'imageIdentityEntries',
        'keyStyleEntries',
        'interactiveStateEntries',
        'rasterSensitiveRegionEntries',
    ];
    const checksumFields = [
        'visibleTextChecksum',
        'headingStructureChecksum',
        'imageIdentityChecksum',
        'keyStyleChecksum',
        'interactiveStateChecksum',
        'rasterSensitiveRegionChecksum',
    ];
    const mismatchFields = [];
    for (const field of arrayFields) {
        if (!Array.isArray(staticSemantic?.[field]) || !Array.isArray(laravelSemantic?.[field])) {
            mismatchFields.push(`missing:${field}`);
        } else if (normalized(staticSemantic[field]) !== normalized(laravelSemantic[field])) {
            mismatchFields.push(field);
        }
    }
    for (const field of checksumFields) {
        if (
            typeof staticSemantic?.[field] !== 'string'
            || staticSemantic[field].length !== 64
            || typeof laravelSemantic?.[field] !== 'string'
            || laravelSemantic[field].length !== 64
        ) {
            mismatchFields.push(`missing:${field}`);
        } else if (staticSemantic[field] !== laravelSemantic[field]) {
            mismatchFields.push(field);
        }
    }

    const staticAdminHref = staticSemantic?.adminHref;
    const laravelAdminHref = laravelSemantic?.adminHref;
    const configuredLaravelAdminUrl = laravelSemantic?.laravelAdminUrl;
    const validAdminSchema = (
        typeof staticAdminHref === 'string'
        && staticAdminHref.trim() !== ''
        && typeof laravelAdminHref === 'string'
        && laravelAdminHref.trim() !== ''
        && laravelAdminUrl(configuredLaravelAdminUrl) !== null
    );
    const adminHrefException = isAdminHrefException(
        staticAdminHref,
        laravelAdminHref,
        configuredLaravelAdminUrl,
    );
    if (!validAdminSchema || (staticAdminHref !== laravelAdminHref && !adminHrefException)) {
        mismatchFields.push('adminHref');
    }
    const visibleTextDifference = entryDifferences(
        staticSemantic?.visibleTextEntries,
        laravelSemantic?.visibleTextEntries,
    );
    return {
        passed: mismatchFields.length === 0,
        mismatchFields: [...new Set(mismatchFields)],
        visibleTextDifference,
        adminHrefException,
        staticAdminHref,
        laravelAdminHref,
    };
}

function comparisonFingerprint(item) {
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

export function assessRepeatability(runPairs) {
    const crossRunDeltas = { static: [], laravel: [] };
    for (const side of ['static', 'laravel']) {
        for (let index = 1; index < runPairs.length; index++) {
            crossRunDeltas[side].push(
                compareRasterBuffers(runPairs[0][`${side}Buffer`], runPairs[index][`${side}Buffer`]).rawDifferentPixels,
            );
        }
    }
    const staticHashes = [...new Set(runPairs.map((item) => item.staticHash))];
    const laravelHashes = [...new Set(runPairs.map((item) => item.laravelHash))];
    const comparisonFingerprints = [...new Set(runPairs.map(comparisonFingerprint))];
    const exactSideRepeatability = (
        staticHashes.length === 1
        && laravelHashes.length === 1
        && crossRunDeltas.static.every((value) => value === 0)
        && crossRunDeltas.laravel.every((value) => value === 0)
    );
    const deterministicComparisons = comparisonFingerprints.length === 1;
    const semanticParity = runPairs.every((item) => item.semanticMatch);
    const geometryParity = runPairs.every((item) => item.boxesMatch && item.responsiveModeMatch);
    const rawCounts = runPairs.map((item) => item.rawDifferentPixels);
    const perceptualCounts = runPairs.map((item) => item.perceptualDifferentPixels);
    const materialCounts = runPairs.map((item) => item.materialDifferentPixels);
    const adminHrefException = runPairs.every((item) => item.adminHrefException);
    const boundedSubpixelProof = runPairs.every((item) =>
        item.rawDifferentPixels === 0 || item.subpixelProof?.passed === true);
    const basePassed = (
        runPairs.length === 3
        && exactSideRepeatability
        && deterministicComparisons
        && semanticParity
        && geometryParity
    );
    const passed = basePassed && (
        rawCounts.every((value) => value === 0)
        || boundedSubpixelProof
    );
    let classification = 'unresolved-material-visual-difference';
    if (passed && rawCounts.every((value) => value === 0)) {
        classification = adminHrefException ? 'approved-non-visual-admin-href-change' : 'exact';
    } else if (
        passed
        && rawCounts.some((value) => value > 0)
        && perceptualCounts.every((value) => value === 0)
        && materialCounts.every((value) => value === 0)
        && boundedSubpixelProof
    ) {
        classification = 'deterministic-subpixel-rasterization';
    }
    return {
        runs: runPairs.length,
        staticHashes,
        laravelHashes,
        crossRunDeltas,
        comparisonFingerprints,
        exactSideRepeatability,
        deterministicComparisons,
        semanticParity,
        geometryParity,
        boundedSubpixelProof,
        rawDifferentPixels: rawCounts,
        perceptualDifferentPixels: perceptualCounts,
        materialDifferentPixels: materialCounts,
        passed,
        classification,
        classified: classification !== 'unresolved-material-visual-difference',
    };
}

export function sanitizeUrl(rawUrl) {
    try {
        const value = new URL(rawUrl);
        return `${value.origin}${value.pathname}`;
    } catch {
        return '[invalid-url]';
    }
}

function localOptionalRule(event) {
    let url;
    try {
        url = new URL(event.url);
    } catch {
        return null;
    }
    if (!['127.0.0.1', 'localhost'].includes(url.hostname)) return null;
    const escaped = PROTECTED_APP_ID.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const rules = [
        {
            id: 'protected-spa-user-me',
            method: 'GET',
            resourceType: 'xhr',
            path: new RegExp(`^/api/apps/${escaped}/entities/User/me$`),
        },
        {
            id: 'protected-spa-app-log',
            method: 'POST',
            resourceType: 'fetch',
            path: new RegExp(`^/api/app-logs/${escaped}/log-user-in-app/[a-z0-9-]+$`),
        },
        {
            id: 'protected-spa-public-settings',
            method: 'GET',
            resourceType: 'xhr',
            path: new RegExp(`^/api/apps/public/prod/public-settings/by-id/${escaped}$`),
        },
        {
            id: 'protected-spa-analytics',
            method: 'POST',
            resourceType: 'xhr',
            path: new RegExp(`^/api/apps/${escaped}/analytics/track/batch$`),
        },
    ];
    return rules.find((rule) =>
        rule.path.test(url.pathname)
        && rule.method === event.method
        && rule.resourceType === event.resourceType
        && event.status === 404
    ) ?? null;
}

export function classifyNetworkEvidence(network, captureStates = {}, stageName = 'visual') {
    const classified = {
        console: [],
        failedRequests: [],
        errorResponses: [],
    };

    for (const event of network.errorResponses) {
        const optionalRule = localOptionalRule(event);
        const expectedNegative = (
            event.resourceType === 'document'
            && (
                (event.label.startsWith('admin:') && event.status === 403)
                || (event.label.startsWith('preview:') && [403, 404].includes(event.status))
            )
        );
        const classification = optionalRule?.id
            ?? (expectedNegative ? 'expected-negative-control' : 'unclassified-http-error');
        classified.errorResponses.push({
            ...event,
            url: sanitizeUrl(event.url),
            classification,
            allowed: Boolean(optionalRule || expectedNegative),
        });
    }

    for (const event of network.failedRequests) {
        let parsed;
        try {
            parsed = new URL(event.url);
        } catch {}
        const state = captureStates[event.label];
        const protectedMediaAbort = (
            parsed?.hostname === 'media.base44.com'
            && DECORATIVE_MEDIA_ALLOWLIST.includes(parsed.pathname)
            && event.resourceType === 'media'
            && event.error === 'net::ERR_ABORTED'
            && event.phase === 'cleanup-after-evidence'
            && state?.fullyHydrated === true
            && state?.visuallyStable === true
            && state?.screenshotPersisted === true
            && state?.visibleVideosReady === true
            && state?.videoSources?.includes(parsed.pathname)
            && state?.readyVideoSources?.includes(parsed.pathname)
        );
        classified.failedRequests.push({
            ...event,
            url: sanitizeUrl(event.url),
            classification: protectedMediaAbort
                ? 'protected-media-range-abort-after-stable-frame'
                : 'unclassified-request-failure',
            allowed: protectedMediaAbort,
        });
    }

    const optional404s = classified.errorResponses.filter((event) =>
        event.allowed && event.classification.startsWith('protected-spa-'));
    const generic404ConsoleCount = network.console.filter((event) =>
        event.text === 'Failed to load resource: the server responded with a status of 404 (Not Found)').length;
    const settings404Count = optional404s.filter((event) =>
        event.classification === 'protected-spa-public-settings').length;
    const appStateConsoleCount = network.console.filter((event) =>
        event.text.startsWith('App state check failed: Base44Error:')).length;
    const genericCorrelationPassed = generic404ConsoleCount === optional404s.length
        + classified.errorResponses.filter((event) => event.classification === 'expected-negative-control').length;
    const appStateCorrelationPassed = appStateConsoleCount === settings404Count;

    for (const event of network.console) {
        const generic404 = event.text === 'Failed to load resource: the server responded with a status of 404 (Not Found)';
        const appState = event.text.startsWith('App state check failed: Base44Error:');
        const expectedNegative = generic404 && ['security', 'preview'].includes(stageName);
        let classification = 'unclassified-console-message';
        let allowed = false;
        if (generic404 && genericCorrelationPassed) {
            classification = expectedNegative
                ? 'expected-negative-control-console'
                : 'protected-spa-optional-404-console';
            allowed = true;
        } else if (appState && appStateCorrelationPassed) {
            classification = 'protected-spa-public-settings-console';
            allowed = true;
        }
        classified.console.push({
            ...event,
            text: event.text.replace(/https?:\/\/[^\s)]+/g, (value) => sanitizeUrl(value)),
            classification,
            allowed,
        });
    }

    const all = [
        ...classified.console,
        ...classified.failedRequests,
        ...classified.errorResponses,
    ];
    const contractFailures = [];
    if (stageName === 'visual') {
        const expectedOptionalRules = [
            'protected-spa-user-me',
            'protected-spa-app-log',
            'protected-spa-public-settings',
            'protected-spa-analytics',
        ];
        for (const [label, state] of Object.entries(captureStates)) {
            if (!state?.screenshotPersisted) continue;
            const responses = classified.errorResponses.filter((event) =>
                event.label === label && event.classification.startsWith('protected-spa-'));
            const consoleEvents = classified.console.filter((event) => event.label === label);
            if (state.protectedSpaExpected === false) {
                const protectedConsoleEvents = consoleEvents.filter((event) =>
                    event.classification.startsWith('protected-spa-'));
                if (responses.length > 0 || protectedConsoleEvents.length > 0) {
                    contractFailures.push({
                        label,
                        classification: 'unexpected-protected-spa-activity',
                        allowed: false,
                        responseCount: responses.length,
                        consoleCount: protectedConsoleEvents.length,
                    });
                }
                continue;
            }
            if (state.protectedSpaExpected !== true) {
                contractFailures.push({
                    label,
                    classification: 'protected-spa-expectation-missing',
                    allowed: false,
                });
                continue;
            }
            const counts = Object.fromEntries(expectedOptionalRules.map((rule) => [
                rule,
                responses.filter((event) => event.classification === rule).length,
            ]));
            const genericCount = consoleEvents.filter((event) =>
                event.classification === 'protected-spa-optional-404-console').length;
            const appStateCount = consoleEvents.filter((event) =>
                event.classification === 'protected-spa-public-settings-console').length;
            if (
                expectedOptionalRules.some((rule) => counts[rule] !== 1)
                || genericCount !== 4
                || appStateCount !== 1
            ) {
                contractFailures.push({
                    label,
                    classification: 'protected-spa-optional-service-contract-mismatch',
                    allowed: false,
                    counts,
                    genericConsoleCount: genericCount,
                    appStateConsoleCount: appStateCount,
                });
            }
        }
    }
    all.push(...contractFailures);
    const blocking = all.filter((event) => !event.allowed);
    const requiredLocalAssetFailures = [
        ...classified.failedRequests,
        ...classified.errorResponses,
    ].filter((event) =>
        !event.allowed
        && ['script', 'stylesheet', 'image', 'font'].includes(event.resourceType)
        && /^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?\//.test(event.url)
    );
    const moduleMimeRefusals = classified.console.filter((event) =>
        /MIME type|Failed to load module script|module was blocked/i.test(event.text));
    const countsByClassification = {};
    for (const event of all) {
        countsByClassification[event.classification] = (countsByClassification[event.classification] ?? 0) + 1;
    }
    return {
        passed: blocking.length === 0 && requiredLocalAssetFailures.length === 0 && moduleMimeRefusals.length === 0,
        rawCounts: {
            console: network.console.length,
            failedRequests: network.failedRequests.length,
            errorResponses: network.errorResponses.length,
        },
        classifiedCounts: countsByClassification,
        optionalServiceContract: {
            scope: 'captures-explicitly-requiring-protected-spa',
            expectedCallsPerProtectedSpaCapture: 4,
            expectedConsoleErrorsPerProtectedSpaCapture: 5,
            protectedSpaCaptureCount: Object.values(captureStates)
                .filter((state) => state?.screenshotPersisted && state.protectedSpaExpected === true).length,
            serverRenderedCaptureCount: Object.values(captureStates)
                .filter((state) => state?.screenshotPersisted && state.protectedSpaExpected === false).length,
            failures: contractFailures,
        },
        correlations: {
            generic404ConsoleCount,
            optionalOrNegative404Count: optional404s.length
                + classified.errorResponses.filter((event) => event.classification === 'expected-negative-control').length,
            genericCorrelationPassed,
            appStateConsoleCount,
            settings404Count,
            appStateCorrelationPassed,
        },
        requiredLocalAssetFailures,
        moduleMimeRefusals,
        blocking,
        evidence: classified,
    };
}
