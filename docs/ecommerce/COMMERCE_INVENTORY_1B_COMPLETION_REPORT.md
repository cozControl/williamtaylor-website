# COMMERCE-INVENTORY-1B completion report

Date: 2026-09-10

**COMMERCE-INVENTORY-1B IMPLEMENTATION READY FOR GENERAL INSPECTION**

Implementation and focused checks are complete. Physical verification remains paused: the single browser discovery returned no browser. This report does not claim visual acceptance or a full audit.

1. **Status:** Ready for general inspection, subject to the browser limitation above. COMMERCE-CART-1 has not started.
2. **Existing availability UI:** Canonical Product detail already resolved options, SKU, price and media, but had no authoritative stock state. Imported demonstration purchase controls used independent client state. Some original Product templates were static; canonical records now use the existing canonical Taylor detail layout.
3. **Existing badges:** The catalogue contains a sold-out badge definition. Imported storefront styles provide compact uppercase badge treatment; no reusable authoritative inventory resolver existed in the compiled storefront.
4. **Readiness boundary:** Catalogue readiness remains a content/pricing/media/Variant concern. Zero stock does not unpublish, hide, or invalidate a ready Product.
5. **Location:** Uses the existing MAIN location through InventoryAvailabilityService. Inactive/non-fulfillment locations do not provide sellable stock. No all-location sum was introduced.
6. **Product rule:** A Product is available when at least one eligible Variant has positive available-to-sell stock. Archived identities, missing SKU and inactive assigned options/values are excluded.
7. **Variant projection:** The service composes canonical balances and eligibility into a bulk Product/Variant map. Each public Variant receives a binary is_available flag. The current default Variant remains selected even when another Variant has stock.
8. **Public quantities:** Exact available-to-sell quantities stay in PHP. Public payloads expose availability flags, without inventory balance, location, ledger or scarcity information.
9. **ProductCardPresenter:** Adds derived Product availability, warms identities in bulk, and suppresses the legacy operational sold-out badge from presentation. Shared cards render a derived Out of Stock badge and retain their Product link.
10. **Product detail:** ProductPresenter composes the same service map for the selected Product and related Products. Canonical detail disables imported SPA mounting so demonstration Product/Cart state cannot replace the server projection.
11. **Colour and Size:** Colour selection retains media preview behavior and recomputes Size availability. The exact selected combination determines SKU, price and purchase state. Missing combinations are unavailable; no stocked alternative is selected automatically.
12. **Colour only:** Colour maps directly to its Variant. Unavailable Colours remain inspectable and show an unavailable treatment; their purchase controls are disabled.
13. **Size only:** Each Size uses its Variant availability. Unavailable Sizes are disabled. An unavailable default remains the displayed initial selection.
14. **No options:** The canonical default Variant controls availability directly, without artificial option labels.
15. **Unavailable options:** Colours receive a muted/dashed treatment and accessible unavailable wording while remaining selectable for inspection. Sizes receive disabled/struck treatment relative to the selected Colour.
16. **Purchase controls:** Unavailable selections disable Add to Cart and Buy Now and display Out of Stock. Stocked selections restore their normal visual state. A shared click boundary prevents these controls and imported demonstration purchase actions from persisting Cart data.
17. **Gallery/SKU/price:** Focused tests preserve media and colour-image payloads, SKU and price across inventory changes. Pure resolver assertions cover combination-specific SKU/price. Existing gallery code and thumbnail composition are retained. Browser interaction fidelity is not claimed.
18. **Collections:** Zero-stock Products remain linked, in their configured order, with the shared derived badge. No inventory-based collection filter was added.
19. **Homepage/shared cards:** Managed New Arrivals, Handbags, Collection cards and canonical related Products reuse the same availability projection. New Arrivals and Collection ordering/zero-stock rendering have focused coverage. Unconfigured static reference content remains a fallback rather than an inventory authority. Campaign/collection media tiles are not ordinary Product stock cards.
20. **Campaign boundary:** Pre-Order and Limited Edition retain their approval, claims and scheduling semantics; zero immediate MAIN stock does not invalidate them. A focused approved Pre-Order projection test passes without stock. Following a canonical Product link uses ordinary immediate-stock rules; no preorder purchasing workflow was added.
21. **Sold Out authority:** Inventory-derived status replaces the legacy sold-out badge in canonical Product presentation. Persisted badge records are untouched; other editorial badges remain intact.
22. **Bulk queries:** Consumers warm represented Product IDs together. Variants, option eligibility and balances are loaded in bulk. A focused two-Product test bounds the initial query count and proves repeated projection reads in the same request add no queries.
23. **Caching:** The presentation map is request-scoped only. A subsequent request reads current stock without publication or cache clearing. Future Cart checks must call fresh availability rather than trust the public snapshot.
24. **Opening Stock propagation:** The focused HTTP test posts Opening Stock from zero to three and observes available detail/card state on the next request. No persistent catalogue stock was fabricated.
25. **Adjustment propagation:** Counting back to zero restores disabled purchase controls and Out of Stock detail/card state on the next request. Ledger/projection quantities remain consistent.
26. **Accessibility:** Stock text uses a polite live status region; option labels include unavailable state, selected options use aria-pressed, and unavailable purchase/Size controls use native disabled attributes. Colour inspection remains possible.
27. **UI reuse:** Reuses the existing canonical Product layout, cream/ink/oxblood/gold palette, compact uppercase badge typography and button styles. Protected compiled assets were inspected but not edited. Visual acceptance remains pending.
28. **Focused tests:** StorefrontAvailabilityTest: **4 tests, 87 assertions passed**. InventoryFoundationTest regression: **10 tests, 90 assertions passed**. Approved Campaign projection: **1 test, 22 assertions passed**. Node Variant resolver: **12 assertions passed**, covering both options, each single option, no options and missing combinations.
29. **Scoped checks:** Scoped Larastan passed for the eight changed production PHP files. Changed-file Pint passed for nine PHP files; PHP syntax, Blade compilation, changed JavaScript syntax and git diff --check passed. No full suite, full analysis, build, dependency audit or fidelity matrix was run.
30. **HTTP/database evidence:** A disposable outer MySQL transaction exercised the actual local HTTP kernel for /products/tshirt at quantities 0, 3 and 0. Every response was 200; status was Out of stock, In stock, Out of stock; purchase disabled state was true, false, true; ProductCardPresenter availability was false, true, false. Receipt/count writes and audit entries were rolled back, and the persistent movement count was unchanged. This is local HTTP-kernel evidence, not browser or separate Apache-process acceptance. Collection/New Arrivals rendered-card evidence is supplied by the focused HTTP tests.
31. **Browser:** One discovery returned an empty list. No retry or alternate browser mechanism was attempted. No screenshot or physical acceptance is claimed.
32. **Cart:** NOT implemented. No line persistence, session/local-storage mutation or production Cart endpoints were added.
33. **Reservations:** NOT implemented. Reservations remain zero under the existing foundation; storefront reads never post stock movements.
34. **Orders/payments:** NOT implemented. Production Order, Checkout, payment and stock issue behavior remain outside this phase. Existing demo Order data was not changed. No migration or persistent stock operation was required by 1B.
35. **Next contract:** COMMERCE-CART-1 must accept Variant ID and requested quantity, resolve canonical Product/Variant server-side, check fresh availableToSell, and reject excess quantities on both add and update. Cart makes no reservation or stock movement. Checkout revalidates atomically; reservations begin only in the authorized Order phase. This contract is recorded in COMMERCE_INVENTORY_ARCHITECTURE.md.

The AGENTS.md full-audit authorization gate remains in force. General inspection readiness does not imply visual or production purchasing acceptance.
