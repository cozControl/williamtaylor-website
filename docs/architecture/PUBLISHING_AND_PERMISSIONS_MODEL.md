# Publishing and Permissions Model

## Authorization model

Use server-side policies backed by explicit permissions; UI hiding is convenience only. Start with roles as permission bundles, allow exceptional grants sparingly and show effective access. Current required defaults map as follows: **Super Administrator** has all permissions and **CMS Manager** may create/edit/review/approve/publish content. Preserve separation-of-duty capability for sensitive policies, pricing, campaign claims and settings.

Suggested roles: Super Administrator, Site Administrator, Content Manager, Content Editor, SEO Manager, Catalogue Manager, Merchandiser, Media Manager, Marketing Manager, Reviewer/Approver, Customer Service and Read-only Analyst.

Permission namespaces use resource/action, e.g. `pages.create`, `pages.edit`, `pages.review`, `pages.approve`, `pages.publish`, `pages.unpublish`, `pages.schedule`, `pages.archive`, `pages.restore`, `pages.delete`, plus `seo.manage`, `redirects.manage`, `navigation.manage`, `media.manage`, `products.manage`, `pricing.manage`, `inventory.view/manage`, `analytics.view`, `settings.manage`, `audit.view`, `users.manage`, `roles.manage`. Publish does not imply settings/permission management. Inventory and pricing permissions remain separate from catalogue copy.

| Role | Typical scope |
|---|---|
| Super Administrator | All, break-glass monitored |
| Site Administrator | Users/site operations except protected super-admin controls |
| CMS Manager | Content/navigation review, approval, publish |
| Content Editor | Draft/edit/submit; no publish |
| SEO Manager | SEO/redirects/sitemap findings; publish SEO within scope |
| Catalogue Manager | Products/variants/product publication; not stock/payment |
| Merchandiser | Collections/relations/placements/campaign submissions |
| Media Manager | Upload/metadata/replace/archive; delete under safeguards |
| Marketing Manager | Campaigns/announcements/newsletter content/schedules |
| Reviewer/Approver | Review/diff/notes/approve; publish only if explicitly granted |
| Customer Service | Enquiries/appointments/limited customer data; no content publish |
| Analyst | Read-only analytics/permitted audit views |

## Workflow state machine

Draft → In review → Changes requested → Approved → Scheduled or Published → Unpublished → Archived. Archived can restore to Draft. Scheduled entries publish only the approved immutable revision. Editing an approved/published resource creates a new draft revision without mutating the live revision.

- Authors save drafts and submit with a summary.
- Reviewers annotate, compare revisions, request changes or approve.
- Publishers publish/schedule after readiness validation; self-approval policy is configurable by resource sensitivity.
- Emergency unpublish requires permission, reason, step-up confirmation and audit; public fallback/redirect behavior is previewed.
- Expired campaigns automatically leave active placement, transition to expired/unpublished policy, invalidate caches and notify owners; underlying history remains.

Every transition records actor, effective role/permission, from/to state, revision, timestamp, note, request/IP/session context where proportionate, and scheduled-job identity. Revisions are immutable snapshots with semantic field/block diffs and rollback by creating a new draft from an old revision—not rewriting history.

## Preview and scheduling

Preview uses short-lived signed URLs plus authenticated authorization or explicit secure share tokens. It is `noindex`, excluded from sitemap/cache, visually marked, revocable and audited. Support desktop/tablet/mobile preview and exact public components.

Store schedules in UTC and display Africa/Dar_es_Salaam timezone by default. Use database locks/idempotency keys so scheduled publish runs once. Publication transaction updates the live revision/status, audit/outbox record and cache version; external search/sitemap/provider work queues after commit. Failed jobs alert owners and remain retryable.

## Publication readiness

Typed validators check required content, valid links/relationships, media processing and alt state, SEO/canonical/index status, campaign windows, product identity/media/price readiness, and legal/operational claims. Critical failures block publication; warnings require acknowledgment. The dashboard surfaces failures before submit/publish.

## Security and compliance

Require CSRF, server validation, authorization per action/resource, rate limits, secure sessions/cookies, password/passkey/2FA readiness, step-up auth for permissions/settings/destructive actions and session revocation. Sanitize rich text server-side. Validate uploads and signed Cloudinary callbacks. Keep secrets outside database/source. Audit privileged reads/exports and mutations.

Minimize personal data, define purpose-based retention and deletion. Current Tanzania direction of 30 days applies to transient customer images/enquiries where appropriate, not blindly to legally necessary order/account records; obtain legal validation and document schedules per data class. Customer images require explicit consent, private delivery, scoped access and deletion. Backups are encrypted, access-controlled, restoration-tested and reconciled with deletion obligations.