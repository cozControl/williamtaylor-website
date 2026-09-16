# CATALOGUE-COLLECTIONS-1: Collection storefront and Category ownership

Date: 2026-09-16

**Implemented and checkpoint-validated, including the follow-up request for individual Collection destinations in `/admin/homepage/hot-sale`.** Both forward migrations were applied to the local database. No historical ownership decision remains unresolved in this database. Deployment and the separately gated full audit are not claimed.

## Existing architecture and design source

Inspected the actual rendered `/shop` before extraction. It used an imported runtime with demo Products, an oxblood patterned catalogue banner, Category/Size filters, five sort choices, grid/list controls, heart buttons, cream background and responsive Product cards. It was not a server-backed canonical catalogue. The desktop filter panel worked; its existing `hidden lg:block` treatment left the mobile Filters button without a usable panel.

The implementation preserves that visual language and supplies canonical data through shared Blade components. Shop and Collection pages use the same banner, toolbar, filter controls, result count, grid/list, cards, pagination and empty states. A mobile drawer makes the existing filters usable with scrolling, Escape, backdrop dismissal and focus containment. No additional colour, price or stock filter was invented.

Canonical Product cards retain the existing Media, TZS pricing, badges and Variant availability presenters. Heart controls retain browser-local saving: canonical Product slugs now populate the existing Wishlist presentation. This required a small canonical Wishlist controller and browser script because the imported Wishlist understood only demo numeric IDs. No account Wishlist persistence was added.

## Domain and schema

Products retain their canonical `collection_products` memberships. Category ownership classifies Products within a Collection; it does not create, remove or replace those memberships.

- `Collection::categories()` and `ProductCategory::collection()` express ownership.
- `product_categories.collection_id` is required and references Collections with restricted deletion.
- Category uniqueness is `(collection_id, slug)`, with an owner/visibility/order index.
- Admin routes continue binding Categories by their existing identity. There was no global public Category-detail route to break.
- On global Shop, Category query values use `collection-slug/category-slug` to distinguish repeated slugs. Within a Collection they use the Category slug.

Migration `2026_09_16_180000_scope_product_categories_to_collections.php` resolves every historical mapping before DDL. It infers only a single unambiguous owner covering every assigned Product; orphaned, shared or incompatible parent mappings fail with the Category identity. SQLite table rebuilding checks foreign keys before committing. MySQL DDL is not represented as transactional. Reversal refuses to restore global slug uniqueness when scoped slugs overlap; no local rollback was performed.

## Historical data decision and applied outcome

Read-only inspection found one Category: **Category One**, assigned to four Products. All four belong to New Arrivals; two also belong to Women's Wear Updated and one also belongs to Shoes. The user explicitly decided:

> Make Category One belong to New Arrivals; preserve its four existing Product assignments.

The migration records that exact approved mapping and refuses it if the four approved assignments or their New Arrivals membership have changed. It does not generalize the exception to other databases or Categories.

The local forward migration completed. Before/after snapshots verify:

- Category One belongs to New Arrivals.
- All four Category assignments are unchanged.
- All eleven existing Collection membership rows, including archived rows, are unchanged.
- Collection records and the Category's other attributes are unchanged.

Evidence: `storage/logs/collections-ownership-before.json` and `collections-ownership-after.json`. The read-only helper is `scripts/evidence/collection-ownership-audit.php`. No Product was duplicated, reassigned, deleted or silently placed into another Collection.

## Admin management

Category create/edit requires a Collection using the existing Admin field component. The register exposes owner, parent, visibility/archive status, Product count and update date, with a Collection filter and existing search. Product editors identify Categories by their owner as well as name.

Parent Categories must belong to the same Collection. Reassignment requires every assigned Product to already have an active membership in the destination Collection and refuses Categories with children until those relationships are deliberately resolved. The action changes Category ownership only. Archive continues preserving Product assignments. Existing permissions, audit records and Media selection remain in place.

