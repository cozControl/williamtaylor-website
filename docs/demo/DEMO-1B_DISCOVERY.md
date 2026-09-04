# DEMO-1B discovery

## Existing foundations

- Customers are represented by the existing `User` identity model; Orders may optionally link to a user but must retain immutable customer/contact/delivery snapshots.
- Catalogue `Product` and `ProductVariant` models already use opaque ULIDs, stable identities, archives, lock versions, and snapshot-friendly revision data. They do not provide authoritative Pricing or Inventory.
- No viable Order, Cart, Checkout, Payment, Receipt, production invoice, or commerce persistence exists.
- Database conventions use normalized tables, ULID primary keys, foreign keys, explicit indexes, UTC timestamps, integer positions, and optimistic `lock_version` fields.
- Identity uses the code-owned `PermissionRegistry`, `PermissionMetadata`, and `RoleRegistry`; Super Administrator receives every registered permission. No new role is necessary.
- Mutations are action-oriented, transactional, server-authorized with `Gate`, stale-safe where relevant, and recorded through `RecordAuditEvent` with bounded metadata.
- Status-like domains use backed enums and dedicated actions rather than unrestricted form status assignment.
- Admin routes use `auth`, `verified`, `admin.access`, destination permissions, named `/admin` routes, and permission-aware navigation.
- Admin UI already provides responsive panels, forms, tables, feedback, dashboard sections, and a global Demo Environment banner.
- `DemoMode` is a code-owned, allowed-environment boundary that cannot be enabled by request input. The publication kill switch continues to control frontend content only.
- Factories and tests use isolated databases and explicit permission provisioning. Existing test helpers support direct role assignment.
- No PDF dependency suitable enough to justify adding one was found; a protected HTML print view is the appropriate bounded output.
- Existing notification infrastructure is generic Laravel notification support only; automated customer communications are deferred.

## Minimal production-compatible design

Create one normalized Order aggregate:

- `orders`: ULID identity, unique random server-issued display number, demo/fixture markers, optional customer link, customer/delivery snapshots, currency and integer totals, fulfilment/payment states, lifecycle timestamps, cancellation, receipt snapshot metadata, idempotency key, and lock version.
- `order_items`: ULID identity, optional Product/Variant links plus immutable product/variant/SKU/options/price snapshots.
- `order_status_events`: immutable transition history with actor and bounded reason.
- `order_notes`: immutable internal notes with actor and visibility.
- `order_payment_events`: immutable informational payment-state history and bounded reason.

Dedicated actions will create Orders, transition fulfilment states, cancel, add notes, and change informational payment state. All totals are calculated server-side from integer minor-unit line snapshots. Historical rendering uses snapshots, never live Product names or prices.

## Demo intake and fixture strategy

- Intake is protected Admin-only because no safe public checkout exists.
- Intake accepts validated snapshot-only lines so the demo works with zero Product records; optional Product/Variant IDs may be retained when available.
- Creation uses a required idempotency key, a database unique constraint, a transaction, and an initial `new` event.
- An explicitly invoked demo/testing fixture will create seven fictional representative Orders, mark them as fixture-owned, and leave client-created demo Orders untouched.
- Demo intake, fixture creation, and order operations fail closed unless `DemoMode` is configured.

## Permissions

Add the bounded permissions requested by the phase:

`orders.view`, `orders.create`, `orders.confirm`, `orders.prepare`, `orders.mark-ready`, `orders.dispatch`, `orders.deliver`, `orders.cancel`, `orders.notes.create`, `orders.payment-status.manage`, and `orders.receipts.view`.

They are registered for Super Administrator through the existing registry. Ordinary roles receive no automatic Order access.

## Migration, rollback, and data impact

One forward migration creates the five Order tables. Its `down` method removes only those tables in reverse dependency order. No existing table is rewritten and no production data is seeded. Focused validation uses isolated SQLite databases; the optional fixture is environment-guarded, explicit, idempotent, and non-destructive.

## Explicitly deferred

- Public checkout and customer self-service
- Authoritative Pricing, discounts, tax, currency conversion, and fiscal numbering
- Inventory reservation, stock deduction, and overselling guarantees
- Payment gateways, callbacks, settlement, refunds, and accounting journals
- Shipping-provider booking and tracking integrations
- Production invoices, tax/fiscal receipts, and PDF generation
- Automated customer notifications
- Product, Collection, Campaign, Pricing, Inventory, or broader Commerce rollout

## Rollback strategy

Application rollback removes the new routes/navigation and Order code, then rolls back the single Order migration. Demo fixture records are identifiable by `fixture_key`; a future reset may delete only those keys after explicit confirmation. Client-created demo Orders are not fixture-owned and must not be deleted by default.
