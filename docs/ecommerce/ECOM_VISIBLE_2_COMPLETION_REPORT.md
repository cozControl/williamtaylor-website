# ECOM-VISIBLE-2 Completion Report

1. **Status:** **ECOM-VISIBLE-2 IMPLEMENTATION READY FOR OWNER VERIFICATION**. Automated checks do not constitute physical acceptance.
2. **Create route:** `GET /admin/products/create` and `POST /admin/products`, protected by `products.manage`.
3. **Edit route:** `GET /admin/products/{product}/edit` and `PUT /admin/products/{product}`; writes require `products.manage`.
4. **Admin composition:** Product details, Pricing, Categories, Media & colours, Options & variants, Merchandising on edit, and Status use bounded panels. The shared sticky action bar places Back left and Save right.
5. **Pricing:** TZS input is parsed into canonical integer minor units. Compare-at must exceed base price. Display conversion uses integer division, not floating-point input conversion.
6. **Categories:** One primary Category is required and additional Category assignments are supported.
7. **Primary Media:** The reusable searchable single-select Media modal is used and only ready images can be assigned.
8. **Gallery:** The reusable multi-select modal supports ordering, removal and alt overrides; removing usage preserves the Media Asset.
9. **Colour Media:** Existing Colour values own their shared primary/gallery Media, so imagery is not duplicated for every Size. Colour imagery is assigned on Edit after the first save creates stable Colour identities.
10. **Colours:** Product-specific line-separated Colour values are normalized to stable keys; edit supports labels, swatch hex, active domain identity and order.
11. **Sizes:** Product-specific line-separated values support arbitrary sets and size-only Products.
12. **Variant synchronization:** Colour × Size combinations are generated automatically; existing combination fingerprints retain Variant identities on resynchronization.
13. **SKU:** Generated SKUs derive from the immutable Product stable key and option keys, are normalized, editable after generation, and server-checked for uniqueness.
14. **Default Variant:** The first generated sellable Variant becomes default; Edit exposes an explicit default radio selection and validates Product ownership.
15. **Status/readiness:** Staff sees Active, Hidden and Archived. Active requires content, canonical price/category, primary Media and a default Variant; hidden/archived Products fail closed publicly.
16. **Create feedback:** `Product created successfully.`
17. **Update feedback:** `Product updated successfully.`
18. **Validation:** A summary says “Please correct the highlighted fields”; field errors and old input are retained. Domain input failures return to Admin instead of an exception page, and failed creates roll back atomically.
19. **Dynamic route:** New active Products use `/products/{product}` through the canonical wildcard route. No Product-specific route was added.
20. **Storefront:** `ProductPresenter` feeds the existing William Taylor Product template. The legacy payload variable name remains internal for template compatibility; generic Products now receive their own payload.
21. **Flexible shapes:** Focused tests cover Colour + Size (eight Variants), Size only, and no options (one canonical Variant).
22. **Storefront reality check:** The retained composition provides header/footer, gallery, SKU, canonical/override price, Colour and Size selectors, structured descriptions, Colour imagery, badges and related cards. Generic option sections are now omitted when absent and Variant resolution works from the Product's actual option shape. The static cart/buy controls and imported review/shipping copy are compatibility content, not new commerce authority.
23. **Inventory boundary:** No stock quantity, reservation, availability engine or authoritative inventory claim was introduced.
24. **Cart boundary:** No Cart or Checkout behavior was implemented; existing imported controls remain non-operational compatibility UI.
25. **Files changed:** `ProductController`, `StorefrontProductController`, `ProductPrice`, Product create/edit views, Admin revision marker, generic Product template, focused catalogue tests, and this report.
26. **Focused tests:** 36 tests passed with 317 assertions across Product core, Oxford storefront, Collection workflow and Product-detail regression after the generic route correction. No full suite was run.
27. **Browser evidence obtained:** None in this implementation session.
28. **Browser verification unavailable:** Authenticated owner interaction, modal selection, responsive behavior and screenshots remain owner gates; no physical PASS is claimed.
29. **Exact owner workflow:** Follow the 35 steps below.
30. **Boundary confirmation:** Catalogue import, Shop migration, Inventory, Cart, Checkout, campaigns, homepage merchandising and customer accounts were not started. The prohibited full audit was not run because `AUTHORIZE_BE6A1_FULL_AUDIT` was not supplied.

## Exact owner workflow

1. Open Products.
2. Click New Product.
3. Enter Product name.
4. Enter slug.
5. Enter descriptions.
6. Select Category.
7. Set price.
8. Select primary Media.
9. Add gallery Media.
10. Add Ivory.
11. Add Navy.
12. Note that colour-specific Media becomes assignable on Edit once stable Colour identities exist.
13. Add S, M, L, XL.
14. Select Generate / synchronize Variants and SKUs.
15. Save Hidden and inspect the eight generated combinations on Edit.
16. Verify or edit SKUs.
17. Select the default Variant.
18. Assign Ivory and Navy colour-specific Media, then set Active.
19. Save.
20. See **Product updated successfully.** (The initial create shows **Product created successfully.**.)
21. Open Product index.
22. Find Product.
23. Edit Product.
24. Confirm state is prefilled.
25. Change price or description.
26. Save.
27. See **Product updated successfully.**
28. Click View storefront.
29. See the existing William Taylor Product composition.
30. Change Colour.
31. Confirm gallery changes.
32. Change Size.
33. Confirm correct Variant resolution.
34. Confirm canonical price.
35. Refresh and confirm persistence.

Only an explicit owner physical PASS closes ECOM-VISIBLE-2.
