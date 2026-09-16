# PAYMENTS-SNIPPE-2B: Reconciliation safety and acceptance preparation

Date: 2026-09-16

**Reconciliation remediation implemented and focused validation passed. Live acceptance is not yet ready.** No real payment, provider POST, USSD push or real reconciliation was executed during this task. Tests used isolated SQLite databases and HTTP fakes.

## Previous scheduler side effect

`payments:reconcile-snippe` called `StartSnippePayment::refresh()`. Mobile Money delegated to `MobileMoneyPayment::refresh()`, which could send the saved initiation POST when no provider reference was recorded. An unsent legacy hosted record could similarly create a Session. A scheduler status check could therefore have an external payment side effect.

## New boundary

The command now calls `StartSnippePayment::reconcile()`, which delegates Mobile Money to `MobileMoneyPayment::reconcile()`. These methods explicitly disable initiation before entering the shared existing lifecycle machinery. They preserve leases, next-check timing, rate-limit deferral, bounded batches, the existing execution deadline and canonical finality.

Known Mobile Money references use the existing gateway status GET. Historical hosted references use Session GET; previously sent hosted attempts may use the existing bounded GET discovery. An unsent hosted attempt cannot reach Session creation. Sticky mismatch/review reasons are preserved for both paths.

Provider-read-only means no external create/push/replay request. Reconciliation may still update local Orders, payments and inventory through the existing verified finality service. It does not allocate another local payment attempt. The gateway, payload construction, encrypted snapshots, signature verification, provider-evidence finality and inventory architecture remain intact.

The Admin status-check action and direct webhook verification also use the read-only boundary. Historical browser retry uses read-only reconciliation. The customer Mobile Money retry route now refuses to initiate an active ambiguous attempt; deliberate retry after `retrySafe()` confirms a definite original rejection is preserved.

## Missing-reference handling

| Existing attempt | Scheduler behavior |
| --- | --- |
| No reference and no recorded send timestamp | Mark `attention_required` / `initiation_not_recorded`; do not infer that the provider could not have received it |
| No reference with recorded initiation | Mark `attention_required` / `initiation_outcome_unknown` |
| Initiation timestamp at least 23 hours old | Preserve active attempt/reservations; flag sticky `idempotency_window_elapsed`; no replay |
| Existing sticky review reason | Preserve it; do not overwrite it or send HTTP |
| Active lease or future retry time | Defer without provider I/O |
| Definite rejected, inactive attempt | Excluded from active scheduled work; existing customer-safe retry remains available |
| Unsent historical hosted record | Retain unresolved state with `initiation_not_recorded`; no Session creation |

Missing-reference classification schedules another local check after five minutes. It never releases inventory or declares the Order paid/failed. The original Mobile Money failure code is retained when classification adds an unresolved reason, allowing configuration/network history to remain visible. Admin now shows the local Attempt ID and clear explanations that scheduled checks will not send/replay a request. No stored payload or idempotency key is exposed. Customer pages continue to show neutral checking/uncertainty messages.

## Explicit recovery

Added the separately executed command:

```text
php artisan payments:recover-snippe-initiation <payment-id> --actor=<operator-user-id> --reason="Investigated the original attempt" --execute
```

This command **can send a Mobile Money prompt** and is not a read-only check. It is absent from the scheduler and was exercised only with fakes. It requires trusted operator shell access, explicit execution, an existing operator identity, a reason, and the existing `orders.view` plus `orders.payment_status.manage` permissions. No permission or role grants were added.

`MobileMoneyPayment::recoverInitiation()` repeats eligibility under canonical Order/payment locks: active unpaid Order, supported recoverable state, no bound provider reference, exact existing active reservations, available lease and permitted retry timing. It retains sticky-review and 23-hour protections. It records `commerce.payment.initiation_recovery_requested` with operator, reason and safe existing-attempt context before HTTP.

Recovery reuses the original encrypted request snapshot and idempotency key. It never calls `prepare()` or allocates a new attempt. Repeated uncertain responses retain that identity; a bound reference prevents a later recovery POST. Provider calls remain outside database transactions. The existing low-level initiation path remains available to intentional initial checkout and definite-rejection retry; it is not used by scheduled reconciliation.

No new hosted recovery command was added: legacy read/discovery compatibility remains, and historical explicit initiation is separate from scheduler reads.

## Verification

| Check | Result |
| --- | --- |
| Payment suite including all original 47 tests plus 19 new cases | 66 passed, 2,279 assertions |
| Payments + Checkout + Inventory, including reservation/cancellation and production Order Admin | **107 passed, 3,496 assertions**, zero failures/errors |
| Scoped Larastan across eight changed application PHP files | Passed, zero errors |
| Pint on all changed PHP/test files | Passed |
| `artisan schedule:list` | Five-minute reconciliation registered; recovery absent |
| Recovery command registration/help | Passed; no recovery operation executed |

