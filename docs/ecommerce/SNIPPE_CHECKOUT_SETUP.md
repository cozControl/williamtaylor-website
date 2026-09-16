# Snippe Mobile Money setup

Current integration: PAYMENTS-SNIPPE-2B, 2026-09-16. New payments use the direct Mobile Money API and William Taylor checkout. Scheduled reconciliation uses provider reads only. The older hosted completion report is historical.

## Configure the application

Apply the forward migration `2026_09_15_010000_extend_commerce_payments_for_mobile_money.php` before enabling direct payments. Preserve the existing application encryption key; payment phone/request snapshots use Laravel encrypted casts.

```dotenv
APP_URL=https://your-canonical-store-domain
SNIPPE_ENABLED=true
SNIPPE_BASE_URL=https://api.snippe.sh
SNIPPE_API_KEY=
SNIPPE_WEBHOOK_SECRET=
```

Supply secrets securely in the deployment environment. Never place them in frontend configuration, screenshots, source control, Site Settings, logs, or customer error messages. Refresh the deployment configuration cache after configuration changes using the project's deployment procedure.

`SNIPPE_PROFILE_ID` and `SNIPPE_SESSION_EXPIRES_IN` are retained only for historical hosted records. New direct payments do not send Profile, Session, line-item, redirect, or checkout-URL fields.

## Snippe Dashboard

1. Inspect the configured William Taylor key in Settings > API Keys. Confirm `collection:read` and `collection:create` are selected, without copying or exposing the key. Do not regenerate it merely to inspect scopes. Do not grant payout/disbursement scopes.
2. Confirm Mobile Money collections are available for the merchant account: M-Pesa, Airtel Money, Mixx by Yas, and Halotel.
3. Copy the signing secret from Settings > Webhook Secret into the server environment.
4. Verify that the canonical HTTPS `POST /webhooks/snippe` URL is publicly reachable. Every payment creation request sends this URL; if configuring account webhooks separately, use the same endpoint and enable completed, failed, expired, and voided payment events.
5. Preserve signing verification and replay protection in production; proxy/firewall configuration must retain the signature/timestamp headers and exact raw body.

