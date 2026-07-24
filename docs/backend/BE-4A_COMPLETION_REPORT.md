# BE-4A Completion Report

Date: 2026-07-23
Status: Superseded for registry details by BE-4A.1 closeout; foundation implementation retained
Scope: Minimal Identity, RBAC, and Append-Only Audit Foundation only

## Outcome

BE-4A integrated persistent RBAC with the existing Fortify `User` and `web` guard, established project-owned permission and role registries, created the initial Super Administrator and CMS Manager bundles without assigning them to anyone, added controlled transactional assignment/revocation actions, protected the final Super Administrator, and added project-owned append-only audit evidence for material RBAC events.

No administration shell, `/admin` route, management screen, CMS, media, SEO, catalogue, commerce, localization or AI capability was started.

## Package gate

`spatie/laravel-permission` 8.3.0 is installed under the reviewed `^8.3` constraint. It declares PHP 8.3 and Illuminate 12/13 support and uses the MIT licence. Teams, wildcard permissions, Passport integration and verbose permission/role exception details remain disabled. The package owns only its five RBAC tables; application registries, policy, actions, safeguards and audit semantics remain project-owned. Full evidence and exit strategy are in `BE-4A_PACKAGE_GATE.md`.

## Identity and authorization implementation

- Existing `App\Models\User` remains the only authenticatable/admin-capable user model and retains integer identity.
- The existing `web` guard remains the only guard.
- `PermissionRegistry` now defines exactly nine authorized foundation permissions after BE-4A.1 corrective alignment.
- `RoleRegistry` defines Super Administrator and CMS Manager bundles.
- `PermissionRoleSeeder` provisions/reconciles the registry idempotently but never assigns a user.
- `UserPolicy` authorizes role assignment and revocation on the server.
- The Super Administrator permission bypass applies only to registered permissions and writes a monitored operational notice.
- `ControlledRoleMutation` rejects direct package assignment, revocation and synchronization calls; project actions open the mutation boundary narrowly.
- `AssignRoleToUser` and `RemoveRoleFromUser` lock the target, authorize, mutate, clear permission cache and audit inside one transaction.
- Removing or deleting the final Super Administrator throws `FinalSuperAdministratorException` even after bypass authorization.
- `rbac:audit` detects permission, role and bundle drift.

## Safe bootstrap

`rbac:bootstrap-super-admin` accepts only an existing verified Fortify user, asks for confirmation unless `--force` is explicitly supplied, fails after the first Super Administrator exists, creates no user, consumes no credential, and writes an append-only audit event. No email or administrator identity is configured automatically.

## Audit foundation

`audit_records` uses a ULID primary key and UTC microsecond creation timestamp. The envelope stores actor ID, effective role/permission snapshots, action, resource, proportionate before/after summaries, permission, reason, request/session/IP/user-agent context where applicable, console job identity and correlation ULID. It has no update timestamp or ordinary deletion path; model update/delete attempts throw. Role mutation and audit insertion share a transaction.

The actor ID is intentionally an immutable scalar rather than a nulling foreign key, so later user deletion cannot alter historical evidence. Database-account-level append-only enforcement and legal retention/export remain documented production confirmations. No purge, export or audit viewer exists.

## Schema

Created the package RBAC migration for `roles`, `permissions`, `model_has_roles`, `model_has_permissions` and `role_has_permissions`, plus the project `audit_records` migration. Existing user/passkey/authentication schemas were not replaced. MySQL 8 remains the approved production target; the current automated suite validates SQLite equivalence only, and main CI MySQL evidence remains a pre-launch requirement.

## Focused test evidence

`php artisan test tests/Feature/Identity` passed: 13 tests, 40 assertions. Coverage includes:

- exact registry/bundle provisioning and zero automatic assignments;
- idempotent provisioning and drift detection;
- authorized and denied assignment;
- append-only audit envelope and ULID;
- final-admin removal and deletion rejection;
- safe removal when another Super Administrator remains;
- direct package mutation rejection;
- existing/verified-only bootstrap, unknown-user refusal and second-bootstrap refusal.

## Full validation evidence

| Gate | Result |
|---|---|
| Scoped Pint | Passed |
| Isolated migration + seed + `rbac:audit` | Passed; all 7 migrations and registry reconciliation |
| Larastan | Passed, 0 errors |
| Full Laravel tests | Passed, 70 tests / 470 assertions |
| Blade compilation | Passed |
| Vite production build | Passed; existing optional Fontaine notice only |
| Composer audit | Passed, no advisories |
| npm audit | Passed, 0 vulnerabilities |
| Composer validation | Passed |
| Protected-template checksums | Passed, 56/56 |
| Application routes | Stable, 15 routes; no `/admin` route |
| Git whitespace/error check | Passed; existing CRLF normalization warnings only |
| Credential scan | No credential addition detected |

The repository-wide `composer run lint:check` still reports formatting differences in three pre-existing out-of-scope files: `scripts/fidelity/static-router.php`, `tests/Feature/AccountFrontendPagesTest.php`, and `tests/Feature/ProductDetailFrontendPageTest.php`. BE-4A did not modify those files; every BE-4A PHP file passes the scoped Pint gate. A Composer post-update hook also attempted `boost:update` and reported that Boost has not been installed/configured; Composer package discovery, lock writing, validation and security audit completed successfully.

## Files introduced or changed by BE-4A

- Composer manifest/lock for the RBAC package.
- `config/permission.php` and the package RBAC migration.
- Identity registries, controlled mutation boundary, actions, exceptions and policy.
- Audit recorder/model and audit migration.
- Bootstrap and RBAC audit commands.
- User RBAC trait/safeguards and provider policy/bypass registration.
- Permission role seeder and DatabaseSeeder registration.
- Focused identity tests.
- Package gate, permission matrix, operations runbook and this report.

The previously requested `document-head.blade.php` hyphen correction and ARCH-3B approval documents were already present in the working tree before BE-4A and were preserved; BE-4A made no frontend/template change.

## Deferred confirmations and assumptions

- Audit retention remains a proposed seven-year direction pending Tanzanian legal confirmation; no destructive retention job was added.
- Production MySQL/Redis topology, distinct database credentials for stronger append-only controls and centralized bypass log monitoring remain infrastructure/operations confirmations.
- The first Super Administrator identity must be provided and independently authorized by the owner at deployment; none was assumed.
- CMS and `pages.*` permissions remain deferred; no CMS behavior, route or UI exists.

## Required completion confirmations

- Only the authorized identity, RBAC and audit foundation was implemented.
- No administration shell, navigation or access-management screen was started.
- No unrelated domain was introduced.
- No user is automatically granted administrative access.
- No credential was committed.
- Existing Fortify authentication remains stable under the full test suite.
- Existing public routes remain unchanged.
- Protected-template checksums remain 56/56.
- BE-4B was not started.

## Recommendation

BE-4A is complete. BE-4B, the permission-aware administration shell/navigation, may be considered only under a new separate authorization. Before deployment, choose the existing verified owner account, run migrations/seeding in the intended environment, execute `rbac:audit`, and perform the controlled bootstrap from the runbook.
## BE-4A.1 corrective closeout

The original BE-4A implementation incorrectly introduced pages.*, oles.assign, and oles.revoke instead of the authorized nine-permission foundation. BE-4A.1 corrects the registry and role bundles through a preview-first audited alignment, uses oles.manage for both mutation methods, and adds dmin.access for the future shell boundary. See BE-4A-1_PERMISSION_ALIGNMENT_REPORT.md for current evidence. No CMS permission remains.
