# ADR-012 - Media Metadata and Alt-Text Ownership

## Status

Approved

## Date

2026-07-23

## Context

One reusable image may convey different meaning in a product gallery, editorial placement or decorative banner.

## Decision drivers

Contextual accessibility, rights evidence, reuse, safe deletion and stable replacement.

## Alternatives considered

Global alt only is semantically wrong; usage-only repeats defaults. Asset default plus usage override/decorative state is selected.

## Decision

Media Asset owns provider identity, technical facts, default alt, title, caption, credit, rights evidence, focal point, tags and processing/lifecycle state. Media Usage owns resource/field/role/order plus contextual alt override or decorative=true. Effective alt is override then default; decorative output uses empty alt and presentation semantics. Usage tracking blocks delete, supports stable replacement and reports missing accessibility data.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Restrict rights/private metadata and prevent private originals/customer details from leaking.

## Operational implications

Publication fails for meaningful usage without effective alt. Replacement preserves asset identity and usage graph; rights expiry/processing failure creates alerts.

## Testing implications

Test effective-alt resolution, decorative output, readiness, authorization, rights, safe-delete and replacement.

## Migration or rollback implications

Inventory existing assets without rendering changes. Missing facts may remain flagged until resource management. Rollback keeps references intact.

## Conditions for reconsideration

Extend fields for legal/new media requirements; never collapse contextual usage to one alt.

## Affected phases

First affects Media foundation and later content/catalogue publication.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.

## BE-4E implementation reference

CMS drafts reuse only ready logical Media assets and attach contextual alt/decorative Media usages to immutable content revisions. Upload and provider truth remain owned by Media.