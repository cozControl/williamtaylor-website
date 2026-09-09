# ECOM-HOME-2A Completion Report

## 1. Status

**ECOM-HOME-2A IMPLEMENTATION READY FOR OWNER VERIFICATION**

Implementation and bounded verification are complete. Physical browser acceptance is not claimed.

## 2. Exact Products-index layout defects found

The Products markup already contained a dedicated filter grid, but legacy `admin.css` also changed the entire `.catalogue-filters` form into a three-column grid at 640px. The heading, inner grid and action row consequently became competing grid children. The workspace also lacked explicit `min-width: 0` containment, and the table wrapper retained overflow behavior that exposed page-level horizontal scrolling.

## 3. Products filter repair

The filter form is now a single bounded column containing its own Search/Status/Category grid. Search retains the majority width, labels stay above controls, and the separately bordered action row keeps Clear left and Apply filters right. The legacy rule is explicitly neutralized for this composition.

## 4. Products table/card repair

Desktop retains the deliberate seven-column Product, Category, Price, Status, Variants, Updated and Actions table with fixed proportional widths. Product identity combines thumbnail, name and slug. Workspace/table containment prevents page overflow, prices stay together, and cells wrap within their own columns. Below 768px the existing labelled-card structure is retained without a squeezed table.

## 5. Page-header action convention implemented

The shared Admin layout now accepts an optional page-heading action slot. It places the action at the upper-right of the title/description region with normal vertical separation before page content, and stacks it at mobile width.

## 6. New Product placement

`New Product` now uses the shared heading action slot and is no longer attached to the Filters panel.

## 7. New Collection placement

`New Collection` now uses the same shared heading action slot. The empty-state action remains available when no Collections exist.

## 8. New Category placement

`New Category` now uses the shared heading action slot. Category domain behavior is unchanged.

## 9. Exact Collection create failures found

Every Product without a supplied display order rendered and submitted `999`. Selecting multiple new Products therefore produced duplicate orders and the controller returned `product_order: Each selected Product must have a unique display order.` The form exposed only the generic summary because this collection-level error had no nearby Products error target. Other traced failures were duplicate slug, unavailable Media/Product, missing effective image alt text and invalid numeric order.

## 10. Exact Collection edit failures found

The same `999` default affected edit when adding multiple previously unassociated Products. Per-Product validator keys such as `product_order.{productId}` also had no rendered error binding, so an invalid order could be reported only by the top summary. Existing name/description state was retained by Laravel, but the useful diagnosis was not attached to the relevant control.

## 11. Field-level validation implementation

Collection validation now supplies client-facing messages for required fields, duplicate slug, stale Media/Product selection and order constraints. Name, slug, description, visibility, image, alt text, Products and individual order controls render nearby errors, invalid boundaries, `aria-invalid` and valid `aria-describedby` relationships. The top message remains a summary.

## 12. State-preservation behavior

Failed create/update restores name, normalized slug, description, visibility, selected Media, alt override, selected Product IDs and submitted display orders. Focused tests verify visible state, checked Product selection, invalid order text and field errors after redirect.

## 13. Product eligibility behavior inside Collection selection

Each candidate now shows `Ready for storefront` or `Needs attention`, with up to three canonical readiness reasons. Existing semantics intentionally permit incomplete membership, particularly for hidden Collections; public Collection and Homepage presenters continue to omit Products that fail canonical readiness. Eligibility was not weakened.

## 14. Display-order UX behavior

New candidates display deterministic zero-based orders rather than repeated `999`. If the client omits orders, the controller assigns `0, 1, ...` in submitted Product order. Explicit non-integer, out-of-range or duplicate orders remain validation failures and display beside the Products/order controls.

## 15. Collection index spacing/action behavior

Collection rows preserve Edit as the bounded Admin action and View storefront as the secondary action. They now reuse the compact row-action composition rather than the broader page-action layout.

## 16. ECOM-HOME-2 New Arrivals regression result

The persisted Collection source, ordered eligible Product projection, eight-card limit, canonical card data and safe empty behavior remain unchanged. Homepage Admin now explicitly says `This section has no Products ready to display.` when the selected source resolves to zero eligible Products.

## 17. Focused tests

- Catalogue, Collection, Product detail, Oxford, Homepage Hero and New Arrivals regression: 56 passed, 541 assertions.
- Collection/Admin layout subset: 15 passed, 145 assertions.
- Scoped PHPStan/Larastan: zero errors.
- Changed PHP syntax: passed.
- Changed-file Pint: passed after formatting.
- Blade compilation: passed.
- `git diff --check`: no whitespace errors; two existing line-ending normalization warnings remain.

No prohibited full suite, full Larastan, fidelity matrix or BE-6A audit was run.

## 18. Served route checks

- `GET /` returned 200.
- `GET /products/tshirt` returned 200.
- `GET /collections/new-arrivals` returned 404 because that public Collection slug is not present/eligible in the working database; focused canonical Collection fixtures return 200.
- Unauthenticated `GET /admin/products` and `GET /admin/collections` returned 302 to Login.

## 19. Browser checks actually performed

The supported in-app browser connection was initialized and its available surfaces queried. No browser surface was exposed. Raw Apache route responses were checked instead.

## 20. Browser checks unavailable

Authenticated desktop and mobile visual checks, Collection create/edit interaction, Media selection and final New Arrivals storefront acceptance remain owner verification steps. No screenshot or post-load browser claim is made.

## 21. Confirmation PRODUCT-GALLERY-1 remains deferred

PRODUCT-GALLERY-1 — Generic Product colour/gallery synchronization remains deferred and was not modified.

## 22. Confirmation William's Hot Sale was not started

William's Hot Sale and all later Homepage, Campaign, importer, Shop, Inventory, Cart and Checkout work were not started.
