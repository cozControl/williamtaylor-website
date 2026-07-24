# ADR-007 - Audit Architecture

## Status

Approved

## Date

2026-07-23

## Context

Publishing, access, media and commerce need durable business evidence. Generic model events omit intent, permission, reason and multi-record context; revision history serves another purpose.

## Decision drivers

Business meaning, append-only integrity, privacy proportionality, correlation and independence from Eloquent details.

## Alternatives considered

Generic model-event logging is incomplete/noisy; a package is rejected as primary truth due schema semantics; combining revisions with audit is rejected. Project-owned explicit audit is selected.

## Decision

Build an append-only audit domain. Explicit actions record actor, effective role/permission, action, resource type/ULID, proportionate before/after summary, revision, request/session, proportionate IP/user agent, reason, timestamp, job identity and correlation/request IDs. Revisions remain separate. Ordinary code cannot update/delete audit rows. Sensitive reads/exports are audited; secrets, passwords, tokens and full payment data are never logged.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Restrict audit.view/export, use database append controls where feasible, monitor integrity and redact/minimize personal fields.

## Operational implications

Write audit atomically with the action or durable outbox. Retention is legally pending under ADR-020. Archives preserve access restrictions.

## Testing implications

Test envelope completeness, atomicity, immutability, redaction, job actors, correlation, sensitive reads, export authorization and fail-closed high-risk actions.

## Migration or rollback implications

Additive rollout. Rollback does not delete existing audit; disabling required audit blocks affected high-risk mutations.

## Conditions for reconsideration

Reconsider storage for volume, tamper-evident external retention, SIEM or legal needs while preserving the project event contract.

## Affected phases

First affects Phase 2 and expands with every domain.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
