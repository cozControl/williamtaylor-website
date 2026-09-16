# PAYMENTS-SNIPPE-1: Direct Mobile Money

Date: 2026-09-15

> Subsequent authorized full audit: **failed**. See [full audit results](PAYMENTS_SNIPPE_1_FULL_AUDIT_REPORT.md). Build passed; broader tests, protected-source integrity, static analysis, dependency advisories, and fidelity remain unresolved.

## Architecture and scope

New storefront payments use Snippe `POST /v1/payments`. Checkout stays on William Taylor and redirects to its own Order confirmation page after initiation. No new hosted Sessions or demo Orders are created by the public checkout/retry routes.

The existing production domain is `App\Domain\Checkout`: `commerce_orders`, immutable `commerce_order_lines`, `PlaceOrderService`, and Variant-based `inventory_reservations`. `CartService` and `CartPresenter` remain the price/availability review path. Inventory remains authoritative through `InventoryAvailabilityService`, `InventoryReservationService`, and `InventoryLedgerService`. The existing `/admin/commerce/orders` workspace and permission routes are extended.

The existing `commerce_payments` aggregate is reused. Existing hosted records keep their method and contract so outstanding payments and staff cancellation history remain reconcilable. Their historical regression fixtures explicitly prepare hosted records, rather than asking the new storefront to create them.

## Provider contract consulted

