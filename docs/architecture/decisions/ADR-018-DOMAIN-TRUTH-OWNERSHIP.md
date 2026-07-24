# ADR-018 - Inventory, Pricing and Catalogue Ownership

## Status

Approved

## Date

2026-07-23

## Context

Clear mutation ownership prevents editors/integrations from overwriting price, stock or identity through unrelated screens.

## Decision drivers

One writer per fact, transactions, auditable cross-domain reads and commerce safety.

## Alternatives considered

A single Product aggregate owning everything is rejected. Separate domain owners with explicit actions/projections are selected.

## Decision

Catalogue mutates product identity, taxonomy, attributes and Sellable Variants. Pricing mutates currency amounts/windows. Inventory mutates locations, stock, reservations and derived availability. Merchandising mutates placements/recommendations. CMS mutates editorial resources. SEO mutates metadata but reads domain facts. Presentation mutates approved template configuration. Commerce mutates carts, checkout, payments and orders. Public read models may compose domains; checkout rechecks Pricing/Inventory. Cross-domain mutation calls the owning action within a documented transaction/outbox. Product means identity aggregate; Sellable Variant is purchasable; Publication is workflow; Published Revision is immutable output; Media Asset is reusable truth; Media Usage is contextual placement.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Permissions follow owners. CMS cannot impersonate price, stock or reviews. Commerce reauthorizes/rechecks at mutation.

## Operational implications

Use stable IDs/versioned projections, avoid cyclic writes and consequential observers, and maintain the ownership matrix.

## Testing implications

Test cross-domain write denial, reconciliation, checkout rechecks, transaction/outbox atomicity and schema truth.

## Migration or rollback implications

Map static content explicitly by owner. Rendering rollback never overwrites authoritative domain data.

## Conditions for reconsideration

Change boundaries only for proven transaction/performance needs via another ADR; never allow shared writers.

## Affected phases

Applies from Phase 2 conventions through every domain phase.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
