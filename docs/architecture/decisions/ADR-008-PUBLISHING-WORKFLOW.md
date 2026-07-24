# ADR-008 - Publishing Workflow

## Status

Approved

## Date

2026-07-23

## Context

Managed content needs review, scheduling, rollback and emergency removal without editing the live version in place.

## Decision drivers

Immutable evidence, controlled self-approval, sensitive-resource separation, reliable schedules and reversibility.

## Alternatives considered

A direct live flag lacks governance. Universal two-person approval blocks ordinary editorial work. A resource-sensitive state machine is selected.

## Decision

Use Draft -> In review -> Changes requested -> Approved -> Scheduled or Published -> Unpublished -> Archived. Published revisions are immutable; edit and rollback create drafts. Schedules pin an approved revision. Every transition is authorized/audited. Preview is signed, short-lived, noindex, visibly marked and excluded from public cache. CMS Managers may self-review/publish ordinary editorial content; Content Editors cannot publish. Policies, pricing, promotional claims, gift-card financial rules, global settings and major redirects require a distinct approver unless a monitored Super Administrator acts. Sensitivity is resource/action data. Emergency unpublish requires reason and elevated permission.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Reauthorize scheduled execution, prevent preview leakage and invalidate approval if the referenced draft changes.

## Operational implications

Use UTC, locking/idempotent schedulers and post-commit cache invalidation. Queues expose expiry/readiness failures.

## Testing implications

Test all transitions, policy/self/distinct approval, revision immutability, stale approval, races/idempotency, preview noindex/leakage and emergency unpublish.

## Migration or rollback implications

Static pages remain code-owned until mapped to an initial published revision with reversible rendering switch and fidelity evidence.

## Conditions for reconsideration

Reconsider ordinary self-approval after incidents, staffing or regulation; sensitive rules may become stricter.

## Affected phases

Policy begins Phase 2; schema/workflow arrives in CMS/publishing phases.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
