# DEMO-1B completion report

## Status

DEMO-1B CHECKPOINT READY.

## Delivered foundation

DEMO-1B introduces a bounded, production-compatible Order aggregate using ULID identities and normalized Order, item, status-event, note, and payment-event tables. Orders carry a unique random server-generated `WT-{year}-{token}` communication number, an explicit demo marker, optional customer linkage, immutable customer/delivery/item/price snapshots, integer minor-unit totals, ISO currency, optimistic lock version, and idempotency key.

Dedicated transactional actions create Orders, confirm, start preparation, mark ready, dispatch, deliver, cancel, add immutable internal notes, and change informational payment state. Transitions are permission-controlled, stale-safe, invalid-skip resistant, history-producing, and recorded using existing bounded audit conventions. Audit metadata excludes customer contact/address and note bodies.

## Admin workflow

The permission-aware Admin navigation and Client Demo dashboard expose Order Operations. Staff can search, filter, sort, paginate, create a snapshot-only demo Order, inspect customer/delivery/items/totals, perform only valid next transitions, cancel with a reason, add internal notes, manage manual payment state, print a customer Order Summary, and view a protected stable Demo Receipt for an eligible manually paid Order.

Customer documents render from snapshots, omit internal notes and actor IDs, have no Admin navigation, and carry explicit demo/non-fiscal notices. `refunded` is unavailable because no settlement capability exists.

## Permissions and demo boundary

Eleven business permissions were registered: view, create, five ordinary progression permissions, cancel, create notes, manage manual payment status, and view receipts. Existing Super Administrator access includes the registry; ordinary roles receive no Order permissions. Every route retains `auth`, `verified`, `admin.access`, and a destination permission. Every mutation reauthorizes through its dedicated action.

All Order intake and operations fail closed unless the existing code-owned `DemoMode` is configured. The publication kill switch remains independent and continues to govern frontend content only.

## Fixture and data safety

The explicit testing/demo-only fixture creates seven fictional representative Orders and an authorized manager/view-only actor. Fixture Orders use stable `fixture_key` ownership, are idempotent, and do not delete or overwrite client-created demo Orders. The browser workflow used an isolated SQLite database and removed its runtime. No production or persistent demo data was created.

## Validation

- Level 1: 18 tests, 116 assertions, passed.
- Level 2: 30 tests, 197 assertions, passed.
- Scoped Larastan: zero errors.
- Changed-file Pint: passed.
- Blade compilation: passed.
- JavaScript/PHP syntax and `git diff --check`: passed.
- Focused Playwright run: `demo1b-20260728122000`, passed all 13 recorded assertions.

The browser workflow verified login/banner, Orders navigation and search, snapshots, immutable note, every delivery transition, six-event status history, customer-summary privacy, manual paid state, stable protected Demo Receipt, logout protection, view-only transition denial, unauthorized receipt denial, zero required local-asset failures, and no Base44 dependency for Admin Orders.

## Deferred scope

No public checkout, authoritative Pricing, Inventory reservation, stock mutation, payment gateway, settlement, accounting, real refund, shipping integration, tax/fiscal receipt, customer portal, or notification rollout was introduced. No complete audit was run. BE-6A.1 remains deferred; BE-6A.2 and DEMO-1C remain unstarted.
