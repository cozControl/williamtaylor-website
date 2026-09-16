# PAYMENTS-SNIPPE-1A: Checkout UI and payment wiring

Status: implemented and verified with a fake provider. Live Snippe acceptance remains outstanding.

## Root causes

1. The local `.env` contained the API-key and webhook-secret settings but omitted `SNIPPE_ENABLED`. Its default is false. Both the payer input and payment initiation were therefore bypassed in favor of the existing order-only flow. Added `SNIPPE_ENABLED=true` locally and cleared Laravel's configuration cache. Credentials were not changed or printed.
2. The confirmation view led pending Mobile Money orders with `Order Received` and generic thank-you text. It now leads with the canonical payment state.
3. Checkout's padded anchor buttons retained inline display. A browser diagnostic restoring that display reproduced 4 pixels of overlap with the preceding text at all eight widths. Scoped inline-flex button layout makes their full height participate in layout. The submit button also preceded the summary on mobile; it now follows it. Grid tracks have explicit zero minimums, and the desktop sticky summary remains inside its own column with bounded viewport height.

No fixed storefront header, announcement bar, mobile bottom navigation or footer is rendered in this dedicated checkout composition. The checkout header is in normal flow. Shared chrome templates remain inert, and the closed Cart dialog does not occupy layout space. The imported demonstration runtime remains disabled on checkout and confirmation.

## Actual orchestration

The Cart link uses `/checkout`. `CheckoutController::store()` still validates the payer with `TanzanianPhone` and calls `PlaceOrderService::place()`. That service revalidates canonical prices and availability, creates production `commerce_orders` and immutable lines, reserves inventory and calls `MobileMoneyPayment::prepare()` within the transaction. After commit, `SnippePaymentController::initiate()` reuses `MobileMoneyPayment::start()`. The browser redirects to the existing secret-reference confirmation route, now presented as a payment waiting/status page.

This sequence already existed for enabled payments. The remediation activates its local configuration and repairs presentation; it does not introduce a second gateway, request builder, amount authority or payment lifecycle. Client totals remain ignored. Provider calls remain outside database transactions. Demo Orders remain separate.

## UI and state behavior

- Payment Method identifies Mobile Money and lists M-Pesa, Airtel Money, Mixx by Yas and Halotel.
- Enabled checkout exposes the existing payer input and `Place Order & Pay` action, explaining Order creation, stock reservation and the phone prompt. Disabled installations explicitly explain that Mobile Money is unavailable and retain their existing unpaid order-only behavior.
- Mobile order is Contact/Delivery, Payment Method, Summary, then submit. Desktop keeps the summary in the adjacent column.
- Submission disables the button, changes its label and marks the form busy. Repeated submit events are rejected locally; canonical server idempotency remains authoritative. Returning through browser history resets the checkout button.
- Pending Mobile Money leads with `Check your phone`, a masked number, phone approval instructions and a waiting indicator. It explicitly says payment is not yet confirmed.
- `CheckoutPaymentState` shares safe state between the page and existing status endpoint. The browser polls only William Taylor every ten seconds, with a bounded fetch. State changes reload the canonical page; final states stop polling.
- Ambiguous results say `Checking your payment` and do not offer another attempt. Existing reconcilable states can continue read-only polling. Sticky review reasons stop polling. Only the existing backend `retrySafe()` result exposes deliberate retry.
- Verified paid Orders show `Payment received`, Order reference, persisted total, fulfillment status, delivery next steps and the existing WhatsApp support destination. Provider details and customer address/email remain absent.
- Historical hosted-payment messaging and reconciliation remain supported.

## Validation

| Check | Result |
| --- | --- |
| Payments + Checkout + Cart + Inventory, including production Order Admin | 93 passed, 2,846 assertions; zero failures/errors |
| Payment subset, separately rerun | All 47 passed, 1,529 assertions |
| Scoped Larastan: state presenter and payment controller | Passed, zero errors |
| Pint on changed PHP and payment test | Passed |
| Production Vite build | Passed, exit 0 |
| Blade compilation | Passed |
| Focused Chromium browser verification | Passed; 23 screenshots at eight widths |

