# ECOM-VISIBLE-2C Completion Report

## 1. Status

**ECOM-VISIBLE-2C IMPLEMENTATION READY FOR OWNER VERIFICATION**

Implementation and focused verification are complete. Physical acceptance is not claimed.

## 2. Exact 404 root-cause classification

The reported generic Product failures classify as a **served-state defect**. Both records existed, passed the canonical readiness evaluator, resolved through the presenter, and matched the source route. The current Apache-served URLs return HTTP 200. Route, binding, eligibility and renderer defects are therefore ruled out for these records. The exact historical cache or process divergence cannot be reconstructed after the served state changed; Laravel's route cache was explicitly cleared before final served checks.

## 3. Route, binding and eligibility hardening

The public generic route now declares `{product:slug}` and dispatches to `StorefrontProductController::show(Product $product, ...)`. Laravel therefore resolves the public identifier as the Product slug before rendering. The controller still applies canonical readiness and fail-closed visibility checks. Protected static Product routes retain their bounded fallback behavior.

## 4. `tshirt` eligibility evidence

| Requirement | Safe observed value | Result |
| --- | --- | ---: |
| slug | `tshirt` | Pass |
| catalogue status | `ready` | Pass |
| archived | No | Pass |
| authoritative price | 32,000,000 minor units, TZS | Pass |
| current revision | Present | Pass |
| primary Category | `Category One` | Pass |
| primary Media | Ready and confirmed | Pass |
| default Variant | `Black / M` | Pass |
| default SKU | `WT-TSHIRT-BLACK-M` | Pass |
| active Variants | 8 | Pass |
| readiness/presenter | Ready and resolves | Pass |

## 5. `trouser-beige` eligibility evidence

| Requirement | Safe observed value | Result |
| --- | --- | ---: |
| slug | `trouser-beige` | Pass |
| catalogue status | `ready` | Pass |
| archived | No | Pass |
| authoritative price | 50,000,000 minor units, TZS | Pass |
| current revision | Present | Pass |
| primary Category | `Category One` | Pass |
| primary Media | Ready and confirmed | Pass |
| default Variant | `Black / S` | Pass |
| default SKU | `WT-TROUSER-BEIGE-BLACK-S` | Pass |
| active Variants | 6 | Pass |
| readiness/presenter | Ready and resolves | Pass |

No passwords, tokens or record payloads are included.

## 6. Served HTTP result

After clearing Laravel's route cache, the Apache-served `/products/tshirt` and `/products/trouser-beige` URLs each returned HTTP 200. A deliberately unknown `/products/not-a-real-product` URL returned HTTP 404, preserving strict missing-Product behavior.

## 7. Public slug and Admin identifier separation

Only the public Product route uses explicit slug binding. Existing Admin routes remain `/admin/products/{product}` and continue to resolve canonical Product identifiers, so the public fix does not convert or weaken Admin ULID routing.

## 8. Storefront-readiness panel

The existing canonical Admin readiness panel remains the authority for content, price, Category, Media, option/Variant completeness, active default Variant and SKU. It reports ready, hidden or actionable incomplete states without duplicating presenter rules.

## 9. View-storefront action

The Admin storefront action remains enabled only when a Product is Active and canonically ready. Incomplete or Hidden Products receive corrective guidance instead of a link to a predictable public 404.

## 10. Generic renderer result

Slug-bound Products render through the shared William Taylor Product-detail view using `ProductPresenter` data. No per-Product Blade registration, hard-coded generic slug or additional bootstrap path is required.

## 11. Duplicate Colour and Size label fix

The concatenation came from rendering the internal option key immediately beside the editable client label, producing text such as `blackBlack`. Internal keys are no longer visible; each control displays one client-facing label while stable keys remain in submitted form identity.

## 12. Variant section presentation

The edit section is titled `Variants / Sellable combinations` and shows the persisted combination count, Colour-by-Size breakdown and current default Variant before the matrix.

## 13. Desktop Variant matrix

Desktop uses an intentionally spaced table with separate Variant, SKU, Price, Status and Default columns. Fixed proportional widths, cell padding and borders prevent controls and labels from collapsing into one line.

## 14. Mobile Variant cards

At the bounded mobile breakpoint, each Variant row becomes a bordered card. Every value receives its own visible field label from `data-label`, avoiding compressed horizontal scrolling and concatenated controls.

## 15. SKU presentation

SKU has a dedicated column, monospace treatment and full-value title. Create remains editable; edit displays the canonical persisted SKU without merging it into the Variant label.

## 16. Price presentation

Price inheritance and optional override are separate elements. `Uses Product price` is explicit, and the override control is introduced independently rather than appearing as an unexplained empty field.

## 17. Status presentation

Active or inactive status occupies its own column/card field and remains visually distinct from SKU, price and default selection.

## 18. Default presentation

Default selection occupies its own centered column/card field. Context is supplied accessibly without repeating cramped `Default` or `Make default` text inside every row; the selected default is summarized above the matrix.

## 19. Focused verification

- Combined Product creation, generic Product, Oxford, Collection and public Product regression: 41 passed, 365 assertions.
- Scoped PHPStan/Larastan: zero errors.
- Changed-file Pint check: passed.
- Changed PHP syntax: passed.
- Blade compilation: passed.
- Product builder and storefront JavaScript syntax: passed.
- `git diff --check`: no whitespace errors; one line-ending normalization warning remains.

No prohibited full audit was run.

## 20. Served and browser checks actually performed

The two safe database states, canonical evaluator results, presenter resolution, registered route binding, explicit route-cache clear, two successful Apache-served requests and one strict missing-Product 404 were checked. No controlled interactive browser session or new screenshots were produced, so visual acceptance is not claimed.

## 21. Owner verification required

The owner should open both public URLs and confirm their Product content, then inspect Product edit on desktop and a narrow mobile viewport. Confirm one readable Colour/Size label per control, separated SKU/price/status/default fields, and unchanged Colour/Size/default behavior after saving.

## 22. Phase boundary

No catalogue import, Shop migration, Inventory, Cart, Checkout, Homepage, Campaign or subsequent phase was started.
