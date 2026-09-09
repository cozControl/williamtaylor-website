# ECOM-HOME-2C Completion Report

## 1. Status

**ECOM-HOME-2C IMPLEMENTATION READY FOR OWNER VERIFICATION**

Implementation and bounded verification are complete. Physical acceptance is not claimed.

## 2. Collection selector exact CSS/layout root cause

Legacy `.collection-product-choices label` CSS imposed a three-column grid on every descendant label. The new identity label was therefore split into `auto minmax(0,1fr) 5rem` inside an already nested grid; the readiness badge and text consumed those tracks and collapsed the identity/reasons toward one-character lines. Absolute badge positioning and later component rules compounded the cascade.

## 3. Collection selector fix

The candidate is now a two-column checkbox/content card. A specifically classed identity element owns a full-width name/slug plus bounded badge layout; metadata, readiness reasons, and compact order control stay inside the content card. Mobile converts identity and order to single-column flow. Existing search, prefill, selection, validation, restoration, and persistence contracts remain.

## 4. Sticky-action overlap result

The Collection editor retains Back-left/Save-right sticky actions and reserves bottom form flow so the final Product and order control can scroll completely above the bar.

## 5. Admin mobile viewport finding

The tiny desktop-like mobile rendering was consistent with the malformed intrinsic selector width, not an absent viewport declaration. All relevant Admin containers remain shrinkable at the existing 430px breakpoint; Product filters/cards and actions retain their mobile stack rules.

## 6. Exact viewport meta result

The shared Admin head contains exactly `<meta name="viewport" content="width=device-width, initial-scale=1">`. Live `window.innerWidth`, document client width, and workspace width could not be measured because no browser surface was available.

## 7. Mobile Products result

The existing bounded Product card conversion, wrapping slug, full-width filters/actions, shrinkable pagination, and no-fixed-minimum containment were preserved. No body overflow concealment or desktop-table redesign was added.

## 8. Catalogue heading clipping root cause/fix

The shared serif heading used a `.98` line box, clipping upper glyph extents under the sticky-header composition. Shared page headings now receive slight upper breathing room and a `1.05` line height, covering Dashboard, Catalogue, Products, Categories, Collections, and Homepage rather than special-casing Catalogue.

## 9. Oxford persisted Product Media counts

Persisted slug: `the-taylor-oxford-shirts`. Product-owned Media contains one primary asset (`01m1r5z5cyymd54phg46ejrrht`) and four gallery assets (`01m1pjqwqwg0baffm768r0q935`, `01m1r5ychb3aqd4g8es3wssgp9`, `01m1r5z4q4cqbf385dwpt9ek66`, `01m1r5yc8kpbbte6wa6jbkptve`) in canonical role/order.

## 10. Oxford persisted Colour Media counts

- Ivory: one preview, `01m1pjqwqwg0baffm768r0q935`.
- Black: one preview, `01m1r5z5cyymd54phg46ejrrht`.
- White: one preview, `01m1r5z4eags6b99sxypf90a7j`.

All are retained as canonical Colour-owned associations.

## 11. Whether one thumbnail was data-correct or renderer-defective

It was renderer-defective. Product-owned data supplies five total Product images, while the prior renderer replaced stable Product thumbnails with the selected Colour's one-image list.

## 12. Product gallery interaction result

Product primary/gallery thumbnails now remain stable. A thumbnail click changes only the large active preview and thumbnail active state; it does not mutate option or Variant state.

## 13. Colour preview interaction result

Colour selection uses the first ordered Colour-owned image for the large preview without rebuilding thumbnails. A Colour without Media falls back to Product primary, then the first Product gallery image.

## 14. Variant/SKU regression

The existing Colour/Size selection and canonical Variant resolver still update SKU and Variant price. Gallery clicks do not change selected values, SKU, Variant, or price.

## 15. `/collections/new-arrivals` exact route trace

Route `collections.show` invokes `StorefrontCollectionController`. The current visible Collection resolves with active canonical memberships and storefront-ready Product cards through `ProductCardPresenter`. It returns HTTP 200 using `frontend.collection-show`; raw HTML contains New Arrivals and canonical `/products/{slug}` links.

## 16. Exact source of `Coming soon`

Laravel already rendered the populated Collection grid. The imported compiled SPA runtime then took ownership of `/collections/new-arrivals` and rendered its generic fallback. Dynamic Collection documents now disable that incompatible runtime while retaining protected CSS and Laravel storefront assets; the protected bundle was not edited.

## 17. Collection Product-grid implementation

The existing William Taylor Collection hero and responsive one/two/three-column Product grid are retained, populated by shared canonical Product-card presentation.

## 18. Collection ordering

The controller loads active Collection memberships in their canonical relationship order and maps eligible Products without re-sorting, preserving membership position.

## 19. Empty Collection behavior

Only an empty eligible projection shows: `No products are currently available in this collection.` Populated Collections no longer expose `Coming soon.`

## 20. Homepage New Arrivals regression

Homepage selection, eligible Product projection, canonical order, card composition, arrows, spacing, and View All to `/collections/new-arrivals` remain unchanged. The destination now remains the populated Laravel Collection after load.

## 21. Focused test results

- Collection Admin/public, Product media/gallery, Homepage New Arrivals, and Admin catalogue: 37 passed, 347 assertions.
- Scoped PHPStan/Larastan: zero errors.
- Changed-file Pint, Blade compilation, affected PHP syntax, served Product JavaScript syntax, and `git diff --check`: passed.
- Two pre-existing line-ending normalization warnings remain.

No prohibited full suite, full Larastan, build, dependency audit, fidelity matrix, or BE-6A audit was run.

## 22. Served HTTP/body checks

- `/`: 200.
- `/collections/new-arrivals`: 200; title and Product links present; `Coming soon.` absent; incompatible SPA runtime absent.
- `/products/the-taylor-oxford-shirts`: 200.
- `/products/tshirt`: 200.
- Unknown Product and Collection: 404.
- Unauthenticated `/admin/catalogue` and `/admin/products`: 302 to Login.

## 23. Browser checks actually performed

The supported in-app browser connection was attempted once for this phase.

## 24. Browser checks unavailable

No browser surface was exposed. Consequently no computed 430px widths, screenshot, sticky-bar visual check, or post-click physical claim is made. These remain owner verification items.

## 25. Confirmation William's Hot Sale was not started

William's Hot Sale and every later Homepage section remain untouched.

**ECOM-HOME-2C IMPLEMENTATION READY FOR OWNER VERIFICATION**
