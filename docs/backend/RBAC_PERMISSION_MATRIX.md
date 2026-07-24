# BE-4A.1 RBAC Permission Matrix

Date: 2026-07-23
Status: Corrected foundation
Guard: `web`

`PermissionRegistry` is the canonical foundation list. It contains exactly nine permissions; later domains add their permissions only in separately authorized phases.

| Permission | Super Administrator | CMS Manager | Foundation purpose |
|---|---:|---:|---|
| `admin.access` | Yes | Yes | Future permission-aware shell boundary; no `/admin` route exists yet |
| `users.view` | Yes | No | Future access-management read boundary |
| `users.manage` | Yes | No | Future controlled user administration |
| `roles.view` | Yes | No | Future role/effective-access read boundary |
| `roles.manage` | Yes | No | Authorizes both current assignment and revocation actions |
| `audit.view` | Yes | Yes | Future audit viewer boundary; no viewer exists |
| `audit.export` | Yes | No | Future restricted export boundary; no export exists |
| `settings.view` | Yes | Yes | Future read-only settings boundary |
| `settings.manage` | Yes | No | Future sensitive settings mutation boundary |

## Explicit absence

`roles.assign`, `roles.revoke`, and every `pages.*` permission are absent. CMS, media, SEO, catalogue, pricing, inventory, commerce, localization and AI permissions remain deferred to their own implementation phases.

## Invariants

- No migration, seeder, registration or authentication flow assigns a role.
- CMS Manager has exactly `admin.access`, `audit.view`, and `settings.view`.
- Super Administrator passes registered permission checks through the monitored bypass but cannot bypass final-admin safeguards.
- `roles.manage` is checked by separate `assignRole` and `revokeRole` policy methods.
- Direct package role mutation remains blocked.
- A foundation alignment preview must precede explicit application on an existing BE-4A database.
## BE-4C operational use

Users and Roles inspection now use their respective `*.view` permissions. Assignment and revocation require both `users.manage` and `roles.manage`; neither permission is sufficient alone. The registry and bundles are unchanged.

## BE-4D media permissions

BE-4D adds exactly media.view, media.upload, media.edit, media.replace, media.archive and media.restore. CMS Manager receives all six; Super Administrator receives registered access through the monitored bypass. No media.delete permission exists.

## BE-4E draft-page permissions

BE-4E adds exactly `pages.view`, `pages.create`, `pages.edit`, `pages.preview`, `pages.archive`, and `pages.restore`. CMS Manager receives all six; Super Administrator receives registered access through the monitored bypass. `pages.review`, `pages.approve`, `pages.publish`, `pages.schedule`, `pages.unpublish`, and `pages.delete` do not exist.