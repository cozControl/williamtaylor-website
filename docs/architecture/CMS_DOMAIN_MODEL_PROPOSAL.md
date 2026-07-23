# CMS Domain Model Proposal

## Boundaries

- **Identity & Access:** users, roles, permissions, sessions, security posture.
- **Publishing:** pages, sections, revisions, workflow transitions, previews and schedules.
- **Brand & Navigation:** organization/contact/social settings, menus, announcements.
- **Editorial & Services:** articles, lookbooks, style guides, FAQs, testimonials, tailoring/corporate/wedding services, locations and policies.
- **Media:** reusable assets, metadata, transformations and usage references.
- **SEO:** metadata, canonical/indexing decisions, schema configuration, redirects, slug history, sitemap state and health findings.
- **Catalogue:** products, attributes, variants, categories, collections, tags, badges, product media and product relations.
- **Merchandising & Campaigns:** ordered placements, campaigns, banners, pre-orders and limited editions.
- **Pricing:** currency amounts, sale prices, effective windows and tax presentation; never inventory.
- **Inventory:** stock by sellable variant/location, reservations and availability projections; never promotional copy.
- **Engagement:** newsletter subscriptions, wishlists, enquiries, reviews, appointments, measurements and preferences.
- **Commerce (deferred):** carts, gift-card ledger, checkout, payments, orders, shipping, refunds and fulfilment.

## Core aggregates and relationships

| Aggregate | Key fields/relationships | Invariants |
|---|---|---|
| Page | type, locale, title, slug, template, sections, SEO, status | Unique canonical slug per locale; only approved section types |
| ContentRevision | resource type/id, payload snapshot, author, note, checksum | Immutable; published revision identifiable |
| Menu | key, locale, items tree | Valid internal target or validated external URL; depth limits |
| Announcement | copy variants, CTA, audience, start/end, priority | Valid window; one deterministic active winner per slot |
| Campaign | type, dates, content, product/collection placements | Expiry handled; claims auditable |
| Article/Guide/Lookbook | title, excerpt, body, authorship, media, taxonomy | Sanitized rich text; publication workflow |
| Service | kind, summary, body, benefits, FAQ, locations | Genuine service/location relationship |
| Location | name, address, geo, contacts, hours, service relationships | Verified contact/address; LocalBusiness eligibility |
| Policy | kind, effective date, body, revision | Published effective version retained |
| Product | name, SKU, slug, type, copy, status, tax/category/collection relations | SKU/slug uniqueness; published readiness |
| Variant | product, option values, SKU/barcode, dimensions/weight | Unique option combination and SKU |
| Attribute/Value | type (size/color/material/fabric/style), label, swatch/metadata | Typed validation and stable identity |
| ProductMedia | product/variant, media, role, order, alt override | Primary image required to publish |
| ProductRelation | source/target, kind, order | No self-link/duplicate; target publishable |
| Price | sellable, currency, amount, compare-at, effective window | Non-negative; one effective price per scope |
| InventoryItem | variant/location, on-hand, reserved, safety stock | Available derived transactionally, not editor-entered copy |
| MediaAsset | provider/public id, facts, accessibility/editorial metadata, state | Reusable; deletion blocked while referenced |
| SeoProfile | owner, title/description, canonical, robots, OG, schema settings | Schema facts sourced from domain truth |
| Redirect/SlugHistory | from path, target, status, owner | No loops/chains on creation |
| Publication | resource/revision, state, schedule, approvals | State transitions policy-controlled and audited |

CustomerProfile extends User only when needed. Wishlist supports authenticated owner or anonymous token, with merge-on-login later. MeasurementProfile is sensitive personal data with scoped staff access, explicit purpose and retention. Gift cards require an immutable stored-value ledger, not a mutable balance field alone.

## Laravel application structure

Keep one deployable Laravel application. Organize `app/Domain/{Identity,Publishing,Content,Media,Seo,Catalogue,Merchandising,Pricing,Inventory,Engagement,Commerce}` with domain-specific Actions, Queries, DTOs/ValueObjects, Policies, Events and Jobs. HTTP/Livewire adapters call application actions; they do not own business rules.

Use Eloquent models for persistence and relationships, with small invariant methods/casts/scopes. Use Form Requests for HTTP validation and Livewire form objects for UI state, both delegating invariant checks. DTOs are useful at publish, media ingestion, catalogue import and payment boundaries. Repositories are justified only for external provider/search boundaries or when aggregate persistence cannot be expressed clearly with Eloquent—not as wrappers around every model.

Transactions cover publication + revision pointer + outbox/audit record; product + variants; price activation; stock reservation; gift-card ledger; checkout/order/payment state transitions. Dispatch external jobs after commit. Avoid consequential model observers; use explicit actions/events.

## Services, events and jobs

Examples: `PublishResource`, `SchedulePublication`, `CreateProduct`, `ReplaceMediaAsset`, `ActivatePrice`, `ReserveInventory`; queries such as `PublicPageQuery`, `PublishedProductQuery`, `SeoHealthQuery`. Events include `ResourcePublished`, `SlugChanged`, `MediaReplaced`, `ProductPublished`, `PriceActivated`, `InventoryChanged`. Listeners invalidate tagged caches and enqueue sitemap/search/media work. Jobs handle Cloudinary ingestion/metadata, variants/previews, search indexing, sitemaps, link/SEO scans, scheduled publication and bulk work.

## Rendering and transition

Introduce typed view models/presenters and cached public queries. Convert one literal page family at a time behind fidelity tests: global settings; homepage; collections; campaign pages; products. Components receive normalized data and preserve current markup exactly. Preview routes use signed, short-lived URLs, authorization and `noindex`; draft data never enters public caches. Eager-load declared graphs, enforce query-count tests, and invalidate versioned/tagged cache keys after committed publication.

Canonical product route: `/products/{product:slug}` with reserved-slug validation and locale strategy decided before launch. Store slug history and issue single-hop 301 redirects. Legacy `/product/{slug}` resolves an alias and redirects to canonical. Preview uses a separate signed route and never changes canonical URLs.

## Testing layers

Unit tests for value objects, state machines and rules; feature tests for policies/actions/transactions/routes; Livewire component tests for editor flows; database tests for constraints and concurrency; contract tests for Cloudinary/search/Pesapal adapters; browser tests for critical dashboard workflows; fidelity tests for public rendering; security tests for authorization, preview leakage, uploads and rich-text sanitization; performance/query-budget tests for public pages.