# BE-5A.1 Completion Report

1. **Passed — Architecture reviewed.** Phase map, roadmap, BE-5A.0 inventory, catalogue domain, audit conventions, and preservation runbooks reviewed.
2. **Passed — BE-5A.0 inventory constraints accepted.** No observed catalogue item was persisted and no observed SKU was assigned.
3. **Passed — Existing schema implementation reviewed.** One catalogue migration, five models, actions, result object, fingerprints, readiness, and tests inspected.
4. **Passed — Six-table count correction.** Exactly six normalized catalogue tables verified on SQLite and MySQL.
5. **Passed — Files created and changed.** Added three metadata update actions and one focused test; catalogue typing and this documentation were updated.
6. **Passed — Product-type registry.** Fail-closed `apparel` registry; full Larastan clean.
7. **Passed — Apparel content schema.** Typed normalized editorial payload retained.
8. **Passed — Supported options.** Only `colour` and `size`.
9. **Passed — Maximum-option enforcement.** Maximum two active options remains enforced.
10. **Passed — Rich-text normalization.** Existing normalized document processing retained.
11. **Passed — Rich-text sanitization.** Restricted sanitizer retained.
12. **Passed — Revision checksums.** Deterministic checksum implementation retained.
13. **Passed — Product creation action.** Transactional and audited.
14. **Passed — Product revision action.** Transactional and stale-safe.
15. **Passed — Revision immutability.** Update/delete model guards retained.
16. **Passed — Revision numbering concurrency.** Product lock serializes revision numbering.
17. **Passed — Slug-change action.** `ChangeProductSlug` normalizes, locks, stale-checks, rejects archives/duplicates, converts SQLSTATE 23000 races, no-ops idempotently, and audits old/new values.
18. **Passed — Option actions.** Create, display-only update, archive, and restore are explicit.
19. **Passed — Option-value actions.** Create, display-only update, archive, and restore are explicit.
20. **Passed — Option/value archive and restore.** Explicit target-only lifecycle behavior retained.
21. **Passed — Variant fingerprint service.** Deterministic SHA-256 service retained.
22. **Passed — Fingerprint normalization.** Option identities are sorted before hashing.
23. **Passed — Explicit Variant creation.** No implicit creation path exists.
24. **Passed — No automatic Variant generation.** Code and route scans found no generator or observer.
25. **Passed — Nullable SKU behavior.** MySQL schema inspection confirms nullable unique SKU.
26. **Passed — Nullable barcode behavior.** MySQL schema inspection confirms nullable unique barcode.
27. **Passed — Unresolved SKU preservation.** No visible SKU was assigned.
28. **Passed — Variant relationship validation.** Ownership, state, completeness, and restored identifier validation pass.
29. **Passed — Duplicate-combination prevention.** Application validation and `prod_var_combo_uq` verified.
30. **Passed — Default-Variant action.** Explicit assignment/clearing; no inferred replacement.
31. **Passed — Product archive.** Stale-safe, reason-bounded, reversible, and draft-forcing.
32. **Passed — Product restore.** Clears archive metadata and remains draft.
33. **Passed — Variant archive.** Preserves identity/relationships; default pointer clears without replacement.
34. **Passed — Variant restore.** Dependencies revalidated; remains draft and unassigned.
35. **Passed — State fingerprints.** Product and Variant fingerprints cover aggregate lock state.
36. **Passed — Stale-state behavior.** Rejected metadata/lifecycle updates produce zero mutation.
37. **Passed — Catalogue readiness result.** Typed `CatalogueReadinessResult` with `list<string>` failures.
38. **Passed — Catalogue readiness evaluator.** Pure persisted-state evaluation retained.
39. **Passed — Draft-valid zero-Variant behavior.** Draft is valid; readiness reports missing Variants.
40. **Passed — Ready transition requirements.** Revision, valid combinations, and active default remain required.
41. **Passed — Catalogue-status transition.** Explicit synchronization remains the only promotion path.
42. **Passed — Readiness purity.** No database write or audit call exists in the evaluator.
43. **Passed — Audit evidence.** Slug, option, value, lifecycle, Variant, default, and status event inventory is complete and bounded.
44. **Not applicable — MediaUsage owner registration.** Product Media assignment remains outside BE-5A.1 and was not changed.
45. **Passed — Migration test results.** SQLite and real MySQL lifecycle validation passed.
46. **Passed — SQLite migration validation.** Focused and full isolated suites passed.
47. **Passed — MySQL fresh migration.** MySQL 8.4.7, host `127.0.0.1`, disposable schema `william_taylor_be5a1_validation`.
48. **Passed — MySQL schema-only result.** Six catalogue tables, each with zero rows.
49. **Passed — MySQL rollback correction.** Pointer constraints and reverse table order rolled back successfully.
50. **Passed — MySQL rollback result.** Complete rollback exited successfully.
51. **Passed — MySQL re-migration result.** Re-migration succeeded with six zero-row catalogue tables.
52. **Passed — Registry tests.** Catalogue Unit tests pass.
53. **Passed — Revision tests.** Full regression retains revision and immutability coverage.
54. **Passed — Option/value tests.** Updates, identity rejection, ownership, stale state, lifecycle, and relationship preservation pass.
55. **Passed — Fingerprint tests.** Determinism and state sensitivity pass.
56. **Passed — Variant tests.** Create/update/archive/restore/default/base rules pass.
57. **Passed — Readiness tests.** Pure evaluator and synchronization regression pass.
58. **Passed — Archive/restore tests.** Product, Variant, option, and value lifecycle coverage passes.
59. **Passed — Catalogue-focused test totals.** SQLite 22 tests/82 assertions; MySQL 22 tests/82 assertions; zero skips/warnings.
60. **Passed — Public Page regression.** 7 tests/59 assertions.
61. **Passed — Public Site Content regression.** PublicProjection 9/36 and SiteContent 16/101.
62. **Passed — Factory regression.** 10 tests/133 assertions, including checksum-backed manifests.
63. **Passed — Content regression.** 26 tests/131 assertions.
64. **Passed — Publishing regression.** 13 tests/47 assertions.
65. **Passed — Media regression.** 18 tests/77 assertions.
66. **Passed — Admin regression.** 25 tests/198 assertions.
67. **Passed — Identity regression.** 16 tests/131 assertions.
68. **Passed — Final full-suite result.** `php artisan test`: 219 tests/1,425 assertions in 128.301 seconds.
69. **Passed — Larastan.** Catalogue scope 0 findings; full configured analysis 0 findings; no ignores/baseline changes.
70. **Passed — Pint and syntax.** Initial Pint 1.29.3 baseline was 26 files. Sixteen safe files were mechanically formatted; ten immutable Factory files are narrowly excluded and pass the 10/10 byte/hash gate. `vendor\bin\pint --test` exits 0 with zero failures; all formatted PHP files pass syntax.
71. **Passed — Route comparison.** 40 routes; five explicit static Product routes; no wildcard/dynamic Product route.
72. **Passed — Scheduler inventory.** Two existing publishing schedules only.
73. **Passed — Composer validation and audit.** Valid with existing exact `symfony/html-sanitizer` constraint warning; zero advisories.
74. **Passed — Blade compilation.** `php artisan view:cache` succeeded.
75. **Passed — Vite build.** Production build succeeded; optional Fontaine/plugin timing warnings documented.
76. **Passed — npm audit.** Zero vulnerabilities.
77. **Passed — Homepage fidelity.** Existing 11-size harness exited successfully.
78. **Passed — About fidelity.** 12 captures, zero failures, three viewport comparisons with zero geometry/pixel difference.
79. **Passed — Protected checksums.** Manifest verification reports 56/56 and `public/website` has no diff.
80. **Passed — Factory v1 preservation.** No status change; checksum-backed Factory regression passed.
81. **Passed — Factory v2 preservation.** No status change; protected files were not formatted or rewritten.
82. **Passed — Dependency comparison.** `composer.json` changed only to wire `protected:check` into `ci:check`; Composer/npm lock files and package versions are unchanged.
83. **Passed — Credential and provider-secret scans.** Zero candidate files; values were never printed.
84. **Passed — Domain-boundary scans.** Zero prohibited canonical catalogue field matches; no generator/API/public resolver.
85. **Passed — Encoding and whitespace.** Zero BOM, replacement, or mojibake findings; `git diff --check` passed.
86. **Passed — `willy` baseline and guard.** Begin/end harness: 401408 bytes, SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
87. **Passed — Known limitations.** The former 26-file Pint blocker is resolved through targeted formatting plus the documented two-gate protected-source contract; no mandatory gate remains open.
88. **Passed — Five draft Products remain representable with zero Variants.** Confirmed by model and readiness rules.
89. **Passed — No observed SKU assigned.** Confirmed.
90. **Passed — No Product factory data created.** MySQL catalogue tables end at zero rows; no Factory catalogue records exist.
91. **Passed — Public Product routes remain static.** Five explicit routes only.
92. **Passed — Pricing, inventory, collections, and commerce not started.** Canonical catalogue scan is clean.
93. **Passed — Recommendation on closure.** BE-5A.1 may close: repository Pint and protected preservation both exit successfully and every mandatory phase gate passes.
94. **Passed — BE-5A.2 and BE-5B remain unstarted.** Confirmed.

