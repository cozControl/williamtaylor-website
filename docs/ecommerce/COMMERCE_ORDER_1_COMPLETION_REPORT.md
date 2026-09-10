# COMMERCE-ORDER-1 completion report

Date: 2026-09-10

**COMMERCE-ORDER-1 IMPLEMENTATION READY FOR GENERAL INSPECTION**

Physical visual acceptance remains paused. No full audit or COMMERCE-ORDER-2 work was performed.

## Discovery and ownership mapping

| Capability | Current implementation | Production safe / decision |
| --- | --- | --- |
| Protected Checkout | `public/website/js/index-DxdnTNDA.js`: oxblood logo header, 1024px content limit, desktop 3:2 columns, white bordered panels, sticky summary, stacked mobile | Reuse composition in Blade; compiled asset unchanged |
| Contact/address | Email, phone; full name, street address, city, region, postal | Reuse; postal optional; no speculative fields |
| Shipping/payment | Hard-coded delivery choices/free threshold, simulated phone payment, example bank numbers, Pay button | Not business authority; suppress and use truthful pending delivery / no payment copy |
| Success | Imported in-component success state and tracking CTA | Recreate acknowledgement; omit false paid/confirmed/tracking behavior |
| Demo Orders | `orders`, `order_items`, `is_demo=true`, required staff creator, admin-demo source, manually supplied amounts, demo receipt helper | Keep isolated; separate production tables/models |
| Money/number helpers | Demo Money uses float display and multiplication before overflow handling; random number helper includes demo receipts | Do not reuse for commercial calculations/receipts; preserve WT/year visual convention |
| Cart | Session Variant/quantity, canonical presenter, disabled Checkout CTA | Reuse presenter and integer display formatter; enable ready CTA |
| Inventory | MAIN, Product/Variant parent locks, ledger/projection, availability API | Extend with separate active reservations and commitment-safe adjustments |
| Audit | `RecordAuditEvent`, nullable actor | Reuse for guest placement inside transaction |
| Buy Now | Existing guarded imported action | Preserve bounded behavior; ordinary Cart → Checkout is the supported flow |

## Required implementation details

