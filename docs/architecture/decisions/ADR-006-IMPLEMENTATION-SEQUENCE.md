# ADR-006 - Access Foundation Versus Admin Shell Sequence

## Status

Approved

## Date

2026-07-23

## Context

ARCH-3A placed a permission-aware shell before RBAC, creating circular dependency and risk of showing admin placeholders to customers.

## Decision drivers

No broad temporary gate, real permission-aware navigation and a small testable first increment.

## Alternatives considered

Option A, minimal identity/RBAC/audit before shell, is selected. Option B temporary Super Administrator gating is rejected due transitional security/removal risk.

## Decision

Sequence: ARCH-3B approval; Phase 2 minimal identity, RBAC and audit foundation; Phase 3 admin shell/navigation; Phase 4 full user/role/access screens. Phase 2 may install/configure authorization, register/seed defaults, enforce policies and add minimal explicit audit storage, but no broad management UI. The first shell reads real effective permissions.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Ordinary authenticated customers never receive a temporary admin gate. Phase 2 still requires escalation and final-admin protections.

## Operational implications

Bootstrap the first Super Administrator through a controlled documented procedure. Phase 3 stays blocked until Phase 2 gates pass.

## Testing implications

Phase 2 policy/cache/audit tests precede shell tests. Phase 3 asserts every item and route is hidden and denied without permission.

## Migration or rollback implications

If Phase 2 cannot deploy safely, Phase 3 remains blocked. Rollback returns to the customer-only authenticated application.

## Conditions for reconsideration

Reconsider only if an external identity platform replaces local RBAC before implementation; authorization must still precede the shell.

## Affected phases

Corrects the roadmap immediately; first implementation is Phase 2.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