The Oxford bootstrap command requires an explicit existing Collection for creation of its Category; it cannot silently create a global ownerless Category. Test/browser fixtures now specify an owner explicitly.

## Routes, shared components and filtering

| Route | Behavior |
| --- | --- |
| `/shop` | Global canonical Product catalogue |
| `/collections/{collection:slug}` | Same listing scoped to active canonical memberships in a public Collection |
| `/shop?collection={slug}` | Permanent redirect to the canonical Collection URL, retaining other query state |
| `/wishlist` | Existing visual presentation backed by browser-local canonical Product selections |

Both listing views include `resources/views/frontend/catalogue/listing.blade.php`, with shared header, toolbar, filters, results and styles. Existing Product cards and storefront chrome are reused. Collection title and description populate the established banner; no new Collection hero was introduced.

`StorefrontCatalogueQuery` filters and paginates in the database, twelve Products per page. It uses the database form exposed by `CatalogueReadinessEvaluator::publicQuery()` for both Shop and Collection listings. Its readiness projection covers the evaluator's revision, price, primary Category, Variant/default SKU, option and Media rules; parity cases verify structural failures. The existing per-Product evaluator remains unchanged. Only exceptional malformed legacy Media alt-text rows containing markup require the existing PHP plain-text rule; Products/Variants are not loaded wholesale for filtering.

Category facets require the current Collection's ownership, active/public state and eligible Products. Foreign or invalid Category values are ignored without broadening Collection scope. Sizes come from active option values used by relevant active Variants; selections combine with Category constraints. Product publication and readiness remain independent of stock, and availability comes from `InventoryAvailabilityService` through the existing card presenter.

Sort controls retain Featured, Newest, Price ascending/descending and Bestselling. Collection Featured follows membership position; Bestselling reads quantities from canonical paid Orders. It does not change Orders or inventory or fabricate a sales badge. Query state supports refresh, back/forward and pagination; filter changes reset the page. Counts and empty results come from the same filtered query. Card relations and availability are loaded in batches; focused tests check bounded query behavior.

The existing document-head infrastructure receives Collection title, description, canonical URL, robots and Open Graph context. Filtered pages use `noindex,follow`; the legacy query URL redirects rather than becoming a second canonical Collection page.

## Collection links and Hot Sale follow-up

Canonical Collection URLs now cover Collection cards/index, managed Homepage sections, Hero Collection destinations/New Arrivals, Product Category breadcrumbs, Shop links and footer/CMS navigation. Default Homepage Explore cards resolve their legacy destinations through the same canonical map, falling back to the Collection index when the named Collection is unavailable. The existing Laravel-owned synchronization boundary normalizes links recreated by the imported runtime and preserves native navigation on clicks. Protected compiled assets were not edited. Pre-Order and Limited Edition retain their own Campaign routes.

**Each of the three Hot Sale Destination selects now includes individual public Collections**, alongside Shop, Shop—Newest and the Collections index. It reuses the existing Hero Collection destination registry and stores `collection:<stable ID>`, resolving the current slug when rendered. Draft/archived/missing Collections cannot be selected on save; a previously saved unavailable Collection falls back to the Collection index and the editor asks for an available destination.

Migration `2026_09_16_190000_expand_hot_sale_collection_destinations.php` expands the three destination columns from 32 to 64 characters while preserving their defaults. It was applied locally; before/after snapshots prove existing destinations and the Homepage lock version were preserved. The local registry now offers New Arrivals, Shoes, Men's Wear and Women's Wear Updated. No live Hot Sale selection was changed. Existing authorization, Media requirements, optimistic locking, audit and section visibility are retained.

## Validation

