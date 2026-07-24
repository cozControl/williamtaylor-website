# ADR-009 - Typed Content Sections

## Status

Approved

## Date

2026-07-23

## Context

The supplied frontend has finite patterns. Editors need content control without Blade, CSS, JavaScript or arbitrary query control.

## Decision drivers

Fidelity, validation, accessibility, versioned migration and bounded maintenance.

## Alternatives considered

Per-sentence fields are brittle. Unrestricted builders/HTML are unsafe. Constrained typed sections are selected.

## Decision

A code registry initially permits Hero, Video hero, Editorial split, Promotional cards, Product rail, Collection rail, Service summary, FAQ, CTA and Rich editorial body only where a page proves need. Each type has a stable key, schema version, validation, item limits, allowed media roles, Blade/preview renderer, migration, fidelity and accessibility contract. Editors cannot supply Blade, arbitrary HTML/classes/JavaScript/SQL or unrestricted queries. Rails use approved domain relationships/query options.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Validate/escape payloads, authorize referenced resources and prevent drafts/private media from leaking.

## Operational implications

Registry changes deploy as code; old versions render until migrated. Cache keys include renderer/schema version.

## Testing implications

Require schema/migration and invalid-payload tests, renderer coverage, accessibility and screenshot fidelity.

## Migration or rollback implications

Migrate with dry runs/backups and never discard unknown fields. Old renderers remain during rollback.

## Conditions for reconsideration

Add/change a section only for a client-approved pattern. A universal builder requires a new ADR.

## Affected phases

First affects Core CMS; public transitions remain resource-by-resource.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.

## BE-4E implementation reference

Implemented through the code-owned PageType, Template, and Section registries; five bounded section schemas; stable section ULIDs; strict normalization; and immutable revision payloads. Public rendering remains deferred.