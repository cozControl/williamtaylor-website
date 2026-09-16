# Confirmed commerce and inventory architecture

COMMERCE-INVENTORY-1A, 2026-09-10.

## Ownership and invariants

A physical unit belongs to a canonical Product Variant at a Stock Location. Products without visible options still use their existing default Variant. There is no Product stock field, authoritative `in_stock` flag or Variant stock counter.

`inventory_movements` is the authoritative append-only stock history. `inventory_balances` is its runtime projection, unique on `(stock_location_id, variant_id)`. Quantities are whole units. Missing balance rows mean zero; existing catalogue records receive no invented stock.

Catalogue readiness and inventory availability are independent. Zero inventory does not make Product content incomplete. Public stock presentation is deferred to COMMERCE-INVENTORY-1B.

Posted movements are corrected by new adjustments, never edited or deleted through the application. Model mutation guards follow the existing immutable revision/audit convention; these are application guards, not database triggers protecting against privileged direct SQL. Foreign keys restrict removal of referenced Variants, locations and actors.

## Current calculation and query boundary

- `on_hand`: physical stock projected from the sum of signed ledger deltas at a location.
- `reserved`: zero in this phase; no reservation records or placeholder reservation columns exist.
- `available_to_sell`: `on_hand` for eligible Variants at active, fulfillment-enabled locations.

Use `InventoryAvailabilityService::availableToSell` for future purchasing decisions. Do not read balance columns from storefront, Cart or Order code. Archived Variants/Products, missing SKUs, and inactive/non-fulfillment locations are not sellable; physical on-hand remains visible to staff. Content readiness remains a separate caller concern.

`onHand`, `availableToSell` and `productHasAvailableStock` default to Main Store (code `MAIN`) rather than silently aggregating multiple locations. An explicit location can be passed to the Variant queries. Multi-location allocation is a later policy decision. Product availability means at least one non-archived Variant with a SKU has positive availability at Main Store.

Admin uses a bulk summary method to avoid querying a balance separately for every row. No availability cache is introduced.

## Current posting boundary

`InventoryLedgerService` supports OPENING, RECEIPT, ADJUSTMENT_IN and ADJUSTMENT_OUT. Types are application enums stored as strings; SALE/ORDER_ISSUE and RETURN can be added later without a database enum rewrite. The current service does not accept those future operations.

All writes authorize `inventory.manage`, validate whole units/reason/reference details, lock Product and Variant, share-lock the location, lock the balance, append a movement, save the projection and record the existing audit event in one database transaction. Parent locks serialize creation of an absent balance and prevent concurrent receipts from overwriting each other. Lock order is Product, Variant, location, idempotency entry, then balance. Deadlocks use Laravel's bounded transaction retry.

An optional globally unique idempotency key and request fingerprint make exact retries return the existing movement without updating stock or audit again. Reusing a key with different actor, Variant, location, quantity, operation or reference details is rejected. Current reads are used for idempotency lookups, including collision recovery, so a surrounding transaction's older read snapshot is not the authority for replay. Admin supplies a UUID per stock submission.

`source_type` and `source_id` hold optional durable provenance. Future issue keys can use `order:{order_id}:item:{line_id}:issue`. Type-specific reference presentation belongs to the future business workflow, not a generic display of PHP class names.

Opening Stock is permitted only before the first movement for the Variant/location. Receipts add units. Stock count adjustments take a counted total plus the previously displayed on-hand value; a changed balance is rejected for reload/recount. Equal counts create no movement. Ordinary operations cannot produce negative stock. Inputs and resulting balances are capped at 2,147,483,647 whole units to stay within the signed movement range.

`inventory:reconcile` is read-only. It compares summed ledger deltas against projections, including ledger-only and projection-only pairs. It supports `--location=` and `--variant=` filters, streams grouped results, reports drift and exits unsuccessfully when drift exists. There is no implicit repair mode. Future projection repairs must be explicitly governed; they must never rewrite historical movements.

## Future lifecycle (documented only)

