# COMMERCE-PAYMENT-SNIPPE-1 completion report

Date: 2026-09-10

**COMMERCE-PAYMENT-SNIPPE-1 IMPLEMENTATION READY FOR GENERAL INSPECTION**

**LIVE SNIPPE CREDENTIAL VERIFICATION PENDING.** No live Session, real payment attempt or provider charge was created. Physical visual acceptance remains paused. Production activation additionally requires resolving the published Session/API-key authentication discrepancy with Snippe.

## Required implementation record

1. **Status:** Code and focused fake-provider verification complete; external credential/hosted acceptance is separate.
2. **API contract:** Current published [2026-01-25 documentation](https://docs.snippe.sh/docs/2026-01-25) inspected live, including a direct public-page fetch to verify discrepant details. Authentication, Sessions, Profiles, Links, Webhooks, Errors and PHP SDK inspected.
3. **Hosted architecture:** `StartSnippePayment` creates/reuses a durable Payment after Order commit, invokes `SnippeClient`, persists Session evidence, then redirects to validated returned checkout URL.
4. **Sessions:** Uses `/api/v1/sessions`, never direct payment intents or local card/mobile-money entry. Snippe owns payment authorization UI.
5. **SDK:** Laravel HTTP chosen because published official PHP SDK surface focuses on direct payment builders and does not expose documented hosted Session operations. No dependency installed.
6. **Authentication:** Server-only Bearer header. Generic auth docs say API keys; live Session page specifically says JWT. The account must verify acceptance of a collection-scoped API key before production activation. No unverified X-API-Key mode or dashboard-login automation was invented.
7. **Scopes:** Request only collection read/create capabilities, subject to Session-account confirmation; no disbursement/payout privileges.
8. **Configuration:** `config/snippe.php`, `.env.example`: enabled flag (false by default), base URL, API key, profile ID, webhook secret, Session expiry. Trusted APP_URL supplies callbacks; secrets not read into frontend/Site Settings.
9. **Profile:** `profile_id` sent; merchant name/logo/colour/locale configured externally in Dashboard. No Profile mutation API.
10. **Methods:** Omitted from request so Profile controls enabled methods. Live Session docs currently show mobile_money only, differing from prompt examples; no speculative card/QR override or payment-method logic added.
11. **Payment aggregate:** `commerce_payments`, ULID, Order/provider, independent lifecycle, exact expected amounts, attempt key, Session/payment references, private returned URL/reference, error/attention codes and timestamps. Unique active_order_id limits active local attempts; unique provider identities prevent cross-Order reuse.
12. **Payment states:** Typed pending, processing, completed, failed, expired, cancelled. Reconciliation issue is an independent bounded support field, not a guessed paid state.
13. **Separation:** Success means Order confirmed + payment paid + fulfillment unfulfilled. Snapshots remain immutable; lifecycle changes require the scoped mutation boundary.
14. **Money:** ProductPrice establishes 100 internal minor units/TZS. SnippeMoney performs exact integer division, rejecting unsupported fractions/currency, zero/negative/overflow inputs; never rounds.
15. **Minimum:** Session minimum 500 TZS; 50,000 internal → 500 provider; 32,000,000 internal → 320,000 provider. Individual display-line prices need exact conversion but do not independently require 500 TZS.
16. **Amount authority:** Immutable Order.total_minor only. Online-payment representability is checked before Order creation; no browser/live-price authority after placement.
17. **Lines:** Immutable line ID/title/options description/quantity/unit price/SKU; display-only at Snippe. Maximum 50 lines enforced before placement. No unsafe image reference or speculative category added.
18. **Customer:** Order name/email/normalized phone prefill; provider changes never overwrite delivery/contact snapshots.
19. **Metadata:** Order ID/number, source william_taylor_web, short payment attempt identity. No address, confirmation bearer reference or credentials.
20. **Description:** William Taylor Order plus business number.
21. **Expiry:** Configurable 3600-second default, validated 60–86400. Persisted provider expiry prevents redirect to a locally expired URL; actual release requires verified remote finality.
22. **Idempotency:** Stable 27-character `wt-` attempt key sent as Idempotency-Key. Since Session-specific deduplication is not documented, POST is never automatically repeated after ambiguous outcome. Authenticated bounded Session-list discovery matches attempt metadata; unresolved outcomes retain the same attempt.
23. **Transaction separation:** Runtime guard rejects any provider call inside a DB transaction. Order commits/Cart clears before Payment HTTP. Short database-backed I/O lease coordinates concurrent provider operations without inventory locks over network waits.
24. **Initiation success:** Payment Session response binds exact amount/currency/metadata/reference, stores provider URL and redirects using the returned HTTPS allowlisted URL.
25. **Initiation failure:** Saved Order and reservation remain. Known rejected creation permits deliberate new Payment attempt for same Order. Network/5xx/malformed/409/422/429 ambiguity retains active attempt and reconciles; even a later discovery 401 cannot permit duplicate creation.
26. **Persistence:** Session reference and URL are validated; references are unique and immutable after binding; full arbitrary response bodies are not stored.
27. **Webhook:** POST `/webhooks/snippe`; only this path exempted from CSRF. Retry POST remains CSRF-protected, session-blocked and throttled.
28. **Signature:** HMAC-SHA256 over exact timestamp + dot + raw body; constant-time hash_equals; JSON decoded only afterward. Missing/invalid signature is rejected even in testing-style local requests.
29. **Replay:** Decimal Unix timestamp required within ±300 seconds; stale/future/malformed timestamps rejected. Body size bounded to 128 KiB.
30. **Deduplication:** Receipt key hashes provider event ID, or body hash when no ID supplied. Changed bytes under same event ID conflict; successful receipt replay is inert. Receipt and lifecycle share transaction.
31. **Completed event:** Requires current envelope, known Session, completed state, integer amount/currency, payment reference and compatible local state; no phone-based matching.
32. **Reconciliation:** Exact Order/internal/provider amount, TZS, Session/payment relation and supplied Order/attempt metadata must agree. Mismatches persist Needs Attention and do not issue/confirm.
33. **Completion service:** `SnippePaymentLifecycle` locks Order/Payment, sorted Product/Variant/location/balance/reservation identities and verifies every line before applying state/audit.
34. **Issue:** `InventoryLedgerService::issueReserved`; typed order_issue, negative exact quantity, unique Order-line issue identity, null system actor, canonical projection update. Manual posting cannot invoke this movement type.
35. **Consumption:** Active exact MAIN reservations become consumed in the same transaction as issue; no separate stock deduction in controller/provider code.
36. **Stock evidence:** Before: 5 on hand, 2 reserved, 3 available. After valid payment: 3 on hand, 0 active reserved, 3 available; issue -2.
37. **Duplicate success:** Same event and separate repeated completion delivery do not produce another issue. Final paid state cannot be reversed by late failure/pending evidence.
38. **Failed attempt:** Records failed state, bounded local reason and failure payment reference; keeps Session/Order reservation active because hosted payment may be retried.
39. **Final Session:** Authenticated GET completed/expired/cancelled drives final state. Cancel client exists; no public cancel UI or unverified expiry-time release.
40. **Release:** Expired/cancelled unpaid Session releases exact active reservations with no movement; on hand 5 stays 5, available restores from 3 to 5. Late paid evidence after release records attention and cannot resurrect stock. Closed Order retry is rejected; fresh order must revalidate stock.
41. **Return security:** Separate random 256-bit return reference; no raw ID/PII in URL; no browser query parameter is financial evidence.
42. **Return rendering:** Read-only local truth, pending/payment received/final unpaid messages. No merchant API calls during public render.
43. **Confirmation:** Existing high-entropy Order confirmation preserved; status-aware acknowledgement and safe payment retry form; no delivery/email/phone displayed; no-store/no-referrer/noindex retained.
44. **Missed events:** Bounded `payments:reconcile-snippe` command uses authenticated Session reads and same transition service. Unknown early webhook receipt can be retried after binding; reconciliation also recovers completed Sessions without the webhook.
45. **Scheduler:** Existing scheduler extended every five minutes, enabled-only, without overlap. Default 10 records; max 50; deadline limits starting more work. Existing one-minute cron required; no new daemon/queue/Redis dependency.
46. **Logging:** Only safe IDs/local error codes, references and amounts in support/audit. No API exception payloads, secrets, auth headers or full customer provider bodies logged by the integration. Private bearer URLs remain restricted Payment fields.
47. **Network:** 5-second connect / 15-second request limits; GET-only bounded transient retry/backoff. 429 defers work using bounded retry/reset headers. POSTs do not auto-retry; discovery is at most 500 remote Sessions, with absence treated as unknown rather than permission to repost.
48. **Pre-Order:** No Campaign deposit/reservation button connected to Snippe.
49. **Limited Edition:** Only existing ordinary stocked Cart/Order path; edition quantities remain separate.
50. **Refund boundary:** No refunds, returns, disbursement or payout code/privileges.
51. **Focused test counts:** Final totals recorded below.
52. **HMAC tests:** Valid raw-body signature passes; missing/invalid signature, altered bytes, old/future/malformed timestamps and correctly signed malformed JSON reject.
53. **Money tests:** Representative 500/320,000 TZS, exact lines and boundary rejection pass; fake Session request proves 25,000,000 internal is 250,000 TZS, not 25,000,000 TZS.
54. **HTTP/provider-fake evidence:** Feature tests invoke actual Laravel Cart/Checkout/webhook/return/retry routes with sessions, database commits and HTTP fakes. Assertions inspect generated provider payload/auth, redirect, persisted Payment/Order/reservation/ledger and replay/expiry. External call assertion proves transaction level zero. Disposable SQLite memory database is rebuilt; no persistent catalogue stock or live provider calls used.
55. **Scoped checks:** Final analysis/format/syntax results below. No full test suite, full Larastan, build, dependencies, Homepage/fidelity matrix or BE-6A audit run.
56. **Browser:** One connection attempt returned `Browser is not available: iab`. No retry or alternate control. No physical hosted/visual acceptance claimed.
57. **Credentials:** LIVE SNIPPE CREDENTIAL VERIFICATION PENDING; no secrets printed or live connectivity/charge calls made.
58. **Setup guide:** `SNIPPE_CHECKOUT_SETUP.md` created with Dashboard configuration, source discrepancies, amounts, secrets/rotation, cron, errors and go-live acceptance.
59. **Architecture:** Canonical inventory document updated with payment → confirmation → consume → issue, and final unpaid release boundaries.
60. **Admin:** Production Admin Orders workspace NOT implemented. Only existing inventory history made null-safe for system issue actors.
61. **Refund/disbursement:** NOT implemented. No COMMERCE-ORDER-ADMIN-1 work started.

## Verification and practical limits

- Final Snippe contract/payment run: **13 tests, 355 assertions passed**.
- Order/Inventory regression coverage: **16 tests, 230 assertions passed**, included in the earlier combined green run of 28 tests / 580 assertions. Final covered set across the scoped runs: **29 tests, 585 assertions**.
- Scoped Larastan passed for Payment domain, payment/Checkout controllers, changed Order placement/model, reservation model, ledger and reconciliation command. Final Payment/controller rerun passed after the last changes.
- Changed-file Pint passed. PHP syntax: **30 files, zero failures**, plus final client/controller rechecks. Checkout JavaScript passed `node --check` via extracted script input. Blade compilation passed. `git diff --check` passed.
- Protected compiled storefront JS/CSS have no diff. No live provider calls or persistent catalogue stock changes were made for evidence.
- Security evidence includes real web middleware invocation with testing CSRF bypass disabled for the signed webhook flow: unsigned POST returns 401, valid signed POST succeeds, browser return remains read-only. Actual route/session/database tests verify committed Cart clearing and exact provider amount generation outside transaction level zero.
- HTTP client tests cover 400/401/403/409/422/429/5xx, connection failure, malformed/unsafe response, safe GET retries and documented cancel endpoint. Lifecycle tests cover duplicate completion, mismatches, late failure, expiry/cancel release, missed-event reconciliation, post-issue audit rollback, definite initialization retry and ambiguous discovery/authentication safeguards.

During verification, HTTP fake registration initially retained an earlier wildcard response within two multi-scenario tests; the tests now replace the fake factory before changing scenarios. Static analysis required explicit model cast PHPDoc for typed enum/date/JSON properties. These corrections passed the final runs; no suppressed analysis findings or baseline additions were used.

The bounded forward MySQL migration was applied successfully. No MySQL fresh/rollback/re-migration was performed. Automated payment tests run against explicitly asserted SQLite `:memory:` and do not claim MySQL concurrency acceptance. Prior foundation parent-lock conventions are preserved; paid transition rollback is explicitly tested after issue work begins.

Operational limitations are intentional and documented: ambiguous remote creation must never trigger blind release/recreation; unresolved attempts beyond bounded discovery need Dashboard investigation. Definitely rejected initialization keeps the saved Order/reservation for customer retry and requires follow-up if abandoned. Normal remote final Session expiry/cancellation releases automatically through reconciliation. No live acceptance is implied by HTTP fakes.

AGENTS.md requires separate full-audit authorization. Physical acceptance and live Snippe credential verification remain pending independently of implementation readiness.
