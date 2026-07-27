# BE-4A.1 RBAC Bootstrap, Alignment, and Operations Runbook

Date: 2026-07-23

## Fresh deployment

1. Back up and confirm the intended environment/database.
2. Run `php artisan migrate --force`.
3. Run `php artisan db:seed --class=PermissionRoleSeeder --force`. Fresh provisioning creates the exact registry/bundles but no user or assignment.
4. Run `php artisan rbac:audit` and stop on drift.
5. Bootstrap the first administrator only after owner verification.

## Upgrade from the original BE-4A registry

Do not run the normal permission seeder first. It intentionally refuses to hide unregistered legacy permissions.

1. Back up the RBAC and audit tables.
2. Preview only:

```powershell
php artisan rbac:align-foundation-registry
```

3. Review sorted additions, removals, both role-bundle changes, CMS Manager user identifiers, direct assignments and unexpected role assignments.
4. If any unexpected direct assignment or unexpected role is reported, stop and investigate. The command exposes only model type, numeric/safe identifier, role and permission.
5. Apply after review:

```powershell
php artisan rbac:align-foundation-registry --apply --reason="BE-4A.1 authorized registry correction"
```

Production additionally requires `--force`. Application is transactional: it adds approved permissions, updates approved bundles, removes obsolete permissions, audits each affected CMS Manager effective-permission change, records the registry correction, clears permission cache after commit and runs `rbac:audit`. A failed audit insert rolls the alignment back. Re-running an aligned database creates no mutation or duplicate audit event.

The command never creates a user, assigns a role or changes unrelated roles. Existing CMS Manager role membership is retained; only its authorized bundle changes.

## First Super Administrator

The owner must already be an existing verified Fortify user:

```powershell
php artisan rbac:bootstrap-super-admin owner@example.com --reason="Approved owner bootstrap"
```

The command confirms interactively unless `--force` is intentionally supplied, refuses unknown/unverified users and refuses a second bootstrap. Never commit administrator identities or credentials.

## Ongoing mutation and audit

Only `AssignRoleToUser` and `RemoveRoleFromUser` are approved mutation boundaries. Both use separate `UserPolicy` methods backed by `roles.manage`. Direct package role helpers remain rejected. Final-Super-Administrator removal/deletion remains blocked.

`audit_records` remains append-only through the project model. The alignment event is `identity.permission-registry.aligned`; affected CMS Manager users receive `identity.role-bundle.aligned` events with before/after effective permission names. Audit retention/export and database-account append-only enforcement remain later legal/infrastructure confirmations.

## Rollback

- Preview is read-only.
- Any unexpected assignment aborts before mutation.
- Apply uses one transaction; failure restores permissions and bundles automatically.
- After a committed production correction, do not restore obsolete permissions or delete audit evidence ad hoc. A material rollback requires a reviewed corrective action/new ADR as applicable and preserves assignment/audit history.
- Package removal follows the existing export/adapter exit plan.

MySQL 8 remains the production target. The isolated SQLite closeout validates behavior equivalence but does not replace main-CI MySQL evidence before launch.
## BE-4C smoke check

After bootstrap, sign in as the verified Super Administrator and inspect `/admin/access/users` and `/admin/access/roles`. Preview a safe CMS Manager assignment with a reason, confirm it, verify one `identity.role.assigned` audit record, then revoke it with a new reason and verify one `identity.role.revoked` record. Never test by removing the final Super Administrator.

## BE-4D alignment

Run the registry alignment preview before applying the six media permissions to an existing environment. Review effective CMS Manager changes, then apply and audit without removing unrelated direct assignments.

## BE-4E alignment

Before deploying BE-4E to an existing environment, run the foundation registry alignment in preview mode. Confirm the exact six `pages.*` additions and CMS Manager bundle delta, then apply with an approved reason. Application preserves role assignments, runs transactionally and idempotently, and records `content.permission-registry.aligned`. It never creates or promotes a user. Run `php artisan rbac:audit` after application.
## BE-4F alignment

Preview the alignment and confirm the five publishing additions and CMS Manager bundle change. Apply explicitly with `php artisan rbac:align-foundation-registry --apply`, then run `php artisan rbac:audit`. The publishing delta records `content.publishing-permission-registry.aligned`; it does not create users or assign roles.

## BE-4G alignment

Preview the alignment and confirm the single `navigation.manage` addition plus the CMS Manager additions of `navigation.manage` and existing `settings.manage`. Apply explicitly with an approved reason, then run `php artisan rbac:audit`. The delta records `site-content.permission-registry.aligned`; it does not create users, assign roles or project Site Content to the public storefront.

## Factory bootstrap

BE-4H-0 adds Inventory Manager through the same preview-first registry alignment. Factory identity assignment occurs only through explicit factory:install --apply or scoped reset; registry provisioning never assigns a user automatically.
