# BE-4B Administration Shell and Navigation

Date: 2026-07-24

## Scope

BE-4B introduces a protected, responsive administration shell and read-only destination placeholders. It does not introduce management interfaces, domain queries, mutation controls, CMS, media, catalogue, commerce, APIs, or additional dependencies.

The shell is independent from the migrated public frontend. It uses `resources/css/admin.css` and `resources/js/admin.js`; protected template assets and public Blade views are not imported or changed.

## Routes

All routes use the existing `web` guard and the shared `auth`, `verified`, and `can:admin.access` boundary:

| Route name | Path | Additional permission |
| --- | --- | --- |
| `admin.dashboard` | `/admin` | `admin.access` |
| `admin.access.users.index` | `/admin/access/users` | `users.view` |
| `admin.access.roles.index` | `/admin/access/roles` | `roles.view` |
| `admin.audit.index` | `/admin/audit` | `audit.view` |
| `admin.settings.index` | `/admin/settings` | `settings.view` |

The nested routes render purpose-only placeholders. They do not read domain records and expose no CRUD controls.

## UI behavior

Desktop uses a persistent sidebar. Smaller screens use a native modal dialog drawer. The current route has `aria-current="page"`. Escape closes the native dialog, closing restores focus to the trigger, and reduced-motion preferences suppress transitions.

The header provides page context, a non-secret environment label, account settings links, logout, and a storefront link.
