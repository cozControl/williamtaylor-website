# COMMERCE-INVENTORY-1A completion report

Date: 2026-09-10

**COMMERCE-INVENTORY-1A IMPLEMENTATION READY FOR GENERAL INSPECTION**

Foundation and Admin implementation are ready for general inspection. Physical/visual verification remains paused because the single browser discovery returned no browser. This report does not claim a full audit or browser acceptance.

## Discovery mapping

| Current capability | Production inventory safety | Reuse / required change |
| --- | --- | --- |
| Product and Variant ULIDs, immutable content revisions, optional Colour/Size values, default Variant | Canonical catalogue identities already exist | Reuse without adding Product stock |
| Product option combination fingerprints, SKU uniqueness, non-archived Variant selection | Suitable stock identity | Reference the existing Variant; preserve its SKU/options |
| Product/Variant prices in integer minor units | Suitable pricing, independent of units | Reuse untouched; no valuation in this phase |
| Catalogue readiness (content, pricing, category, media, Variant integrity) | Suitable content readiness | Keep independent of stock |
| Sold-out badge registry and static storefront labels | Presentation, not inventory authority | Do not derive inventory from them; defer public integration to 1B |
| Demo Order quantities and snapshots | Demo-only, no stock commitment | Preserve; no production Order or movement integration |
| Stock quantities, locations, ledger, balance or reservations | No implementation found in app/migrations/routes | Add the bounded Variant/location ledger foundation |
| Inventory Manager role | Existed with Admin access only | Add inventory view/manage and Product read access |
| Existing audit, permission registry, Admin shell and Catalogue patterns | Reusable | Extend narrowly |

## Required implementation record

