# ADR-014 - Cache, Queue and Session Infrastructure

## Status

Approved

## Date

2026-07-23

## Context

Defaults are database-backed. Publishing/media/commerce require idempotent async work; production traffic may justify Redis.

## Decision drivers

Post-commit correctness, visible failures, early simplicity, shared production state and operational readiness.

## Alternatives considered

Database-only may contend at scale; Redis everywhere is premature. Database early with production Redis readiness gate is selected.

## Decision

Use database queues early. Local may use database cache/session/queue, with sync only in explicitly isolated tests. CI uses MySQL job integration and array stores only for deliberate cache isolation. Staging mirrors target production. Before high-traffic launch use managed monitored Redis for cache, sessions, rate limits and queues only after persistence/failover/runbooks pass; otherwise launch is blocked or database capacity is explicitly approved. Version/tag cache by published resource, invalidate after commit, dispatch external effects after commit and make scheduled/media jobs idempotent with visible retryable failures.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Private authenticated stores, secure HTTP-only SameSite cookies, minimized/encrypted sensitive payloads and shared production rate limits are mandatory.

## Operational implications

Supervise workers, priorities, timeouts, backoff and failure alerts. Run one overlap-protected scheduler. Redis is never business truth.

## Testing implications

Test after-commit, idempotency/retry, failure visibility, cache staleness, session continuity, rate limits and worker/scheduler behavior.

## Migration or rollback implications

Configuration enables driver cutover after queue drain/serializer checks. Rollback accounts for sessions and duplicate payload risk.

## Conditions for reconsideration

Reconsider Redis timing from load and operational evidence; another queue service needs an ADR.

## Affected phases

Begins lightly in Phase 2; essential in media/publishing/hardening.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
