# ECOM-HOME-2D Completion Report

## 1. Status

**ECOM-HOME-2D IMPLEMENTATION READY FOR OWNER VERIFICATION**

Implementation and bounded verification are complete. Physical acceptance is not claimed.

## 2. Actual New Arrivals persisted membership before fix

Collection `01m1v480fhmfn6beca07smkh0n`, slug `new-arrivals`, is ready. Its database history contains Tshirt at position 1, archived at 2026-09-06 11:46:23 UTC; Trouser Beige at position 2, active; and The Taylor Oxford Shirts at position 3, active.

## 3. Admin Edit membership source

Admin Edit loaded `Collection::products`. That relation previously included active and archived membership rows, producing three checked Products.

## 4. Public Collection membership source

The public controller explicitly constrained memberships with `active()`, producing Trouser Beige and Oxford only.

## 5. Homepage membership source

The Homepage New Arrivals presenter also reads active canonical Collection membership and filters each Product through the public presenter. It therefore showed the same two effective members.

## 6. Exact root cause of removed Products reappearing selected

Persistence was correct: removal archived Tshirt and public projections excluded it. The ordinary Collection relationship lacked the active scope, so Admin prefill combined effective and historical rows. This was not stale old input, caching, duplication, or failed deletion.

## 7. Membership persistence fix

`Collection::products()` now represents effective membership by applying `active()` before canonical position ordering. Historical rows remain queryable directly from `CollectionProduct`.

## 8. Successful-save reload result

After deselection and successful redirect, Edit reads persisted active membership. Retained Products are checked and archived Products are unchecked. Index counts use effective rows.

## 9. Failed-save restoration regression

Laravel old input still takes precedence after validation failure, preserving attempted checkbox and display-order state without mutating canonical membership.

## 10. Display-order behavior

Persisted explicit positions are retained. The live effective projection remains Trouser Beige at 2 and Oxford at 3; this phase did not silently normalize them.

## 11. Historical membership preservation

The archived Tshirt pivot and its audit/history data remain intact. Focused coverage asserts one effective row and the retained archived row after removal.

## 12. Production/reference Collection UI findings

Targeted access to `williamtaylor.co.tz` was unavailable and produced no indexed Collection result. The preserved imported composition and accepted Homepage Product cards were therefore used as the bounded reference; no fallback design was invented.

## 13. Collection hero fidelity changes

The established dark editorial hero, Collection eyebrow, title, description, overlay image treatment, and bounded content width were retained. Horizontal padding now aligns with the accepted storefront grid.

## 14. Product-card fidelity changes

Collection cards now use the Homepage recognition hierarchy: 3:4 image, hover zoom, badges, wishlist affordance, Colour swatches, Product name, canonical price, optional compare-at price, and canonical URL. Both surfaces use `ProductCardPresenter`.

## 15. Collection responsive result

The grid uses one column on mobile, two on small/tablet widths, and four on desktop with bounded page padding and no fixed card width.

## 16. Catalogue workspace problems found

The workspace duplicated management actions above and below the metrics, used shallow KPI cards without deliberate vertical hierarchy, and left excess horizontal space at common desktop widths.

## 17. Catalogue page composition implemented

New Product moved into the shared upper-right heading action. The duplicate shortcut cluster was removed. Six metrics now lead into one clearly named Catalogue management section.

## 18. KPI layout

The six existing metrics use responsive auto-fit cards with consistent minimum height, uppercase label, large value, helper copy, and a real Manage Products link when Needs Attention is non-zero.

## 19. Management-card layout

Three equal management cards describe Products, Categories, and Collections in client language, each with one canonical destination and no revision/domain terminology.

## 20. Responsive Catalogue result

KPI cards consume available desktop width and management cards form a three-column row. Both grids collapse to one bounded column on narrow screens.

## 21. Focused tests

- Collection membership/Admin/public, Homepage New Arrivals, Catalogue Admin, Product gallery, and bounded Product route: 41 passed, 383 assertions.
- Scoped PHPStan/Larastan: zero errors.
- Changed-file Pint, Blade compilation, changed PHP syntax, and `git diff --check`: passed.
- Two existing line-ending normalization warnings remain.

No prohibited full suite, full Larastan, build, dependency audit, fidelity matrix, or BE-6A audit was run.

## 22. Served route/body checks

`/collections/new-arrivals` returns 200 and contains New Arrivals, Trouser Beige and Oxford canonical links in effective order. Tshirt is absent, 3:4 card markup is present, and the incompatible SPA runtime is absent. Existing bounded checks retain Homepage 200, valid Product 200, unknown Product/Collection 404, and unauthenticated Admin redirects.

## 23. Browser/reference checks actually performed

Targeted web access was attempted for the production reference. The supported in-app browser connection was then attempted once.

## 24. Browser unavailable statement

Neither the production Collection reference nor an in-app browser surface was available. No screenshot, computed responsive measurement, or physical visual claim is made.

## 25. Confirmation Product gallery remains accepted

PRODUCT-GALLERY-1 code and its accepted stable-thumbnail, active-preview, Colour-preview, and Variant/SKU behavior were not changed.

## 26. Confirmation William's Hot Sale was not started

William's Hot Sale and every later Homepage section remain untouched.

**ECOM-HOME-2D IMPLEMENTATION READY FOR OWNER VERIFICATION**
