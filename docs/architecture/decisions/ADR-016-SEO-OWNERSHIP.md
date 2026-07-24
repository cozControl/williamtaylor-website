# ADR-016 - SEO Ownership and Rendering

## Status

Approved

## Date

2026-07-23

## Context

Indexable resources need editable metadata without allowing fabricated price, availability, ratings, location or service truth.

## Decision drivers

Single truth, safe canonical/indexing, audited overrides and published-only sitemap.

## Alternatives considered

Free-form schema can fabricate truth; code-only metadata is too rigid. Resource metadata plus code schema/domain facts is selected.

## Decision

Attach SEO profiles to indexable resources. Code owns schema shapes; catalogue, pricing, inventory, genuine reviews, location and service domains supply facts. Editors manage titles, descriptions, social presentation and permitted canonical/robots overrides under seo.manage with audit. Canonical defaults to the generated route. Redirect/history is separate. Sitemaps are asynchronous and published-only. Publication blockers are missing required unique title/description, invalid/off-host/noncanonical canonical, contradictory robots, redirect loop/collision, schema facts disagreeing with domain truth, or missing effective alt on required meaningful media. Warnings include recommended lengths, optional social image/link/schema opportunities.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Escape metadata, allowlist canonical hosts, forbid schema injection, authorize overrides and keep previews/drafts noindex/out of sitemap.

## Operational implications

Findings have severity, owner and lifecycle. Sitemap jobs are idempotent/alerted; canonical changes coordinate redirects.

## Testing implications

Test escaping, canonical/robots, schema sources, blocker/warning policy, sitemap publication, redirects and leakage.

## Migration or rollback implications

Keep static heads until resource transition; compare rendering/fidelity. Rollback restores prior projection and sitemap.

## Conditions for reconsideration

Tune warning/blocker details with SEO evidence, never permitting false domain facts or duplicate canonicals.

## Affected phases

First implementation is SEO/redirect foundation after CMS/global dependencies.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
