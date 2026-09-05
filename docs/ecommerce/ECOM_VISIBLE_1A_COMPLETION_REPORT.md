# ECOM-VISIBLE-1A Completion Report

## Status

**ECOM-VISIBLE-1A IMPLEMENTATION READY FOR OWNER VERIFICATION**

Physical acceptance is not claimed. No next implementation phase was started.

## Orders reconciliation

The authenticated 404 was caused by `OrderController::index()` deliberately
calling `abort_unless($demo->configured(), 404)`. The Admin navigation checked
only `orders.view`, so it exposed a link to a demo-only workflow while Demo Mode
was not configured.

Outcome B applies: Orders is not genuinely available in this served
configuration. Orders is now absent from the sidebar and the dashboard shortcut
unless Demo Mode is configured. No placeholder Orders page was added.

## Admin style delivery

The served Admin layout uses Vite production assets. `public/build/manifest.json`
maps `resources/css/admin.css` to `/build/assets/admin-B6tKY-YU.css`; no
`public/hot` development-server marker is present. The screenshots demonstrate
that the served fingerprint did not contain a usable shared form grid.

The critical, scoped Admin form rules are therefore included through the served
Blade layout in `x-admin.form-styles`. This deliberately avoids depending on an
unrun production build. The layout now includes the non-visible marker:

```html
data-admin-ui-revision="ecom-visible-1a"
```

## Shared field system and UI corrections

- `x-admin.field` provides the label/control structure, helper and error areas.
- Critical form CSS supplies visible boundaries, padding, focus/error states,
  responsive grids and textarea sizing.
- Category fields are separated and associated with stable `for`/`id` values.
  Ready Category Media is presented as thumbnail choices with explicit remove
  and Media Library actions.
- Collection details use the same fields. Ready Media has thumbnail and selected
  states. Products have client-side search, explicit selection, and labelled
  display-order controls.
- Product information uses the shared fields. Generic Products no longer show
  Oxford-specific wording; the historic slug lock remains internal to the
  Oxford Product only.
- Product Media uses compact responsive cards with distinct Primary image,
  Include in gallery, Gallery order and Alt text controls.

## Evidence

### Automated

- Focused PHPUnit: 27 passed, 265 assertions.
- Scoped Larastan for the changed PHP implementation: zero errors.
- Changed PHP files formatted with Pint.
- PHP syntax checks passed.
- Blade templates cached successfully.
- `git diff --check` passed (one existing line-ending warning only).

### Served-route

Unauthenticated requests against the Apache-served application returned the
expected `302` to Login for `/admin/orders`, `/admin/product-categories/create`
and `/admin/collections/create`. Source route registration for Orders remains
intact for configurations where Demo Mode is deliberately enabled. The
authenticated dead navigation is removed when it is not enabled.

### Browser

Browser verification unavailable. The Chrome connection exposed no controllable
browser in this session. No claim is made that the remediated UI has been
observed in an authenticated browser.

## Owner verification gates

1. **Orders:** click through the Admin navigation. Orders must be absent in this
   non-demo configuration and no dead `/admin/orders` link should be exposed.
2. **Category:** open `/admin/product-categories/create`, verify every field and
   image selector is visibly separated, create `Category Physical Test`, save,
   and refresh.
3. **Collection:** open `/admin/collections/create`, verify details, image,
   Product search/selection/order and visibility, create `Collection Physical
   Test`, select existing Media and Product, save, and refresh.
4. **Product:** open the test Product and verify there is no Oxford-specific
   message, Product fields are clear, and Media cards are understandable.

Another phase may begin only after the owner states:

> **ECOM-VISIBLE-1A PHYSICAL PASS**