| Check | Result |
| --- | --- |
| Final Catalogue, Collection, Product, Merchandising, storefront, Hot Sale, Explore and availability regression subset | **155 passed, 1,546 assertions; zero failures/errors** |
| New Hot Sale and corrected availability/Explore checkpoint | 18 passed, 359 assertions |
| Earlier commerce/Homepage fixture-impact checkpoint | 160 tests: 157 passed, three outdated view-data/whole-page assertions failed; all three corrected and passed in the final subset |
| Distinct tests across the final subset and earlier impacted checkpoint, taking the latest result for overlaps | 304 distinct passing tests, 5,885 assertions; this is not a single run or full-suite claim |
| Scoped Larastan, 17 changed application files | Passed, zero errors |
| Final navigation follow-up after desktop editorial-link normalization | 6 passed, zero failures/errors |
| Pint on changed PHP files; final check of latest edits | Passed |
| Local ownership and Hot Sale forward migrations | Passed; before/after data preservation verified |
| Focused browser run at 375×812, 768×1024 and 1440×900 | Passed, 29 recorded states/checks and zero JavaScript page errors |

The earlier commerce checkpoint included Payments, Checkout, Inventory and Cart with HTTP fakes. Its only failures were Collection availability tests requesting the old view-data key and an Explore assertion incorrectly rejecting an independently public footer link. Those assertions now inspect the canonical card projection and the specific Homepage section, preserving their original behavioral guarantees. No payment lifecycle source changed.

Focused coverage includes ownership requirements and scoped uniqueness, safe migration failure and preservation, authorization, owner changes/parent/archive behavior, publication/readiness, cross-Collection filters, canonical availability, URL state, sorting including paid-Order ranking, pagination, query counts, Wishlist behavior, and all three Hot Sale destination fields with slug changes and lifecycle fallback.

### Browser evidence and limits

Final disposable run: `storage/app/test-runtime/collections-1789564036022/`.

At all three widths, checked Shop/Collection grid equivalence, scoped and long filter lists, active Category, zero results/clear filters, pagination/refresh/back, sort, list mode where exposed, empty Collection, header/footer and horizontal overflow. Also exercised canonical Homepage Explore and Collection-index clicks, Wishlist add/remove, Admin Category screens, and an actual Hot Sale form save followed by clicking its Homepage tile to the selected Collection.

The browser run used isolated SQLite, fixture Media mapped to a local image and HTTP requests restricted to the disposable host. Only the fixture Hot Sale editor save permitted a POST. No provider traffic or live application data mutation was allowed. Screenshots verify layout with those fixtures, not remote CDN availability or a global immutable fidelity baseline. Initial diagnostic runs exposed a fixture missing its refreshed lock version; that fixture setup was corrected before the final passing run.

Read-only checks on the actual local database also returned `/shop`, New Arrivals, Shoes and Women's Wear pages with the expected canonical Product counts. An additional actual-homepage Explore click probe could not run because the user has hidden that section; the resolved visibility setting was confirmed false and preserved. Its click flow was exercised in the visible disposable fixture instead.

Evidence includes:

- `storage/logs/collections-final-regression.xml` and `.log`
- `storage/logs/collections-commerce-homepage.xml`
- `storage/logs/collections-hot-sale-tests.xml`
- `storage/logs/collections-navigation-final.xml`
- `storage/logs/collections-types-final.log`
- `storage/logs/collections-pint.log` and `collections-pint-final.log`
- `storage/logs/collections-browser-final.log`
- `storage/logs/hot-sale-destinations-before.json` and `hot-sale-destinations-after.json`
- `storage/app/catalogue-collections-evidence/` (before screenshots and actual local listing results)
- The final disposable browser directory above (`results.json`, screenshots).

## Completion boundary

The approved ownership mapping and requested implementation are complete locally. Deployments with different ambiguous historical Categories must resolve their reported ownership deliberately before migration; no universal fallback owner is assumed.

No full Laravel suite, full Larastan, complete build, dependency audit, global fidelity/self-check, MySQL fresh/rollback/re-migration, baseline approval or live Snippe payment was performed. The separate full-audit gate in `AGENTS.md` still requires `AUTHORIZE_BE6AXB_FULL_AUDIT` in the user's latest message. Existing secrets, payment behavior, inventory data and protected baselines remain outside these changes.
