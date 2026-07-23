# William Taylor E-Commerce Requirements

Date confirmed: 2026-07-23

## Commerce model

William Taylor is a full e-commerce website. The implementation must support the complete customer journey:

1. Browse collections and products.
2. Select product options and quantities.
3. Add products to a shopping bag.
4. Maintain a wishlist.
5. Register, authenticate, or continue as a guest where allowed.
6. Enter customer, billing, and delivery information.
7. Select an available delivery/shipping method.
8. Review prices, discounts, gift cards, optional tax, shipping, and totals.
9. Pay through Pesapal.
10. Receive an order confirmation and transactional messages.
11. Track order and fulfilment progress.
12. Request an eligible refund when the refund feature is enabled.

## Currency

- Supported currencies are Tanzanian shillings (`TZS`) and United States dollars (`USD`).
- Money must be stored as integer minor units together with an ISO 4217 currency code. Floating-point database fields must not be used for monetary values.
- Product prices must have an explicit source currency and an approved conversion/pricing policy.
- The selected currency must persist for anonymous and authenticated customers.
- Orders, payments, refunds, discounts, tax, gift-card redemptions, and shipping charges must retain their original transaction currency and immutable monetary snapshots.
- Currency display must use locale-aware formatting without changing the supplied frontend design.

## Payments

- Online payments will be handled through Pesapal API 3.0; deprecated API 2.0 must not be used.
- Payment credentials, environment, callback URLs, and webhook/IPN configuration must be managed through environment secrets, not editable CMS content.
- The order must be created in a pending state before redirecting or submitting to the payment provider.
- Payment success must be established by verified server-to-server status/IPN processing, not by trusting a browser return URL.
- Payment initiation and callback handling must be idempotent.
- Provider request IDs, tracking IDs, status history, sanitized payload metadata, currency, amount, and timestamps must be recorded.
- Duplicate callbacks must not create duplicate orders, payments, stock deductions, emails, or gift-card value.
- Failed, cancelled, abandoned, pending, completed, and reversed payment states must be supported.
- Production credentials must never be used in local development or automated tests.
- A publicly reachable Pesapal IPN URL must be registered before submitting orders; the returned IPN identifier is required during order submission.

## Products and inventory

- Products may have variants/options such as size and colour.
- Inventory must be tracked at the sellable variant level where variants exist.
- Inventory records must support:
  - quantity on hand;
  - quantity reserved;
  - quantity available;
  - low-stock threshold;
  - stock status;
  - stock adjustment reason;
  - actor and audit timestamp.
- Checkout must reserve stock for a defined period while payment is pending.
- Successful payment converts reservations into committed stock deductions.
- Failed, cancelled, expired, or invalid payment releases reservations safely.
- Overselling must be prevented with database transactions and concurrency-safe updates.
- Every stock movement must be recorded in an immutable inventory ledger.
- Administrators need stock adjustment, low-stock visibility, and movement-history tools.

## Tax

- Tax is a backend-configurable feature that can be enabled or disabled by an authorized user.
- Disabling tax affects future carts/orders only and must not rewrite historical orders.
- Configuration must support, at minimum:
  - enabled/disabled status;
  - tax name/label;
  - rate;
  - whether displayed prices include or exclude tax;
  - effective date;
  - applicable product/shipping rules if later required.
- The final Tanzania tax treatment and rate require confirmation from the client's accountant or tax adviser before production.
- Each order must snapshot the tax configuration and calculated tax amounts used at checkout.

## Delivery and shipping

- Delivery and shipping are first-class commerce features.
- The backend must support:
  - delivery zones;
  - countries/regions/cities or postcode rules as applicable;
  - shipping methods;
  - prices in TZS and USD or an approved conversion policy;
  - free-shipping thresholds;
  - delivery estimates;
  - enabled/disabled status;
  - display order;
  - method restrictions;
  - shipment status and tracking reference.
- Checkout must validate that the selected method remains available for the customer's address and current cart.
- Shipping prices and delivery promises must be snapshotted on the order.
- Initial delivery coverage, carrier integrations, zone rules, and fulfilment workflow remain to be confirmed.

## Refunds

- Refunds and the refund policy are feature-controlled from the backend.
- Authorized users may enable or disable:
  - display of the refund policy;
  - submission of customer refund requests;
  - operational refund processing.
- Disabling the feature must not erase existing refund requests, refunds, or historical policy acceptance.
- Refund policy content must be versioned and publication-controlled.
- Refund records must support full and partial amounts, reason, status, actor, provider reference, timestamps, and audit history.
- Refund eligibility and refund amounts must never be inferred only in the browser.
- Current Pesapal API 3.0 rules must be represented in validation: only completed payments can be refunded; refunds use the original payment currency; card payments may be partially or fully refunded; mobile payments may only be fully refunded; and Pesapal currently permits only one refund request per payment.
- The business''s manual fallback and reconciliation process must be confirmed during payment integration.

## Gift cards

- Gift cards are purchasable stored-value products.
- Administrators can define gift-card products/designs and allowed fixed or custom values.
- A gift card is issued only after confirmed payment.
- Each issued card requires:
  - a cryptographically strong, non-sequential code;
  - currency;
  - original value;
  - current balance;
  - status;
  - purchaser and optional recipient details;
  - issue and optional expiry timestamps;
  - immutable transaction ledger.
- Gift-card codes must be stored and displayed securely; full values must not appear in routine logs.
- Redemption and refund operations must be transactional and concurrency-safe.
- Partial redemption and remaining balances must be supported.
- Cross-currency redemption is prohibited unless an explicit conversion policy is approved.
- Expiry, transferability, cash redemption, refund treatment, and lost-card policy remain business decisions.

