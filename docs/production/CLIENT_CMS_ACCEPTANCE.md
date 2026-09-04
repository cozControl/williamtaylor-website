# Client CMS Acceptance Specification

Date: 2026-09-04  
Status: product-definition reference

All scenarios run with an authorized editor and, where separation of duty applies, a distinct approver. The public result must preserve the supplied William Taylor design. Passing a domain unit test without a usable Admin-to-public workflow does not pass.

## Website content

1. Log in and open Homepage.
2. Edit a visible hero/content field.
3. Upload an image; it becomes ready and renders a thumbnail.
4. Set meaningful alt text and select the image without an oversized/empty picker.
5. Save a draft without changing live content.
6. Preview the exact frontend through an expiring authorized link.
7. Submit, approve with a distinct authorized user, and publish.
8. Refresh `/` and observe the change in the exact existing composition.
9. Roll back to a new draft or unpublish; audit history and static fallback remain intact.

## Product

1. Create a Product with stable slug and description.
2. Upload and order primary/gallery images with alt text.
3. Create evidenced options (currently colour and size), values and valid Variant combinations.
4. Assign unique SKUs and a default Variant.
5. Choose up to four ordered related Products.
6. Assign and order the Product in a Collection and homepage placement.
7. Preview the exact Product page; readiness explains any blocker.
8. Submit, approve and publish.
9. Confirm the Product appears consistently on its dynamic route, Shop, Collection, homepage and related grid.
10. Archive/unpublish safely without corrupting historical Orders.

## Collection

1. Create/edit a Collection and description.
2. Select ready hero/card Media.
3. Add eligible Products and reorder them.
4. Preview index card and detail composition.
5. Approve/publish and confirm both storefront surfaces.

## Campaign

1. Edit a Pre-Order or Limited Edition headline, summary, CTA and schedule.
2. Select Products and ready campaign Media.
3. Submit factual claims with evidence where required.
4. Preview exact campaign and affected Product/home placements.
5. Obtain required independent approvals and publish.
6. Confirm scheduled activation/expiry and safe unpublish.

## Inventory

1. Configure a non-invented test location and receive stock against a Variant/SKU.
2. Confirm ledger, on-hand, reserved and available balances.
3. Reserve for an Order and confirm available decreases, on-hand does not.
4. Cancel/expire and confirm an idempotent release.
5. Reserve again, fulfil, and confirm reserved decreases and on-hand decreases exactly once.
6. Inspect complete movement history and reconcile its projection.

## Orders

1. A guest or registered customer places an Order from a priced, available Cart.
2. One Order appears in Admin despite request/webhook retries.
3. Product, Variant, SKU, options, unit price, tax/delivery and customer/delivery snapshots remain immutable.
4. Staff confirms and progresses preparation, ready, dispatch and delivery with authorized transitions.
5. Inventory reservation converts correctly on fulfilment or releases on cancellation/failure.
6. Customer sees only their Order status/history and receives an appropriate receipt.
7. Email/WhatsApp transactional events are sent once and delivery attempts are auditable.

## Gift cards

1. Purchase an allowed value for a recipient and future delivery date.
2. Deliver a non-guessable claim code through the approved channel.
3. Redeem partially and fully with concurrency protection.
4. Confirm the append-only balance history, status and Order payment snapshot.

## Cross-cutting pass conditions

- Authorization is enforced server-side on every query and mutation.
- Drafts and previews never leak to public responses or search engines.
- Media must be ready and accessible before publication.
- Publication is stale-safe, audited and reversible.
- Money uses integer minor units with explicit currency; stock uses an authoritative ledger.
- Empty states and errors tell normal users what to do without rollout/demo jargon.
- Mobile and desktop preserve the protected visual composition and accessibility semantics.
