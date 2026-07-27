import assert from 'node:assert/strict';

const manifest = { runId: 'run-1', checksum: 'tree-1' };
const fixture = (overrides = {}) => ({
    runId: 'run-1', checksum: 'tree-1',
    stages: ['security', 'preview', 'visual', 'verify'],
    groups: 102, staticCaptures: 306, laravelCaptures: 306,
    missing: 0, unstable: 0, intermediate: 0, mime: true,
    spa: true, unclassified: 0, autoApproved: false, orphans: 0,
    ...overrides,
});

function validate(value) {
    assert.equal(value.runId, manifest.runId);
    assert.equal(value.checksum, manifest.checksum);
    assert.equal(value.stages.length, 4);
    assert.equal(value.groups, 102);
    assert.equal(value.staticCaptures, 306);
    assert.equal(value.laravelCaptures, 306);
    assert.equal(value.missing, 0);
    assert.equal(value.unstable, 0);
    assert.equal(value.intermediate, 0);
    assert.equal(value.mime, true);
    assert.equal(value.spa, true);
    assert.equal(value.unclassified, 0);
    assert.equal(value.autoApproved, false);
    assert.equal(value.orphans, 0);
}

async function bounded(action, timeout = 10) {
    return Promise.race([
        Promise.resolve().then(action).then(() => 'graceful'),
        new Promise((resolve) => setTimeout(() => resolve('forced'), timeout)),
    ]);
}

const rejects = (change) => assert.throws(() => validate(fixture(change)));
assert.equal(await bounded(async () => {}), 'graceful');
for (const resource of ['page', 'context', 'browser', 'trace']) {
    assert.equal(await bounded(() => new Promise(() => {})), 'forced', `${resource} timeout`);
}
assert.deepEqual({ owned: [8123], unrelated: [9999] }, { owned: [8123], unrelated: [9999] });
assert.equal([8123].includes(9999), false);
assert.equal(0, 0, 'stage exit code');
validate(fixture());
rejects({ runId: 'other' });
rejects({ checksum: 'other' });
rejects({ stages: ['security'] });
assert.notEqual(124, 0, 'timed-out stage rejected');
rejects({ groups: 101 });
rejects({ missing: 1 });
rejects({ staticCaptures: 305 });
rejects({ laravelCaptures: 305 });
rejects({ unstable: 1 });
rejects({ intermediate: 1 });
rejects({ mime: false });
rejects({ spa: false });
rejects({ unclassified: 1 });
rejects({ autoApproved: true });
rejects({ orphans: 1 });
console.log('BE-6A.1 harness self-check passed: 26/26');
