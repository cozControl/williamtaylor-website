# ECOM-HOME-9 — Women's Handbags

## 1. Status

ECOM-HOME-9 IMPLEMENTATION READY FOR GENERAL INSPECTION.

Implementation and focused checkpoint validation are complete. Physical browser acceptance remains pending: the single browser discovery attempt returned no available browsers. This is not full-audit closure.

## 2. Protected composition discovered

Inspected `resources/views/welcome.blade.php` and the existing `ehe` function in `public/website/js/index-DxdnTNDA.js` without modifying the protected bundle. The runtime explicitly filters `collection === "womens-handbags"` and applies `slice(0,6)`. Both actions target `/collections/womens-handbags`.

The off-white section follows Complimentary Delivery and precedes Client Stories. Eyebrow: **For Her**. Heading: **Women's Handbags**. Upper action: **View All**, hidden below the supplied `sm` breakpoint. Desktop uses a 12-column layout: four columns for the editorial hero and eight for a six-card Product grid. Product images use the shared 3:4 card composition; grid is three columns at 1024px and above, two below. Main gaps are 24px/32px; Product gaps 16px/24px.

Hero images are `a3ab973a9_waa2.png` and `0528d2077_wall1.png`, rotating every 5 seconds with 1.2-second fade/scale transitions and two clickable dots. No Product rail, pagination, or arrows exist. Hero copy: **New Collection**, **Crafted for Her**, “From totes to clutches — each piece handcrafted in our Dar es Salaam atelier.” Hero action: **Shop the Collection**. Hero retains its gradient, brand icon, minimum 400px mobile height, padding and typography.

Static Products: Savanna Tote Bag (TZS 420,000, NEW), Serengeti Shoulder Bag (TZS 350,000, BESTSELLER), Kilimanjaro Clutch (TZS 185,000, NEW), Zanzibar Crossbody (TZS 295,000), Dar Bag Mini (TZS 250,000, LIMITED / Only 35 Made), Arusha Bucket Bag (TZS 310,000, BESTSELLER). Standard card behavior includes image hover scaling, wishlist control, title/price and optional canonical swatches. Managed output does not copy static claims or fabricate stock quantities.

The corresponding static image files, in that order, are `fec1e30ee_generated_image.png`, `5e4275a15_generated_image.png`, `1aaa8bb8c_generated_image.png`, `21856d944_generated_image.png`, `62414b4d4_generated_image.png`, and `0f5271d44_generated_image.png`, served under `/website/images/`.

## 3. Accepted UI references

Inspected Homepage workspace, `admin/homepage/summer-edit.blade.php`, the canonical `admin/collections/form.blade.php`, and the existing Explore Collection picker/editor. Summer Edit supplies the section-editor composition; the Collection editor supplies identity and management context.

## 4–6. Domain decision and mapping

| Template element | Canonical owner | Implementation |
| --- | --- | --- |
| Curated six-Product preview | Collection and ordered CollectionProduct memberships | One typed `handbags_collection_id` reference on HomepageHero |
| Product identity, price, images, variants, badges, swatches | Product and its existing relations | ProductPresenter readiness followed by ProductCardPresenter |
| Both Collection actions | Collection | Existing `collections.show` route |
| Section and hero wording | Homepage placement | Bounded `handbags_*` fields |
| Two distinct editorial images | Homepage placement / canonical MediaAsset | Two existing MediaUsage records, roles `handbags_hero_1` and `handbags_hero_2` |

Collection is proven by the protected runtime's grouping, ordered preview and destinations. Category would substitute classification for deliberate merchandising order. Individual Product placements would duplicate Collection membership. Campaign lifecycle is absent. No new merchandising model, generic block table, Product-copy fields or alternate catalogue was created.

## 7–9. Capacity and field ownership

Capacity is exactly six eligible Products and two configured editorial image positions. Homepage owns managed flag, section eyebrow/heading/action label, hero eyebrow/heading/copy/action label and the Collection reference. Product title, slug, price, compare-at price, Media, badges, swatches, variants and readiness remain canonical. Collection visibility, identity, public URL and Product ordering remain canonical.

The forward migration `2026_09_09_020000_add_handbags_to_homepage_heroes.php` was applied alone. It adds typed fields and a nullable Collection foreign key; no database reset or rollback was performed.

## 10–14. Selection, readiness, order and Media

The editor reuses the existing paginated Collection endpoint and shared Media picker. The former inline Explore picker slot/dialog/script were extracted into shared partials, preserving its existing behavior. No catalogue-wide select or new picker API was introduced. A selected Collection displays image, name, URL, visibility and readiness, with a link to manage its Products and order.

Public Products pass `ProductPresenter::resolve()` before `ProductCardPresenter::present()`: visibility/archive, pricing, Media and default/sellable Variant checks remain canonical. Eligible Products retain CollectionProduct position order; invalid members are omitted before the six-card cap. Saved-section feedback includes ready and attention counts.

Editorial images must be confirmed, ready, unarchived images with effective alt. Alt is optional contextual MediaUsage override, then MediaAsset default. Product images and their effective alt come from the unchanged canonical Product-card presenter. The unchanged `frontend.partials.product-card` renders cards, including canonical price, compare-at price, badges and swatches.

## 15–17. Destinations and ownership modes

Cards link to `/products/{slug}`; both editorial actions link to `/collections/{slug}`. No Category route or Shop work is required. Existing wishlist presentation is retained; this phase introduces no commerce processing.

