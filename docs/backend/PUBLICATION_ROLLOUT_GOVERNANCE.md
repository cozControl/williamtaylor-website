# Publication Rollout Governance

The code-owned registry recognizes Page, Site Content, Product, Collection, and Campaign. Only Page/About and Site Content have projection resolvers. Product, Collection, and Campaign are foundation-only and accept only `static`.

Rollout modes are `static`, `shadow`, `enabled`, and `emergency_disabled`. The global kill switch defaults off and resolves every resource to static. Invalid and unsupported configuration also resolves to static; request data is never consulted.

Shadow comparison records checksums, bounded categories, readiness, time, and an evidence reference without storing complete HTML. Revision comparison exposes only structured revision fields and omits identifiers, timestamps, authors, and checksums.

Emergency unpublish requires the sensitive `publication.emergency-unpublish` permission, a verified user, and a bounded reason. It preserves revisions, removes the public designation, cancels a scheduled transition, invalidates only the resource cache after commit, audits once, and is idempotent.

Rollback creates a new immutable draft with a new number, checksum, and `source_revision_id`; it never publishes, approves, or schedules.

Signed previews remain authenticated, verified, signed, resource-bound exceptions and must remain noindex and outside public caches.

> A visible Admin link is not an authorization boundary. The linked route and every downstream privileged action must enforce authentication, verification and server-side permission checks.

Production changes require approved access, normal approval separation, shadow/fidelity evidence, scoped cache invalidation, incident correlation, and resource-specific recovery. Public toggles, request-selected modes, arbitrary classes/routes/views/cache prefixes, global cache flushes, and bypassing workflow are prohibited.

Closure and production evidence must include successful interactive browser checks for all four Admin access states and human review of every non-zero fidelity diff; a harness exit code alone does not approve visual differences.

Repository-owned Playwright is the canonical automated browser runner. When no approved immutable PNG baseline exists, fidelity is governed by same-run comparisons between the checksum-protected static storefront and Laravel under identical browser settings. Formal approved baseline capture remains mandatory before BE-6A.2 may enable any additional public-resource rollout.
## Visual baseline governance

The checksum-protected static source is the canonical visual reference. A static reference must pass repeatability before any Laravel comparison is accepted. Every non-zero difference requires an explicit classification and human disposition.

Generated candidate baselines are not approved baselines. Formal baseline approval remains required before BE-6A.2 may enable any additional public-resource projection.

## BE-6A.1 deterministic browser governance

Process-isolated Playwright stages are permitted when one immutable root manifest binds every stage to the same commit, working tree, dependencies, build, browser, configuration, protected manifests, isolated-database procedure, and run ID. Teardown defects never justify omitted evidence. Exactly 102/102 repeatability groups, 612 captures, and 306 comparisons are required.

Generated baseline candidates remain unapproved. They must remain outside approved baseline storage until a designated reviewer records formal approval. Formal approval and separate authorization remain prerequisites before BE-6A.2.