1. **Variant Inventory:** receive and count stock through the ledger.
2. **Storefront Availability:** derive stock presentation through `availableToSell`.
3. **Cart:** check availability; adding, changing or removing Cart items never posts movements and never reserves stock.
4. **Checkout Revalidation / Order Placement:** atomically revalidate and create reservations at a specific location. Reduce availability, leaving on-hand unchanged.
5. **Order Confirmation:** atomically consume the active reservation, post exactly one SALE/ORDER_ISSUE movement per issue identity, and close the reservation. On-hand decreases once.
6. **Cancellation / Expiry:** release the reservation; on-hand is unchanged. A previously issued Order requires an explicit reversal/restock workflow, not an edit to its issue.
7. **Return / Restock:** post a new RETURN movement, retaining the original issue history.

When reservations exist, the query service changes internally to `available_to_sell = on_hand - active reservations`; storefront and Cart callers retain the same service boundary. Reservations are commitments, not negative on-hand movements. Reservation/issue transitions must share the same locking boundary, and later manual adjustments must account for active commitments.

No Cart, Checkout, production Order, payment, reservation, transfer, valuation or storefront stock UI is implemented by 1A. Existing demo Orders remain isolated and never establish or deduct inventory.


## COMMERCE-INVENTORY-1B storefront contract

Canonical Product detail and shared Product cards now compose InventoryAvailabilityService's request-scoped bulk storefront projection. MAIN remains the fulfillment source. Exact `available_to_sell` is retained inside PHP; HTML/bootstrap data exposes binary `is_available` per Variant and Product, not stock quantities or location/movement details. Product availability is any eligible stocked Variant; selected-Variant availability is independent and never silently switches the shopper to another combination.

Normal zero-stock Products remain visible and linked. The card Out of Stock treatment is derived, while persisted legacy sold-out badges are suppressed from this operational presentation without editing those records. Catalogue readiness remains unchanged. Pre-Order and Limited Edition Campaign cards retain approved claims/schedules and are not invalidated by zero immediate stock. Following their Product URL uses ordinary immediate-purchase availability; no Campaign reservation or purchase workflow is invented.

Lists warm all represented Product identities together. Detail embeds one Variant map and uses the existing option resolver for Colour/Size changes. Colours stay inspectable for media; unavailable Sizes are disabled relative to the selected Colour. Product gallery thumbnails are unchanged. No cross-request availability cache or manual publish/cache-clear requirement is introduced. The bulk snapshot is presentation-only; future Cart code must query fresh availability.

Canonical Product detail uses the accepted canonical Taylor Product layout and Laravel interaction code. Imported Product SPA mounting is disabled to prevent static Variant/Cart state from replacing canonical availability. The shared purchase-click boundary blocks imported demonstration Add to Cart/Buy Now actions. Stocked controls are visually ready but do not persist Cart data. Protected compiled files are unchanged. Static, unconfigured catalogue reference content remains a fallback, not an inventory source.

COMMERCE-CART-1 must accept Variant ID and requested quantity, resolve the current canonical Product/Variant server-side, enforce catalogue eligibility, and call `availableToSell`. Add/update rejects quantities above availability. Neither operation creates reservations or stock movements. Checkout must revalidate atomically again; reservation persistence begins only with the authorized Order phase. Existing Products need staff-entered Opening Stock before their ordinary purchase controls become ready. No fabricated stock or scarcity message is introduced.

## COMMERCE-CART-1 implemented contract

Canonical Product/Variant -> InventoryAvailabilityService -> guest session Cart. `CartService::snapshot()` is the fresh server read interface. `CartPresenter` resolves current catalogue readiness, complete Variant option ownership, current Product/base or Variant override pricing, current Media, and one uncached bulk inventory summary at MAIN. No HTML availability flag or client price participates in validation.

