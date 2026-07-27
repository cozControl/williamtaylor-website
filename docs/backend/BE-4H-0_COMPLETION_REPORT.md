# BE-4H-0 Completion Report

Status: Complete and validated; all authorized BE-4H-0 closeout gates pass.

1. Architecture reviewed: BE-4A through BE-4H-A reports, approved role/permission registries, final-Super-Administrator safeguards, Site Content schemas/workflows, Media contracts/models, public chrome, provider configuration, protected assets, and willy preservation controls were reviewed.
2. Final scope: deterministic identities, roles, global Site Content, storefront Media inventory, preview-first install/sync/reset, evidence, tests, and operations documentation only.
3. Migration boundary: unchanged migrations remain schema-only. Disposable fresh migration produced zero users, roles, Site Content, and Media. DatabaseSeeder no longer creates `test@example.com` and is factory-opt-in.
4. Factory version: `william-taylor-factory-v1` is code-owned and date-independent.
5. Manifest structure: `database/factory/william-taylor-v1` contains manifest, roles, users, primary navigation, footer navigation, announcements, Site profile, and Media files.
6. Files created and changed: Factory domain services, three commands, factory seeder/config/manifests, provider synchronization boundary, focused tests, browser evidence scripts, required runbooks, and bounded existing-document updates were added. Protected storefront assets were untouched.
7. Inventory Manager role: registered with exactly `admin.access` and no invented inventory, catalogue, pricing, warehouse, stock, or procurement permission.
8. Role alignment: the existing preview-first alignment detects the new role, applies transactionally, preserves assignments, clears permission cache, audits changes, and is idempotent.
9. Administrator identity: environment-configured display name Administrator, normalized email, verified email, exactly Super Administrator.
10. CMS Manager identity: environment-configured display name CMS Manager, normalized email, verified email, exactly the existing CMS Manager role.
11. Inventory Manager identity: environment-configured display name Inventory Manager, normalized email, verified email, exactly Inventory Manager.
12. Credential handling: `.env.example` contains empty emails/passwords and `FACTORY_SEED_ENABLED=false`; required apply values fail closed.
13. Password security: Laravel configured hashing is used. Output, previews, audits, browser evidence, and manifests contain no passwords. Reruns preserve hashes; reset requires `--include-password-reset`.
14. Factory identity seeder: normalized-email reuse, missing-user creation, verification, exact controlled role assignment, transactionality, audit evidence, and idempotency are implemented.
15. DatabaseSeeder behavior: does nothing unless `FACTORY_SEED_ENABLED=true`; ordinary migration and default seeding do not create accounts.
16. Current-content inventory: source locations are recorded in each content manifest; factual fallback content was extracted without design or editorial changes.
17. Primary-navigation baseline: five current header destinations are represented with stable keys and typed internal links.
18. Footer-navigation baseline: the three schema-owned groups preserve current Shop, Atelier, and Support labels and links.
19. Announcement baseline: one independent free-shipping/express-delivery announcement is represented with stable key and dismissible state.
20. Site-profile baseline: brand, contact, WhatsApp, address, social links, footer description, copyright, and newsletter copy are represented.
21. Revision creation: fresh install creates immutable revision 1 for four resources; drift reset appends a new immutable revision.
22. Publication transitions: each installed/restored revision records draft/published to in-review, approved, and published transitions before designation.
23. Audit evidence: bounded factory version, checksums, logical keys, roles, creation state, and counts are recorded without secrets or full private contact payloads.
24. Factory Media scope: storefront Media is reserved inventory only; no Product, Collection, catalogue, pricing, or inventory record is created.
25. Image inventory: 41 protected local images have stable logical keys and pinned SHA-256 values.
26. Video inventory: two exact Base44 MP4 URLs are represented as optional deferred entries.
27. Remote-source and rights handling: HTTPS, `media.base44.com` allowlist, maximum bytes/duration, explicit `--include-remote`, and client-supplied licensed rights status are recorded. No unverified transfer occurred.
28. Media manifest: 43 unique entries contain the required identity, source, type, MIME, editorial, accessibility, rights, usage, future-domain, provider-suffix, state, and factory-version fields.
29. Cloudinary folder strategy: provider IDs are rooted at `{environment}/william-taylor/media/factory/william-taylor-v1`; root uploads are rejected.
30. Deterministic provider IDs: factory version, logical key, and resource type/suffix determine the public ID. Overwrite is false.
31. Media-sync preview: passed. After the successful scoped upload, the interim plan reported 40 uploads, one reuse, two deferred, and zero drift.
32. Media-sync apply: passed against real Cloudinary in disposable evidence. All remaining 40 local images and both explicitly authorized Base44 remote videos synchronized successfully.
33. Media-sync idempotency: the final preview reports zero uploads, 43 reuses, zero deferred assets, and zero drift. The disposable database contains 43 unique logical keys, provider asset IDs, public IDs, assets, and versions.
34. Provider verification: authoritative provider facts passed for all 43 required assets. Their public IDs and resource types match, and all remain inside the environment-scoped factory folder prefix. Drift continues to fail closed.
35. Cloudinary smoke test: passed using the trusted project CA bundle at `storage/ssl/cacert.pem`, with TLS verification enabled. The named `site_logo` transformation returned HTTP 200 with image content; replacement produced two immutable versions; archive preserved the provider binary; and restore returned the asset to ready state. No provider deletion was performed.
36. Factory-install preview: passed on disposable SQLite and configured MySQL; no mutation and no password output.
37. Factory-install apply: passed on disposable SQLite, creating three identities and four published resources.
38. Installation idempotency: second apply reused three users and four content resources, created no revision/transition duplicate, and preserved password hashes.
39. Factory-reset preview: content preview passed with no mutation.
40. Reset scopes: content, identities, and all are implemented.
41. Reset confirmation: all requires exact phrase plus backup acknowledgment; password reset is limited to identity/all scope and explicit option.
42. Non-factory preservation: tested non-factory Site Content remains after content reset; default reset contains no non-factory deletion path.
43. Production restriction: reset apply allows only local, testing, and staging; there is no force bypass.
44. Cloudinary binary preservation: reset never invokes provider deletion.
45. Protected storefront preservation: no protected asset or static body was modified; public projection remains false by default.
46. Willy preservation: all mutations used memory/disposable databases; apply refuses a database basename of willy. Final guard passed at 401408 bytes and SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
47. Browser evidence: Chromium 149.0.7827.55 produced 10 screenshots at 1440x900, 768x1024, and 375x812. All identities entered the shell, Administrator destinations were visible, CMS destinations were visible with access-management links absent, and Inventory Manager had only shell access with no redirect loop. Console errors, warnings, failed requests, and failed local assets were zero. Existing admin-shell horizontal overflow at 768/375 is recorded.
48. Factory-focused tests: 10 tests, 133 assertions pass.
49. Public Projection regression: included in the focused 133-test group and passes.
50. Site Content regression: included in the focused group and passes.
51. Publishing regression: included in the focused group and passes.
52. Content regression: included in the focused group and passes.
53. Media regression: included in the focused group and passes.
54. Admin regression: included in the focused group and passes.
55. Identity regression: included in the focused group and passes.
56. Full suite: 190 tests and 1,284 assertions pass.
57. Larastan: full project passes with zero errors.
58. Pint and syntax: scoped Pint passes; PHP and JavaScript syntax checks pass.
59. Composer validation and audit: composer.json is valid with the existing exact-version warning; audit reports no security advisories.
60. Blade compilation: passes.
61. Vite build: passes with only the existing optional Fontaine warning.
62. npm audit: zero vulnerabilities.
63. Public fidelity: homepage harness passes all 11 required widths without threshold changes. The current report ranges from identical to 3.448270% at the previously reviewed 639-pixel breakpoint, with screenshots and diff images retained for every size.
64. Protected checksums: 56/56 match the phase-0 manifest.
65. Dependency comparison: Composer/npm manifests and locks are unchanged by BE-4H-0.
66. Credential, secret and encoding scans: passed with zero committed credential, provider-secret, em dash, replacement-character, or mojibake matches in the BE-4H-0 scope.
67. Git whitespace: `git diff --check` passes; line-ending notices are non-errors.
68. MySQL validation: all existing migrations report Ran; preview-only install, Media sync, and content reset pass against MySQL without mutation. Destructive fresh migration correctly remained limited to disposable SQLite.
69. Known limitations: the two dedicated non-factory smoke binaries remain in the scoped provider folder because provider deletion was expressly prohibited. Provider billing is governed by the Cloudinary account plan and was not inferred locally. Existing mobile/tablet admin-shell horizontal overflow remains outside this bootstrap phase.
70. Recommendation: authorize BE-4H-0 closure. The real provider smoke sequence, complete required synchronization, idempotency preview, and all local validation gates pass.
71. Phase boundary: BE-4H-B and BE-4I were not started. No CMS Page projection, new public route, catalogue, product, pricing, inventory domain, commerce, SEO UI, localization, API, customer-image, AI, or virtual-styling work was introduced.

