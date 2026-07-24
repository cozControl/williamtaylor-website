# ADR-021 - Package Governance

## Status

Approved

## Date

2026-07-23

## Context

Proposed dependencies must remove meaningful complexity without silently owning core truth or creating upgrade risk. Installation is prohibited now.

## Decision drivers

Laravel/PHP compatibility, maintenance, licence, advisories, schema ownership, exit strategy and minimal surface.

## Alternatives considered

Native Laravel is default; maintained packages are allowed when they reduce protocol/security complexity; convenience wrappers are rejected.

## Decision

Every package gate records exact compatibility, maintainer/repository, licence, release/advisory history, cadence, transitives, schema ownership, export, replacement complexity and native gap.

| Candidate | Purpose / expected owner-licence | Schema and exit | Recommendation |
|---|---|---|---|
| spatie/laravel-permission | RBAC; Spatie/MIT, verify | package pivots; export/map to PermissionRegistry | Phase 2 gate |
| cloudinary/cloudinary_php | signed provider protocol; Cloudinary/MIT, verify | no business schema; replace MediaProvider/export originals | Media gate |
| laravel/scout | search contract; Laravel/MIT, verify | rebuildable projections; swap driver/adapter | Search gate |
| Tiptap npm packages | editor schema/transactions; ueberdosis/MIT, verify | versioned JSON migration/alternate editor | CMS gate |
| symfony/html-sanitizer | server allowlist; Symfony/MIT, verify | no schema; Sanitizer contract/rebuild projections | CMS gate |

Lock files change only in authorized phases with review and test evidence.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Audit licences/advisories and trusted sources. Pin compatible ranges and avoid abandoned/install-script-heavy packages.

## Operational implications

Assign update ownership, run Composer/npm audits and document rollback; schema-owning migrations require backup review.

## Testing implications

Require integration, upgrade and removal tests; fakes cannot hide protocol/security properties.

## Migration or rollback implications

Use project contracts. Export package data before removal and coordinate lock rollback with schema compatibility.

## Conditions for reconsideration

Reject on compatibility, advisory, abandonment, licence, lock-in or simpler native capability.

## Affected phases

First gate is Phase 2 RBAC; later media/search/CMS gates.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