The session key `commerce_cart` contains only `variant ULID => requested integer quantity`. Same-Variant adds merge; different Variants remain separate. Add and update validate the final requested quantity and leave existing state unchanged on failure. The 100-line bound limits session size, not units per customer; the whole-unit technical range is 1..2147483647 and fresh inventory remains the business maximum. Cart mutations use Laravel session blocking with the existing cache lock infrastructure. No new storage service or Cart table is required.

Cart reads preserve requested quantities when stock falls or identities become unavailable, expose a line issue, and set `is_checkout_ready=false`. Missing identities render an unavailable item, never a substitute Variant. Current canonical metadata and prices are reconstructed, not stored as permanent snapshots. All multiplication and subtotal accumulation use checked integer minor units. Display formatting follows the existing whole-shilling TZS storefront convention without float arithmetic. Only subtotal is calculated.

Drawer, page and header consume the same presentation contract. View-only request reuse is separate from fresh mutation/readiness reconstruction. GET /cart supports HTML or JSON; POST /cart/items, PATCH /cart/items/{variant}, and DELETE /cart/items/{variant} are CSRF-protected session operations. An internal clear operation exists without a public Clear button/route. JSON returns the canonical Cart and server-rendered content; the browser never calculates Money. The old wt_cart localStorage key is retired before the imported module starts; protected compiled files are unchanged.

Adding, viewing, updating, removing and clearing never reserve inventory or write movements. Buy Now remains intercepted and does not create a Cart or Order. Checkout is disabled with truthful interim text because no production Checkout exists. Pre-Order approval and Limited Edition quantities are not stock authority and cannot bypass ordinary availability.

The preceding Cart-phase description records the entering boundary. COMMERCE-ORDER-1 now implements production Checkout as described below; the Cart snapshot alone remains insufficient authority to place an Order.

## COMMERCE-ORDER-1 production Checkout and reservations

Product/Variant → Inventory on hand → availableToSell → session Cart → Checkout → atomic Order placement → active Inventory Reservation → production Order.

`available_to_sell = max(0, on_hand - active reservations)` for the exact Variant and Stock Location. Eligibility remains a separate fail-closed check. `InventoryAvailabilityService` owns this formula for individual and bulk callers. `InventoryLedgerService` rejects adjustments below active commitments. Cart operations still neither reserve nor issue stock.

Production `App\Domain\Checkout\Models\Order` and `OrderLine` use `commerce_orders` and `commerce_order_lines`. Existing `App\Domain\Orders` models, manually priced demo actions, actor-required records and Admin demo routes remain isolated. Production lines retain protected canonical Product/Variant references and immutable title, SKU, option-label, unit-price, quantity and line-total snapshots. Guest contact and delivery snapshots belong to the Order; no account is created. Commercial model updates/deletes are blocked in this phase.

`PlaceOrderService` reloads session identities inside one transaction, locks Products ascending then Variants ascending, shared-locks MAIN, and locks balances ascending by Variant. This extends the ledger's Product → Variant → location → balance ordering. Parent locks protect absent balances. The identity hint query runs before the transaction so it does not establish a stale MySQL repeatable-read snapshot before lock acquisition; locked identity ownership is checked again. Fresh canonical Cart reconstruction follows acquisition. Bulk reservation reads share this transaction; writes are per immutable line. Deadlocks can retry the whole placement up to three times. Database uniqueness remains the last defense for submission/line replay.

Checkout attempts are random session-owned identities with server-side review fingerprints. The database stores a unique hashed submission key and immutable request/cart fingerprints. Replays return the same Order; changed input or a different nonempty bag is rejected. A changed current price or quantity requires a fresh Checkout review and resubmission. No submitted price or total is authoritative. Checked integer minor-unit multiplication and accumulation reconcile to the stored merchandise subtotal and total.

Order numbers use a dedicated database auto-increment allocation, formatted `WT-YYYY-000001` (minimum six digits). Allocation is global, concurrency-safe and not row-count based; gaps are allowed and the year does not reset the sequence. Production IDs remain ULIDs. New Orders are `pending_confirmation`, `unpaid`, `unfulfilled`, with shipping `pending`. No canonical shipping/tax/discount engine was discovered, so monetary totals contain merchandise only; no invented zero-price delivery promise is stored.

