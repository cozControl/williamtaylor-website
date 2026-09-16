# PAYMENTS-SNIPPE-2A: Credential and HTTPS preflight

Date: 2026-09-16

**Decision: preflight is not fully passed. Do not start controlled live payment acceptance yet.**

Snippe accepted a real read-only API request. Public callback HTTPS and remaining deployment/scope checks are unresolved. No payment was created, no USSD prompt was requested, and no Order, inventory or payment state was changed.

## Results

| Check | Result |
| --- | --- |
| Snippe enabled | Yes |
| API base equals `https://api.snippe.sh` | Yes |
| API key present | Yes |
| Webhook secret present | Yes |
| Secret shape accepted by existing verifier | Yes: a non-empty string; matching the provider's actual signing secret remains unverified |
| `APP_URL` | `http://william.taylor` |
| Generated webhook URL | `http://william.taylor/webhooks/snippe` |
| Public HTTPS callback ready | **No** |
| API connectivity | Yes |
| API authentication accepted | **Yes**, HTTP 200 on the existing client's read-only request |
| `collection:read` capability | **Yes** |
| `collection:create` capability | **Unverified**; not established by a read request |
| Normal PHP CA verification before remediation | Failed: cURL 60, certificate verification result 20, unable to get local issuer certificate |
| Supplied CA bundle needed | **Yes**, tested only after the normal verification failure |
| TLS after CA configuration | Passed; certificate verification remained enabled |
| Reconciliation command and application schedule | Present; every five minutes, limit 10, without overlap |
| Production host scheduler actually running | Unverified from this local workspace |
| Reconciliation strictly read-only | **No**; see the distinction below |

Laravel configuration was inspected through a bootstrapped Console Kernel. It is not cached, and the effective enabled flag, base URL and presence booleans were correct. No cache clear/rebuild was necessary during this preflight.

## Live read and TLS evidence

The first sandboxed connection failed with cURL 7. The authorized check outside the network sandbox exposed the actual PHP trust-store failure: cURL 60, OpenSSL verification result 20, `unable to get local issuer certificate`. Both `curl.cainfo` and `openssl.cafile` were initially unset in the active PHP configuration.

One credential-free GET to the API root using the supplied bundle then completed TLS successfully and returned HTTP 404. That root response proved transport connectivity, not key acceptance.

Configured the supported PHP CA settings in:

`C:/wamp64/bin/php/php8.3.28/php.ini`

Both settings now reference:

`C:/Users/HP/Documents/williamtaylor/storage/ssl/cacert.pem`

Original configuration backup:

`C:/wamp64/bin/php/php8.3.28/php.ini.snippe-preflight-20260916.bak`

The next PHP process used those persisted settings and called **only** `GET /v1/payments?limit=1&offset=0` through the existing `SnippeClient::request()`, with automatic retries disabled. It returned HTTP 200 and a valid data envelope. Account records, balances, credential values and response bodies were not printed or saved. No payment creation, push, cancellation or reconciliation operation was called.

The supplied CA file was not changed. No TLS verification bypass or application payment-logic change was introduced. Existing long-running web/PHP processes may need to reload their applicable PHP configuration; this preflight proves the fresh local CLI/Laravel process, not the deployed web server's PHP trust configuration.

