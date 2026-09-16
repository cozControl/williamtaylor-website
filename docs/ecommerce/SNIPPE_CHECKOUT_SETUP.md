# Snippe Mobile Money setup

Current integration: PAYMENTS-SNIPPE-1, 2026-09-15. New payments use the direct Mobile Money API and William Taylor checkout. The older hosted completion report is historical.

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

1. Create a collection API key with only `collection:read` and `collection:create`. Do not grant payout/disbursement scopes.
2. Confirm Mobile Money collections are available for the merchant account: M-Pesa, Airtel Money, Mixx by Yas, and Halotel.
3. Copy the signing secret from Settings > Webhook Secret into the server environment.
4. Verify that the canonical HTTPS `POST /webhooks/snippe` URL is publicly reachable. Every payment creation request sends this URL; if configuring account webhooks separately, use the same endpoint and enable completed, failed, expired, and voided payment events.
5. Preserve signing verification and replay protection in production; proxy/firewall configuration must retain the signature/timestamp headers and exact raw body.

Authoritative specifications: [Mobile Money](https://docs.snippe.sh/docs/2026-01-25/payments/mobile-money), [Payments/status/idempotency](https://docs.snippe.sh/docs/2026-01-25/payments), [Authentication](https://docs.snippe.sh/docs/2026-01-25/authentication), [Webhooks](https://docs.snippe.sh/docs/2026-01-25/webhooks), and [Errors](https://docs.snippe.sh/docs/2026-01-25/error-handling).

## Scheduler and recovery

Keep the existing one-minute Laravel scheduler active. It runs `payments:reconcile-snippe --limit=10` every five minutes without overlap. The command performs bounded provider work, recovers missed webhooks, and uses the canonical lifecycle. It never takes an amount or paid status from the browser.

Within the 24-hour provider idempotency window, unresolved direct initiation uses the same stored body/key. The application stops replay at 23 hours for a safety margin. Once a reference is bound it uses status GET, not another payment POST. Rate limits defer retries. New attempts require a definite original rejection with no unresolved reference. Final unpaid Orders have released stock and require fresh checkout for another purchase.

Known amount/currency/reference/Order mismatches remain under review with stock protected. Inspect the real Admin Orders workspace and merchant Dashboard; do not delete the attempt, clear its reference, issue stock manually, or use demo Orders. No automated override is provided. Unknown outcomes beyond the replay window require investigation before any replacement charge.

Published expiry descriptions differ (payment pages say four hours; webhook summary says one hour). The integration does not use either local duration to release reservations. Authenticated provider final state is required.

## Acceptance still required

- Use the gated deployment/build procedure for frontend and full-audit checks.
- Confirm the checkout, phone input, waiting page, errors, and Admin payment details on desktop and mobile.
- With explicit transaction authorization, perform a merchant-approved test/production payment and inspect the serialized create request, authoritative GET, signed webhook, paid Order, consumed reservation, and one ledger issue.
- Verify duplicate delivery is harmless, final unpaid releases stock, and missed-webhook reconciliation works with the deployed scheduler.
- Confirm HTTPS, secrets, collection scopes, and inbound callbacks in the deployed environment.

Fake-provider tests do not establish live credential validity, network delivery, actual collection, or physical visual acceptance. See [the implementation report](PAYMENTS_SNIPPE_1_MOBILE_MONEY_REPORT.md) for current verification and remaining limits.