Existing tests retain amount authority, post-commit HTTP, encrypted snapshots, duplicate protection, token access, signatures, finality, inventory and demo separation checks. Added assertions verify pending is not success, explicit ambiguous presentation, terminal messages, terminal polling shutdown and no unsafe retry. PHPUnit now defaults Snippe off independently of local `.env`; payment tests explicitly enable their fake-backed configuration.

## Browser evidence

The in-app browser was unavailable. Repository Playwright/Chromium tooling ran the actual Laravel routes with a disposable SQLite database, file sessions, canonical fixture inventory and Laravel HTTP fakes. No application fake routes or provider bypasses were added. Public form submissions, CSRF/session handling, real status polling and canonical provider reconciliation were exercised.

Widths: 375, 639, 640, 767, 768, 1023, 1024 and 1440 pixels. Required viewports used heights 812, 1024 and 900 respectively. Normal checkout was captured at every width. Validation, submitting, pending, fake-paid and fake-expired were also captured at 375, 768 and 1440.

The loading screenshot holds one browser submit event after the application's own listener, inspects the real disabled/busy state, then reloads and performs a normal submission. Paid/expired captures result from fake provider GET reconciliation followed by the page's own polling transition, not browser-injected payment success. Six fake POSTs and six fake GETs were recorded, all with database transaction level zero. No real provider calls occurred.

Screenshots and layout diagnostics:

`storage/app/test-runtime/snippe-checkout-1789539101943/`

Representative captures: `375-checkout.png`, `768-validation.png`, `375-submitting.png`, `375-pending.png`, `1440-paid.png`, `375-expired.png`. `results.json` contains layout/overflow checks and inline-link diagnostics; `provider-calls.jsonl` contains non-sensitive fake-call evidence. The harness's `captures:31` output counts 23 screenshots plus eight diagnostic records.

The earlier successful run is retained at `storage/app/test-runtime/snippe-checkout-1789538663102/`. Intermediate loading-capture diagnostics failed due to navigation timing; they are not acceptance evidence. Final browser exit was zero and its owned disposable server was stopped.

## Files changed

- `.env`: enable existing local integration.
- `resources/views/frontend/checkout.blade.php`: payment copy, mobile ordering and submission state.
- `resources/views/frontend/partials/checkout-styles.blade.php`: scoped responsive/button/summary/waiting styles.
- `resources/views/frontend/order-confirmation.blade.php`: payment-state presentation, polling, total/fulfillment/support and retry loading.
- `app/Domain/Payments/Support/CheckoutPaymentState.php`: shared safe read-only state projection.
- `app/Http/Controllers/SnippePaymentController.php`: reuse state projection for polling.
- `tests/Feature/Payments/MobileMoneyPaymentTest.php`, `phpunit.xml`: presentation/state assertions and local-configuration isolation.
- `scripts/evidence/snippe-checkout-runtime.php`, `scripts/evidence/snippe-checkout-browser.mjs`: disposable fake-provider browser verification.
- Generated frontend build assets and this report.

Logs: `storage/logs/snippe-1a-final.xml`, `snippe-1a-final.log`, `snippe-1a-payments-final.xml`, `snippe-1a-types.log`, `snippe-1a-build.log`, `snippe-1a-blade.log`, `snippe-1a-browser-final.log`.

## Live acceptance still required

Local `APP_URL` currently uses HTTP. The existing gateway correctly requires an HTTPS webhook URL before initiating payment. Enabling the UI does not bypass that requirement. Live acceptance requires the intended public HTTPS application URL, valid production credentials, verified webhook delivery/signatures, the reconciliation scheduler, and a controlled real transaction confirming Order and inventory finality. Credential presence alone was not treated as credential acceptance.

No real payment was attempted. Production payment readiness is not claimed. The unrelated Hero and Factory Instagram baseline decisions remain untouched; no global fidelity acceptance or baseline replacement is claimed.
