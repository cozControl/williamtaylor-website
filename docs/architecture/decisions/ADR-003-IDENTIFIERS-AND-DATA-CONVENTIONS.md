# ADR-003 - Identifiers and Data Conventions

## Status

Approved

## Date

2026-07-23

## Context

Future resources are externally referenced and cross-domain. Money supports TZS/USD, while schedules need unambiguous storage for Tanzania-based editors.

## Decision drivers

Stable non-enumerable references, index locality, exact money, time safety, locale consistency and intentional retention.

## Alternatives considered

Integers everywhere are enumerable; UUIDv4 has poorer index locality. ULIDs for externally referenced records are selected.

## Decision

Use ULID primary keys for externally referenced content, media, catalogue, publication, audit and commerce aggregates. Internal join/detail rows may use ULID or bigint when never exposed, decided per schema. Do not add a second public ID without need. Store money as integer minor units with uppercase ISO 4217 currency, never float. Store UTC timestamps with microseconds where ordering matters; display admin dates in Africa/Dar_es_Salaam. Normalize locales as BCP 47. Soft-delete only resources with a defined recovery/retention reason; never mutate immutable audit, ledger or published revisions.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

ULIDs are not authorization. Currency/locale allowlists apply, and immutable/exact records support forensics.

## Operational implications

Central casts/value objects own ULID, money and time behavior. A currency registry owns minor-unit rules.

## Testing implications

Test uniqueness/order, arithmetic/overflow/currency mismatch, timezone conversion, locale normalization and delete/purge rules.

## Migration or rollback implications

Existing users keep integer IDs absent a separate migration. New conventions are additive; populated identifier conversion requires explicit mapping.

## Conditions for reconsideration

Reconsider per aggregate for provider constraints or exceptional volume; never change the no-float money rule.

## Affected phases

First affects Phase 2 conventions and all later data phases.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
