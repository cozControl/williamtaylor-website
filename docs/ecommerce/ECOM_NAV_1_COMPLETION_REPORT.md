# ECOM-NAV-1 completion report

Date: 2026-09-09

**ECOM-NAV-1 IMPLEMENTATION READY FOR GENERAL INSPECTION**

Implementation and focused checks are complete. Browser discovery returned no available browser; physical/visual acceptance remains paused. This is not a full audit or a claim of pixel-level acceptance.

## Discovery and implementation

1. **Status:** Ready for general inspection under the phase's limited verification policy.
2. **Protected desktop:** `public/website/js/index-DxdnTNDA.js` defines Shop, Collections, New Arrivals, Pre-Order and Limited Edition. Shop is a narrow, single-column oxblood dropdown, not a mega-menu. Its old children were New Arrivals (`/shop?sort=newest`), Men's Wear, Unisex, Accessories and All Products. It uses hover, a 192px width, gold border, large shadow, uppercase labels and a fade/8px translation. Top-level New Arrivals is intentionally repeated outside Shop in the supplied design.
3. **Protected mobile:** Left drawer, black 60% backdrop, 85% width with a 24rem maximum, logo/close header, scrolling navigation and a 300ms slide. Shop children start expanded on a dark inset background. Top-level commerce shortcuts also exist outside Shop. Account/navigation links are separate from the header search and bag controls. The implementation retains these compositions and makes Shop collapsible.
4. **Navigation architecture:** Canonical SiteContent `primary_navigation` owns immutable revisions and the publication workflow. `ResolvePublicSiteChrome` exposes safe public DTOs. The navigation rollout switch remains independent of commerce. Current local primary-navigation draft and published item lists are empty, and its rollout switch is off; commerce navigation works in that state.
5. **Ownership:** Navigation continues to own ordinary editorial links and the existing Shop label where configured. Catalogue owns generated Collection destinations. Homepage's existing Collection relation supplies New Arrivals; Campaign routes supply Pre-Order and Limited Edition. No Collection links or new Shop settings were persisted as Navigation records.
6. **Eligibility:** Navigation uses the same public Collection route conditions: not archived, `catalogue_status=ready`, and a current revision. Deleted/missing rows cannot appear. Navigation needs no thumbnail or separate Product readiness calculation. The Collection index additionally uses the existing card presenter's ready-Media/effective-alt eligibility, so an image-incomplete Collection can remain a valid text destination but have no discovery card.
7. **Order:** `collections.navigation_order` ascending, then canonical slug as the documented deterministic tie-break. This is not database-ID or alphabetical primary ordering. New Collections default to 1000.
8. **Schema:** No Collection-level order existed; only Product membership positions and the Admin list's newest-updated order existed. Added migration `2026_09_09_050000_add_collection_navigation_order.php`, with an indexed unsigned integer. Existing rows receive sequential values preserving that existing Admin sequence at migration time. Only this forward migration was applied locally; no rollback, fresh database or re-migration was run.
9. **All Collections:** Existing named `collections.index` at `/collections` now uses `StorefrontCollectionsController`, replacing the static page. It resolves current canonical Collections and reuses `CollectionCardPresenter`. No redirect to Shop.
10. **Index UI:** Reuses the accepted Explore the Collection article composition: 3:4 image, effective alt, oxblood gradient, cream title, description and gold Explore action. One column on small screens and three from 768px. No Product filters, sorting or other Shop controls. Homepage's original partial is unchanged.
11. **New Arrivals:** Reads `HomepageHero.new_arrivals_collection_id`. Only when no relation is set does it use the known `new-arrivals` identity. A configured but hidden/missing Collection is omitted, not replaced by another Collection. Current local URL is `/collections/new-arrivals`; tests verify a changed slug remains canonical.
12. **Deduplication:** The promoted Collection is excluded from the general Shop list and rendered once among its special links. The supplied top-level shortcut outside Shop is retained explicitly on desktop/mobile.
13. **Pre-Order:** Named `preorders.index`, `/pre-order`, remains Campaign-driven.
14. **Limited Edition:** Named `limited-edition.index`, `/limited-edition`, remains Campaign-driven. The old `/collections/limited-edition` menu destination is suppressed.
15. **Presenter:** `StorefrontShopNavigationPresenter` returns public labels, named-route URLs and active flags. It supplies All Collections, general Collections, New Arrivals, Pre-Order, Limited Edition and existing editorial DTOs. Blade performs no Collection queries.
16. **Desktop:** Shared desktop partial retains the existing five-link header composition and exact menu class vocabulary. Shop supports mouse hover, click, keyboard activation, Arrow Down into links, Escape, outside-click and focus-exit closure. Pointer hover is restricted to mouse so synthesized touch hover does not cancel a tap.
17. **Mobile:** Shared mobile partial consumes exactly the same Shop entries. Drawer opener, backdrop/close actions, initially expanded Shop accordion, scrolling, responsive dismissal and focus containment are wired. Available canonical Gift Cards, Account and Wishlist destinations remain separate. No new dead Track Order, Size Guide or FAQ routes were invented.
18. **Active state:** Named route checks mark Shop active on Collection/Campaign destinations. The matching child uses `aria-current=page` and the header's gold treatment; Collection detail compares the bound Collection identity rather than matching path substrings.
19. **Legacy links:** Compiled protected menu data remains untouched. Header copies in Blade are replaced by the canonical shared partial, including all five supplied Product templates. Published local commerce URLs are suppressed from editorial output, including nested entries; unrelated editorial children are retained. There were no local persisted legacy items to delete or migrate.
20. **Navigation Admin:** Added a concise non-editable ownership explanation to the existing navigation workspace. It directs staff to Catalogue / Collections rather than asking them to duplicate menu records.
21. **Collection Admin:** Added Storefront order alongside visibility, using the existing field/error style. Values are validated from 0 to 999999. `UpdateCollectionNavigationOrder` follows the existing transactional lock, archive guard, version increment, audit and cache invalidation pattern. Product membership order was not changed.
22. **Performance:** One eligible Collection query plus one eager-loaded revision query and one Homepage relation read build the dataset per request; schema guards also run once per request. The request attribute caches the resulting presentation across desktop, mobile and inert templates. No per-item media/Product queries in the header and no long-lived menu cache. All eligible Collections remain available; the desktop submenu scrolls within `min(70vh,36rem)` rather than imposing an arbitrary count limit. The index reuses the richer existing card presenter separately.
23. **Protected runtime:** New shared `shop-navigation-script` owns a server-rendered inert header template and an idempotent observer. A runtime-created header is replaced with that canonical header and controls are rebound once per replacement. Header ownership was removed from the older chrome synchronizer to avoid competing replacements; footer/WhatsApp handling remains there. Protected bundles and route bootstraps were not edited. Actual browser mount behavior still requires visual acceptance.
24. **Accessibility:** Real buttons for controls and links for destinations, expanded/control attributes, dialog naming, focus-visible outlines, Escape/Tab handling, restored opener focus, minimum mobile targets and reduced-motion styles. Bounded vertical scrolling uses no page-wide horizontal overflow suppression.
25. **Shared UI:** Existing `frontend.partials.header`, announcement, footer, document head, admin field components and Collection card presenter are reused. Added only desktop Shop markup, the shared menu behavior/template and the reused Collection article partial. Header height, fonts, spacing classes, icon set, route label/back affordance and 20px scroll-shadow threshold are retained.

