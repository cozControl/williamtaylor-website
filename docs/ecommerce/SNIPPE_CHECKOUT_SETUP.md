# Snippe hosted Checkout setup

Implementation targets the published **2026-01-25** contract, inspected on 2026-09-10. **LIVE SNIPPE CREDENTIAL VERIFICATION PENDING.** Automated evidence uses HTTP fakes; no real charge or live Session was created.

## Architecture

William Taylor collects contact/delivery information and commits the production Order, immutable lines and MAIN reservations first. Only after commit does `StartSnippePayment` create a durable Payment attempt and call `SnippeClient`. No provider request holds a database transaction or inventory lock. The returned hosted URL is persisted and used unchanged after HTTPS/host validation.

The guest Cart clears when its Order commits, even if payment initiation later fails. Retry operates on that same Order. It never restores the Cart or duplicates reservations. Closed/expired Orders cannot be paid again; shoppers must place a new stock-validated Order.

The Payment Profile controls branding and permitted methods. No William Taylor card, PIN, provider-selector, QR or mobile-money form is added. Local checkout charges the immutable merchandise total only. Delivery remains pending and no shipping, tax or discount amounts have been invented.

## Provider documentation and contract discrepancies

- [Authentication](https://docs.snippe.sh/docs/2026-01-25/authentication): generic authentication describes Bearer API keys and `collection:read` / `collection:create` scopes.
- [Payment Sessions](https://docs.snippe.sh/docs/2026-01-25/sessions): the live page says merchant Session endpoints use **Bearer JWT**, and currently lists only `mobile_money`. It does not state Session-specific idempotency semantics. These details differ from the phase prompt. The implementation uses the Bearer header, leaves methods to the profile, and never blindly repeats ambiguous Session creation. **Before enabling production, confirm with Snippe that your scoped API key is accepted by Session create/get/list/cancel. Do not paste dashboard JWTs into source or broaden privileges.** If the account requires a different documented mechanism, resolve it with Snippe before activation.
- [Profiles](https://docs.snippe.sh/docs/2026-01-25/sessions/profiles): branding is Dashboard-managed; the application references a profile and does not create/edit it.
- [Links](https://docs.snippe.sh/docs/2026-01-25/sessions/payment-links): use the returned full checkout URL; no manually constructed payment link or URL metadata is needed.
- [Webhooks](https://docs.snippe.sh/docs/2026-01-25/webhooks): versioned event envelope, raw-body HMAC and timestamp validation.
- [Errors](https://docs.snippe.sh/docs/2026-01-25/error-handling): minimum 500 TZS and maximum idempotency-key length 30; account-specific maxima require merchant verification.
- [PHP SDK](https://docs.snippe.sh/docs/2026-01-25/sdks/php) and [official source](https://github.com/Neurotech-HQ/snippe-php-sdk): published API surface concentrates on direct payments, not hosted Sessions. Laravel HTTP is used; no package was installed.

## Environment

Configure secrets through the deployment environment, never ordinary Site Settings, frontend JavaScript or committed `.env` files:

```dotenv
APP_URL=https://your-production-store.example
SNIPPE_ENABLED=false
SNIPPE_BASE_URL=https://api.snippe.sh
SNIPPE_API_KEY=
SNIPPE_PROFILE_ID=
SNIPPE_WEBHOOK_SECRET=
SNIPPE_SESSION_EXPIRES_IN=3600
```

Keep disabled until account/HTTPS/webhook acceptance is verified. The expiry must be an integer between 60 and 86400 seconds. `config/snippe.php` allowlists the documented hosted domain `snippe.me`; change that code-owned allowlist only after verifying an official provider domain change. Do not add arbitrary shopper-controlled redirect hosts.

Use separate development/test and production credentials, consistent with the environments actually available in the Snippe account. No sandbox host or test-key format is assumed. Unit/feature tests use `Http::fake` and prevent stray provider requests.

Apply the bounded forward migration `2026_09_10_030000_create_snippe_payments.php`. It adds Payment/event history, Order lifecycle timestamps and nullable system ledger actors. Manual ledger actions still require authenticated authorized staff. Rolling back this migration deliberately does not assign fictional staff actors to existing system issue movements.

## Dashboard setup

1. Select/create the William Taylor Payment Profile in Settings → Payment Profiles.
2. Set merchant name **William Taylor**, an approved public logo, existing oxblood brand colour, and `en` or `sw` locale.
3. Enable only methods actually provisioned on the merchant account; record the profile ID in `SNIPPE_PROFILE_ID`.
4. Create the least-privileged collection credential: `collection:create` and `collection:read`, subject to the Session authentication confirmation above. Do not grant disbursement/payout permissions.
5. Obtain the signing key from Settings → Webhook Secret and set `SNIPPE_WEBHOOK_SECRET` securely.
6. Configure `https://<store-host>/webhooks/snippe` as webhook destination and select the 2026-01-25 event format.
7. Sessions override the redirect with `https://<store-host>/checkout/snippe/return/<random-reference>`. Do not put customer data, raw Order IDs or secrets in that URL. The reference is distinct from the existing guest Order confirmation credential.

`APP_URL` is the trusted origin for both URLs; request Host headers do not choose the provider callback destination. Verify the final resolved URLs at deployment. HTTPS is enforced for production initiation.

## Exact Money boundary

`ProductPrice::factor()` establishes **100 internal minor units per TZS shilling**. `SnippeMoney` uses checked integer input and exact division by 100. It rejects non-TZS, zero/negative values, unsupported fractions and session amounts below 500 TZS; no rounding or floating-point conversion occurs.

| Internal Order amount | Snippe amount |
| --- | --- |
| 50,000 | 500 TZS |
| 32,000,000 | 320,000 TZS |
| 12,500,000 × 2 | 250,000 TZS |

The immutable Order total controls the payable amount. Immutable line prices use the same exact conversion; the minimum applies to the Session total, not each line. All lines are display-only at Snippe. The local preflight rejects bags over the provider's 50-line display limit or amounts that cannot be represented, before an Order is created.

## Lifecycle and failure recovery

Payments store provider, Order relationship, expected internal and provider amounts, durable attempt key, separate return reference, Session/payment references, validated checkout URL, typed status, provider status, bounded local error/attention codes and timestamps. No full provider payload or secret is stored as authority.

| Evidence | Local outcome |
| --- | --- |
| Session pending / active | Payment pending / processing; Order unpaid, reservations active |
| Verified payment.failed | Record failed attempt/reference; keep payable Session and reservation |
| Verified completed payment or authenticated completed Session | Payment completed; Order confirmed/paid; reservations consumed; one negative ORDER_ISSUE per line; fulfillment remains unfulfilled |
| Authenticated Session expired / cancelled | Payment and Order final unpaid state; reservations released; no movement |
| Network/ambiguous response | Needs attention; keep same attempt/reservation; reconcile, never blindly repost |
| Definite rejected initialization | Saved Order remains retryable; a deliberate new attempt uses a new provider key |
| Wrong amount/currency/reference/metadata | No paid/stock transition; bounded reconciliation issue recorded |

`wt-` plus 24 random hex characters gives a 27-character attempt key. POST sends it as `Idempotency-Key`, but safety does not assume undocumented Session deduplication. Creation is attempted once per key. Ambiguous outcomes use authenticated list discovery, matching the locally persisted `payment_attempt` metadata, then bind only a matching amount/currency/order. Discovery is bounded to 500 Sessions; absence is not proof of failure. If unresolved, retain commitments and investigate in the Dashboard rather than creating another payable Session or releasing potentially payable stock.

Known rejected initialization has no payable Session, but the pending Order/reservation is deliberately retained for retry. Such abandoned Orders, and ambiguous attempts outside bounded discovery, require operational follow-up; no unverified automatic stock release is performed. Remote final-state reconciliation handles normal Session expiration and cancellation. There is no automatic expiry of Cart contents.

Provider GETs retry at most twice after transient connection/5xx failures with bounded backoff. POSTs never automatically retry. HTTP 429 defers reconciliation for the bounded Retry-After/reset interval; no immediate rate-limit loop. Connect/request limits are 5/15 seconds. Each Payment has a database-backed five-minute I/O lease, separate from inventory locks; the scheduler avoids overlapping batches.

## Webhook and return security

Only `/webhooks/snippe` is exempt from browser CSRF. Every webhook still requires a configured signature secret, a decimal Unix timestamp within **±300 seconds**, and a 64-character hexadecimal HMAC. The verifier signs the exact `timestamp.raw_body` bytes with SHA-256 and compares using `hash_equals` before decoding JSON. It bounds request size and accepts only the current envelope (legacy flat payloads are not supported).

Provider event ID is hashed for deduplication; if absent, a raw-body hash is the fallback. Receipts store hashes, event type, Session reference and processing outcome—not customer payloads. Reusing one event ID with different bytes is rejected. Unknown Sessions remain retryable receipts, allowing delivery-before-binding to recover. Database failures roll back the receipt and all financial/stock changes so provider retry is safe.

Completion reconciles Session, payment reference, expected Order amount/currency, supplied Order/attempt metadata, lifecycle and every exact active reservation under locks. Duplicate completion cannot post another issue. Late failed/unpaid evidence cannot reverse completed payment. Late success after released/closed stock records Needs Attention and does not resurrect the Order.

Return and confirmation pages use only persisted local state; query parameters such as `?status=success` cannot mutate payment. They expose no address/email/phone, use high-entropy references, private/no-store, no-referrer and noindex headers. Refresh the page to see webhook/reconciliation progress. Retry is a CSRF-protected, throttled POST. No merchant API credential reaches browser code.

## Missed-webhook reconciliation and cron

```shell
php artisan payments:reconcile-snippe --limit=10
```

The existing Laravel scheduler runs this every five minutes when enabled, without overlap. The command accepts 1–50 records and stops starting records after a 45-second work budget; an in-flight bounded provider call may finish after that budget. Follow the existing hosting convention: cron must invoke `php artisan schedule:run` every minute from the application directory. No Supervisor, Horizon, Redis, queue worker or daemon is introduced.

GET `/api/v1/sessions/{reference}` is authenticated server truth for missed-webhook recovery. Current Session amount/currency/reference and supplied metadata must agree before transition. Normal public page rendering makes no external API call. The client also exposes cancel, but no shopper/Admin cancellation UI was added; a cancel response alone is not treated as payment finality—reconcile the Session state.

## Troubleshooting and rotation

Use Payment ID, Order number, Session/payment reference, local failure code and event-receipt outcome. Do not copy API keys, signing secrets, authorization headers, checkout/return bearer URLs or full customer payloads into logs/tickets. The application records safe local error identifiers instead of raw provider messages. Restrict operational SQL access to Payment records because checkout/return references grant bearer access.

- `http_401` / `http_403`: resolve the Session credential/scopes discrepancy with Snippe; never add payout privileges as a workaround.
- `rate_limited`: allow the deferred retry; lower batch size if needed.
- `session_outcome_unknown`: investigate the same attempt in Dashboard; do not manually repost or release stock based on a timeout.
- `session_mismatch` / `evidence_mismatch`: compare authoritative Order and provider records; do not edit immutable totals to make them match.
- `reservation_mismatch`: inspect canonical stock/reservation history; do not manually issue or mutate projections.
- `unknown_session`: creation may not yet be bound locally; provider retry or authenticated Session discovery recovers it.
- Signature rejection: check server clock and exact configured signing secret; do not disable HMAC or freshness checks.

Rotate API keys by securely deploying the replacement, refreshing configuration, verifying collection access, then revoking the old key. Signing-key regeneration invalidates the previous key immediately: coordinate Dashboard regeneration and deployment, then reconcile missed events. This phase does not auto-regenerate secrets or log them.

## Go-live acceptance

- Confirm account-specific Session authentication, scopes, permitted methods and amount limits with Snippe.
- Configure the branded profile, secrets, HTTPS origin and current webhook format.
- Apply forward migration, refresh Laravel configuration and confirm scheduler cron.
- Run the focused tests with fakes; keep real calls out of PHPUnit.
- In a separately authorized merchant test, verify Session creation, hosted redirect, signed completion, local exact amount/stock transition and final unpaid expiry.
- Verify actual hosted branding and mobile/desktop return UX. Physical/browser acceptance remains pending.
- Enable only after these external acceptance checks. No refunds, disbursements, Pre-Order deposits, customer accounts or Admin Orders workspace are included.
