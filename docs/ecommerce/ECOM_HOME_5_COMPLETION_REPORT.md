# ECOM-HOME-5 Completion Report

## 1. Status

**ECOM-HOME-5 IMPLEMENTATION READY FOR GENERAL INSPECTION**

Implementation and bounded verification are complete. Physical browser acceptance is not claimed.

## 2. Exact protected Limited Edition template composition discovered

The protected Homepage section occupies a dark oxblood band after The Future of Style. It uses the `Exclusive` eyebrow, an icon-framed `LIMITED EDITION` heading, a decorative divider, and exactly three portrait 3:4 Campaign cards in a desktop row. Each card uses Campaign imagery, a top-left Limited Edition badge, a top-right hexagonal fixed-edition statement, canonical Product identity and price, Campaign copy, a full-width Product CTA, and a centred View All action.

The protected `/limited-edition` page uses an oxblood page composition with the `Numbered pieces. Exclusive access.` introduction and portrait cards containing Campaign imagery, Limited badges, an edition statement, wishlist affordance, canonical Product swatches, title and price. The original fallback contained five reference cards and expands through one, two, three and four columns. Unsupported `remaining` and `left` counters were identified as inventory-like claims and removed.

## 3. Existing `limited_edition` Campaign architecture reused

The existing Campaign aggregate, immutable Campaign revisions, `CampaignProduct` targets, Campaign `MediaUsage`, schedule fields, readiness/effective-state evaluators, evidence-backed claims, separation-of-duties approval and audit path are reused. `limited_edition` remains a registered Campaign type alongside `pre_order`.

## 4. No competing Campaign, Homepage or Product model

No LimitedEditionProduct, HomepageCampaignItem, HomepageLimitedEditionProduct, second Campaign aggregate, duplicate Product record, duplicate price store or parallel publication lifecycle was created. Homepage stores only bounded Campaign references.

## 5. Exact Homepage maximum Campaign capacity

The supplied Homepage composition supports exactly three configured Limited Edition Campaign positions. `HomepageLimitedEditionPresenter::CAPACITY` is `3`, and the Admin editor exposes Position 1 through Position 3 without an unlimited repeater.

## 6. Homepage-owned fields

The Homepage singleton owns the managed-state flag, eyebrow, heading, View All label and three nullable ordered Campaign foreign keys. Foreign keys use `nullOnDelete`. The View All destination is structurally fixed to the canonical `/limited-edition` route and is not persisted as an arbitrary URL.

## 7. Campaign-owned fields

Campaign owns type, internal identity, immutable revision headline/summary/CTA copy, public schedule, lifecycle, Campaign-specific Media usage and optional contextual Alt override. The fixed edition statement and public-window statement are Campaign claims with evidence and approval metadata.

## 8. Product-owned fields

Product remains the source of title, slug, canonical public URL, pricing, options, Colour swatches, Variants, SKU and Product-owned imagery. None of those values is copied into Homepage or Campaign persistence.

## 9. Campaign Admin extension

The existing `/admin/campaigns` workspace now manages both Pre-Order and Limited Edition Campaigns. Its list uses client-facing type labels and reports Campaign identity, Product, effective state, public window, readiness, updated time and Edit action. The existing Campaigns navigation destination and permissions remain shared.

## 10. Campaign type-specific UI behavior

New Campaign opens a deliberate type chooser. Limited Edition exposes fixed-edition claim/evidence inputs and omits estimated shipping. Pre-Order retains estimated shipping and omits Limited Edition fields. The server prohibits irrelevant fields, preserves submitted state on validation failure, and prevents an existing Campaign type from being forged into another type.

## 11. Product target and readiness behavior

Campaigns target canonical, non-archived Products. The selector labels ready Products and explains incomplete Products with ordinary language such as Missing category, Missing image, Missing price and Missing Variants. Publication reuses canonical Product readiness; a Campaign cannot make an incomplete Product public, and public presenters fail closed when its target no longer resolves.

## 12. Media and effective Alt behavior

Campaign imagery uses reusable confirmed READY image assets through the established Campaign `MediaUsage` role. Effective Alt is the optional usage override followed by Media Library default Alt. If both are absent, save returns a field error, retains the selected Media and other form state, and provides an Open Media Library action. Filenames, slugs and Campaign titles are not Alt fallbacks.

## 13. Edition and scarcity semantics implemented

`edition_statement` represents a fixed planned-edition statement such as `Only 30 Made`. It is a bounded plain-text Limited Edition Campaign claim, requires evidence and approval, and is projected into the supplied badge treatment only after approval. Unmanaged static fallbacks retain their composition but no longer publish unsupported numeric scarcity claims.

## 14. Scarcity is not Inventory remaining stock

The edition statement is explicitly not current stock, quantity remaining, sales progress or reservation availability. The original `12 Remaining`, `8 Remaining`, `15 Remaining` and `Only 8 left` reference counters were removed. No stock level, sold count, reservation count or inventory calculation was introduced.

## 15. Claims and evidence behavior

Limited Edition requires both the existing public-window claim and the new typed edition statement. Each claim stores normalized value, non-local evidence reference, evidence summary and checksums, enters review on create/edit, and must receive independent approval. Material edits invalidate publication and return claims to review.