1. **Status:** Implementation ready for general inspection; physical acceptance is not claimed.
2. **Protected composition:** Exact imported contact/address panels, 3:2 desktop summary arrangement, fonts, oxblood/gold/offwhite tokens and gold primary hierarchy were inspected and reused.
3. **Demo discovery:** Demo migrations contain required creator, default demo discriminator/source, fixture identity and staff lifecycle records. Admin queries require configured demo mode and filter demo records.
4. **Reuse:** Canonical Cart presentation/readiness/pricing, integer storefront formatting, Product/Variant identities, MAIN, ledger locking conventions, ULIDs and shared audit infrastructure.
5. **Isolation:** No demo Order action/model/helper is called from Checkout; no demo Admin exposure was changed.
6. **Order model:** `App\Domain\Checkout\Models\Order` → `commerce_orders`; immutable commercial record with guest/delivery snapshots and lifecycle fields.
7. **Line model:** `OrderLine` → `commerce_order_lines`; protected FKs plus title/SKU/options/unit-price/quantity/line-total snapshots; updates/deletes blocked.
8. **Number:** `WT-YYYY-` plus global auto-increment allocation padded to six digits; unique database constraint, server-generated, gaps permitted, no row count or annual reset.
9. **Guest Checkout:** Laravel web session, no login/User/Customer creation; thin controller validates and delegates placement.
10. **Contact:** Name, normalized lowercase email and normalized phone snapshot.
11. **Delivery:** Name/phone, street address, city, region and optional postal code, matching the protected form.
12. **Shipping:** No canonical shipping engine found. Status pending; no fabricated fee or inferred complimentary shipping. Shopper sees charges not calculated.
13. **Tax:** No authoritative rule found; no tax calculation/field invented.
14. **Discount:** No coupon/campaign/compare-at discount calculation.
15. **Summary:** Fresh `CartPresenter`; canonical options, quantities, unit/line prices and merchandise subtotal. No client-side monetary calculation.
16. **Placement service:** `PlaceOrderService` resolves, locks, validates, creates durable snapshots/reservations, verifies totals and records audit.
17. **Atomicity:** All durable placement writes in one transaction; any failure rolls them all back. Clearing is after outermost commit.
18. **Revalidation:** Fresh canonical presenter runs after inventory parent locks; eligibility, quantity, current price and stock are checked again.
19. **Reservations:** Separate `inventory_reservations`; exact Variant, location, Order and Order line; no movements.
20. **Statuses:** Typed active/consumed/released; only active creation implemented.
21. **Reservation replay:** Unique Order-line FK, safe matching replay; mismatched identity/location/quantity/status rejected.
22. **Availability:** Shared service subtracts active reservations in individual and grouped bulk queries, floors at zero and retains eligibility checks.
23. **Oversell:** Relevant parent locks serialize competing stock/checkout operations; insufficient current stock rejects the whole Order. Manual counts cannot reduce on-hand below active commitments.
24. **Lock ordering:** All Products sorted, all Variants sorted, MAIN shared lock, balances sorted by Variant. Identity hints load before transaction and are checked under locks, avoiding a stale initial MySQL consistent-read snapshot. Up to three transaction attempts for retryable deadlocks.
25. **Order status:** Pending confirmation, because no payment or business confirmation has occurred.
26. **Payment:** Unpaid; no Payment rows.
27. **Fulfillment:** Unfulfilled; no picking/shipping/delivery mutation.
28. **Cart success:** Cleared only after successful outermost commit and only if its identity/quantity fingerprint still matches.
29. **Cart failure:** Validation, stock rejection and forced database/audit failure retain requested items.
30. **Historical identity:** Title, SKU and option labels stored independently of live Product edits; FKs preserve traceability.
31. **Price:** Current server price becomes immutable unit minor amount; stale form price causes review/resubmission.
32. **Integrity:** Integer bounds checked before multiplication/addition; line sum must equal subtotal and total. Merchandise is the only authoritative component.
33. **Submission replay:** Session-owned random attempt, server review fingerprint, unique durable hashed key and request/cart fingerprints. Duplicate POST returns same outcome; conflicting input/new bag rejected.
34. **Validation:** Ordinary field errors preserve old input; empty, invalid and stale bags fail closed with shopper-facing messages. Normal CSRF and session locks apply; POST throttled.
35. **Price changes:** No silent repricing into an Order; new Checkout review captures the updated current prices, then permits resubmission.
36. **Confirmation:** Canonical `/order-confirmation/{reference}` renders stored number, pending acknowledgement and immutable item/merchandise summary.
37. **Guest access:** 256-bit random bearer reference, no raw ID/number lookup, no email/address/phone display, no-store/no-referrer/noindex response headers. Possession of the unguessable reference is the access capability.
38. **Pre-Order:** Campaign approval does not bypass ordinary MAIN stock; no deposit/preorder purchasing workflow.
39. **Limited Edition:** Only normally stocked Variants can order; edition counts remain separate.
40. **No issue:** Checkout never calls ledger posting and never creates SALE/ORDER_ISSUE.
41. **On-hand:** Proven unchanged at placement; explicit fixture receipts are the only new movements in evidence, and roll back afterward.
42. **Quantity evidence:** On-hand 5, Order 2, active reserved 2, available 3. Independent option Variants and locations covered.
43. **Audit:** Transactional `commerce.order.placed`, guest actor null, number/line/quantity/money/reserved outcome only; no customer payload or access/submission tokens in audit summaries.
44. **Constraints:** Unique number/reference/submission and Order+Variant line identity; unique reservation line; FK restrictions; lookup/lifecycle/time indexes; positive quantity checks in MySQL and equivalent SQLite triggers.
45. **Queries:** Bulk canonical reconstruction, inventory identities and reservation summaries; per-line durable inserts. `reserveMany` avoids per-line identity/availability reloads. No Redis/queue dependency introduced.
46. **UI:** Existing frontend layout, compiled CSS fonts/tokens/button classes and Laravel-owned Blade panels. Unsupported payment, shipping selectors and tracking are suppressed. Compiled JS/CSS unchanged.
47. **Responsive:** 3:2 grid at ≥1024px, stacked below; two-column fields at ≥640px and single-column narrow fields; constrained inputs, wrapping text and no page overflow workaround. Implemented for desktop/1024/768/430; measurements remain unverified.
48. **Focused tests:** See final verification below.
49. **Actual HTTP/session/database:** `scripts/checkout-http-evidence.php` runs the real HTTP kernel, cookie jar and CSRF against local MySQL, with rollback-only inventory. Add 2 → Checkout → POST → durable Order/line/reservation in transaction → confirmation → replay → reject competing quantity 4. Committed Cart clearing is independently verified by isolated SQLite HTTP tests; MySQL evidence deliberately defers it under an outer transaction.
50. **Rollback:** Injected audit failure after reservations leaves zero production Orders/lines/reservations and original Cart. MySQL evidence restores original persistent Order/movement/reservation counts in `finally`.
51. **Concurrency/replay evidence:** Same POST produces one Order/reservation; conflicting reuse rejected. Independent MySQL connection hits lock timeout 1205 on the placement Product lock. Sequential competing checkout proves stock rejection. This is bounded lock-contention evidence, not a simultaneous two-browser/two-successful-POST race harness.
52. **Scoped checks:** See final verification below. No full analysis/tests/build/dependency/fidelity audit.
53. **Browser:** One in-app connection attempt returned `Browser is not available: iab`. No retries/alternative control; physical verification paused.
54. **Architecture:** Canonical `COMMERCE_INVENTORY_ARCHITECTURE.md` documents the new boundary, formula, locking, snapshots and future consume/issue/release transitions.
55. **Admin:** Production Admin Orders workspace NOT implemented.
56. **Payment gateway:** NOT implemented; no fake payment success.
57. **Consumption/issue:** NOT implemented. No release/expiry workflow; active reservations remain until a future controlled lifecycle action. COMMERCE-ORDER-2 has not started.

