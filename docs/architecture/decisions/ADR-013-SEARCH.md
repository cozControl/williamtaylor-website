# ADR-013 - Search Architecture

## Status

Approved

## Date

2026-07-23

## Context

Administration needs lookup and catalogue search/filtering, but scale and relevance needs are not measured. Domain actions should not bind to a vendor engine.

## Decision drivers

Low initial operations, replaceable Laravel contract, measured scaling and safe structured filters.

## Alternatives considered

Direct Eloquent alone couples semantics. Laravel Scout database engine is selected. Meilisearch, Typesense and Algolia are deferred because need/cost is unproven.

## Decision

Adopt Laravel Scout as the application indexing contract when authorized, starting with its database engine. Structured catalogue filters remain explicit query objects, never editor SQL. Public indexes contain published/public projections only; admin search honors permission and state. External engines remain replaceable drivers.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Prevent draft/private indexing, safely escape highlights, authorize hydrated results and omit sensitive index fields.

## Operational implications

Measure p95 latency, lag, quality and volume. Evaluate an external engine near 50,000 sellable variants, p95 over 250 ms at target load, or unmet typo tolerance, advanced facets, synonyms, analytics/geography with operations ready.

## Testing implications

Run contract, publication/deletion indexing, leakage, rebuild idempotency, budget and relevance-fixture tests. External drivers need parity tests.

## Migration or rollback implications

Indexes are rebuildable projections. Driver migration uses rebuild/dual validation and cutover; rollback returns to database without domain changes.

## Conditions for reconsideration

Reconsider thresholds using measured traffic/UX or hosting constraints, never anticipated scale alone.

## Affected phases

First affects CMS/catalogue search; external service is deferred.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