## 16. Campaign publication and effective-state behavior

Publication requires an approved revision, valid Product target, usable Campaign Media, approved required claims and a valid schedule. Public projection requires the canonical effective state to be Active at request time. Draft, scheduled, ended, archived, incomplete or stale-approval Campaigns are omitted even when Homepage still references their IDs.

## 17. Homepage Limited Edition rendering

Laravel populates the original section location and composition with configured eligible Campaigns in explicit Homepage position order. Campaign image/Alt, approved edition statement, revision copy, canonical Product title/price and Product URL are projected. Zero, partial and full three-position states render without broken placeholder cards. The existing post-mount synchronization guard preserves Laravel-managed content if the imported Homepage runtime mounts afterward.

## 18. `/limited-edition` rendering

`/limited-edition` is controller-backed and uses the same Limited Edition presenter. Eligible Campaigns are ordered deterministically by start time and Campaign identity, canonical prices and Product destinations are used, and ended/ineligible Campaigns are omitted. The supplied page shell and responsive portrait grid remain. The incompatible imported SPA runtime is disabled for this dynamic Laravel document, preventing a post-load route replacement.

## 19. Shared versus type-specific presenter architecture

`CampaignPublicProjection` contains only genuinely shared eligibility, Product resolution, Campaign Media/effective Alt, approved claims, schedule and canonical URL logic. `PreOrderCampaignPresenter` retains countdown/delivery semantics. `LimitedEditionCampaignPresenter` independently requires and exposes the approved fixed edition statement. No large Campaign-type branch was introduced into either presenter.

## 20. Canonical pricing source

Limited Edition uses the existing canonical `ProductPresenter` price projection, including the Product currency and established Variant/base-price behavior. Homepage and Campaign persistence contain no copied price.

## 21. CTA behavior before Cart and Checkout exist

Campaign card CTAs lead to the canonical Product detail route. Homepage View All leads to `/limited-edition`. No CTA creates a reservation, allocation, deposit, purchase limit, payment or checkout state.

## 22. Responsive implementation

Homepage retains 3:4 imagery and moves from one column to the supplied three-card row at the medium breakpoint. The public page retains one, two, three and four-column capacities at its existing breakpoints. Images remain `object-cover`, card controls stay within their card, and no `body { overflow-x: hidden; }` concealment was added. Physical checks at desktop, 1024px, 768px and 430px remain owner inspection because no browser surface was available.

## 23. Focused test counts and assertions

- Campaign foundation, Campaign Admin, Limited Edition Homepage/public projection, Future of Style regression and special-commerce shell: 19 passed, 232 assertions.
- One canonical Product-route regression: 1 passed, 14 assertions.
- One canonical Collection create/public-route regression: 1 passed, 45 assertions.
- Total bounded verification: 21 passed, 291 assertions.

The tests cover permissions, type distinction, type-specific validation, state restoration, edit/type immutability, Media Alt behavior, incomplete Product failure, evidence/approval/publication, configured order, capacity three, canonical Media/title/price/destination, deterministic public order, ended omission, zero/partial/full eligibility, `/`, `/pre-order`, Product and Collection regressions.

## 24. Scoped static-analysis, format and syntax results

Scoped PHPStan/Larastan passed with zero errors. Changed-file Pint passed. Changed PHP syntax passed. Blade templates cached successfully. The changed Homepage inline synchronization script passed `node --check`. `git diff --check` reported no whitespace errors and only existing CRLF-to-LF normalization warnings. No full suite, full Larastan, full build, dependency audit, fidelity matrix or BE-6A audit was run.

## 25. Served HTTP results

After applying the additive `2026_09_06_210000` migration, Apache-served `GET /`, `GET /limited-edition`, `GET /pre-order`, `GET /products/the-taylor-oxford-shirt` and `GET /collections` returned 200. The Limited Edition response contained the protected introduction and Laravel grid marker, omitted the incompatible SPA bundle and omitted the unsupported `Only 8 left` claim. Unauthenticated `GET /admin/campaigns` returned 302 to Login. The working database has no Collection detail slug; the focused canonical Collection fixture verified its detail route at 200 instead of manufacturing live data.

## 26. Browser attempt result

Supported in-app browser control was initialized once. No browser surface was exposed. No retry, screenshot, authenticated interaction or physical responsive claim was made; owner visual inspection remains authoritative.

## 27. Accepted Homepage sections were not redesigned

Hero, New Arrivals, William's Hot Sale and The Future of Style were not redesigned. Only the shared Campaign projection needed for a second Campaign type and the existing Homepage runtime synchronization list were extended.

## 28. Explore the Collection was not started

Explore the Collection, The Summer Edit, Women's Handbags, Client Stories, Follow the Journey, Inner Circle and Sign the Ledger were not started.

## 29. Commerce phase boundary

Inventory, live availability, Cart, Checkout, payments, deposits, reservations, allocations, purchase limits, Shop migration and Gift Card commerce were not implemented or started.

**ECOM-HOME-5 IMPLEMENTATION READY FOR GENERAL INSPECTION**
