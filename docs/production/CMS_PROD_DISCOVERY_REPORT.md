# CMS-PROD-DISCOVERY-1 Report

Date: 2026-09-04  
Scope: comprehensive low-cost, read-only code/configuration assessment. Only these documentation artifacts were created. No application code, configuration, data, dependencies or routes were changed; no complete audit was run.

## Executive assessment

The repository has a substantial governance-oriented backend foundation, but it **cannot honestly be called a usable production CMS today**. It has strong pieces—RBAC, immutable revisions, signed previews, review/publication actions, audit records, Media Usage, catalogue/collection/campaign models, merchandising relationships and a snapshot-oriented demo Order aggregate. The client experience does not connect those pieces to most of the storefront. Homepage, Shop, Collections, campaigns, gift cards, wishlist and all five Product pages remain protected static Blade/template content. Shared chrome and About have projection pilots, but rollout flags default off and the real database may contain no About Page.

### Estimated completion

These are architecture estimates, not earned phase-completion percentages:

| Area | Estimate | Basis |
|---|---:|---|
| Backend CMS/domain foundation | 58% | Strong content/media/catalogue/campaign/order foundations; Pricing, Inventory, Customer, Gift Card and communications domains absent |
| Client-usable CMS | 12% | Admin governance exists, but no end-to-end editable storefront region and Media is failing manually |
| Ecommerce storefront | 18% | High-fidelity browsing presentation exists; dynamic catalogue, cart, checkout and payment do not |
| Inventory | 0% | No Inventory domain/migration/ledger |
| Production readiness | 8% | Safety foundations exist; core commerce, usable CMS, operational media and live projections do not |

## Measured storefront result

- Public storefront routes: **14** — `/`, `/about`, `/collections`, `/shop`, `/pre-order`, `/limited-edition`, `/gift-cards`, `/wishlist`, `/login`, and five Product routes. `/dashboard` is authenticated application UI, not counted as a public storefront route.
- Visible regions inventoried: **92** using the counting rules in the ownership matrix.
- Client-editable content end to end: **0**.
- Technically governed but not client usable: **11**.
- Backend-only: **40**; static: **26**; missing: **14**; one client-usable row is Login behavior, not editable CMS content.
- No collection-detail route exists; Collections is an index only. No dynamic Product route exists.

## Findings by area

### Media pipeline — P0

The browser validates image/video type and size, asks Livewire for a signed intent, uploads directly to Cloudinary, and sends the returned evidence to `ConfirmUploadedAsset`. The server revalidates credentials, a five-minute timestamp, signature over `public_id` and `version`, configured folder prefix, delivery type, format and bytes. It then writes a ready `MediaAsset`, immutable version and audit event. Media Usage attaches ready assets to Page/Product/Collection/Campaign owners; frontend URLs are generated through Cloudinary transformation profiles.

