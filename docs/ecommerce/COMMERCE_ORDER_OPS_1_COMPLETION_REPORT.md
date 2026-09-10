# COMMERCE-ORDER-OPS-1 completion report

Date: 2026-09-10

**COMMERCE-ORDER-OPS-1 IMPLEMENTATION READY FOR GENERAL INSPECTION**

Controlled unpaid cancellation is available from an eligible production Order's detail page to staff with `orders.view` and `orders.cancel`. Visual acceptance remains pending: the single browser attempt returned `Browser is not available: iab`. No live provider calls were made.

## Required implementation record

1. **Status:** Controlled cancellation service, confirmation page, permanent cancellation record, Admin presentation and honest public terminal state implemented. Final scoped verification results appear below.
2. **Discovery:** Production Order already had typed Cancelled and PaymentExpired states; Snippe already had authenticated GET/cancel operations, a Payment I/O lease and a transactionally controlled final-state service. Active reservations can become released; consumed reservations cannot. Existing demo cancellation belongs to separate demo models/routes and is not reused for production financial transitions.
3. **Exact eligibility matrix:** See the matrix below. PendingConfirmation + unpaid + unfulfilled is necessary, with every immutable line matched to an exact active MAIN reservation and no Order issue. Every historical Payment is inspected. Unknown provider types, inconsistent history, ambiguous initiation and completed Payments fail closed.
4. **Paid prohibition:** Paid/Confirmed or any completed Payment rejects cancellation. Consumed/missing/mismatched commitments and existing issue movements also prohibit it. No paid-state reversal, movement deletion or stock compensation exists.
5. **Service:** `CancelUnpaidOrderService` owns authorization, bounded reason validation, locked eligibility, provider coordination, final local transition, reservation release and audit. Controller validates the confirmation form and delegates; it never assigns business status directly.
6. **Network separation:** Initial claim transaction ends before any provider HTTP. Existing Payment `io_lease_until` coordinates with initiation/reconciliation. HTTP fakes assert transaction level zero. Final release and audit occur in a separate short database transaction.
7. **Bound Session:** Reads existing Session first. Valid pending/active evidence is checked through `SnippePaymentLifecycle`, then existing `SnippeClient::cancel` uses a stable 27-character cancellation idempotency key. Authenticated GET afterward is authoritative; a 2xx cancellation response by itself never releases stock. No second client or Session creation path was introduced.
8. **Completed race:** Completed GET evidence runs the existing paid lifecycle, producing Confirmed/paid, consumed reservations and one issue. If a signed webhook completes during provider cancellation, the final Order lock observes paid state and cancellation loses. Provider success always takes precedence.
9. **Unknown outcomes:** Timeout, ambiguous transport failure or still-active provider evidence cannot finalize cancellation. Active attempts retain reservations and record `cancellation_unconfirmed`, with bounded next-check delay. A cancel rejection/timeout is followed by an authenticated read because it can race with successful payment. If that read proves finality, its verified state is used.
10. **No remote Session:** Local cancellation is permitted with no Payment records, or only inactive Failed attempts having no bound Session, no reconciliation issue and an existing definitive-rejection code (`http_400`, `http_401`, `http_403`, `http_404`, `configuration`, `https_required`). The existing integration classified these at preparation/creation; no new ambiguity classification was invented. Failed history remains unchanged.
11. **Ambiguous initialization:** Any active unbound attempt is blocked, including an attempt not yet sent. Missing references or bounded discovery absence never authorize release. Existing reconciliation/provider review must establish safe truth first.
12. **Release:** Exact active reservations become Released. Provider-backed release reuses the existing lifecycle; no-Payment/definite-rejection release is owned by the cancellation service under the same canonical lock order.
13. **On hand:** Cancellation never changes balance projections. Five on hand remains five.
14. **Movements:** No return, receipt, adjustment, restock or OrderIssue is created by unpaid release. The fixture's original receipt remains the only movement.
15. **Order state:** Reuses typed `OrderStatus::Cancelled`. A staff cancellation encountering a verified already-expired Session records a Cancelled Order while retaining the true Expired Payment. An Order already finalized by ordinary expiry is not relabelled by a later request.
16. **Payment state:** Bound verified cancelled/expired Sessions retain their canonical final state and references. Historical failed attempts remain immutable history. No synthetic Payment is created for offline/no-attempt Orders; Order payment status remains Unpaid.
17. **Fulfillment:** Remains Unfulfilled. No new fulfillment state or operation.
18. **Reason:** Required trimmed text, maximum 500 characters, plus accepted explicit confirmation. `commerce_orders.cancellation_reason`, `cancelled_at` and `cancelled_by` store the successful staff operation. The actor FK preserves attribution; cancellation history cannot be rewritten. Reason is hidden from model serialization and appears only in authorized Admin detail.
19. **Permission:** Reuses existing registered `orders.cancel`; metadata now explicitly describes safe production unpaid cancellation alongside existing isolated demo behavior. No new speculative permission.
20. **Role grants:** Existing Super Administrator bundle already contains all registered permissions, including `orders.cancel`. CMS Manager, Inventory Manager and Campaign Claims Approver receive no new grants. Existing deliberate direct grants remain governed by the current access system. No permission-seeding migration was necessary.
21. **Server enforcement:** Confirmation GET and cancellation POST require auth, verified email, `admin.access`, `orders.view` and `orders.cancel`. Service independently authorizes both Order permissions and revalidates lifecycle. POST uses normal CSRF and throttling; no CSRF exemption or arbitrary status payload.
22. **Action location:** Detail only, `/admin/commerce/orders/{order}`. No index-row cancellation action. Eligibility and permission control the enabled link; paid/ineligible Orders get concise explanatory text.
23. **Confirmation UI:** Separate deliberate confirmation page at the Order's `/cancel` route, using existing Admin layout, mutation panel, field, validation/flash, danger button, confirmation checkbox and Keep order action. No browser confirm, new JavaScript, modal dependency or raw editable CRUD.
24. **Index/KPIs:** No query redesign or definition change. Existing Cancelled filter works; Total includes history, Awaiting payment requires PendingConfirmation/unpaid and therefore excludes cancellation, Paid/confirmed is unchanged, and Awaiting fulfillment requires paid. Needs attention remains based on Payment issues.
25. **Detail:** Shows Cancelled, Unpaid, Unfulfilled, Released inventory and a cancellation panel with internal reason, actor and local display timestamp. Payment history retains provider truth. No generic edit/delete.
26. **Timeline:** Adds the canonical successful cancellation timestamp with staff name. Suppresses the duplicate generic closed event for a staff-recorded cancellation. Existing reservation release timestamps remain visible. No fabricated requested/remote events are emitted.
27. **Audit:** `commerce.order.cancelled` uses the existing append-only audit framework, with actor, permission, Order number, released quantity and provider final state. Free-text reason stays on the protected Order rather than entering generic audit metadata; no customer snapshots, bearer URLs or provider secrets are logged. Audit failure rolls back local Payment finality, release and cancellation fields together.
28. **Retry blocking:** Existing guest retry checks canonical PendingConfirmation/unpaid server-side. Cancelled Orders return to their terminal confirmation without creating a Payment or Session. The public payment button is absent.
29. **Public confirmation:** Explicit “Order cancelled” and “This order has been cancelled.” Removes misleading future-contact/delivery copy and stale payment notices for closed Orders. Staff reason/actor and customer PII are not exposed; bearer/no-store protections remain. No Cart restoration.
30. **Idempotency:** Repeated successful requests return Already cancelled and preserve original reason, actor and timestamp. No duplicate release/audit. The stable Session cancellation key and pre-cancel GET support deliberate retries; provider POSTs do not auto-repeat. An active I/O lease makes simultaneous requests defer.
31. **Local atomicity:** Locks Order, sorted Payments, sorted Products, sorted Variants, shared MAIN, sorted balances and reservations. Revalidates complete exact reservations and absence of issue movements. Existing provider finalization nests in the outer cancellation transaction; cancellation metadata and audit commit with it. No network under those locks.
32. **Race behavior:** Tests interleave a second cancellation while the I/O lease is held and a signed webhook during the cancel HTTP callback. Final Order/Payment locks prevent release and issue from both succeeding. SQLite fixtures prove deterministic interleavings and rollback; they do not claim live multi-connection MySQL concurrency acceptance.
33. **Paid evidence:** Successful signed webhook first produces on hand three, consumed reservation and one OrderIssue. Cancellation is refused; state, quantity and issue remain unchanged, with no cancellation audit.
34. **Active Session evidence:** Actual stock/Cart/Checkout fake-provider fixture sends GET → cancel POST → GET outside transactions. Valid final cancelled state releases two units, records staff cancellation and blocks repeat payment.
35. **Ambiguity evidence:** 5xx cancel plus active read and 2xx cancel plus active read both retain reservations. Network failure and mismatched final amounts do not release. Ambiguous initialization blocks without HTTP. Verified later finality permits safe retry.
36. **Inventory evidence:** Before = on hand 5, active reserved 2, available 3. After unpaid cancellation = on hand 5, active reserved 0, released 2, available 5. Original receipt only; no OrderIssue or compensating movement. Late signed paid evidence after cancellation records Needs Attention and leaves stock unchanged.
37. **Focused tests:** Final totals below. Includes no-Payment local cancellation, definite versus ambiguous initialization, provider-expired truth, paid rejection, both completion races, duplicate cancellation, confirmation/permissions/CSRF, unknown and mismatch outcomes, public retry protection, presentation and audit rollback.
38. **Scoped checks:** Final results below. No full tests, full analysis, BE-6A, build, dependency audit, Homepage/fidelity matrix, live provider calls or destructive MySQL migration.
39. **HTTP/database evidence:** Uses real Product Admin, receipt ledger, Cart and Checkout routes, then authorized cancellation POST and Admin/public reads. Tests assert SQLite `:memory:` before rebuilding disposable fixtures. Persistent catalogue stock is untouched. Only bounded forward cancellation metadata migration is applied to local MySQL.
40. **Browser:** One attempt returned `Browser is not available: iab`; no retries or alternate browser tools. Physical acceptance is not claimed. Live Snippe credentials and Session authentication acceptance remain external deployment checks.
41. **Refunds:** NOT implemented. Paid cancellation is prohibited.
42. **Returns/restocking:** NOT implemented. Release never increments physical stock or creates a movement.
43. **Fulfillment:** NOT implemented. No packing, shipping, tracking, delivery, customer cancellation UI, accounts or Campaign deposit operations. COMMERCE-FULFILLMENT-1 was not started.