`InventoryReservationService::reserveMany` bulk-loads identities, active availability and existing reservations, then reserves exact positive quantities at MAIN. `reserve` provides single-line replay. Reservation rows identify Order and line, and a unique line FK prevents duplicates. Typed statuses are active, consumed and released; this phase creates active only. Positive quantity database constraints and model validation apply. No expiry is invented; commitments remain active until a future controlled lifecycle action. No reservation CRUD is exposed.

After the actual outermost commit, matching session Cart contents clear. Validation, stock failure, database rollback and failed audits preserve the Cart. A replay cannot erase a new unrelated bag. An audit record contains safe order/quantity/money/outcome metadata, without contact/address snapshots or access tokens.

GET/POST `/checkout` reuse guest session blocking and normal web CSRF, with submission throttling. Valid Cart CTAs now navigate to Checkout. Empty/invalid bags cannot show a working order form. Confirmation uses a 256-bit random bearer reference, private/no-store, no-referrer and noindex headers. It renders immutable summary information without email/address/phone, and raw IDs/order numbers do not resolve as public receipt credentials.

Future COMMERCE-ORDER-2 transitions, **not implemented**:

- Confirmation/payment → consume reservation and post idempotent ORDER_ISSUE → on-hand decreases.
- Pending cancellation → release reservation → on-hand unchanged and available-to-sell restored.

Pre-Order Campaign approval and Limited Edition edition counts never bypass normal stock. Admin production Orders, payment gateways, reservation consumption/release workflows and automatic expiry remain outside this phase. Protected compiled storefront assets remain untouched.

## COMMERCE-PAYMENT-SNIPPE-1 payment and inventory lifecycle

The preceding COMMERCE-ORDER-1 description records its phase boundary. Hosted payment, reservation consumption and final unpaid release are now implemented for ordinary stocked Orders:

Order + active MAIN reservation → committed database transaction → Snippe Payment Session → signed `payment.completed` → confirmed/paid Order → consumed reservation → `ORDER_ISSUE` ledger movement → fulfillment still unfulfilled.

For on-hand 5 / reserved 2 / available 3, successful payment results in on-hand 3 / active reserved 0 / available 3. The availability reduction occurs at reservation, and is not repeated at confirmation. `InventoryLedgerService::issueReserved` writes the immutable negative movement and updates its balance projection. A unique `order:<order>:line:<line>:issue` identity prevents duplicate issue. Staff posting APIs cannot call ORDER_ISSUE directly; system completion requires the controlled payment lifecycle. System ledger actors are null and history labels them appropriately; manual stock actions retain their existing authorization requirements.

`commerce_payments` stores independent typed Payment state and immutable amount/Order/attempt identities. One nullable unique active-Order association prevents multiple active attempts. A Session failure can be retried inside the same payable Session without releasing stock. Authenticated final Session expiry/cancellation closes the unpaid Order and releases active reservations without movements. Closed Orders cannot reuse released stock; a new Order requires normal Cart/stock validation. Abandoned definitely-rejected initialization and unresolved ambiguous provider creation retain reservations for operational review rather than guessing payment finality.

Snippe network calls run outside all database transactions. A Payment I/O lease serializes provider operations without holding inventory locks. Completion/release locks Order, Payment, sorted Products, sorted Variants, MAIN, sorted balances and sorted reservations, extending the existing Product → Variant → location → balance lock order. Canonical line/reservation relationships and positive quantities are revalidated. Payment/Order transition, reservation state, issue movements/projection, audit and webhook receipt are committed together; exceptions roll them all back.

Guest return pages are read-only and cannot mark paid. Signed webhooks validate raw-body HMAC, ±300-second freshness, event deduplication, exact expected TZS amount/currency, persisted Session and supplied metadata. A server-side reconciliation command reads authenticated Session state for missed events; it never infers finality from local expiry alone. The existing scheduler invokes `payments:reconcile-snippe --limit=10` every five minutes when enabled.

