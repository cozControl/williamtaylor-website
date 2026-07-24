# ADR-022 - Environment and Deployment Topology

## Status

Approved

## Date

2026-07-23

## Context

Config supports multiple drivers but hosting/services are not provisioned. Production must not share credentials or data with development.

## Decision drivers

Parity, inexpensive local work, isolation, recovery, observable workers/scheduler and safe debug handling.

## Alternatives considered

Shared environments are unsafe; identical local infrastructure is costly. Four isolated environments with production-like CI/staging gates are selected.

## Decision

Target topology:

| Concern | Local | CI | Staging | Production |
|---|---|---|---|---|
| DB | SQLite light; MySQL available | MySQL 8 main | isolated MySQL 8 | backed-up/PITR MySQL 8 |
| Cache/session | database/array scoped | array scoped + parity | Redis target | managed monitored Redis before launch |
| Queue | database; explicit sync | database workers | Redis workers | supervised Redis; DB only approved fallback |
| Mail | log/catcher | fake | sandbox recipients | authenticated transactional provider |
| Search | Scout DB | Scout DB | DB/candidate parity | DB initially |
| Media/storage | local + nonprod Cloudinary | fake/isolated fixtures | staging-isolated nonprod | separate production Cloudinary/private backup |
| Scheduler | manual | schedule tests | one monitored runner | one overlap-protected runner |
| Logs/debug | local/on | captured/test | centralized/off | redacted centralized/off+alerts |
| Secrets | uncommitted env | CI store | staging store | production manager/rotation |
| Backup | fixtures | rebuild | automated restore rehearsal | encrypted/PITR + restore tests |

Production shares no DB, Cloudinary, cache, queue or secret with lower environments. Staging uses production-like HTTPS/integration sandboxes.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Least privilege, rotation, TLS, debug off and no production data copied down without anonymization.

## Operational implications

Document deploy/migration, worker/scheduler, backups/restores, monitoring, quotas and incident owners.

## Testing implications

CI validates config/MySQL. Staging runs smoke/integration/workers/restore/browser gates; production has health/rollback.

## Migration or rollback implications

Use immutable deploys and expand/contract schema. Rollback restores compatible code/config; provider/data use runbooks.

## Conditions for reconsideration

Revisit managed services when hosting is selected; isolation and restore proof remain mandatory.

## Affected phases

Environment readiness starts before Phase 2; full gate is launch hardening.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