## Verification

26. **Focused PHPUnit:**
    - `StorefrontShopNavigationTest`: **4 tests, 83 assertions passed**. Eligibility, explicit order, rename/slug changes, New Arrivals deduplication, named destinations, index cards/alt, shared six-route header, active output, Admin order validation/audit, and published editorial/legacy handling.
    - `CollectionAdminWorkflowTest`: **11 tests, 133 assertions passed**.
    - `PublicSiteContentProjectionTest --filter=test_only_the_current_published_revision_is_projected`: **1 test, 5 assertions passed**.
    - Total: **16 focused tests, 221 assertions passed**. Initial failures exposed a copied Product header and test fixtures that bypassed immutable revisions; corrected before the final green runs.
27. **Other checks:** Scoped Larastan passed with zero errors for the presenter, ordering action, Collection model and two affected controllers. Changed-file Pint passed for nine PHP files; those nine files passed PHP syntax checks. Blade cache compilation passed. New menu JavaScript passed `node --check`. `git diff --check` passed; only existing CRLF normalization warnings were printed. No full suite, full analysis, build, fidelity matrix or dependency audit was run.
28. **Apache response evidence:** All routes below returned 200 with one root header and matching desktop/mobile Shop labels, destinations and active flags. Live Shop entries: All Collections, Shoes, Men's Wear, Women's Wear, New Arrivals, Pre-Order, Limited Edition. Draft Kosdn is omitted.

| Route | Body evidence | Current menu destination |
| --- | --- | --- |
| `/` | William Taylor heading; canonical Shop header | None |
| `/collections` | All Collections heading; four canonical cards | All Collections |
| `/collections/new-arrivals` | New Arrivals heading | New Arrivals |
| `/pre-order` | Pre-Order heading | Pre-Order |
| `/limited-edition` | Limited Edition heading | Limited Edition |
| `/products/tshirt` | Tshirt heading | None |

29. **Browser:** One discovery attempt returned `[]`. No retry or alternate browser mechanism was used. No screenshot/interaction/visual acceptance is claimed; physical verification remains paused.
30. **Homepage:** No Homepage section content, visibility, managed/unmanaged projection or registry changes in ECOM-NAV-1. Earlier phase changes already present in the workspace are separate. Only the shared navigation consumed by Homepage changes here.
31. **Shop:** Full Product Shop migration was not started. `/shop` content/filtering/sorting/search/pagination were not implemented; its duplicated header now uses the shared renderer.
32. **Next phases:** Follow the Journey was not started. No subsequent phase was started automatically.

The full-audit authorization gate in `AGENTS.md` remains in force. General inspection and later physical acceptance are distinct from the focused evidence above.
