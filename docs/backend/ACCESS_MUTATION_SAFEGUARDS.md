# Access Mutation Safeguards

## Preview integrity

Role confirmation is bound to a deterministic server-generated fingerprint. Apply reloads the target and recomputes the preview from current roles, direct permissions, actual persisted role bundles, the permission registry, and the role registry.

If current state differs from confirmed state, the existing role actions are not invoked, no role-mutation audit event is written, the current preview replaces the stale preview, and confirmation resets. Operation, selected role, and reason remain for review.

This check does not replace action-owned transactions, locks, authorization, final-administrator protection, self-lockout protection, cache reset, or append-only audit insertion.

## Workflow

1. Select assign or revoke.
2. Select one registered role.
3. Provide a non-empty reason, maximum 2,000 characters.
4. Generate a server-side preview.
5. Review current/proposed roles, gained/lost permissions, administration change, duplicate grants, and elevated Super Administrator warning.
6. Explicitly confirm.
7. Reauthorize both `users.manage` and `roles.manage`.
8. Reload and recompute current target access.
9. Invoke `AssignRoleToUser` or `RemoveRoleFromUser`.
10. Show success only after role and existing audit event commit.

The application actions reject empty reasons even outside the UI. Invalid or arbitrary roles continue to fail through the code-owned registry.

## Final administrator

The existing locked transaction prevents revocation of the final Super Administrator. The UI catches the domain exception and preserves the entered proposal with a safe message.

## Self-management

BE-4C applies a conservative rule: an actor may revoke their own role only when another remaining registered role or an existing direct `admin.access` grant still provides administration entry. The presence of another administrator does not make intentional self-lockout safe. This does not weaken the final-Super-Administrator rule.

## Audit

Existing `identity.role.assigned` and `identity.role.revoked` events are written once by the existing actions. An audit insertion failure rolls back the role change. The UI adds no duplicate audit event.