## Evidence inventory

- Factory browser findings: `storage/app/evidence/be-4h-0/browser-findings.json`
- Factory browser screenshots: 10 PNG files under `storage/app/evidence/be-4h-0`
- Disposable databases: `storage/app/evidence/be-4h-0-validation.sqlite`, `be-4h-0-migration.sqlite`, `be-4h-0-browser.sqlite`, and `be-4h-0-cloudinary.sqlite`
- Real-provider smoke harness: `scripts/evidence/be4h0-cloudinary-smoke.php`
- Fidelity report: `storage/app/fidelity/homepage/reports/comparison.md`
- Protected manifest: `docs/phase-0/template-sha256.txt`
- Manifest checksum: `1b6ee0f82ac5ce2a866b9664e7f4c956a1dcada406332faedb9587fde6a2b757`
- Media manifest checksum: `d8b56c21727e251c6e8499f3f0d33dbd75c4a69982e4325d9267875c32b4e314`
## Cloudinary closeout evidence - 2026-07-25

The real scoped upload for `storefront-7a24ade71-logo-3` succeeded. The subsequent preview reported 40 uploads, one reuse, two explicitly deferred remote videos, and zero drift.

The remaining 40 local images then synchronized successfully. The two approved Base44 videos synchronized only after the explicit remote-source option was supplied. The final preview reported zero uploads, 43 reuses, zero deferred assets, and zero drift. Provider-fact and folder-prefix checks passed for all 43 required assets.

The real provider smoke sequence used the trusted project CA bundle at `storage/ssl/cacert.pem`; TLS verification remained enabled. The `site_logo` named transformation returned HTTP 200, replacement created two immutable provider-backed versions, archive retained the provider binary, and restore returned the logical asset to ready state. No provider binary was deleted. Provider credentials, signatures, and machine-specific secrets are not present in this report.

The focused regression passed 133 tests with 854 assertions, and the complete Laravel suite passed 190 tests with 1,284 assertions. Larastan, Pint, syntax, audits, Blade compilation, Vite build, fidelity, and 56/56 protected checksums passed. BE-4H-B and BE-4I remain unstarted.