The displayed error is not diagnostic: `MediaLibrary::confirmUpload()` catches every `RuntimeException` and returns the same “Secure provider confirmation failed” string. Current `.env` selects Cloudinary and all three credential values are present, so missing credentials are unlikely. Code-supported causes are stale/missing `created_at_timestamp`, signature mismatch (especially a provider response signature contract differing from the server's exact two-field calculation), configured-folder/public-ID mismatch, malformed response fields, unsupported format/type, or byte limit failure. Database records showing confirmation failure are not evidence that provider upload itself failed: the direct upload can succeed before confirmation is rejected.

Recommended remediation: retain Cloudinary for staging/production if it is the approved provider; add safe internal failure codes/log correlation while keeping secrets/provider payloads out of the UI; confirm Cloudinary's actual signed upload response contract; bind intent and confirmation to an expiring server-side nonce; preserve the folder boundary; and add a focused real-provider smoke test. For local development, implement a first-class **local public-disk adapter** with the same interface and readiness semantics (or intentionally use Cloudinary). Do not use the deterministic evidence adapter outside tests, and do not use a private disk unless signed delivery is also implemented. Acceptance is upload -> ready -> thumbnail -> alt -> selector -> preview -> publish -> storefront.

### Admin UX — P0

Observed code supports the physical review: Site Settings uses dense generic workspaces and large ready-media selectors; workflow/status grids expose candidate/designated-revision terminology; several actions are links/raw controls rather than a clear primary task; Dashboard says “Client Demo operations”, “DemoMode”, “Published demo Pages” and “Kill switch”; Navigation explicitly says public projection is inactive. Empty states exist in Pages/Media, but Pages correctly shows zero when no Page rows were installed into the current database—the factory installer is explicit, not normal seeding or lazy creation.

Severity:

- Critical: non-operational Media confirmation; no live CMS-to-storefront path; zero-Page real environment.
- High: malformed/responsive form layout reported in manual review, oversized selectors, missing Product/Collection/Campaign Admin, technical publication language.
- Medium: dense governance detail in ordinary editors, fragmented primary actions, guidance tied to demonstration phases.
- Cosmetic: badge/copy consistency and action styling after layout repair.

Recommended information architecture: Dashboard; Website (Homepage, Pages, Navigation, Announcements, Media); Catalogue (Products, Collections, Featured Products, Campaigns); Commerce (Orders, Customers, Gift Cards); Inventory (Stock, Locations, Movements, Adjustments); Administration (Users, Roles, Audit Log, Site Settings, Publishing Controls). It fits the registry's group/item approach, but new destinations require explicit registered permissions and server-side enforcement.

### Homepage — P0

The homepage contains 12 page-specific regions in presentation order: video hero, service strip, new arrivals, collection cards, campaign panels, dark editorial/product grid, occasion cards, full-width editorial feature, testimonials, featured campaign Products, social gallery and journal cards, followed by shared newsletter/footer. All remain static Blade. Use a fixed typed Homepage aggregate and domain references as defined in the matrix; a generic drag-and-drop builder would expose design decisions the client should not change.

### Pages — P0

The Page foundation supports standard and editorial-landing types, five typed section types, immutable revisions, signed preview, submit/review/approve/publish/schedule/unpublish, rollback provenance, Media Usage and audit. Only `/about` is registered for public Page projection. About can be edited only if its factory Page was explicitly installed/reconciled; a zero-row Pages index means it is absent, not hidden. Page creation supports typed records broadly, but public routing is allow-listed, so arbitrary records do not create arbitrary routes. About and future editorial/legal/contact pages belong here; Product, Collection, Campaign, Homepage, Gift Card transaction and account routes do not.

### Product and Variant — P0/P1

Domain logic supports stable Product/slug/lifecycle, immutable content revisions, archive/restore, primary/gallery Media Usage, badges, options/values, Variant combinations, unique SKU, default Variant and readiness. Evidence restricts options to **colour and size** and at most two axes. There is no barcode field, Variant-specific media, authoritative price, stock relationship, Product Admin UI, public dynamic route, preview or publication projection. The five visible product pages are hard-coded. Product cannot realistically be sold until Pricing, variant-level Inventory, cart/checkout availability validation and production Order integration exist.

### Collections and merchandising — P0

Collections support identity, slug, typed revision content, hero/card Media roles, ordered Product memberships, lifecycle/readiness and cached configuration queries. Merchandising supports one ordered `homepage-featured-products` slot (maximum five) and directional ordered related Products (maximum four). These are backend-only: no Admin workflows, preview/publication state, collection-detail route, public readers or homepage/product integration. Replace hard-coded Product IDs/cards with typed placements; add only slots evidenced by the homepage composition.

### Campaigns — P0

The domain supports `pre_order` and `limited_edition`, revisions, Product targets, card/hero Media, schedule/effective state, archive/restore, and a separately governed `public_window_statement` claim with evidence and independent approval. It does not provide a client Admin, public projection, Collection targeting, pricing/deposit/reservation truth, or broad claims such as stock quantity/popularity/delivery guarantee. Current public campaign routes are static. The target workflow is edit content/media/products/window -> submit claims -> independent approval -> exact preview -> publish/schedule -> expire/unpublish.

### Gift Cards — P1/P2

Only frontend presentation/forms exist. There is no gift-card aggregate, value policy, purchaser/recipient, secure claim token, balance ledger, redemption, status or delivery communication. Treat the presentation as Page-like content but the financial lifecycle as a dedicated Gift Card domain integrated with Pricing, Orders, Payments, Customer and Communications.

### Pricing — P1

No authoritative Pricing exists; visible prices are static, and demo Order amounts are authorized staff-entered snapshots. Add currency-explicit integer minor-unit Prices, Product default/Variant override, separately validated compare-at amount, effective windows and campaign adjustment. Resolve and snapshot price/tax/discount into Order lines. Business decisions required: selling currency/currencies, tax-inclusive policy, promotion precedence and rounding.

### Inventory — P1

No Inventory code exists. Add configurable locations, Variant/SKU inventory items, append-only movements, balance projection, reservations, expiry/release and fulfilment conversion. Required permissions include view, receive, adjust-increase, adjust-decrease, transfer, reserve/system, fulfil, reconcile and export; sensitive manual adjustment/reconciliation should require reasons and separation as appropriate.

### Cart and Checkout — P1

No cart persistence, totals, Variant identity, quantity mutation, stock validation, delivery selection, checkout, payment or confirmation exists. Template buttons/forms are presentation/JavaScript only. Cart requires Pricing and availability; checkout requires atomic reservation, immutable snapshots and idempotent Order intake.

### Orders — P1/P2

The demo aggregate has valuable production-compatible elements: ULID identity, random number, optional user link, immutable customer/delivery/item/SKU/options/price snapshots, integer totals, idempotency key, optimistic lock, status and payment event history, notes and server-authorized transitions. Preserve those. Replace `CreateDemoOrder`, demo gates/markers/manual price entry and “Demo Receipt” with a production checkout/payment intake. Add reservation/fulfilment coupling, gateway webhook/reconciliation, shipping/tracking, fiscal receipt policy, customer ownership and event outbox. Payment status is currently informational, not settlement truth.

### Customers/account — P1/P2

There is no separate Customer domain. Orders can optionally reference the staff-oriented `User` model, while retaining snapshots; this must not become the default customer model without an explicit boundary decision. Add guest checkout plus Customer identity/profile, addresses, preferences, order/receipt view, Wishlist and Gift Cards. The current authenticated dashboard is a starter screen, not a customer Order dashboard.

### Communications — P2

Laravel mail transports, database queue tables and generic notification capability exist; local mail defaults to `log` and queue to `database`. No commerce notification classes, WhatsApp adapter, versioned templates, outbox/idempotency or delivery log were found. Initial events: order received/confirmed/in preparation/ready/dispatched/delivered/cancelled, payment status and receipt ready; later gift-card delivery/redemption and consented cart reminders.

### SEO — P2

Static document-head content exists, and prior architecture recognizes domain ownership, but production Homepage/Page/Product/Collection/Campaign SEO, canonical policy, OG media, structured Product/Breadcrumb data, sitemap and noindex rules are not end-to-end implemented. Put SEO beside each publishable domain using shared schema/value objects, not one unrelated generic table.

### Publishing architecture — P0/P2

Keep internal static/shadow/enabled modes, global publication switch, emergency disable, immutable revisions, audit, rollback and static fallback. Current defaults are disabled/static for Site Content, About Page, Product, Collection and Campaign. Ordinary editors should never need “projection”, “candidate”, “designated published” or “kill switch”. Move rollout controls to an Advanced Publishing area and show Draft, In review, Approved, Published, Unpublished, “Website publishing paused”, and “Published version”.

## Client-facing terminology disposition

| Current wording | Disposition | Production wording |
|---|---|---|
| DemoMode / Demo mode / Demo Environment | Remove from ordinary UI; retain internal environment guard until retired | Test environment (only where necessary) |
| Client Demo operations | Replace | Website overview |
| Published demo Pages | Replace | Published pages |
| Create Demo Order / Demo Receipt | Remove when production intake exists; retain internal watermark only for legacy fixtures | Create order / Receipt |
| Global publication kill switch / Kill switch active | Advanced/system only | Website publishing paused |
| Candidate revision | Replace | Version in review / Approved version |
| Designated published revision | Replace | Published version |
| Typed revision comparison | Replace | Review changes |
| Public storefront projection | Internal-only | Live website |
| rollout / shadow / emergency-disabled | Internal/Advanced Publishing | Publishing mode / Publishing paused where exposed |

## Priority gaps

### P0 — client blockers

1. Media upload/confirmation and thumbnails are not operational in the manual environment.
2. Admin form layout/selectors and ordinary workflow language are not client-ready.
3. Homepage is entirely static.
4. About/Page baseline can be absent and live projection is disabled.
5. No Product, Collection, merchandising or Campaign Admin-to-preview-to-live workflow.
6. Public Product/Collection/Campaign routes do not read governed domains.

### P1 — ecommerce core

1. Pricing and tax policy.
2. Variant/SKU Inventory ledger and reservations.
3. Persistent Cart and Wishlist boundary.
4. Checkout, delivery and production Order intake.
5. Payment integration and reconciliation.
6. Gift-card monetary lifecycle.

### P2 — production operations

Customer accounts, transactional Email/WhatsApp, fiscal receipts, shipping/tracking, domain-owned SEO, monitoring, backups, reconciliation and production publishing controls.

### P3 — enhancements

Journal domain, external social feed, advanced search/recommendations, incoming stock/purchase orders, multilingual storefront and consented cart reminders.

## Recommendation

Proceed next with **CMS-PROD-1: Admin UX and Media recovery**. Do not start Homepage migration until a normal staff user can upload, see, describe and select media reliably. Then deliver Homepage/global ownership as the first visible production CMS outcome.

## Deliverables

- [Storefront ownership matrix](CMS_STOREFRONT_OWNERSHIP_MATRIX.md)
- [Target architecture](CMS_TARGET_ARCHITECTURE.md)
- [Production roadmap](CMS_PRODUCTION_ROADMAP.md)
- [Client CMS acceptance](CLIENT_CMS_ACCEPTANCE.md)
- This discovery report
