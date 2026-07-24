# ADR-005 - Authorization and RBAC

## Status

Approved

## Date

2026-07-23

## Context

Fortify authentication exists, but roles, permissions and policies do not. Administration and sensitive actions require persistent, explainable access.

## Decision drivers

Mature primitives, explicit server checks, auditable effective access, safe administration and Laravel 13 compatibility.

## Alternatives considered

spatie/laravel-permission is selected subject to package gate. Custom RBAC duplicates mature persistence/cache integration; gates alone lack durable role administration.

## Decision

Adopt a current compatible package version during Phase 2. Use the web guard, code-owned resource.action permission registry, roles as bundles and policies for resource state/ownership. Seed Super Administrator and CMS Manager idempotently. CMS Manager reviews/publishes ordinary content but lacks sensitive finance/access powers. A monitored Super Administrator Gate bypass may apply except explicit final-admin/self-lockout safeguards. Direct grants are rare, auditable and preferably time-bounded. Sensitive changes require password/2FA confirmation. Assignment/registry changes invalidate cache.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Prevent final Super Administrator removal, self-lockout, privilege escalation and mass assignment. Audit before/after effective access and test hidden UI plus direct requests.

## Operational implications

The registry is source-controlled and seeding reports drift. Later screens explain effective permissions. Package governance follows ADR-021.

## Testing implications

Policy matrix covers allow/deny, stale cache, direct grants, ownership/state, self-demotion, final-admin removal and all routes/actions.

## Migration or rollback implications

Removal requires exporting/mapping package data to the project registry contract. Permission bundle rollback preserves audit history.

## Conditions for reconsideration

Reconsider on compatibility, security, abandonment or tenant-isolation failure; explicit policies remain mandatory.

## Affected phases

First affects Phase 2; full access screens are Phase 4.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