## Shopping bag and wishlist

- Both features must support anonymous and authenticated customers.
- Anonymous state must use a secure opaque browser identifier backed by server-side records. Product pricing and availability must always be recalculated server-side.
- On login or registration, anonymous and account state must be merged deterministically:
  - identical bag lines combine subject to available stock;
  - duplicate wishlist entries collapse;
  - conflicts are resolved without losing valid items;
  - the anonymous state is retired after successful merge.
- Customers must be able to remove items and clear their own state.
- Expired anonymous records must be removed according to the retention policy.
- Bag and wishlist identifiers must not allow access to another customer's data.

## Social profiles

- Authorized backend users need a form to manage social profile links.
- Each profile must support platform, public URL, label, enabled status, display order, and optional accessibility label.
- URLs must be validated and rendered safely.
- Adding a social profile must not permit custom HTML, JavaScript, CSS, or arbitrary icon uploads into the frontend.
- The frontend must preserve the supplied social-link design.

## Media rights

- The client confirms that all supplied images and remote videos are licensed for production.
- Approved remote media should still be copied to controlled production storage where source quality and licensing permit, protecting the website from third-party availability changes.
- Original media, attribution/license notes where relevant, optimized derivatives, alt text, and usage references must be managed in the media library.

## Roles and publication

Default administrative roles:

- `Superadmin`
- `CMS Manager`

Both roles may review and publish content.

Permissions remain feature/action based. Role names do not replace authorization policies.

- `Superadmin` receives the complete permission set and protected system access.
- `CMS Manager` receives content, media, SEO, review, and publication permissions.
- Commerce, payment, refunds, customers, exports, configuration, and user/role administration must be granted explicitly according to the final permission matrix.
- The last active Superadmin must not be removable, demotable, or disabled accidentally.
- Publication, permission, payment, refund, inventory, and customer-data actions require audit records.

## Tanzania privacy and retention

- The initial privacy jurisdiction is Tanzania.
- Operational implementation must account for Tanzania's Personal Data Protection Act and applicable regulations; production legal text and compliance interpretation require qualified local legal review.
- The confirmed default retention period is 30 days.
- Thirty-day retention applies to data that no longer has an active operational or legal purpose; it must not automatically destroy records subject to statutory accounting, tax, payment, fraud, dispute, warranty, or other mandatory retention duties.
- A documented retention schedule must classify:
  - anonymous bags and wishlists;
  - abandoned checkouts;
  - contact enquiries;
  - newsletter data;
  - analytics events;
  - application and security logs;
  - customer profiles;
  - orders, invoices, payments, refunds, and inventory ledgers;
  - consent records;
  - exports and backups.
- Scheduled jobs must delete or anonymize eligible records and produce auditable results.
- Customers must be able to request access, correction, export, and eligible deletion through an authenticated or identity-verified process.

## Internal communications and analytics

- Newsletter management will be implemented internally.
- Transactional email handling will be implemented internally.
- Analytics will be implemented internally.
- Internal handling means application-owned data models, administration, consent enforcement, queues, reporting, and auditability. It does not by itself decide the underlying email delivery infrastructure.
- Newsletter requirements include verified subscription state, consent evidence, unsubscribe, suppression, segmentation, campaign status, and delivery-event tracking.
- Transactional emails must be queued, retryable, idempotent, template-versioned, and traceable to their originating order/payment/customer event.
- Internal analytics must:
  - respect consent choices;
  - avoid collecting unnecessary personal data;
  - use first-party identifiers;
  - apply the retention schedule;
  - exclude secrets and sensitive payment data;
  - support aggregate operational and commerce reporting.

## Remaining decisions before commerce implementation

1. TZS/USD pricing source: independently maintained price lists or exchange-rate conversion.
2. Exchange-rate owner, update frequency, rounding rules, and checkout price-lock duration.
3. Pesapal merchant account readiness, API environment, supported payment methods, and refund workflow.
4. Tanzania tax label, rate, inclusive/exclusive treatment, exemptions, and shipping tax treatment.
5. Delivery coverage, zones, carriers, rate rules, free-shipping rules, and delivery estimates.
6. Inventory reservation duration and back-order/pre-order rules.
7. Product variant structure, SKU format, and initial inventory source.
8. Gift-card allowed values, expiry, transferability, cash redemption, and refund rules.
9. Guest checkout policy and the customer information required at checkout.
10. Order numbering, statuses, cancellation rules, fulfilment workflow, and invoice requirements.
11. Refund eligibility window, approval workflow, partial refund rules, and return-shipping responsibility.
12. Exact application of the 30-day retention rule by data category and statutory exceptions.
13. Email delivery infrastructure, sender domains, bounce/complaint handling, and expected volumes.
14. Internal analytics event catalogue, reporting needs, and consent categories.

## Recommended next implementation slice

Build the commerce foundation before the CMS editing interface:

1. Commerce migrations and models for products, variants, prices, inventory, carts, wishlists, customers, addresses, orders, payments, shipping, tax snapshots, gift cards, and ledgers.
2. Feature-based permissions and the two default roles.
3. Seed the currently supplied products and currencies.
4. Implement anonymous/authenticated bag and wishlist persistence and merge behavior.
5. Add transactional inventory reservation and order-total services with automated tests.
6. Integrate Pesapal only after order state, idempotency, inventory reservation, and currency rules are stable.
