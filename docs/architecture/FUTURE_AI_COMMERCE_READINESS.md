# Future AI Commerce Readiness

Status: post-launch design only; no AI behavior or API is implemented.

## Capability separation

1. **Conversation/orchestration:** intent, dialogue, citations and safe fallback; no direct database access.
2. **Read-only catalogue search:** permissioned service/API returning published products, genuine effective prices, availability timestamps, attributes, policies and source links.
3. **Recommendation:** deterministic/ML ranking over eligible in-stock catalogue and merchandising rules; explain why, never invent fit/style facts.
4. **Commerce actions:** narrowly scoped cart commands, separate from recommendation, requiring authenticated/anonymous cart context, idempotency and explicit user confirmation.
5. **Customer image intake:** separate consent/private-media lifecycle.
6. **Image generation/styling:** separate moderated, budgeted job using approved product assets; output is illustrative, not a fit guarantee.

Expose versioned tool contracts through an application gateway, not SQL/Eloquent access. Each response includes stable IDs, currency, price/availability `as_of`, locale, evidence URLs and uncertainty/fallback. Tool authorization, field allowlists, rate limits and audit logs are mandatory.

## Tool examples and safeguards

- `search_catalogue(query, filters, locale)` and `get_product(id)` are read-only and published-only.
- `get_policy(kind, locale)` returns the effective published policy and date.
- `recommend_complements(product_id, constraints)` returns only eligible catalogue IDs plus reasons.
- `get_cart(cart_token)` is scoped; `add_cart_item(product_id, variant_id, quantity, idempotency_key, confirmation_token)` executes only after a clear confirmation showing item, variant, quantity, currency and price.
- Payment/order/refund tools are excluded until separately reviewed; the assistant never collects payment credentials.

Recheck price and stock at cart mutation and checkout. Never claim guaranteed fit, delivery, stock, refund eligibility or ranking. When tools fail/stale, say so and route to human support/normal storefront.

## Images and generated media

Before upload, explain purpose, processing, retention and deletion; require affirmative consent. Keep customer images private, isolated from the public media library/training, malware/type/size validated and auto-expired after the approved period (current direction 30 days). Allow immediate deletion. Generated outputs record user/request/product asset references, model/provider, prompt policy, cost, moderation state and expiry; deliver through private/signed Cloudinary URLs until explicitly saved. Clearly label mockups as illustrative and prevent face/body-sensitive misuse.

## Moderation, privacy and operations

Moderate input/output, redact logs, minimize conversation/customer data, enforce per-user/IP budgets and concurrency, cache safe read-only answers against published revision IDs, and provide kill switches per capability. Audit tool invocation, arguments after redaction, evidence/result IDs, confirmation and mutation outcome. Monitor hallucination reports, stale-data incidents, tool failures, costs, latency, conversion and human handoff—not just engagement.

## Readiness supplied by earlier domains

AI depends on stable catalogue IDs, structured attributes, genuine inventory/pricing, published policies, product relations, accessible media, search indexing, audit identity and cart command APIs. It must not be used to compensate for incomplete content/data. Discovery, recommendation, cart execution and image generation are separately authorized post-launch phases.