New cases cover:

- Scheduler GET-only pending/completed/expired/failed/voided provider outcomes and canonical reservation/ledger results.
- Missing references after unsent/local-failure/ambiguous/stale/sticky/leased/rate-limited/rejected states: zero HTTP requests, no new attempt, protected reservations.
- Legacy unsent Session: zero requests; legacy lost-reference discovery: GET only.
- Explicit recovery permission and execution requirements, durable operator audit, identical snapshot/key over repeated uncertain responses, preserved lease/delay/review/window guards, successful binding and no repeated POST afterward.
- A due ambiguous attempt cannot be replayed by the customer retry endpoint.
- Existing initial checkout POST, definite rejection retry, encrypted snapshots, finality/signature/idempotency, canonical Orders and inventory regressions remain green.

The no-reference tests return a fake HTTP response if called and then assert no request was recorded. This ensures a command catching an HTTP exception cannot hide a forbidden request from the invariant assertion.

Evidence: `storage/logs/snippe-2b-payments.xml`, `snippe-2b-regression.xml`, `snippe-2b-regression.log`, `snippe-2b-types.log`, `snippe-2b-summary.json`. Focused tests render the changed Admin diagnostics. No build, full Laravel suite, full Larastan, dependency audit or global browser/fidelity run was performed for this backend change.

## Live-acceptance preparation

The user selected a **deployed site**. Its actual HTTPS address has not yet been supplied; no domain or temporary tunnel was guessed or committed. Local inspection still resolves:

- `APP_URL`: `http://william.taylor`
- Callback: `http://william.taylor/webhooks/snippe`

Local `.env` was unchanged in this task. Deployment must set its actual HTTPS origin before preparing any live attempt. Existing stored callbacks remain immutable; changing APP_URL does not rewrite an old attempt's request.

`collection:read` and credential acceptance were established in preflight 2A. **`collection:create` has not been manually confirmed.** The operator was asked to check Snippe Dashboard > Settings > API Keys > the configured William Taylor key. Required selections are `collection:read` and `collection:create`; disbursement permissions are unnecessary. No credential was printed, changed, regenerated or probed by creating a payment.

The [setup guide](SNIPPE_CHECKOUT_SETUP.md) now documents both deployed HTTPS and optional future tunnel preparation, an external unsigned empty POST that must reach Laravel and return signature rejection, PHP/web/CLI trust checks, current synchronous payment handling, and the verified-path cron template. The existing raw-body HMAC, constant-time comparison, replay timestamp window and durable receipts remain unchanged.

The application schedule is configured. The deployed host's scheduler trigger, PHP/web trust configuration and actual runtime/queue settings remain **unverified** because deployment access/details were not provided. Local PHP CA success from 2A does not establish deployed CA trust. No new queue worker is required by these payment paths.

## Remaining blockers

1. Supply and configure the deployed public HTTPS origin, then verify the callback externally: valid TLS, POST reaches Laravel, invalid signatures safely rejected, no login/CSRF/firewall interception.
2. Manually confirm `collection:create` on the configured key, together with the required read scope and least-privilege scope selection.
3. Verify deployed PHP/web/scheduler certificate trust and the actual one-minute scheduler trigger with execution evidence. The recovery command must remain unscheduled.

The scheduler safety blocker recorded by preflight 2A is resolved by this code and its fake-provider invariant tests. The deployment/scope blockers are not resolved. No readiness token or production payment readiness is claimed. A controlled real payment and signed webhook cycle remain a separately authorized acceptance step.

## Files changed

- `app/Domain/Payments/MobileMoneyPayment.php`
- `app/Domain/Payments/Snippe/StartSnippePayment.php`
- `app/Domain/Payments/Snippe/ProcessSnippeWebhook.php`
- `app/Console/Commands/ReconcileSnippePayments.php`
- `app/Console/Commands/RecoverSnippeInitiation.php`
- `app/Http/Controllers/SnippePaymentController.php`
- `app/Http/Controllers/Admin/CommerceOrderController.php`
- `app/Domain/Checkout/Admin/OrderStatusPresenter.php`
- `resources/views/admin/commerce/orders/show.blade.php`
- `tests/Feature/Payments/MobileMoneyPaymentTest.php`
- `tests/Feature/Payments/SnippePaymentTest.php`
- `docs/ecommerce/SNIPPE_CHECKOUT_SETUP.md`
- This report and local focused evidence.
