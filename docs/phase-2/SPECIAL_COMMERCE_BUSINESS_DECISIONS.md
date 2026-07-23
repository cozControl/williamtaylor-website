# FE-2B special-commerce business-decision register

Date: 2026-07-23

Nothing below is decided or implemented by FE-2B.

## Pre-orders

- Enquiry versus order; deposit (the template claims 50%) versus full payment; Pesapal timing.
- Reservation semantics, inventory commitment, variant selection, and expiry of unconfirmed requests.
- Expected delivery dates (template shows 2026-08-15 and 2026-08-01), date changes, price changes and customer notification rules.
- Cancellation/refund rules and authorization/audit requirements.

## Limited editions

- Product- and variant-level stock limits; whether ìOnly 30/40/25/35/35 Madeî and ìOnly 8 leftî are inventory facts or campaign copy.
- Release and sale-end dates, purchase limits, back-order behavior and campaign status.
- Price rules, generic attribute/value/variant modelling, publication authority and scarcity-claim auditability.

## Gift cards

- Informational, enquiry or purchasable status; stored-value accounting and Pesapal payment flow.
- Fixed/custom amounts, TZS/USD issuance and redemption currency, expiry and dormant value.
- Partial/multiple redemption, transferability, refundability and refund-policy feature interaction.
- Digital/physical recipient delivery, notification timing, code generation/security, lost-code handling and fraud controls.
- Customer ownership, authorization, ledger/audit trail, tax treatment and financial reporting.

Visible ìDigital Deliveryî, ìNo Expiryî and ìRedeemable at Checkoutî text is preserved client copy, not an implemented or approved rule.

## FE-2C wishlist decisions

- Wishlist ownership, authenticated storage, anonymous persistence, merge-on-login, privacy/retention and item lifecycle remain deferred.
- The supplied empty state and "Shop the Collection" action are presentation only; FE-2C does not create, add, remove, synchronize or persist wishlist items.


## FE-2D product-detail decisions

Nothing below is decided or implemented by FE-2D.

- Product source of truth, publication workflow, TZS/USD pricing, discounts, tax inclusion, inventory and stock/scarcity claims.
- Generic attributes, color/size values, variant combinations, option availability and quantity limits.
- Add-to-cart and buy-now semantics, anonymous/authenticated bag persistence, merge-on-login and checkout hand-off.
- Wishlist anonymous/authenticated persistence, merge rules, retention and customer authorization.
- Ratings/reviews eligibility, moderation, aggregation and publication.
- Shipping, delivery, return and refund-policy claims, including backend feature enable/disable behavior.
- Related-product selection and ordering, enquiry behavior, analytics and notification events.

Visible product values and claims are preserved client copy and static UI, not backend facts or approved business rules.
## FE-2E Mercerized Cotton Polo decisions

Nothing below is decided or implemented by FE-2E.

- Product source of truth, publication workflow, TZS/USD price, tax inclusion, inventory, availability and discount claims.
- Camel/Sand color and XS-3XL size values, variant combinations, option availability and quantity limits.
- Add-to-cart and buy-now semantics, anonymous/authenticated bag persistence, merge-on-login and checkout hand-off.
- Wishlist persistence and merge rules; rating/review eligibility, moderation, aggregation and publication.
- Gallery/media governance, shipping, delivery, returns/refunds, related-product selection, enquiry, analytics and notification events.

Visible values and claims are preserved client copy and static UI, not backend facts or approved business rules.
## FE-2F Dar es Salaam Linen Suit decisions

Nothing below is decided or implemented by FE-2F.

- Product source of truth, publication workflow, TZS/USD price, tax inclusion, inventory, availability and the ‚ÄúOnly 12 pieces remaining‚Äù claim.
- S-XXL size values, S/XXL unavailability, variant combinations, option availability and quantity limits; the absence of a primary color selector is preserved as supplied.
- Add-to-cart and buy-now semantics, anonymous/authenticated bag persistence, merge-on-login and checkout/Pesapal hand-off.
- Wishlist persistence and merge rules; rating/review eligibility, moderation, aggregation and publication.
- Gallery/media governance, shipping, delivery, returns/refunds and refund-policy feature toggling, related-product selection, enquiry, analytics and notification events.

Visible values and claims are preserved client copy and static UI, not backend facts or approved business rules.
## FE-2G Slim Tapered Chinos decisions

Nothing below is decided or implemented by FE-2G.

- Product source of truth, publication workflow, TZS/USD price, tax inclusion, inventory and availability.
- Sage/Cream colors, XS-3XL sizes, variant combinations, option availability and quantity limits; all sizes are visually available in the supplied initial state.
- The disabled-until-size-selected Add to Cart state, add-to-cart and buy-now semantics, anonymous/authenticated bag persistence, merge-on-login and checkout/Pesapal hand-off.
- Wishlist persistence and merge rules; rating/review eligibility, moderation, aggregation and publication.
- Gallery/media governance, shipping, delivery, returns/refunds and refund-policy feature toggling, related-product selection, enquiry, analytics and notification events.

Visible values and states are preserved client copy and static UI, not backend facts or approved business rules.
## FE-2H Executive Overcoat decisions

Nothing below is decided or implemented by FE-2H.

- Product source of truth, publication workflow, TZS/USD price, tax inclusion, inventory, availability and pre-order status.
- S-XXL size values, variant combinations, option availability and quantity limits; the absence of a primary color/material selector is preserved as supplied.
- The displayed 2026-08-15/August 2026 shipping claims, reservation eligibility, deposit/full-payment rules, commitment, expiry, cancellation and customer notification.
- Reserve Your Piece and Buy Now semantics, anonymous/authenticated bag or reservation persistence, merge-on-login, checkout and Pesapal hand-off.
- Wishlist persistence and merge rules; rating/review eligibility, moderation, aggregation and publication.
- Gallery/media governance, free Dar es Salaam delivery, 2-4 day nationwide delivery, 14-day free returns, secure-payment claims, refund-policy feature toggling, related-product selection, enquiry, analytics and notifications.

Visible values, dates and claims are preserved client copy and static UI, not backend facts or approved business rules.