# CMS Target Architecture

Date: 2026-09-04  
Status: target design; no implementation

## Product boundary

The supplied William Taylor composition remains code-owned. Staff edit typed content and domain records; they do not redesign arbitrary layouts. Every public projection reads only approved, published, ready data and retains static fallback plus emergency unpublish.

```text
Authorized Staff
    |
    v
Laravel Admin CMS
|-- Website
|   |-- Homepage (typed fixed composition)
|   |-- Pages (standard/editorial content only)
|   |-- Navigation / Announcements / Site Profile
|   `-- Media Library and Usage
|-- Catalogue
|   |-- Products -- Options -- Variants -- SKU
|   |-- Collections -- ordered memberships
|   |-- Featured placements / Related Products
|   `-- Pre-Order / Limited Edition Campaigns
|-- Commerce
|   |-- Customers / Guest snapshots
|   |-- Carts -- Checkout -- Payments
|   |-- Orders -- immutable line/price snapshots
|   `-- Gift Cards
|-- Inventory
|   |-- Locations -- Inventory Items (Variant/SKU)
|   |-- Movement Ledger -- Balance Projection
|   `-- Reservations / Releases / Fulfilment / Adjustments
`-- Communications
    |-- Email / WhatsApp adapters
    |-- Versioned templates
    `-- idempotent delivery history

Draft -> Preview -> Submit -> Approve -> Publish -> Public Projection
                   |                       |
                   `-- audit history       `-- cache invalidation
                                             + static fallback
                                             + emergency unpublish

Public storefront (existing visual templates)
|-- Global chrome <- published Site Content
|-- Home <- published Homepage + eligible placements/campaigns
|-- Pages <- published Page projections
|-- Shop/Product/Collection <- Catalogue + Pricing + Availability
|-- Cart/Checkout/Account <- Commerce + Customer + Inventory
`-- SEO/schema <- domain-owned published metadata
```

## Domain truths

| Truth | Owning domain | Rule |
|---|---|---|
| Brand/contact/navigation/announcement | Site Content | Global, revisioned and independently publishable |
| Homepage composition | Homepage | Typed slots matching the protected design |
| Editorial route content | Page | Known route types; never Product/Collection replacement |
| Files, alt text, transformations, usages | Media | Asset readiness required before publication |
| Product identity/content/options/SKU | Catalogue | Stable identity plus immutable revisions |
| Price | Pricing | Integer minor units, currency explicit, snapshotted into Order |
| Physical availability | Inventory | Variant/SKU per location, ledger-authoritative |
| Collection membership/order | Catalogue | Ordered and lifecycle-aware |
| Featured/related ordering | Merchandising | Typed slots and relations, eligible targets only |
| Pre-order/limited-edition window and claims | Campaign | Effective dates, evidence and approvals |
| Cart/checkout/order | Commerce | Idempotent intake; immutable customer, delivery, item and price snapshots |
| Customer profile/account | Customer | Separate from staff authorization; guest checkout supported |
| Gift-card liability/balance | Gift Card | Ledger-backed code/token lifecycle; not a generic Product field |
| Messages and delivery attempts | Communications | Event-driven, idempotent, auditable |
| SEO | Each publishable domain | Shared value object/schema, domain-owned records |

## Inventory design

`InventoryItem` belongs to a sellable Variant/SKU. `InventoryLocation` is configurable; no real locations are assumed. An append-only `StockMovement` ledger records opening balance, receipt, adjustments, reservation/release, fulfilment, return and paired transfers. A transactionally maintained projection exposes `on_hand`, `reserved`, and `available = on_hand - reserved`; incoming is deferred until purchase-order truth exists.

Reservations require an expiry, cart/order identity and idempotency key. Checkout locks the relevant SKU/location balances, rejects insufficient availability, and creates reservations atomically. Cancellation/payment failure/expiry releases; fulfilment converts reserved quantity to an on-hand decrease without double counting. Reconciliation compares the projection with the ledger and records, never silently overwrites, discrepancies.

## Pricing design

Store `{currency, amount_minor, starts_at, ends_at}` in a dedicated Price record, with Product default and optional Variant override. Compare-at price is a separately validated amount/window. Campaign promotion references its applicable Product/Variant price; it does not mutate catalogue truth. Tax inclusion policy is explicit per market. Checkout resolves one effective price under lock and snapshots unit, discounts, tax basis and totals into Order lines. Never use binary floats for authoritative amounts.

## SEO ownership

Homepage, Page, Product, Collection and Campaign each own title, description, canonical override, robots policy and social media reference through a shared typed value object. Product projection emits Product structured data only when price/availability are authoritative; collection/page breadcrumb schemas derive from canonical routing. Sitemap includes only live indexable projections. Preview, drafts, account, cart and checkout are `noindex`.

## Publication UX

Normal editors see Draft, Preview, Submit for review, Approved, Publish and Unpublish. Technical rollout modes (`static`, `shadow`, `enabled`, emergency disabled, global kill switch) remain code/config-owned and appear only in an Advanced Publishing screen for specifically authorized operators. Editors see plain outcomes such as “Website publishing paused” and actionable blockers.

## Permission fit

Existing granular permissions fit Website content, Media, Pages, Orders, users and roles. Catalogue, Campaign and Merchandising domain actions are server-authorized foundations but lack a finished Admin navigation/workspace. Add bounded permissions later for product/variant/collection/placement administration, Pricing approval, Inventory view/receive/adjust/transfer/reconcile, customer access, gift cards and communications. Keep financial, publication, claim-approval and stock-adjustment duties separable. Navigation hiding is never the authorization boundary.
