# ADR-015 - Locale Strategy

## Status

Approved

## Date

2026-07-23

## Context

English is the only complete supplied locale. Premature Swahili fallback would mix languages and create duplicate indexed pages.

## Decision drivers

Complete experience, independent publication, stable URLs, correct SEO and real editorial ownership.

## Alternatives considered

English-only schema creates retrofit cost. Partial fallback URLs create duplicates. Localization-ready resources with English first are selected.

## Decision

English en is initial/default and unprefixed. Future Swahili uses /sw/...; later non-default locales use normalized lower-case BCP 47 segments. Localizable content is locale-specific at resource/revision level with field translations inside each immutable revision; each locale publishes independently and shares nonlinguistic records. Enable a locale only with complete navigation, metadata, policies and owner. Do not translate ULIDs, SKUs, provider facts, audit state or financial records. Missing locale never renders an indexed duplicate fallback at /sw. hreflang and sitemaps include published equivalents only; each locale self-canonicalizes.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Locale-aware previews/authorization prevent draft leakage. Legal text requires human approval.

## Operational implications

Routes, caches, search and sitemaps include locale. English data is not duplicated merely for schema.

## Testing implications

Test URLs/canonicals/hreflang, independent publication, missing translations, collisions, fallback non-indexing and cache isolation.

## Migration or rollback implications

Current English routes remain stable. New locales are additive; route withdrawal retains revisions and approved redirects.

## Conditions for reconsideration

Change URL policy only before first non-English launch. Add locales only with complete content and named owner.

## Affected phases

CMS schemas become ready first; localized routes need separate approval.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