- [Payments and payment status](https://docs.snippe.sh/docs/2026-01-25/payments): direct endpoints, nested response amounts, supported currency/minimum, and idempotency.
- [Mobile Money](https://docs.snippe.sh/docs/2026-01-25/payments/mobile-money): request fields, customer names, phone format, and supported networks.
- [Authentication](https://docs.snippe.sh/docs/2026-01-25/authentication): Bearer API keys and collection scopes.
- [Webhooks](https://docs.snippe.sh/docs/2026-01-25/webhooks): event envelope, raw-body HMAC, timestamp, and duplicate events.
- [Errors](https://docs.snippe.sh/docs/2026-01-25/error-handling): HTTP status semantics, idempotency conflict, rate limits, and input validation.

The payment documentation says expiry is four hours; the webhook event summary says one hour. Neither duration determines local release. Only an authenticated provider final state does. The `/push` endpoint is listed, but no undocumented replay semantics are assumed or used.

## Payment preparation and state

Inside the existing Order transaction, checkout revalidates current Variant prices and stock, creates Order/line snapshots and reservations, and prepares a payment attempt. The attempt contains a server-generated 27-character key, canonical amount, payer phone, and encrypted immutable request snapshot. Order placement commits, clears the Cart through the existing after-commit callback, then performs provider HTTP with transaction level zero.

Existing `completed` is retained as the payment aggregate's paid state; the Order uses `paid`. Added states are `created`, `initiating`, `attention_required`, and `voided`; existing `pending`, `processing`, `failed`, `expired`, and `cancelled` remain compatible. `method=mobile_money` distinguishes direct attempts from historical `hosted_session` records.

`PaymentGateway` provides an application interface. `SnippePaymentGateway` uses the existing sanitized HTTP transport and normalizes direct response evidence. `MobileMoneyPayment` coordinates preparation, initiation, leases, binding, and reconciliation. `StartSnippePayment::refresh` dispatches direct records to that service while preserving historical Session reads.

The direct API requires whole TZS and at least TZS 500. Existing `SnippeMoney` converts 100 internal minor units to one TZS using exact integer arithmetic. No browser amount/currency or live price after placement becomes payment authority. Checkout asks for first and last name, email, and a Tanzanian payer phone; normalizing the payer phone does not replace the delivery contact.

## Idempotency and uncertainty

The application persists the key and encrypted request before any HTTP call. Uncertain retries reuse that exact body and key. The provider documents a 24-hour key window; automatic replay stops at 23 hours, leaving a safety margin and requiring review. New attempts are allowed only after a definite first-send rejection with no provider reference; they receive new keys. Authentication failure during replay never proves that the original request failed.

There is one active attempt per Order. Short database leases with unique ownership tokens coordinate requests without holding database locks over HTTP. An older worker cannot release a newer lease. POST is not automatically retried by the transport. Timeout, malformed response, 5xx, 429, and idempotency conflicts retain the unresolved attempt and stock. Rate-limit delays are respected by reconciliation and webhook-triggered checks.

## Webhooks and finality

`POST /webhooks/snippe` retains raw-body HMAC-SHA256 verification over `timestamp.raw_body`, constant-time comparison, a 300-second freshness boundary, a bounded body, and its narrow CSRF exception. Public retry remains CSRF-protected and throttled.

Direct event identity and body hash are committed in `snippe_webhook_receipts` before provider I/O. The unique event key deduplicates deliveries; different bytes under the same identity conflict. Receipts store bounded reference/type/outcome/timestamps rather than customer payloads. Failed processing leaves the receipt available for retry. No event can independently set paid: finality requires `GET /v1/payments/{reference}`.

The existing finality service validates provider, bound reference, expected amount/currency, supplied Order/attempt metadata, and local lifecycle. A signed webhook mismatch is also blocked even when GET would otherwise look valid. Mismatch cases are sticky review states: automatic reconciliation cannot consume or release stock. Normalized reference/status/amount/currency evidence is retained privately for review; request snapshots and phone numbers are encrypted and hidden from serialization.

Successful finality atomically marks payment completed, Order confirmed/paid/unfulfilled, consumes exact reservations, and issues through `InventoryLedgerService::issueReserved`. Existing unique Order-line issue identities prevent duplicate movements. Final failed/expired/voided states close the unpaid Order and release its reservations through the existing canonical lifecycle, without changing on-hand stock. Another purchase requires fresh checkout and stock validation. Late completion after release cannot resurrect inventory.

The scheduler retains bounded `payments:reconcile-snippe --limit=10` every five minutes. Public polling reads local truth and never calls Snippe. A 256-bit confirmation bearer reference authorizes public status access; responses contain only Order/payment state, retry permission, and polling permission. Polling stops on final/manual-review states and continues for recoverable uncertainty.

## Storefront and Admin

Checkout reuses `wt-checkout-panel`, existing field styling, typography, buttons, and layout. It lists M-Pesa, Airtel Money, Mixx by Yas, and Halotel, confirms the payer number, and uses “Place Order & Pay”. The confirmation page shows a masked phone, USSD/PIN instructions, and a waiting state. Safe retries appear only after proven rejection.

The real Admin Orders screen shows method, masked payer phone, provider/payment reference, attempt history, request/completion/failure/expiry timestamps, and last verification. Existing permission gates protect viewing and checks. No manual mark-paid action is added. Active Mobile Money cannot use the historical Session-cancel operation; verified finality must release its stock.

## Files and migration

New production files:

- `app/Domain/Payments/Contracts/PaymentGateway.php`
- `app/Domain/Payments/Support/PaymentEvidence.php`
- `app/Domain/Payments/Support/TanzanianPhone.php`
- `app/Domain/Payments/Snippe/SnippePaymentGateway.php`
- `app/Domain/Payments/MobileMoneyPayment.php`
- `database/migrations/2026_09_15_010000_extend_commerce_payments_for_mobile_money.php`

The forward migration extends existing payment/receipt tables with method, encrypted phone/request storage, bounded normalized evidence, lease token, last verification/expiry timestamps, provider receipt reference, and processing time. It does not replace payment history or alter stock quantities.

Modified production files:

- `app/Domain/Payments/Models/Payment.php`, `app/Domain/Payments/Enums/PaymentStatus.php`
- `app/Domain/Payments/Snippe/ProcessSnippeWebhook.php`, `SnippeClient.php`, `SnippePaymentLifecycle.php`, `StartSnippePayment.php`
- `app/Domain/Checkout/Models/Order.php`, `PlaceOrderService.php`, `CancelUnpaidOrderService.php`, `Admin/OrderStatusPresenter.php`
- `app/Http/Controllers/CheckoutController.php`, `SnippePaymentController.php`, `Admin/CommerceOrderController.php`
- `app/Providers/AppServiceProvider.php`, `app/Console/Commands/ReconcileSnippePayments.php`
- `config/snippe.php`, `.env.example`, `routes/web.php`
- `resources/views/frontend/checkout.blade.php`, `resources/views/frontend/order-confirmation.blade.php`
- `resources/views/admin/commerce/orders/show.blade.php`

New coverage is in `tests/Feature/Payments/MobileMoneyPaymentTest.php`. Historical fixtures are adjusted in `tests/Feature/Payments/SnippePaymentTest.php`, `tests/Feature/Checkout/CommerceOrderAdminTest.php`, and `tests/Feature/Checkout/CancelUnpaidOrderTest.php`. This report, the setup guide, the inventory architecture addendum, and a historical-report notice document the change. Protected compiled storefront assets and demo commerce code are unchanged by this phase.

## Verification

Focused validation completed:

| Check | Result |
| --- | --- |
| Payments, Checkout, Inventory, Cart regression run | 86 passed; 2,630 assertions |
| Final expanded direct Mobile Money suite | 34 passed; 1,134 assertions |
| Final late-completion attention-state checkpoint | 1 passed; 39 assertions |
| Scoped Larastan for payment domain and affected commerce controllers/services | Passed, zero errors |
| Changed-file Pint | Passed |
| Checkout, confirmation, and Admin Order Blade compilation/PHP parsing | Passed |
| Checkout and polling JavaScript syntax | Passed |
| Scoped credential-pattern scan | Passed; no real credentials added |
| Protected compiled storefront assets | Unchanged |
| New forward migration | Applied successfully to local MySQL; new payment columns verified |

These runs cover 93 distinct focused tests; the later direct tests overlap the earlier regression run. HTTP fakes assert transaction level zero, persisted canonical Order/attempt/reservation and cleared Cart before provider HTTP. Coverage includes safe retries, stale stock and prices, minimum amount, event signatures/replay/conflict/durability, authoritative provider mismatches, final unpaid release, exact-once consumption/issue, rollback, missed-webhook reconciliation, lease/rate-limit behavior, public-token access, and Admin authorization.

Tests use disposable SQLite `:memory:` and assert that boundary before migration setup. No MySQL fresh/rollback/re-migration, full test suite, full Larastan, complete build, or fidelity matrix was run. The MySQL forward migration is schema evidence, not concurrency acceptance.

No real Snippe transaction has been performed. Browser connection discovery returned no available browser, so physical visual acceptance is pending. Complete builds and full audits require `AUTHORIZE_BE6A1_FULL_AUDIT` under `AGENTS.md`. Initial scoped analysis findings were corrected with an accurate customer-snapshot model annotation and safe name-splitting validation; no suppression/baseline was added.

## Production setup and limits

See [SNIPPE_CHECKOUT_SETUP.md](SNIPPE_CHECKOUT_SETUP.md). Configure `SNIPPE_ENABLED`, `SNIPPE_BASE_URL`, `SNIPPE_API_KEY`, `SNIPPE_WEBHOOK_SECRET`, a canonical HTTPS `APP_URL`, and the existing scheduler. Dashboard setup requires collection read/create scopes, Mobile Money availability, and the signing secret. Direct payments do not require a hosted Profile.

Live provider acceptance, real MySQL concurrency testing, visual acceptance, and the separately gated full audit are not established by HTTP fakes. Amount/currency/reference mismatches and unresolved requests past the replay window require deliberate operational review; there is no automatic override or manual mark-paid bypass.