Authoritative specifications: [Mobile Money](https://docs.snippe.sh/docs/2026-01-25/payments/mobile-money), [Payments/status/idempotency](https://docs.snippe.sh/docs/2026-01-25/payments), [Authentication](https://docs.snippe.sh/docs/2026-01-25/authentication), [Webhooks](https://docs.snippe.sh/docs/2026-01-25/webhooks), and [Errors](https://docs.snippe.sh/docs/2026-01-25/error-handling).

## Scheduler and recovery

Keep the existing one-minute Laravel scheduler active after checking the deployed configuration. It runs `payments:reconcile-snippe --limit=10` every five minutes without overlap. Provider calls in this command are GET-only. It may update local payment/Order/inventory state through verified canonical finality; provider-read-only does not mean database-read-only. It does not initiate payment, replay POST, send a push or allocate another attempt.

Missing Mobile Money references remain protected: `initiation_not_recorded` means no send timestamp is recorded, and `initiation_outcome_unknown` means a timestamp exists but the provider result is unresolved. Neither proves non-payment. The scheduler retains reservations and defers another check for five minutes. Existing failure context remains visible in Admin. A 23-hour-old initiation becomes sticky `idempotency_window_elapsed`. Legacy hosted attempts can use bounded GET discovery for an existing request; an unsent hosted record is retained without Session creation.

Initial checkout and a customer retry after a definite rejection keep their existing initiation workflow. A customer retry does not replay an ambiguous active attempt. Explicit operator recovery is separate:

```text
php artisan payments:recover-snippe-initiation <existing-payment-id> --actor=<operator-user-id> --reason="Investigated the original attempt" --execute
```

This command **may send a Mobile Money prompt**. It is not a preflight check and is never scheduled. Run it only from a trusted operator shell after authorization to recover that specific payment. Without `--execute`, actor and reason, it refuses to run. The actor must have existing `orders.view` and `orders.payment_status.manage` permissions. Admin shows the local Attempt ID, not the secret idempotency key.

Recovery requires an active unpaid Order, no bound provider reference, matching active reservations and a recoverable attempt state. It respects in-flight leases, retry delays, rate limits and sticky review reasons. It records `commerce.payment.initiation_recovery_requested` with the operator and reason before provider I/O. It reuses the exact stored encrypted request snapshot and original key, never a new attempt. The application stops replay at 23 hours. Repeated execution cannot send again once the provider reference is bound. Never put credentials or customer payloads in the reason.

Existing stored callbacks are immutable: changing APP_URL does not repair a previously stored HTTP callback, and recovery never rewrites it. Resolve such attempts through the existing review policy. New attempts require a definite original rejection with no unresolved reference. Final unpaid Orders have released stock and require fresh checkout for another purchase.

Known amount/currency/reference/Order mismatches remain under review with stock protected. Inspect the real Admin Orders workspace and merchant Dashboard; do not delete the attempt, clear its reference, issue stock manually, or use demo Orders. No automated override is provided. Unknown outcomes beyond the replay window require investigation before any replacement charge.

Published expiry descriptions differ (payment pages say four hours; webhook summary says one hour). The integration does not use either local duration to release reservations. Authenticated provider final state is required.

## Acceptance still required

### Deployed site preparation

Use the actual operator-supplied HTTPS origin; do not guess it. Set deployment `APP_URL` to that origin and refresh the deployment configuration cache using its existing process. Confirm `snippe.webhook` resolves to that origin plus `/webhooks/snippe`. Local `.env` is not evidence of deployed configuration. The operator chose a deployed site for this acceptance; its address has not yet been provided.

From a machine outside that host/network, send one unsigned empty request to the actual callback:

```sh
curl --max-time 15 --request POST \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Request-ID: snippe-unsigned-preflight' \
  --data '{}' 'https://<actual-public-host>/webhooks/snippe'
```

Use normal certificate verification; never use `-k`. Expect HTTP 401 from Laravel's signature verifier, with no login redirect and no CSRF 419. Correlate the request with deployed routing/access evidence to distinguish Laravel rejection from a firewall/proxy 401. Do not add a fake signature or use a payment creation/push endpoint. This check must succeed before any controlled payment.

If a controlled local tunnel is chosen later, supply its HTTPS origin at runtime consistently before preparing the attempt. Do not hardcode or commit the temporary URL. The same external reachability and signature-rejection check applies.

### Runtime and scheduler

- Verify certificate trust from the actual web PHP runtime and the scheduler's PHP executable. The local Windows CLI CA repair does not establish either deployed runtime. Keep TLS verification enabled. Do not expose `phpinfo()` or configuration/secrets publicly.
- Payment initiation and webhook verification currently execute synchronously; the existing scheduled Artisan command recovers missed provider events. This integration adds no queue worker dependency. Confirm the application's actual queue/runtime settings through the deployment operator rather than guessing them.
- Inspect the host's scheduler trigger. On cron-based hosting, the usual one-minute entry is below; replace both paths with verified host paths. Do not install a duplicate trigger.

```cron
* * * * * cd /actual/application/path && /actual/php/path artisan schedule:run >> /actual/private/log/path/scheduler.log 2>&1
```

- `artisan schedule:list` proves registration, not that cron runs. Check actual execution evidence, permissions, persistent cache/overlap-lock support and the PHP version/trust store used by cron. If the platform manages scheduling differently, record that mechanism.
- Keep `payments:recover-snippe-initiation` out of all unattended schedules. Do not execute either command against real payments merely to test the scheduler.

- Use the gated deployment/build procedure for frontend and full-audit checks.
- Confirm the checkout, phone input, waiting page, errors, and Admin payment details on desktop and mobile.
- With explicit transaction authorization, perform a merchant-approved test/production payment and inspect the serialized create request, authoritative GET, signed webhook, paid Order, consumed reservation, and one ledger issue.
- Verify duplicate delivery is harmless, final unpaid releases stock, and missed-webhook reconciliation works with the deployed scheduler.
- Confirm HTTPS, secrets, collection scopes, and inbound callbacks in the deployed environment.

Fake-provider tests do not establish live credential validity, network delivery, actual collection, or physical visual acceptance. See [the implementation report](PAYMENTS_SNIPPE_1_MOBILE_MONEY_REPORT.md) for current verification and remaining limits.
