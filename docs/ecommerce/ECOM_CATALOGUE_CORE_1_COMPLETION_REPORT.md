# ECOM-CATALOGUE-CORE-1 Completion Report

## Status

Implementation ready for physical acceptance. Physical acceptance is not
claimed.

## Delivered

- Functional hierarchical Categories Admin with search, parent, description,
  reusable Media image, visibility, display order and archive handling.
- Primary and additional Product Categories with canonical breadcrumbs.
- Typed Product content for short description, description, materials, care,
  size/fit and features.
- Integer-minor TZS Product pricing, optional compare-at pricing, Variant price
  override and one formatter/effective-price service.
- Optional Colour and Size axes, retained option identities and combination
  synchronization without per-row manual creation.
- Product/default gallery plus Colour-owned ordered galleries and swatch hex.
- Unique normalized Variant SKUs, explicit default Variant and related Products.
- General Product and reusable Product Card presenters plus a reserved-safe dynamic Product route. Hidden and
  archived canonical Products fail closed; unmigrated known templates retain
  static compatibility.
- Sidebar grid mechanics keep brand/footer deliberate and make navigation
  independently scrollable; the mobile drawer also scrolls.

## Migration strategy

The explicit idempotent Oxford bootstrap remains non-destructive and now adds
its canonical Category and TZS price. The general architecture accepts the other
storefront shapes without slug, Colour, Size or Variant-count assumptions.
Known templates remain static until deliberately imported; a canonical record
never gets overwritten or silently falls back when Hidden.

## Boundaries retained

Campaign-derived PRE-ORDER/LIMITED/scarcity claims remain campaign-owned.
Inventory availability is not represented. Global delivery/returns/payment
assurances remain Site content. No Inventory, Cart or Checkout code was added.

## Validation

The final focused group passed 80 tests with 455 assertions. Scoped Larastan,
changed-file Pint, Blade compilation, PHP syntax, changed inline JavaScript
syntax and `git diff --check` passed. The full audit and other gated commands
were not run.