## Exact validation commands

Key commands included `php artisan test`, `vendor\bin\phpstan analyse`, `vendor\bin\pint --test`, `php artisan migrate:fresh --force`, `php artisan migrate:rollback --force`, `php artisan migrate --force`, MySQL-focused PHPUnit, `npm.cmd run fidelity:homepage`, `node scripts/evidence/be4hb-about.mjs`, `php artisan view:cache`, `npm.cmd run build`, `composer validate`, `composer audit`, `npm.cmd audit`, route/scheduler inventories, protected checksum verification, and the `willy-preservation.ps1` begin/end guard.

## Pint baseline classification

| File | Category | Resolution |
|---|---|---|
| `app/Console/Commands/InstallFactoryBaselineCommand.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Console/Commands/ResetFactoryBaselineCommand.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Console/Commands/SynchronizeFactoryMediaCommand.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Domain/Content/Support/TemplateRegistry.php` | B — user-owned active work | Line-ending-only Pint formatting retained. |
| `app/Domain/Factory/Services/FactoryManifest.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Domain/Media/Actions/ArchiveMediaAsset.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Domain/Media/Actions/ReplaceMediaAsset.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Domain/Media/Actions/RestoreMediaAsset.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `app/Domain/Publishing/Services/PagePublishingWorkflow.php` | B — user-owned active work | Mechanical Pint formatting retained. |
| `config/public_page_projection.php` | A — safe first-party source | EOF formatting retained. |
| `routes/web.php` | B — user-owned active work | Line-ending-only Pint formatting retained. |
| `scripts/evidence/be4h0-fixture.php` | A — safe first-party validation source | Mechanical Pint formatting retained. |
| `scripts/evidence/be4hb2-fixture.php` | A — safe first-party validation source | Mechanical Pint formatting retained. |
| `scripts/fidelity/static-router.php` | A — safe first-party validation source | Line-ending-only Pint formatting retained. |
| `tests/Feature/AccountFrontendPagesTest.php` | A — safe first-party test | Line-ending-only Pint formatting retained. |
| `tests/Feature/ProductDetailFrontendPageTest.php` | A — safe first-party test | Mechanical Pint formatting retained. |
| `database/factory/william-taylor-factory-v2/about-page.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-factory-v2/manifest.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/announcements.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/footer-navigation.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/manifest.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/media.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/primary-navigation.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/roles.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/site-profile.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |
| `database/factory/william-taylor-v1/users.php` | C/E — protected immutable, incorrect Pint scope | Excluded by `database/factory`; SHA-256 and size enforced. |

No Category D generated or third-party files were present in the 26-file baseline. The final commands are `vendor\bin\pint --test` and `composer protected:check`; both exit 0. `composer ci:check` runs the protected gate followed by Pint, Larastan, and the complete Laravel suite.