Snippe documents listing payments as a GET and associates payment reads with `collection:read`. Creation requires the separate `collection:create` permission. No documented scope-introspection endpoint was found in the reviewed authentication/payment documentation, so create permission and the absence of unnecessary disbursement permissions were not inferred from the successful read. Confirm those selections in the provider Dashboard without sharing the key. [Snippe payments](https://docs.snippe.sh/docs/2026-01-25/payments), [Snippe authentication and scopes](https://docs.snippe.sh/docs/2026-01-25/authentication).

The PHP CA configuration is compatible with Guzzle's supported certificate verification mechanism. [Guzzle verification options](https://docs.guzzlephp.org/en/stable/request-options.html#verify).

## Webhook boundary

Verified from the resolved route and current source:

- `snippe.webhook` exists and accepts `POST /webhooks/snippe`.
- The resolved browser-forgery middleware excludes `webhooks/snippe`.
- No authentication middleware is attached; an authenticated browser session is not required. Normal web session middleware remains present.
- `SnippePaymentController::webhook()` calls `VerifySnippeWebhook::verify()` before processing the body.
- Verification retains HMAC-SHA256 over the timestamp and raw body, constant-time comparison, the 300-second timestamp window, expected signature/timestamp formats and bounded body parsing.
- Direct events retain durable `snippe_webhook_receipts`, a unique event key, conflicting-body rejection and processed-event idempotency. Provider GET verification still precedes payment finality.

The generated callback uses the actual named route and the same configured origin construction as payment preparation. It is currently HTTP, so it fails the existing HTTPS requirement. No replacement production domain was guessed, and public reachability was not claimed. The provider's actual delivery/signature cycle was not exercised.

## Reconciliation readiness and limitation

`php artisan schedule:list` confirms:

`*/5 * * * * php artisan payments:reconcile-snippe --limit=10`

The schedule is enabled-only and uses a ten-minute overlap lock. The command limits a batch to 10 by default, permits at most 50, and bounds the start of additional work by a 45-second deadline. No scheduler job was executed.

Existing referenced Mobile Money payments are verified with provider GET requests through the canonical lifecycle, allowing recovery after missed webhooks. Leases, next-reconcile times, stored request/idempotency identity, the 23-hour retry boundary and sticky review reasons remain enforced.

**The command is not a harmless status-only probe.** It calls `StartSnippePayment::refresh()`, which delegates Mobile Money records to `MobileMoneyPayment::refresh()`. If an active stored attempt has no provider reference, that method can initiate/replay `POST /v1/payments` with its existing snapshot and key. An unsent attempt can therefore create its provider-side payment and prompt; a previously accepted request relies on provider idempotency. This path does not deliberately allocate a fresh local attempt, but it does not meet a literal requirement that reconciliation can never initiate a provider payment. Historical unsent hosted records also have a create path.

This is an existing lifecycle behavior, not a configuration defect. It was left unchanged under the instruction to preserve PAYMENTS-SNIPPE-1/1A. It must be accounted for before enabling production scheduling or treating the command as read-only. The production host's one-minute Laravel scheduler trigger was not accessible or verified in this workspace.

## Remaining blockers and evidence gaps

1. Configure the intended publicly reachable HTTPS application origin and verify the resulting `/webhooks/snippe` endpoint in that deployment. Current HTTP `APP_URL` blocks live initiation by design.
2. Confirm the configured key has `collection:create` as well as the proven `collection:read`, and has no unnecessary disbursement grants. No permission was added or credential regenerated.
3. Verify the actual deployed PHP trust configuration and running scheduler trigger. Local CLI success does not establish the production environment.
4. Resolve the stated read-only reconciliation expectation against the existing initiation/replay behavior before treating that scheduler as safe to run during a no-payment preflight.

After those items are resolved, a separately authorized controlled payment must verify real prompt delivery, provider completion, webhook signatures/delivery, canonical Order finality and inventory consumption. This report makes no production-readiness claim.

## Changes and validation

- Changed only the active local PHP CA configuration, with the backup above.
- Added `scripts/evidence/snippe-production-preflight.php`: fixed-origin, bounded GET-only checks with allowlisted output and suppressed exception/response details.
- Added this report and `storage/logs/snippe-2a-config.json`, which contains safe configuration booleans, URLs and middleware names only.
- Ran Pint and PHP syntax checking on the preflight script: passed.
- Read the scheduler with `schedule:list`; did not run it.
- PAYMENTS-SNIPPE-1/1A application implementation, `.env`, Orders, inventory, secrets and protected baselines were unchanged during this task.

All actual API operations in this preflight were GET requests. The live read's safe result was: HTTP 200; authentication accepted yes; collection read yes; collection create undetermined; account data reported no.
