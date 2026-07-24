# BE-4A.1 Permission Alignment Report

Date: 2026-07-23
Status: Corrective implementation complete; validation evidence below

## 1. Cause

BE-4A implemented a plausible future CMS Manager bundle from ADR examples rather than the narrower registry explicitly authorized by the BE-4A implementation brief. That introduced CMS-domain `pages.*` permissions early and split role mutation into `roles.assign`/`roles.revoke`. BE-4A.1 treats this as a material foundation mismatch and corrects it without changing authentication, persistence, controlled mutation, audit or final-admin architecture.

## 2. Previous registry

`users.view`, `roles.view`, `roles.assign`, `roles.revoke`, `audit.view`, `audit.export`, `pages.view`, `pages.create`, `pages.edit`, `pages.review`, `pages.approve`, `pages.publish`, `pages.unpublish`.

## 3. Final registry

Exactly: `admin.access`, `users.view`, `users.manage`, `roles.view`, `roles.manage`, `audit.view`, `audit.export`, `settings.view`, `settings.manage` on the existing `web` guard.

## 4. Previous CMS Manager bundle

`pages.view`, `pages.create`, `pages.edit`, `pages.review`, `pages.approve`, `pages.publish`, `pages.unpublish`.

## 5. Final CMS Manager bundle

Exactly: `admin.access`, `audit.view`, `settings.view`. It cannot manage users, roles or settings and cannot export audit data.

## 6. Assignment-impact inspection

The configured local database could not be inspected because `.env` names a missing SQLite path (`willy`); no production/staging store is available in this workspace. No claim is made about external assignments. A deterministic reconstruction of the previous seeded state found obsolete permissions only on the expected Super Administrator and CMS Manager bundles, no direct assignment, no unexpected role and no CMS Manager user. Separate fixtures prove the assigned-CMS-Manager path preserves role membership and audits before/after effective permissions, while any direct obsolete permission assignment aborts before mutation.

Every real deployment must run the read-only preview and review its safe model/role identifiers before `--apply`. This is an operational prerequisite, not silently delegated to the normal seeder.

## 7. Alignment command

`php artisan rbac:align-foundation-registry` previews sorted additions/removals, role-bundle changes, CMS Manager user IDs, direct assignments and unexpected roles. `--apply` is explicit; production also requires `--force`. It never creates a user or assigns a role. After application it invokes `rbac:audit`.

## 8. Transaction and rollback

The apply path locks RBAC records, re-inspects impact, aborts unexpected assignments, adds approved permissions, synchronizes only the two registered bundles, deletes obsolete permission records, audits affected users and registry correction, and clears package cache after commit in one retryable transaction. Audit failure rolls back the data change. An aligned rerun makes no change or duplicate audit. The normal seeder refuses legacy/unregistered permissions so it cannot hide this correction.

## 9. Policy alignment

`UserPolicy::assignRole` and `UserPolicy::revokeRole` remain separate. Both require `roles.manage`. Assignment/revocation transactions, direct package mutation blocking and final-Super-Administrator protection are unchanged.

## 10. Audit evidence

Successful correction records `identity.permission-registry.aligned` with removed permissions, role-bundle differences, final registry and final bundles. Each affected CMS Manager user records `identity.role-bundle.aligned` with before/after effective permission names. Preview and idempotent no-op runs create no audit event.

## 11-13. Focused coverage

Tests assert the exact nine registry entries; presence of `admin.access`, `users.manage`, `roles.manage`, `settings.view`, and `settings.manage`; absence of old mutation and all `pages.*` permissions; exact role bundles; Super Administrator checks; customer/guest denial; `roles.manage` assignment/revocation; final-admin and direct-mutation guards; preview no mutation; apply changes/audit/cache reset; direct-assignment abort; assigned-CMS-Manager audit; and idempotent rerun.

## 14-24. Validation results

| Gate | Result |
|---|---|
| Focused identity tests | Passed, 16 tests / 85 assertions |
| Full Laravel suite | Passed, 73 tests / 515 assertions |
| Larastan | Passed, 0 errors |
| Scoped Pint | Passed for all BE-4A/BE-4A.1 PHP files |
| PHP syntax | Passed for all BE-4A/BE-4A.1 PHP files |
| Composer validation | Passed; manifest valid |
| Composer audit | Passed, no advisories |
| Blade compilation | Passed |
| Vite build | Passed; existing optional Fontaine notice only |
| npm audit | Passed, 0 vulnerabilities |
| Routes | Stable, 15 application routes; no `/admin` or CMS route |
| Protected-template checksums | Passed, 56/56 |
| Git whitespace | Passed; existing CRLF normalization warnings only |

## 25-29. Scope confirmation and recommendation

No administration route, shell, layout, navigation or UI was introduced. `admin.access` is a permission-only readiness boundary for BE-4B. No CMS behavior or CMS permission remains; `pages.*` is deferred until CMS implementation. No media, SEO, catalogue, pricing, inventory, commerce, localization, Cloudinary, Pesapal, API or AI behavior was introduced. No dependency or migration was added by BE-4A.1. BE-4B was not started.

BE-4A may finally close. The exact registry, role bundles, alignment safety, authentication, route, build, dependency and protected-template gates pass. BE-4B remains unstarted and requires separate authorization.