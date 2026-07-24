# Role Inspection Workspace

The Roles index lists exactly Super Administrator and CMS Manager from `RoleRegistry`. It shows the code-owned description, risk label, bundle size, assigned-user count, bypass behavior, and a detail link.

Role detail groups the registered permission bundle with `PermissionMetadata`, explains bypass and final-administrator behavior, provides a filtered Users link, and places raw names in developer details.

Database state is compared with the registered bundle. Drift displays a prominent warning instructing operators to run `php artisan rbac:audit`. Web requests never synchronize or correct the registry.

There are no create, rename, edit, delete, clone, permission-composition, direct-grant, Save, or browser-run audit controls.
