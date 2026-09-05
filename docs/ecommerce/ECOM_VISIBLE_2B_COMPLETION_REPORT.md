# ECOM-VISIBLE-2B Completion Report

## 1. Status

**ECOM-VISIBLE-2B IMPLEMENTATION READY FOR OWNER VERIFICATION**

Implementation and focused verification are complete. Physical acceptance is not claimed.

## 2. Swatch colour-picker implementation

Create and edit now use the native visual `input type="color"` control in the existing Admin field design. New Colours start at neutral `#808080`; no colour is inferred from the Colour name.

## 3. Hex synchronization and validation

The visual picker and visible hex input synchronize in both directions. Valid hex is normalized to uppercase, while the existing server rule accepts only canonical six-digit values and returns malformed input to the Colour field without losing draft state.

## 4. Exact root cause of `/products/tshirt` 404

The saved Product was not ineligible. Direct database evaluation shows it satisfies the canonical requirements and `ProductPresenter::resolve('tshirt')` returns data. The current registered generic route is present and the Apache-served URL returns HTTP 200. The physically observed 404 therefore came from stale served route/application state at the time of testing, not the saved Product record or an Oxford-only presenter gate.

## 5. Saved Product eligibility comparison

| Requirement | Saved value | Required value | Pass? |
| --- | --- | --- | ---: |
| slug | `tshirt` | valid unique slug | Yes |
| status | `ready` | active/ready | Yes |
| archived | `false` | false | Yes |
| price | `32,000,000` minor units, TZS | authoritative Product price | Yes |
| primary category | `Category One` | one primary Category | Yes |
| primary image | ready and confirmed | one usable ready image | Yes |
| default Variant | `Black / M` | active owned Variant | Yes |
| Variant active | unarchived | true | Yes |
| SKU | `WT-TSHIRT-BLACK-M` | non-empty valid SKU | Yes |
| public resolver | presenter resolves; served HTTP 200 | eligible | Yes |

No passwords, tokens or record payloads are included.

## 6. Routing fix

The canonical generic `products.show` route remains ahead of no conflicting catch-all and continues to pass the requested slug to `StorefrontProductController`. Eligible canonical Products resolve dynamically. Protected static Product slugs retain their static fallback during migration when a legacy canonical record was manually marked ready but is incomplete; unknown, Hidden and archived generic Products fail closed.

## 7. Generic dynamic Product rendering result

Non-Oxford canonical Products render through the shared William Taylor Product-detail composition using `ProductPresenter` data. They require no Blade registration, hard-coded slug, route addition or bootstrap command.

## 8. Admin storefront-readiness feedback

Admin edit now shows `Ready for storefront`, `Hidden from storefront` or `Needs attention`. The canonical evaluator checks content, authoritative price, primary Category, usable primary Media, option/Variant completeness and an active default Variant with SKU. Attempts to mark an incomplete Product Active are declined with client-language corrective steps.

## 9. View storefront behavior

The enabled `View storefront` action is rendered only when the Product is both Active and canonically ready. Otherwise it is replaced with guidance to complete the required information, preventing staff from being sent to a predictable 404.

## 10. Variant summary implementation

The create builder shows the sellable combination count, the Colour × Size breakdown where applicable, and the selected default label. The edit view shows its persisted combination count and current default.

## 11. Variant matrix implementation

The matrix presents readable option labels, editable SKU, explicit price inheritance/override, Active status and default selection. Internal IDs remain hidden.

## 12. Default Variant UX

Default selection is a radio control in each create matrix row. The selected row says `Default`; other rows say `Make default`. Edit uses the same direct table placement.

## 13. SKU UX

Generated SKUs remain visible and editable before save. Regeneration retains edited SKUs for stable draft-combination keys. Duplicate SKU validation remains row-specific and preserves all submitted state.

## 14. Price inheritance UX

A Variant initially displays `Uses Product price` instead of an ambiguous empty box. Staff can explicitly open an override input and can return the Variant to Product-price inheritance.

## 15. Variant status UX

Create rows clearly show `Active` and explain that inactive means unavailable for purchase, not out of stock. Persisted archived Variants are labelled `Inactive`; active Variants are labelled `Active`.

## 16. Variant update and regeneration behavior

`Update Variants` reports how many draft combinations are new and how many were retained. Stable combination keys retain entered SKU and price state. Existing canonical synchronization continues to reuse persisted values/Variants rather than replacing stable identities.

## 17. Colour gallery relationship

Colour Media remains owned once by the canonical Colour value. Admin copy explains that every Size Variant of that Colour shares its gallery; no duplicate Size-level image picker was introduced.

## 18. Generic storefront Colour switching

The generic presenter provides Colour galleries keyed by canonical Colour value identity. The shared Product template switches the main image and thumbnail count on Colour selection, while Size selection resolves the corresponding Colour/Size Variant and its SKU/price.

## 19. Tests

- Final focused ECOM-VISIBLE-2B Product test: 17 passed, 114 assertions.
- Combined Product creation, generic Product, Oxford, Collection and public Product regression: 40 passed, 351 assertions.
- Scoped PHPStan/Larastan: zero errors.
- Changed-file Pint: passed.
- PHP syntax: passed.
- Blade compilation: passed.
- Product builder and storefront JavaScript syntax: passed.
- `git diff --check`: no whitespace errors; one line-ending normalization warning remains.
- Apache-served `/products/tshirt`: HTTP 200.

No prohibited full audit was run.

## 20. Browser checks actually performed

The actual `tshirt` database state, canonical readiness evaluator, presenter resolution, registered route list and Apache-served HTTP response were checked. Chrome control was attempted through the required connection path and retried once, but no Chrome browser was available to the session.

## 21. Browser verification unavailable

No authenticated clicks, visual colour-selector interaction, screenshots or physical Colour/Size switching claims are made. Owner verification remains required for picker interaction, edit feedback, default visibility and generic storefront Colour switching.

## 22. Previous accepted behavior remains

Blank-slug generation, failed-validation state preservation, field highlighting, Product Media selection, reusable Media picker behavior, first-save Colour creation and first-save Colour Media association remain covered by the focused regression tests. The accepted overall Product form, Media picker, pricing architecture, Categories, Collections and navigation were not redesigned.

## 23. Phase boundary

No catalogue import, Shop migration, Inventory, Cart, Checkout, Homepage, Campaign or subsequent phase was started.
