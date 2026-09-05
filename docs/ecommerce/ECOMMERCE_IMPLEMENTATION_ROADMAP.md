# Ecommerce Implementation Roadmap

## Gate 0 - physically close Media

Re-test a new real Cloudinary JPG/PNG/WebP after the MIME normalization fix: Upload -> READY -> decoded thumbnail -> alt text -> reusable selection. Stop catalogue work if this remains blocked.

## ECOM-PROD-1 - Oxford Shirt vertical slice

Use `/products/the-taylor-oxford-shirt`, the most representative everyday Product with gallery, TZS price, Colour/Size choices, details and related Products.

1. Reconcile one Product record with the existing stable slug and exact visible copy.
2. Associate existing imagery through primary/gallery Media roles; retain static fallback until reconciliation succeeds.
3. Reuse Colour and Size options, generate active combinations and require unique SKUs.
4. Add a simple Product Admin editor: identity/content, Media, options, Variants/SKUs, active/hidden and related Products.
5. Add a Product storefront presenter matching the existing Blade composition.
6. Route only this slug through the presenter; leave four Product routes static.
7. Preserve the displayed TZS value through a clearly isolated transitional display value only if authoritative Pricing is not yet implemented; it must not power checkout.

Acceptance: Admin edit -> save -> exact Oxford Shirt template reflects details/media/options/SKU; inactive Product fails closed or uses explicitly documented fallback; no generic Page/publisher workflow.

## ECOM-PROD-2 - Catalogue and Pricing (implemented by ECOM-CATALOGUE-CORE-1)

Add integer-minor Product base price, optional Variant override, currency and compare-at price; create effective-price service. Migrate remaining Products and exact Shop cards/query/search/filter/sort/pagination. No cart until pricing is authoritative.

Core schema, Admin pricing, Categories, flexible option synchronization,
Colour-level galleries, dynamic Product lookup and presenter are implemented.
Remaining template-by-template canonical imports and complete Shop/card adoption
are deliberate follow-on migration work, not Inventory work.

## ECOM-PROD-3 - Collections and merchandising

Build direct-save Collection Admin and public presenters. Add exact named Product/Collection placements and related-Product editor. Feed Collection index/detail and existing Homepage placements without changing their design.

## ECOM-PROD-4 - Campaigns

Build simplified Pre-Order/Limited Edition editors and effective public readers. Keep claim approval only for evidence-sensitive scarcity/window claims.

## ECOM-PROD-5 - Homepage fixed composition

Implement the existing twelve sections as one typed schema. References resolve Product/Collection/Campaign truth; fixed content owns only section copy/media/CTA. Preview uses the same storefront presenter; Save is direct.

## ECOM-PROD-6 - Inventory

Add locations, SKU InventoryItems, immutable movements and balances; then reservations. Provide Stock, Locations and Movement screens using operational language.

## ECOM-PROD-7 - Cart, checkout and Orders

Implement server-authoritative Cart lines, price snapshots, reservation, customer/delivery capture and idempotent production Order intake. Evolve the current Order aggregate and remove demo wording from client UI.

## ECOM-PROD-8 - Customer, communications and Gift Cards

Add separate customer identity/account, Orders/receipts/wishlist, transactional outbox with Email/WhatsApp adapters, then the monetary Gift Card ledger.

Each phase uses focused tests and one representative physical storefront path. A full audit remains separately authorized.