## Verification

- Production Checkout/reservations: **6 tests, 140 assertions passed** on the final run.
- Cart/Inventory regressions: **18 tests, 267 assertions passed**; the combined run before the final additional Checkout test also passed (23 tests, 386 assertions). Final covered set: **24 tests, 407 assertions** across the scoped runs.
- Scoped Larastan: Checkout domain/controller, Cart presenter, changed inventory services and reservation model passed; final controller-only recheck also passed.
- Changed-file Pint passed. Sixteen PHP files passed syntax checks, with final controller/evidence-script rechecks passing. Blade compilation passed; extracted Checkout JavaScript passed `node --check`; temporary check file removed.
- `git diff --check` passed. Protected compiled JS/CSS show no diff.
- Local MySQL HTTP/session/database script passed, including CSRF 419, quantity 2 against 5 on-hand, 2 reserved / 3 available, no issue movement, safe confirmation, duplicate replay, competing quantity rejection and independent-connection lock timeout 1205. Rollback restored original persistent counts and zero fixture on-hand.

The original DatabaseMigrations test setup encountered an unrelated Collection navigation-index down-migration defect; Checkout tests now explicitly assert SQLite `:memory:` and rebuild only that disposable test database without invoking unrelated down migrations. A new historical-title fixture initially omitted its required revision timestamp; that fixture was corrected and the final tests passed. No MySQL fresh/rollback/re-migration command was run.

The local MySQL schema contains both bounded forward migrations. Evidence fixture changes were rolled back. The existing Xdebug log-path warning did not prevent execution.

Full audit requires the separate AGENTS.md authorization token; this report does not claim full-audit or physical acceptance.
