# BE-4C Access Management

Date: 2026-07-24

BE-4C replaces only the Users and Roles placeholders with access-administration workflows. Audit and Settings remain placeholders.

## Routes

| Route | Permission |
| --- | --- |
| `admin.access.users.index` | `admin.access` and `users.view` |
| `admin.access.users.show` | `admin.access` and `users.view` |
| `admin.access.roles.index` | `admin.access` and `roles.view` |
| `admin.access.roles.show` | `admin.access` and `roles.view` |

Role changes are framework-managed Livewire actions. There are no project POST, PUT, PATCH, DELETE, API, user-creation, user-editing, role-registry, permission, audit-viewer, or settings-editing routes.

Assignment and revocation require both `users.manage` and `roles.manage`. The existing `AssignRoleToUser` and `RemoveRoleFromUser` actions remain authoritative.
