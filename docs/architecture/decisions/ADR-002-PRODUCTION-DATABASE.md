# ADR-002 - Production Database

## Status

Approved

## Date

2026-07-23

## Context

SQLite is the development default. Revisions, variants, publishing, pricing, inventory and commerce need production concurrency, foreign keys, JSON, predictable indexes and tested recovery.

## Decision drivers

Team/hosting familiarity, Laravel support, transactions, utf8mb4, JSON/full-text, concurrency, troubleshooting and restore tooling.

## Alternatives considered

MySQL 8/InnoDB is selected. PostgreSQL is technically sound but rejected without an operational advantage. SQLite production is rejected for concurrent publishing, inventory and commerce writes.

## Decision

Use a supported MySQL 8 release with InnoDB, utf8mb4 and strict SQL modes in production and main CI. SQLite is limited to lightweight local/isolated tests with equivalent behavior. The complete suite, migrations, concurrency tests and restore drill must pass on MySQL before launch.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Use least-privilege accounts, encrypted transport where remote, managed secrets, encrypted backups and restricted production access.

## Operational implications

Require automated backups, point-in-time recovery where available, retention alerts, slow-query monitoring, schema-change runbooks and regular restore evidence.

## Testing implications

CI covers MySQL constraints, collation, JSON, indexes, locking and concurrency. SQLite-only success cannot authorize release.

## Migration or rollback implications

Author schemas MySQL-first. Destructive changes use expand/contract plus backup. SQLite fixtures never become production dumps.

## Conditions for reconsideration

Reconsider for a documented hosting mandate or PostgreSQL-specific capability with clear operational value; changing after data exists requires a new migration ADR.

## Affected phases

First affects Phase 2 persistence and every schema phase.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
