# BE-4A Package Gate - spatie/laravel-permission

Date: 2026-07-23
Decision: Accepted for BE-4A

| Gate | Evidence |
|---|---|
| Package/version | `spatie/laravel-permission` 8.3.0, released 2026-07-03 |
| Purpose | Persistent roles, permissions, guard integration and cache-aware authorization; native Laravel gates do not provide durable RBAC bundles/pivots |
| Compatibility | Declares PHP `^8.3` and Illuminate Auth/Container/Contracts/Database `^12.0|^13.0`; installed with Laravel 13.17/PHP 8.3 |
| Maintainer/source | Spatie; official GitHub repository referenced by Composer metadata |
| Licence | MIT |
| Security posture | Composer advisory audit required at every phase/release; no advisory accepted silently |
| Schema ownership | Package owns `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, and `role_has_permissions` |
| Configuration | Existing `web` guard; teams, wildcard permissions, Passport and verbose exception details disabled |
| Upgrade policy | Review release notes, compatibility, migrations and focused tests before lock update |
| Project boundary | Permission/role registries, policies, controlled actions, final-admin safeguards and audit semantics remain project-owned |
| Exit strategy | Export package tables, retain permission names/action contracts, migrate assignments to replacement persistence, verify parity, then remove package/schema |
| Replacement complexity | Moderate because pivot data must migrate; contained by project-owned registries and action boundaries |

The package's convenience mutation commands are not an approved operational interface. Project-owned mutation guards reject direct role helpers outside controlled actions.
## BE-4A.1 clarification

The corrective alignment changes only the project registry, bundles, policies, actions, tests and operations command. Package version, constraint, configuration, schema and exit strategy are unchanged. No Composer or npm dependency is added or updated.