1. **Status:** COMMERCE-INVENTORY-1A IMPLEMENTATION READY FOR GENERAL INSPECTION, subject to the explicit visual verification limitation above.
2. **Existing inventory:** No `stock_quantity`, warehouse, inventory balance or authoritative `in_stock` field existed. Demo Order quantity is unrelated. No competing inventory source was created.
3. **Product/Variant architecture:** Reuses Product, ProductVariant, ProductOption, ProductOptionValue, default Variant identity, immutable current revisions, SKU architecture and existing prices. Product Create already generates saved combinations; no stock is required to create them.
4. **Variant-level ownership:** Every balance and movement has a Variant foreign key and a Stock Location foreign key. No Product-level stock input or counter.
5. **Locations:** New StockLocation model/table with ULID, unique code, name, active/fulfillment flags and timestamps. Multiple locations are supported by storage and explicit queries; transfers/allocation/complex location CRUD are deferred.
6. **Default:** Deterministic Main Store, code MAIN, valid 26-character ULID. One location was inserted by the forward migration; no stock was inserted.
7. **Ledger:** InventoryMovement records type, signed whole-unit delta, balance after, reason/note, UTC occurrence/creation timestamps, actor, optional source identity, unique optional idempotency key and request fingerprint. Balance-after is context; summed deltas remain authoritative.
8. **Types:** Opening, Receipt, Adjustment In and Adjustment Out are implemented with an application enum/string column. Future issue/return types can extend it without a destructive schema enum change.
9. **Immutability:** Updating/deleting movement models throws. Admin provides no edit/delete action. Foreign keys restrict deletion of referenced identities. As with existing immutable models, privileged direct SQL can bypass model events; no database-trigger enforcement is claimed.
10. **Projection:** InventoryBalance is unique per location/Variant, stores integer on-hand and timestamps, and is written by the posting service. Missing rows mean zero.
11. **Atomicity/locking:** Product/Variant parent locks serialize first-balance creation and protect lifecycle state; a shared location lock protects operational settings, with row locks for balances and current idempotency reads. Ledger, projection and existing audit commit together. A failed projection is proven to roll back the movement and audit. Laravel retries transaction deadlocks up to three attempts.
12. **Negative stock:** Whole-number validation and arithmetic bounds reject operations below zero. Explicit outward service operations report the on-hand quantity/location. Counted totals cannot be negative; the service derives the direction/delta under lock.
13. **Idempotency/references:** Optional durable unique key plus fingerprint; matching retries return the same movement, conflicting reuse fails validation. Admin uses a per-submission UUID. Optional source type/ID are available to later integrations; no Order deduction exists now.
14. **Availability:** InventoryAvailabilityService exposes onHand, availableToSell, productHasAvailableStock and bulk Admin summaries. Omitted location means MAIN, not an unapproved all-location sum.
15. **Calculation:** Reservations are zero. For eligible Variants at active fulfillment locations, available-to-sell equals on-hand. Archived identities, missing SKU and disabled fulfillment are not sellable, while physical stock remains inspectable. Product stock checks require at least one eligible stocked Variant; content readiness remains separate.
16. **Derived state:** No authoritative stored in-stock boolean was introduced.
17. **Admin workspace:** `/admin/inventory` is permission-controlled and appears after Campaigns in Catalogue. Search covers Product title and SKU; location, derived stock status and include-inactive controls are provided. Results are paginated to 25.
18. **Identity:** Rows show Product title, option labels where present and SKU. No artificial Default / Default or raw Variant ID as primary identity. Inactive records are identified explicitly.
19. **Opening Stock:** Available before the first movement at that Variant/location. Recorded as a positive Opening movement; later opening attempts are rejected.
20. **Receive Stock:** Choose a Variant from Inventory, select the location and Receive Stock operation, enter units/reason and optional reference note, then Record stock. Posting yields a success message and updated history/balance.
21. **Adjustment:** Stock count adjustment shows current on-hand and accepts the counted total. The service computes Adjustment In/Out, requires a reason, rejects unchanged counts and rejects stale displayed balances for reload/recount.
22. **History:** Variant/location detail includes paginated permanent movements, date/time, actor, movement label, signed change, balance after and reason/reference note. The page heading retains Product/Variant/SKU/location context. No technical polymorphic class names are displayed.
23. **Product integration:** Existing Product edit now contains a separate Stock by Variant summary and Manage Inventory/Add stock links for authorized staff. It uses bulk balance loading and does not redesign the Product form.
24. **New Product workflow:** Save Product/options/Variants first, then use its Inventory summary. New Variants legitimately start at zero; Product creation is not blocked by stock.
25. **Existing data:** Five Products and 21 Variants were preserved exactly by pre/post checksums. Existing zero Orders/Order Items remain unchanged. No opening stock was fabricated. Persistent movements and balances both remain zero after all checks.
26. **Permissions:** Added inventory.view and inventory.manage. Super Administrator and the existing Inventory Manager receive both; Inventory Manager also receives Product view for summaries. CMS Manager is not silently given stock mutation authority. Routes, UI visibility and the service enforce permissions; view-only access cannot post.
27. **Audit:** Uses RecordAuditEvent with inventory.opening, inventory.receipt, inventory.adjustment_in/out. Before/after stock and signed change are recorded in the same transaction. Exact retries create no second ledger/audit entry.
28. **Reconciliation:** `php artisan inventory:reconcile` compares grouped ledger/projection totals, including unmatched pairs. Optional location/Variant filters are supported. It streams results, reports drift and returns failure on drift. Default is read-only; no repair switch or silent mutation.
29. **Query/index behavior:** Unique location/Variant projection, movement-history index, Variant/location foreign keys, source lookup index and unique idempotency index. Index page uses eager-loaded identities and a bulk balance query; history is paginated to 20. Product summaries avoid per-Variant balance queries. No long-lived availability cache.
30. **UI references/reuse:** Inspected Catalogue overview, Product index/editor and existing Collection/Admin field patterns. Reuses x-admin.layout, page actions, panels, field validation, flash messages, button vocabulary and Back-left / Record-right placement. Added only scoped Inventory tables/metrics/status/form styles and the identity/summary partials. Boxy controls and restrained existing cream/ink/gold palette are retained.
31. **Responsive implementation:** Filters reflow at 1024px; at 768px table rows become labelled blocks, with readable identity and numeric values; 480px rules stack operation fields/actions/metrics for 430px use. Wrapping and minmax constraints avoid page-level horizontal overflow hacks. These are implemented CSS boundaries, not browser-verified measurements.
32. **Focused tests:** InventoryFoundationTest: **10 tests, 90 assertions passed**. Covers zero stock, all four movement types, negative/fractional/blank/stale/no-op validation, repeated opening prevention, immutability, projection/ledger consistency, Variant/location isolation, replay/conflict, counted replay, balance uniqueness, availability, inactive identities, permissions, Admin flows/search/history/Product summary, audit and transaction rollback. SQLite tests verify contracts; no simultaneous MySQL writer stress test was run.
33. **Analysis/format/syntax:** Scoped Larastan passed for the new Inventory domain/controller/command and modified Product controller, identity registries and navigation registry. Changed-file Pint and PHP syntax checks cover 16 PHP files. Blade compilation passed. No new JavaScript was added. `git diff --check` passed. No full suite, full Larastan, BE-6A, dependency audit, full build or browser matrix was run.
34. **Migrations/data/service evidence:** Applied only `2026_09_10_010000_create_variant_inventory_foundation` and `2026_09_10_010100_register_inventory_permissions` forward. Local reconciliation: zero balances checked, zero drift. A disposable MySQL transaction verified replay, count from 5 to 3 and ledger total 3 with locking statements observed; it was rolled back completely. No persistent inventory was left. Authenticated local-kernel GETs of Inventory index, Variant detail and Product edit each returned 200 with the expected inventory content.
35. **Browser:** One discovery attempt returned `[]`; no retry or alternate mechanism. No visual acceptance or screenshot claim. Physical verification remains paused.
36. **Future architecture:** `COMMERCE_INVENTORY_ARCHITECTURE.md` documents on-hand/reserved/available quantities, Cart checks, Checkout revalidation, atomic reservations, confirmation/issue, cancellation release and return/restock. Future reservations change the internal calculation without changing callers. Cart never changes inventory.
37. **Cart/Checkout/payments:** NOT implemented.
38. **Orders/reservations:** Production Order, stock issue and reservation persistence were NOT implemented. Existing demo Order code/data were not changed.
39. **Public storefront:** Product cards/detail, Homepage/Collection cards, public stock UI and Add to Cart were NOT changed by this phase. COMMERCE-INVENTORY-1B was not started.

The full-audit authorization gate in AGENTS.md remains in force. General inspection readiness is distinct from physical acceptance and future production Order readiness.