Unmanaged mode retains the complete original static section. Managed mode owns the complete section and its inert projection template. No static Products fill managed gaps. A missing/ineligible Collection or zero usable hero images emits a hidden managed section marker, preventing runtime fallback. One remaining valid image displays without dead slide dots. Zero eligible Products leaves the valid editorial Collection feature with an empty Product area; fewer than six renders only available Products.

## 18–20. Homepage and dedicated editor

Workspace position nine is Women's Handbags, after Complimentary Delivery. GET/PUT `/admin/homepage/womens-handbags` use existing settings view/manage permissions and Admin access middleware. Saving uses transactional optimistic locking and an audit event; it updates only this section and its two MediaUsage roles.

Editor panels: section status, section content, featured Collection, editorial copy and two image selections. Shared UI includes `x-admin.layout`, flash, field, media-picker, form-actions, section-summary, `admin-panel`, `admin-section-heading`, choice rows and entity-card/picker styles. Back is left; Save is right. A narrow scoped style keeps the single selected Collection thumbnail compact instead of expanding it to the full editor width. No new visual language was introduced.

## 21–25. Public pipeline and actual Apache evidence

Pipeline: persisted HomepageHero/MediaUsage → HomepageHandbagsPresenter → canonical Collection/Product presenters → `frontend.partials.homepage-handbags` → Apache `/` → inert `homepage-handbags-projection` → existing bounded synchronizer.

Temporary real-data fixture:

- Collection **New Arrivals**, ID `01m1v480fhmfn6beca07smkh0n`, `/collections/new-arrivals`.
- Heading **Apache Handbags Verification**.
- Hero copy **Canonical Collection fixture for projection verification.**
- Products in canonical order: **Tshirt**, TZS 320,000, `/products/tshirt`; **Trouser Beige**, TZS 500,000, `/products/trouser-beige`; **The Taylor Oxford Shirt**, TZS 350,000, `/products/the-taylor-oxford-shirts`.
- Editorial Media IDs `01m1r5z507dm975jbaqf7d94ze` and `01m1vpbm9w1cr65vvv0g512d6y`, temporarily reusing existing New Arrivals and Shoes Collection images and their current contextual alt. These were verification assets, not a published handbag merchandising selection.

Actual Apache output contained the exact names, prices, Product Media URLs, Product destinations, Collection destination, both hero Media URLs, managed copy and projection template. The managed section had no Savanna Tote Bag static card. Delivery appeared before it, Client Stories after it, and the prior Summer Edit projection remained present. Homepage, selected Collection and first Product all returned HTTP 200.

Local evidence: `storage/app/handbags-apache-evidence.json` and `storage/app/handbags-apache-managed.html`. The JSON records the exact canonical Cloudinary URLs and all served-response checks.

`synchronizeHandbags` extends the existing Homepage synchronization function: find this section by its stable managed marker or protected heading, clone the inert projection once, and return when already managed. The existing root observer handles imported runtime reconstruction. The separate small slideshow initializer observes this same root, initializes the current managed hero, retires the previous timer on replacement and uses only two bounded slides. Attribute changes do not trigger the child-list observers. No protected bundle changes or alternate hydration strategy were introduced. Final interactive DOM behavior was inspected in code, not physically accepted in a browser.

The temporary fields and MediaUsage records were restored in `finally`. A second Apache request returned 200 with the original static Products restored, the managed template absent, and verification heading absent. Current working configuration is **unmanaged**, with no selected Handbags Collection or editorial Media. Staff can select the intended Collection/images and enable managed content in the new editor.

## 26–28. Empty states, responsiveness and gutters

Focused tests cover six-card capping, canonical ordering, hidden members, missing default Variant, missing price, archived Product, partial and zero Products, one remaining image, zero images and invalid Collection. Managed gaps never borrow static cards.

The supplied layout remains stacked with two Product columns at approximately 430px and 768px, and 4/8 hero/Product spans with three Product columns at 1024px and desktop. Section-scoped served CSS explicitly sets 16px/24px Product gaps and 24px/32px outer gaps and `minmax(0,1fr)` tracks. No shared Product-card margins, body overflow suppression or protected CSS changes were added. Physical viewport measurements and slideshow clicking remain pending browser availability.

## 29–32. Validation

- Focused PHPUnit: **4 tests, 109 assertions passed**. Two Handbags tests plus one Explore shared-picker editor regression and one managed Delivery isolation regression. The Handbags scenarios also exercise `/`, Product/Collection 200s, workspace ordering, authorization, invalid-save state, stale locking, persistence and unmanaged prefill.
- Scoped PHPStan: **passed, zero errors** for new presenter/controller and changed Homepage model/public controller.
- Changed-file Pint: **passed**.
- PHP syntax, changed inline JavaScript syntax, Blade compilation and scoped `git diff --check`: passed.
- Actual Apache: managed `/`, Collection and Product 200; restored `/` 200. All JSON served checks passed.
- Single browser discovery attempt: **empty browser list**. No retry, screenshots, physical visual acceptance or full browser matrix claimed.

## 33–35. Boundaries

Complimentary Delivery was not redesigned; Site Settings was not changed. The attachment's old contact dependency had already been addressed by the preceding Site Settings task and was not reopened. Client Stories and all later Homepage sections were not started. Shop migration, Inventory, Cart, Checkout and payments were not started.

No full suite, full Larastan, complete build, dependency audit, full fidelity matrix or database reset was run. Root `AGENTS.md` requires `AUTHORIZE_BE6A1_FULL_AUDIT` before a full audit; focused checkpoint success does not authorize it.

**ECOM-HOME-9 IMPLEMENTATION READY FOR GENERAL INSPECTION**
