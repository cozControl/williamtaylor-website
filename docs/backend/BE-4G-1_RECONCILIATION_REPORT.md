# BE-4G.1 Reconciliation Report

Date: 2026-07-25
Status: Complete and validated; BE-4G may close.

1. Original divergence: BE-4G used one `global` record, one `navigation.manage` permission, Page workflow permissions, and prohibited self-approval.
2. Gap analysis: recorded in `BE-4G-1_ARCHITECTURE_GAP_ANALYSIS.md` before correction.
3. Reuse: immutable revisions, transitions, transactions, fingerprints, scheduler, audit and signed preview foundations were retained.
4. Files: domain registry/actions/models, migrations, controllers, Livewire workspaces, views, routes, tests, evidence scripts and documentation were added or corrected.
5. Schema: resources now have type, key, locale, title, draft pointer and archive metadata.
6. Migration: the legacy splitter creates immutable per-type revisions, preserves attribution where possible, audits alignment and is idempotent.
7. Registry: a code-owned fail-closed registry defines four types, schemas, permissions, policy, comparison, media roles and limits.
8. Singletons: primary navigation, footer navigation and site profile are unique per locale.
9. Announcements: each is an independent ULID resource with its own revision and publication lifecycle.
10. Additions: 8 navigation, 11 announcement and 6 settings-workflow permissions were added.
11. Retirement: `navigation.manage` is removed from the registry and alignment removes it transactionally.
12. CMS Manager: receives every approved Site Content permission; no role is auto-assigned.
13. Alignment: preview/apply detects additions, retirement and drift, preserves assignments, resets cache and audits `site-content.permission-registry.aligned`.
14. Navigation: Content exposes Navigation and Announcements; System exposes Site settings.
15. Routes: named Navigation, Announcement, Settings and typed signed-preview routes match the brief; the combined route is removed.
16. Navigation workspace: separate primary/footer editors expose draft, history, comparison, workflow and preview.
17. Footer: bounded code-owned groups and non-nested typed links are validated.
18. Announcements: index, filters, create/edit, workflow, archive/restore and collision feedback are separate.
19. Collision: intersecting active/scheduled periods fail; adjacent periods pass; cancelled, unpublished and archived records are excluded.
20. Settings: typed brand, contact, WhatsApp, social, footer and media sections replace generic key/value editing.
21. Profile schema: only approved brand, contact, social, footer and media data is accepted.
22. Contact: email, telephone and WhatsApp are bounded and normalized by typed validation.
23. Social: platform allowlist, HTTPS URL, accessible label, stable key and order are enforced.
24. Media: revision-owned usage is retained for approved profile/footer roles; only ready non-archived assets may be newly selected.
25. Revisions: immutable historical payloads are never edited in place.
26. Concurrency: draft saves and high-impact actions use locks/state versions and stale fingerprints.
27. Comparison: type-specific semantic summaries are shown instead of raw JSON.
28. Authorization: every action resolves its exact permission from the resource type; Page permissions do not authorize Site Content.
29. Self-approval: all four current types allow it through a code-owned policy; prohibited-policy behavior is tested independently.
30. Immediate publication: approved candidates can be designated transactionally after readiness and collision checks.
31. Scheduling: Dar es Salaam input is converted to UTC and stored per resource.
32. Idempotency: due jobs lock and recheck state; duplicate execution has no second success transition.
33. Supersession: a new candidate supersedes the earlier candidate explicitly.
34. Cancellation: scheduled publication can be cancelled without mutating revisions.
35. Unpublish: clears designation through an audited transition.
36. Archive: announcements alone support guarded archive/restore; archived announcements cannot publish.
37. Preview: authentication, verification, signature, type permission, relationship, no-store and noindex are enforced.
38. Audit: bounded events cover creation, drafts, workflow, publication, archive/restore and registry alignment without full payloads.
39. Accessibility: labels, live regions, keyboard reorder and visible focus are implemented.
40. Responsive behavior: workspaces and preview target 1440x900, 768x1024 and 375x812.
41. Browser evidence: Chromium 149.0.7827.55 produced 26 screenshots at 1440x900, 768x1024 and 375x812; console errors, warnings, failed requests, failed assets and horizontal overflow are all zero. Keyboard, focus, validation, preview, publication and authorization checks passed.
42. Focused tests: Site Content and Publishing pass 29 tests, 148 assertions.
43. Publishing regression: included in the focused passing result.
44. Content regression: passes within 85 impacted tests, 537 assertions.
45. Media regression: passes within the same impacted result.
46. Admin regression: passes within the same impacted result.
47. Identity regression: passes within the same impacted result.
48. Full suite: 171 tests and 1,115 assertions passed.
49. Query budgets: covered by focused resource-index tests.
50. Larastan: passed with 0 errors.
51. Pint/syntax: scoped Pint, PHP syntax and Node syntax passed.
52. Routes: 22 administration routes were inventoried; the typed preview route is separately protected.
53. Scheduler: Page and Site Content scheduled publishers are registered every minute.
54. Composer: manifest validation passed with the existing exact-version warning; audit found 0 advisories.
55. Blade: cache compilation passed.
56. Vite: production build passed with only the existing optional Fontaine notice.
57. npm: audit found 0 vulnerabilities.
58. Public regression: the full 11-size homepage gate passed. Non-zero pixels are the previously classified hero timing and raster variance; no migration defect was found.
59. Protected checksums: all 56 tracked storefront files are byte-identical to the Git baseline.
60. Dependencies: composer.lock and package-lock.json are unchanged.
61. Scans: no credential literal, provider secret, forbidden dash, or new mojibake was found; matches were variable names and empty password form properties only.
62. Whitespace: `git diff --check` passed; Git reported only line-ending normalization notices.
63. Database baseline: 401408 bytes, SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
64. Isolation: PHPUnit uses memory; standalone migration/evidence uses an explicit disposable SQLite path and refuses `willy`.
65. Guard: browser, homepage fidelity and final validation all began and ended at the exact current baseline hash and byte size.
66. Limitations: public projection is intentionally inactive; legal/provider/operational details remain deferred.
67. Public boundary: no public controller, route, cache or template reads Site Content.
68. Later domains: SEO, catalogue, pricing, inventory, commerce, localization, APIs, customer images and AI were not started.
69. Recommendation: authorize BE-4G closure. Every corrective, browser, storefront and preservation gate now passes.
70. BE-4H: not started and not authorized.
