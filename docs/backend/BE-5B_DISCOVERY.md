# BE-5B Badge and Placement Discovery

Date: 2026-07-26

## Badge-like labels

| Observed text | Source page | Source location | Visual purpose | Stable key | Owning domain | BE-5B assignable | Decision |
|---|---|---|---|---|---|---|---|
| `NEW` | Product cards and related-product cards | Homepage and five static Product templates | Recency/promotional marker | `new` | Analytics/Campaigns | No | Date-window or approved campaign evidence is absent; Catalogue must not assert recency. |
| `LIMITED` | Product cards and related-product cards | Homepage, Shop All, Limited Edition, static Product templates | Limited-release claim | `limited` | Campaigns | No | Scarcity and limited-release claims require the later Campaigns phase. |
| `PRE-ORDER` | Product cards and Product detail | Shop All, Pre-Order, static Product templates | Pre-order state/fulfilment claim | `pre-order` | Campaigns | No | Requires campaign dates and operational evidence; explicitly deferred. |
| `SALE` / crossed-out price treatment | Homepage/Shop-style card treatment | Static templates | Price promotion | `sale` | Pricing | No | Price truth and effective windows belong to Pricing. |
| `Sold out`/availability treatment | Potential card and selector state | Static commerce presentation | Availability claim | `sold-out` | Inventory | No | Availability must be derived from Inventory, not Catalogue. |

No stable Catalogue-owned badge is proven by the approved frontend. The registry therefore classifies these keys but exposes zero assignable BE-5B definitions. This is deliberate and prevents fabricated Catalogue truth.

## Product relation evidence

| Observed concept | Source | Ordering | Registered kind | Included | Reason |
|---|---|---:|---|---|---|
| `You May Also Like` | All five static Product-detail templates | Explicit card order | `related` | Yes | Approved frontend directly proves an ordered directional related-Products concept. |

`related` is directional, ordered, capped at four active targets, and never reciprocal automatically. Upsell, cross-sell, alternatives, complete-the-look, frequently-bought-together, and personalized recommendations remain unsupported.

## Placement surfaces

| Surface | Route | Template location | Current capacity | Ordering | Slot key | Product-only | BE-5B | Decision |
|---|---|---|---:|---|---|---|---|---|
| Homepage featured Product rail | `/` | `resources/views/welcome.blade.php`, first five-card Product rail | 5 | Explicit | `homepage-featured-products` | Yes | Included | Proven global ordered Product-only rail. |
| Shop All grid | `/shop` | `resources/views/frontend/shop.blade.php` | Query-defined | Query/sort | — | Yes | Deferred | Catalogue query surface, not manual placement. |
| Related Products | Five Product routes | Product templates | Up to four cards | Explicit | — | Yes | Relation | Governed by `related`, not a global slot. |
| Pre-Order rail/grid | `/pre-order` | `resources/views/frontend/pre-order.blade.php` | Campaign-defined | Campaign | — | Yes | Deferred | Campaign-owned. |
| Limited Edition rail/grid | `/limited-edition` | `resources/views/frontend/limited-edition.blade.php` | Campaign-defined | Campaign | — | Yes | Deferred | Campaign-owned. |
| Collection cards/rails | `/collections` and homepage editorial sections | Collection templates | Collection-defined | Explicit | — | No | Deferred | Collections phase owns collection targets. |
| Search/cart/wishlist recommendations | Deferred application surfaces | None approved | Unknown | Unknown | — | No | Deferred | Search, Engagement, and Commerce remain outside BE-5B. |

The included slot accepts Products only, has minimum zero and maximum five, rejects duplicates, and supports neither scheduling, campaigns, audiences, locales, arbitrary target types, nor presentation metadata.

## Eligibility boundary

Configuration eligibility is a pure Merchandising-domain evaluation. Products must exist, remain unarchived, and satisfy the current Catalogue readiness evaluator, including required media. This permits configuration before any future public publication workflow while preventing incomplete or archived Products from receiving new relations or placements.

Public projection eligibility remains unimplemented. A future projection must re-evaluate current Catalogue readiness and publication/campaign rules rather than treating persisted configuration as public authorization.