The money adapter converts 100 internal minor units to one integer TZS exactly and rejects fractional/invalid amounts. Immutable Order totals are payment authority; live Product prices and provider display line items are not. Profile branding/methods are Dashboard-owned. No shipping/tax/discount amounts, Pre-Order deposits, refunds/disbursements, accounts, returns or production Admin Orders workspace were added.

Operational configuration, current provider documentation discrepancies, credential verification, unresolved-attempt policy, cron and go-live acceptance are documented in [SNIPPE_CHECKOUT_SETUP.md](SNIPPE_CHECKOUT_SETUP.md). Live Snippe credential and physical visual acceptance remain pending.

## COMMERCE-ORDER-OPS-1 controlled unpaid cancellation

`CancelUnpaidOrderService` owns the authorized staff operation. Pending-confirmation, unpaid, unfulfilled Orders must have complete, exact active MAIN reservations and no issue movement or completed Payment. No-Payment Orders and definitively rejected initialization with no active/unresolved remote attempt can cancel locally. An unbound active or ambiguous attempt always requires investigation first; a missing Session reference is not evidence of no payable Session.

For a bound Session, the existing Payment I/O lease coordinates the operation. Authenticated GET, optional Session cancel POST and a final authenticated GET run outside transactions. Cancel POST transport success alone is never finality. Existing `SnippePaymentLifecycle` validates amount, currency, Session and metadata and applies completed/cancelled/expired evidence. Completed evidence confirms and issues; it prohibits cancellation. Unknown outcomes retain active reservations and a payment support issue for reconciliation.

Final unpaid cancellation locks Order, sorted Payments, Products, Variants, MAIN, balances and reservations. Provider final state, release, immutable staff cancellation record and audit share one local transaction. Without a Payment, the same service releases validated reservations under that lock order without inventing a Payment record. Order becomes Cancelled/unpaid/unfulfilled; historical failed Payments remain unchanged, and verified expired Sessions retain Expired payment state. No movement or stock projection update occurs: on hand 5 / reserved 2 / available 3 becomes on hand 5 / reserved 0 / available 5.

Duplicate cancellation returns existing final state without replacing reason/actor or duplicating audit. Paid, consumed or issued Orders cannot use this operation. A late paid event after release follows the existing needs-attention path; it cannot reopen the Order or issue stock. Customer retries remain server-blocked for cancelled Orders, guest confirmation shows an honest terminal state, and no Cart is recreated. Refunds, returns, restocking and fulfillment remain outside this phase.


## PAYMENTS-SNIPPE-1 direct Mobile Money (2026-09-15)

New checkout uses the existing production Order/line/reservation architecture and `commerce_payments`, with `method=mobile_money`. The local payment attempt and encrypted immutable request are prepared inside Order placement. Order commit and Cart clearing precede all provider I/O. Canonical Variant price and available-to-sell revalidation remain mandatory.

Direct completion requires authenticated payment-status GET and exact reference/amount/currency/lifecycle validation. Existing `SnippePaymentLifecycle` and `InventoryLedgerService::issueReserved` atomically confirm the Order, consume reservations, and append the single Order-line issue. Verified failed, expired, or voided direct payments close the unpaid Order and release reservations without changing on hand. Another purchase needs fresh checkout and stock validation. Active Mobile Money cannot use the hosted Session cancellation operation.

Timeouts, ambiguous creation, and provider inconsistencies never release stock. Mismatches require review. Direct webhook receipts are committed before provider checks and survive processing failures; Order/payment/inventory changes still roll back together. Historical Session records retain their original method and reconciliation contract. The direct flow supersedes hosted-checkout requirements above for newly placed Orders.

See [PAYMENTS-SNIPPE-1 report](PAYMENTS_SNIPPE_1_MOBILE_MONEY_REPORT.md) and [setup](SNIPPE_CHECKOUT_SETUP.md).
