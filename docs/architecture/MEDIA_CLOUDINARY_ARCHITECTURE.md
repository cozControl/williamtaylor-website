# Media Library and Cloudinary Architecture

## Goal and ownership

Upload once, reuse many. Laravel owns editorial/accessibility metadata and references; Cloudinary owns binary storage, delivery and derived transformations. `MediaAsset` is provider-neutral enough to permit migration but records Cloudinary `asset_id`, `public_id`, resource/delivery type and version when used.

## Data model

Fields: UUID, provider, provider asset/public identifiers, version, resource type, MIME, original filename, normalized internal title, width, height, duration, bytes, checksum/perceptual hash, alt text, caption, credit, tags, collection/folder, focal x/y, dominant color, accessibility classification, decorative flag, rights/license/source, locale where relevant, upload owner, provider metadata, lifecycle state, timestamps and soft deletion. Usage references record owner type/id, field/role, locale and timestamps. Replacing an asset creates a revision/version and preserves references; audit who replaced it.

Alt text belongs to the semantic usage where context changes; the asset provides a required default and each usage may override it. Decorative usages explicitly emit empty alt. Transformed URLs never become database truth.

## Upload and processing flow

1. Authorized editor requests a signed upload intent from Laravel with size/type/folder constraints.
2. Browser uploads directly to Cloudinary using short-lived signed parameters; unsigned public presets are prohibited for admin originals.
3. Laravel verifies the provider callback/result signature and persists authoritative metadata.
4. After-commit jobs compute/check duplicate hashes, moderation/malware policy outcome as applicable, accessibility/readiness state and transformation previews.
5. Asset becomes selectable only when processing succeeds; failures remain retryable and visible.

Support drag/drop, multi-upload, progress/cancel/retry, duplicate warning before creating another logical asset, search by title/filename/alt/tag/credit/product, filters by type/dimensions/state/owner/date/usage/accessibility, collections/folders, bulk tagging and metadata completion. Upload size/type limits are purpose-based and server enforced.

## Delivery and transformations

Generate Cloudinary URLs from named, code-controlled transformation profiles: product card, product gallery, hero desktop/mobile, editorial, thumbnail, OG/social and video poster/streaming. Profiles specify crop mode, focal/gravity behavior, width sets, DPR, quality auto, format auto (AVIF/WebP fallback), and sharpening only when justified. Store intrinsic dimensions; render `width`, `height`, `srcset` and `sizes` to prevent layout shift. Lazy-load below-fold media; eagerly load/fetch-prioritize only the single critical LCP asset. Videos use adaptive delivery, muted/autoplay only where design/accessibility permits, captions/posters where relevant.

Editors preview crops at target aspect ratios and set a focal point; they do not compose arbitrary transformation strings. Signed/authenticated delivery is used for drafts/private customer uploads; public approved assets use CDN delivery.

## Safety and lifecycle

Validate MIME by content, extension, dimensions, duration, decompression risk and maximum bytes. Consider a malware scanning service for document/customer uploads; image/video provider processing is not a substitute for policy. Strip dangerous metadata where appropriate while retaining rights/credit facts in Laravel. Secrets stay in environment/secret management; webhook signatures, replay windows and rate limits are mandatory.

Archive hides assets from new selection but preserves live references. Deletion is blocked while used, requires usage inspection and elevated confirmation, and should first soft-delete/tombstone. Provider deletion is queued after retention and backup policy. Customer images are a separate private class with explicit consent, purpose, expiry (30 days by current direction unless a documented legal/transactional need overrides), deletion workflow and no reuse as marketing media.

## Governance and reporting

Readiness rules report missing alt/default metadata, decorative conflicts, oversized originals, poor dimensions/aspect, missing credit/license, duplicates, failed processing, unused assets and broken provider references. Usage inspection links directly to resources and distinguishes published/draft usage. Maintain provider-independent metadata backups and export mapping for disaster recovery.

No Cloudinary package or external integration is installed in ARCH-3A. Approval must select account topology, folders, signed-upload policy, transformation profiles, backup/export plan, video limits and cost budgets.