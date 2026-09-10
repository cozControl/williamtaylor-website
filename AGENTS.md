# FULL-AUDIT AUTHORIZATION GATE

You are not authorized to run any command matching or invoking:

- `npm run fidelity:be6a1`
- `npm run fidelity:be6a1:self-check`
- the complete browser/fidelity matrix
- `composer ci:check`
- the full Laravel test suite
- full Larastan
- MySQL fresh/rollback/re-migration
- complete builds or dependency audits

unless the user's latest message contains this exact token:

`AUTHORIZE_BE6A1_FULL_AUDIT`

Passing focused or checkpoint tests does not grant this authorization.

When implementation and checkpoint validation are complete, stop and request the token. Do not infer approval, do not proceed automatically, and do not run a preliminary full audit.

# PERMANENT HOMEPAGE SECTION VISIBILITY CONTRACT

Every new Homepage section must:

- Register its key, fixed position, default visibility, editor route and protected-runtime boundary in `App\Domain\Homepage\Support\HomepageSectionRegistry`.
- Reuse `HomepageSectionVisibility` and `homepage_section_settings`. Never add section-specific visibility booleans or interpret raw settings independently.
- Keep visibility independent of content mode, publication, copy, Media and canonical entity references. Hidden takes precedence over managed and unmanaged content and renders no section or empty space.
- Expose visibility and the shared authorized Show/Hide action through the Homepage workspace, and show visibility using the shared status component in the dedicated editor.
- Gate the complete server-rendered section and its inert projection, and ensure the existing bounded Homepage synchronizer removes any hidden section recreated by the protected runtime. Do not edit protected compiled assets.
- Resolve the settings map once per request. Preserve configuration when hiding/showing, existing authorization, CSRF and audit behavior.
- Add focused coverage for the visibility boundary. Homepage section order and allowed keys remain code-owned; this is not a page builder.

This applies to Follow the Journey, Inner Circle, Sign the Ledger and all later sections when implemented. Do not register or implement later sections before their authorized phase.

# PERMANENT STOREFRONT SHOP NAVIGATION OWNERSHIP

- Navigation owns ordinary editorial/global links and legitimate existing labels. Catalogue owns Collection discovery and generated Collection URLs; do not copy Collection links into Navigation records or Site Settings.
- Reuse `StorefrontShopNavigationPresenter` for desktop and mobile. Use Collection lifecycle visibility and `navigation_order`; Product membership positions are separate.
- Resolve New Arrivals through the existing Homepage Collection relation. Pre-Order and Limited Edition remain canonical Campaign destinations, not fabricated Collections.
- Preserve the protected header composition and the Laravel-owned header synchronization boundary. Do not edit protected compiled bundles to supply commerce data.

# PERMANENT VARIANT INVENTORY CONTRACT

- Inventory belongs to canonical Variants at Stock Locations, including default Variants for Products without options. Never add authoritative Product/Variant stock counters or stored in-stock flags.
- Use the append-only inventory movement ledger and its balance projection through `InventoryLedgerService`; corrections are new movements. Preserve transactional locks, nonnegative whole-unit balances, authorization, audit and idempotency.
- Catalogue readiness is independent of stock. Future storefront and Cart code must ask `InventoryAvailabilityService::availableToSell`.
- Adding, updating or removing Cart items never deducts or reserves stock. Reservations are separate from on-hand movements and begin only in a future Checkout/Order phase.
- Follow `docs/ecommerce/COMMERCE_INVENTORY_ARCHITECTURE.md`. Do not implement reservation/Order persistence or public stock presentation before the corresponding authorized phase.
