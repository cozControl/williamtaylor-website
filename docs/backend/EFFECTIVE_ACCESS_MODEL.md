# Effective Access Model

`EffectiveUserAccessQuery` returns typed `EffectiveUserAccess` and `EffectiveAccessPreview` results.

Effective access is derived from actual loaded role/direct-permission relationships, limited to `PermissionRegistry`, plus the registered Super Administrator bypass. Sources identify registered roles, the monitored bypass, and direct-grant warnings. More than one source is reported as a duplicate grant.

`PermissionMetadata` supplies business label, area, and description. Slugs are not transformed into primary labels.

Warnings surface:

- Unknown assigned roles.
- Unknown role permissions.
- Unknown direct permissions.
- Direct grants of registered permissions.
- Database role bundles that differ from `RoleRegistry`.

Warnings are display-only. The query never authorizes, mutates, audits, or normalizes access.

Preview begins with actual permissions on proposed registered roles, adds existing registered direct permissions, and adds Super Administrator bypass sources when applicable. Unknown permissions remain warnings only. Duplicate grants are calculated from every unique role, direct, and bypass source.

The typed preview contains current and proposed roles, effective permissions, permission sources, sorted gains and losses, duplicate grants, administration state, Super Administrator state, warnings, and a server-generated SHA-256 fingerprint.

The fingerprint covers normalized security state plus deterministic registry and role-bundle checksums. It contains no email, reason, credential, or authentication token.

Execution reloads the target, reauthorizes the actor, and recomputes proposed access. A fingerprint mismatch replaces the displayed preview, clears confirmation, and prevents both mutation and role-mutation audit insertion. Only a matching, newly confirmed preview can invoke the approved action.
