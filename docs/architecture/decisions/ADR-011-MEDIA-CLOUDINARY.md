# ADR-011 - Media and Cloudinary Integration

## Status

Approved

## Date

2026-07-23

## Context

Managed content needs reusable assets, secure uploads/delivery and provider portability while Laravel retains metadata and reference truth.

## Decision drivers

Official support, signed upload, lifecycle safety, environment isolation, transformation consistency and exit strategy.

## Alternatives considered

Official SDK behind a contract is selected. Community wrappers add maintenance abstraction; REST reimplements signatures/retries; filesystem alone lacks Cloudinary capabilities.

## Decision

Use official Cloudinary PHP SDK behind MediaProvider: CreateUploadIntent, ConfirmUploadedAsset, GenerateDeliveryUrl, GeneratePrivateDeliveryUrl, ReplaceProviderAsset, DeleteProviderAsset and GetProviderMetadata. Browser intents are signed and expire in 5 minutes; results/signatures are server-verified. Allow images/video; raw requires separate approval. Initial proposed maxima are 20 MB images and 250 MB videos pending business/hosting approval. Profiles are code-owned and transformed URLs are never truth. Production uses a separate Cloudinary product environment; development/staging use nonproduction isolation. Public IDs use environment/domain/date/random ULID. Verify webhooks, retry idempotently, delay provider delete at least 7 days after archive/unreference, export originals/metadata and alert on quota/cost. Customer images are private authenticated delivery.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Never expose API secrets. Bind intents to actor/type/size/path; verify magic type/dimensions/webhooks; audit replace/delete; prevent public customer transforms.

## Operational implications

Reconcile orphan uploads, tolerate provider outage without corrupting references and test export/restore.

## Testing implications

Contract/fake, expiry/tamper, size/type, webhook replay, retry, safe-delete, environment isolation and private delivery tests apply.

## Migration or rollback implications

Contract and exports enable replacement. Rollback disables upload while preserving delivery; deletion remains delayed.

## Conditions for reconsideration

Reconsider on cost, terms, residency, availability or private-media failure. Production isolation remains required.

## Affected phases

First affects Media foundation; no credential/package now.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
