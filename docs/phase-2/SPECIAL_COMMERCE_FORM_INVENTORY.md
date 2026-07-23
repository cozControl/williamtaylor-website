# FE-2B special-commerce form inventory

Date: 2026-07-23

All forms remain static/deferred. No action or method is declared, no Laravel endpoint receives them, and no data is persisted.

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Pre-Order / notify form | Email; ìNotify Meî | Static visual form; browser required-email validation only; no visible success/error/loading state | Email; consent, internal notification, authorization and Tanzania 30-day retention treatment required | Notification vs enquiry vs reservation; Commerce/communications phase |
| Pre-Order / footer newsletter | Required email; ìJoinî | Static visual form shared from verified newsletter partial | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |
| Limited Edition / footer newsletter | Required email; ìJoinî | Static visual form shared from verified newsletter partial | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |
| Gift Cards / purchase form | Six fixed amount buttons (TZS 50,000; 100,000; 200,000; 500,000; 1,000,000; 2,000,000), custom amount number input (min 10,000, step 5,000; visible maximum claim TZS 5,000,000), three design selectors (Oxblood, Gold, Noir), required recipient name/email and purchaser name/email, optional message, purchase submit | Client-side visual selectors update presentation; no action/method, issuance, payment, code, persistence, success/error/loading state or backend validation | Recipient and purchaser identity/contact plus message; consent, authorization, notifications and 30-day operational retention policy required before collection | Purchasable/stored-value contract, currency, delivery, redemption, security, fraud and accounting; Gift-card/checkout phase |
| Gift Cards / footer newsletter | Required email; ìJoinî | Static visual form shared from verified newsletter partial | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The Gift Cards page contains a purchase action, not a separate enquiry form. The specificationís two-form count is the purchase form plus global newsletter form.

## FE-2C additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Wishlist / footer newsletter | Required email; ìJoinî | Static visual form shared from the verified newsletter partial; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

Customer Login is not classified as a presentation-only form: it intentionally retains the application's pre-existing Fortify login endpoint, CSRF protection, named credentials, reset/register links and validation rendering. FE-2C added no authentication backend behavior.

## FE-2D additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Taylor Oxford Shirt / product controls | Four-image gallery; Ivory/Noir color choices; XS-3XL sizes; quantity decrement/increment; Add to Cart; Buy Now; Add to Wishlist; Reviews (20); related-product wishlist controls | Imported client-side presentation only; no form action, endpoint, Laravel state, validation contract or persistence | Future product, inventory, customer and behavioral data requires authorization, audit and Tanzania privacy/retention treatment | Product truth, variants, stock, cart, wishlist, checkout, reviews and enquiry contracts; later catalogue/commerce phases |
| Taylor Oxford Shirt / footer newsletter | Required email; ‚ÄúJoin‚Äù | Static shared newsletter form; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The product-detail controls are buttons rather than a product form. The only HTML form on this page is the shared footer newsletter form. FE-2D adds no submission or persistence behavior.
## FE-2E additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Mercerized Cotton Polo / product controls | Four-image gallery; Camel/Sand colors; XS-3XL sizes; quantity decrement/increment; Add to Cart; Buy Now; Add to Wishlist; Reviews (7); related-product wishlist controls | Imported client-side presentation only; no product form, action, endpoint, Laravel state, validation contract or persistence | Future product, inventory, customer and behavioral data requires authorization, audit and Tanzania privacy/retention treatment | Product truth, variants, stock, cart, wishlist, checkout, reviews and enquiry contracts; later catalogue/commerce phases |
| Mercerized Cotton Polo / footer newsletter | Required email; ‚ÄúJoin‚Äù | Static shared newsletter form; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The product controls are buttons rather than a product form. The only HTML form is the shared footer newsletter form. FE-2E adds no submission or persistence behavior.
## FE-2F additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Dar es Salaam Linen Suit / product controls | Five-image gallery; no primary color selector; S-XXL sizes with S and XXL visibly unavailable; quantity decrement/increment; ‚ÄúOnly 12 pieces remaining‚Äù scarcity copy; Add to Cart; Buy Now; Add to Wishlist; Reviews (6); related-product wishlist controls | Imported client-side presentation only; no product form, action, endpoint, Laravel state, validation contract or persistence | Future product, variant, inventory, customer and behavioral data requires authorization, audit and Tanzania privacy/retention treatment | Product truth, size variants, stock, cart, wishlist, checkout, reviews and enquiry contracts; later catalogue/commerce phases |
| Dar es Salaam Linen Suit / footer newsletter | Required email; ‚ÄúJoin‚Äù | Static shared newsletter form; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The product controls are buttons rather than a product form. The only HTML form is the shared footer newsletter form. FE-2F adds no submission or persistence behavior.
## FE-2G additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Slim Tapered Chinos / product controls | Four-image gallery; Sage/Cream colors; XS-3XL sizes with none marked unavailable; quantity decrement/increment; Add to Cart initially disabled until size selection; Buy Now; Add to Wishlist; Reviews (26); four related products with wishlist controls | Imported client-side presentation only; no product form, action, endpoint, Laravel state, validation contract or persistence | Future product, variant, inventory, customer and behavioral data requires authorization, audit and Tanzania privacy/retention treatment | Product truth, color/size variants, availability, cart, wishlist, checkout, reviews and enquiry contracts; later catalogue/commerce phases |
| Slim Tapered Chinos / footer newsletter | Required email; ‚ÄúJoin‚Äù | Static shared newsletter form; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The product controls are buttons rather than a product form. The only HTML form is the shared footer newsletter form. FE-2G adds no submission or persistence behavior.
## FE-2H additions

| Page / identifier | Visible purpose and fields | Current behavior | Data / governance | Open decision and future phase |
|---|---|---|---|---|
| Executive Overcoat / product controls | Four-image gallery; no primary color/material selector; S-XXL sizes with none marked unavailable; quantity decrement/increment; PRE-ORDER badge; ‚ÄúPre-Order ¬∑ Ships 2026-08-15‚Äù; disabled Reserve Your Piece until size selection; Buy Now; Add to Wishlist; Reviews (9); four related products with wishlist controls; delivery, returns and secure-payment claims | Imported client-side presentation only; no product form, reservation action, endpoint, Laravel state, validation contract or persistence | Future product, variant, reservation, inventory, customer and behavioral data requires authorization, audit and Tanzania privacy/retention treatment | Product truth, pre-order/reservation contract, size variants, availability, checkout, wishlist, reviews, shipping, returns and payment; later architecture/commerce phases |
| Executive Overcoat / footer newsletter | Required email; ‚ÄúJoin‚Äù | Static shared newsletter form; no action, method, endpoint or persistence | Email; express marketing consent and retention required | Newsletter subscription contract; Communications phase |

The product controls are buttons rather than a product form. The only HTML form is the shared footer newsletter form. FE-2H adds no reservation submission or persistence behavior.