## Cancellable-state matrix

| Order / payment / commitment | Admin cancellation | Provider action | Inventory outcome |
| --- | --- | --- | --- |
| PendingConfirmation, unpaid, unfulfilled; exact active reservations; no Payment | Allowed | None | Release only |
| Same; only definitely rejected inactive unbound Failed attempts | Allowed | None; historical failures retained | Release only |
| Same; active bound Pending/Processing/Failed Session | Conditional | GET, cancel if active, GET verified finality | Release only after cancelled/expired truth |
| Same; provider reports completed | Rejected | Existing paid lifecycle | Consume and issue once |
| Active unbound or ambiguous attempt | Blocked for review | Existing reconciliation/investigation | Retain reservations |
| Unknown cancellation or provider still active | Not finalized | Bounded deferred check/retry | Retain reservations |
| Paid/Confirmed, completed Payment, consumed reservation or existing issue | Prohibited | No cancellation request for known paid state | No reversal or restock |
| Already Cancelled | Idempotent existing state | None | No duplicate release |
| Already PaymentExpired, non-unfulfilled or inconsistent reservation evidence | Ineligible | None | No change |

## Verification and practical limits

Final cancellation run: **13 tests, 476 assertions passed**. Bounded existing Orders Admin snapshot/privacy regression: **1 test, 62 assertions passed**. Bounded existing failed-attempt/verified-expiry Snippe regression: **1 test, 40 assertions passed**. Total across the scoped runs: **15 tests, 578 assertions passed**. The entire Orders Admin or Snippe suite was not rerun.

Scoped Larastan passed with zero errors for the service, changed Order model, status presenter, Admin controller, permission metadata and migration. Changed-file Pint passed. PHP syntax passed for all eight changed/new PHP files. Final Blade compilation passed. No JavaScript was introduced. `git diff --check` passed, and protected compiled assets have no diff. The bounded forward cancellation-record migration applied successfully; no persistent stock was changed.

The service records a staff cancellation only when its local final transaction succeeds. If a process dies after provider cancellation, existing reconciliation may later close/release the Order using provider evidence without a staff cancellation record; it does not fabricate a reason or actor. The Order remains safely observable, and a later click does not rewrite that historical event. Unknown outcomes retain reservations until verified finality or deliberate operational review. No Dashboard-login automation, live credential verification, payment refund or manual financial override was introduced.

The project-wide audit remains gated by the exact `AUTHORIZE_BE6A1_FULL_AUDIT` token in AGENTS.md. Focused verification does not authorize a full audit or the next phase.
