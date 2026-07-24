# ADR-017 - Canonical Routes and Slug History

## Status

Approved

## Date

2026-07-23

## Context

Current Laravel products use /products while a source used /product/executive-overcoat. Dynamic routes must avoid duplicates and chains.

## Decision drivers

Stable canonicals, compatibility, locale readiness, secure preview and deterministic collisions.

## Alternatives considered

Rendering multiple aliases is rejected. Singular product is rejected. Plural canonical plus history redirects is selected.

## Decision

Canonical products use /products/{slug}. Slugs are unique per product locale, lowercase ASCII transliteration, collapsed/trimmed hyphens, 3-160 characters, and may be curated. A code registry reserves system/admin/auth and supplied static paths. Slug changes are reviewed, retain history and create a single-hop 301 directly to newest canonical. Legacy /product/{slug}, including executive-overcoat, redirects only with unambiguous mapping and never renders. Collisions fail and need an explicit alternate. Preview uses signed /preview/... routes with authorization/noindex. Future /sw/products/{slug} follows ADR-015.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Normalize before validation, expire preview tokens and loop-check/allowlist redirect targets.

## Operational implications

Monitor 404, redirect hits/chains/conflicts. Publish slug/history atomically and invalidate route/sitemap caches.

## Testing implications

Test normalization/reserved/collisions, locale uniqueness, 301/history, legacy mapping, preview and canonical rendering.

## Migration or rollback implications

Keep literal plural routes until dynamic cutover. Seed mappings, validate and switch with fidelity; rollback restores literal routes.

## Conditions for reconsideration

Reconsider before catalogue cutover only; never render duplicate canonical resources.

## Affected phases

Affects SEO redirects and catalogue foundation.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
