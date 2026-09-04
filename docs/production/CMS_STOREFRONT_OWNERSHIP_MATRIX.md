# CMS Storefront Ownership Matrix

Date: 2026-09-04  
Status: read-only discovery; no implementation

## Counting rules

The inventory counts a visible, independently managed region once per page composition. Repeated shared chrome is counted once as a global region, while repeated product-detail compositions are recorded per Product route because each contains distinct product truth. Product cards inside one grid are one region, not one row per card. There are **14 public storefront routes** (including `/about`; the supplied template itself contributed 13) and **92 visible regions** in this matrix.

“Preview” and “Publication” mean an end-to-end Admin-to-exact-storefront path, not the existence of a domain action in isolation. “Technically editable but not client usable” covers governed foundations that lack a credible populated Admin-to-live workflow in the inspected environment.

## Global regions

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| All storefront | Announcement bar | Site Content with static fallback | Technically editable but not client usable | Announcements | Site Content | Yes, isolated | No, rollout disabled | N/A | Client wording, live proof, scheduling UX |
| All storefront | Desktop/mobile primary navigation | Site Content with static fallback | Technically editable but not client usable | Navigation | Site Content | Yes, isolated | No, rollout disabled | Logo is profile/static | Live projection and usable hierarchy editor |
| All storefront | Mobile bottom navigation | Site Content with static fallback | Technically editable but not client usable | Navigation | Site Content | Partial | No, rollout disabled | N/A | Explicit slot editor and live proof |
| All storefront | Newsletter presentation/form | Laravel Blade | Static | None | Website Content + Communications | No | No | No | Copy ownership, consent, persistence/provider |
| All storefront | Footer identity/contact/social links | Site Content with static fallback | Technically editable but not client usable | Site Settings | Site Content | Yes, isolated | No, rollout disabled | Logo not end-to-end proven | Repair form layout and live projection |
| All storefront | Footer navigation/legal copy | Site Content with static fallback | Technically editable but not client usable | Navigation / Site Settings | Site Content | Yes, isolated | No, rollout disabled | N/A | Legal destinations and live projection |
| All storefront | WhatsApp floating action | Site Content with static fallback | Technically editable but not client usable | Site Settings | Site Content | Partial | No, rollout disabled | N/A | Confirm number/template and add operational ownership |

## Homepage `/`

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| Home | Full-screen video hero, eyebrow, heading, copy, CTAs | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Typed hero with desktop/mobile image/video and CTAs |
| Home | Trust/service strip | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Typed ordered service items |
| Home | New arrivals heading and product grid | Laravel Blade | Static | None | Product placement | No | No | No | Read ordered eligible Products |
| Home | Shop by collection cards | Laravel Blade | Static | None | Collection placement | No | No | No | Ordered Collection cards and managed media |
| Home | Pre-order and limited-edition feature panels | Laravel Blade | Static | None | Campaign placement | No | No | No | Effective Campaign records, media, CTA |
| Home | Dark editorial product/story grid | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Typed promotional/editorial cards |
| Home | Occasion/category cards | Laravel Blade | Static | None | Collection placement | No | No | No | Typed ordered collection placement |
| Home | Full-width brand/editorial feature | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Typed editorial split/banner |
| Home | Testimonial carousel | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Bounded testimonial schema and controls |
| Home | Featured products campaign block | Laravel Blade | Static | None | Product placement | No | No | No | Existing placement foundation lacks Admin/public reader |
| Home | Instagram/social gallery | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Managed cards/links or approved feed strategy |
| Home | Journal/editorial cards | Laravel Blade | Static | None | Homepage aggregate | No | No | No | Typed article cards; no editorial domain exists |

## About `/about`

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| About | Hero | Page with protected static fallback | Technically editable but not client usable | Pages | Page | Yes, signed | No, rollout disabled | Yes in schema; upload blocked | Factory Page may be absent in real DB |
| About | Introduction rich text | Page with protected static fallback | Technically editable but not client usable | Pages | Page | Yes, signed | No, rollout disabled | N/A | Same record/projection blocker |
| About | Editorial split | Page with protected static fallback | Technically editable but not client usable | Pages | Page | Yes, signed | No, rollout disabled | Yes in schema; upload blocked | Same blocker |
| About | Principles cards | Page with protected static fallback | Technically editable but not client usable | Pages | Page | Yes, signed | No, rollout disabled | Optional schema media | Same blocker |
| About | Closing CTA | Page with protected static fallback | Technically editable but not client usable | Pages | Page | Yes, signed | No, rollout disabled | Optional background | Same blocker |

## Catalogue listing routes

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| Collections `/collections` | Page hero/title | Laravel Blade | Static | None | Collection index presentation | No | No | No | Typed index settings or code-owned heading |
| Collections | Six collection/campaign cards | Laravel Blade | Static | None | Collection placement | No | No | No | Dynamic Collections, ordering, routes, media |
| Shop `/shop` | Heading/result count | Laravel Blade | Static | None | Catalogue query | No | No | No | Dynamic count and catalogue reader |
| Shop | Search | Protected frontend JavaScript data | Static | None | Catalogue discovery | No | No | No | Server-backed/search-index query |
| Shop | Category/size/colour filters | Protected frontend JavaScript data | Static | None | Catalogue discovery | No | No | No | Taxonomy/option-backed filters |
| Shop | Sort controls | Protected frontend JavaScript data | Static | None | Catalogue discovery | No | No | No | Whitelisted query sorting |
| Shop | Product grid/cards/badges/prices | Laravel Blade | Static | None | Product | No | No | No | Dynamic Product projection, Pricing, availability |
| Shop | Pagination/load-more state | Protected frontend JavaScript data | Static | None | Catalogue query | No | No | N/A | Real pagination and accessible state |

## Campaign routes

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| Pre-Order `/pre-order` | Hero/title/copy | Laravel Blade | Backend only | None | Campaign | No | No | Backend relationship only | Campaign domain has no Admin/public projection |
| Pre-Order | Process/how-it-works content | Laravel Blade | Static | None | Campaign | No | No | No | Expand typed campaign content |
| Pre-Order | Enquiry/pre-order form | Protected frontend JavaScript data | Static | None | Future Cart/Order intake | No | No | N/A | No persistence, validation, pricing, reservation |
| Pre-Order | Featured product/CTA | Laravel Blade | Backend only | None | Campaign + Product placement | No | No | Backend only | Admin, effective-state reader, pricing |
| Limited Edition `/limited-edition` | Hero/title/copy | Laravel Blade | Backend only | None | Campaign | No | No | Backend relationship only | Admin/public campaign projection |
| Limited Edition | Limited-edition product grid | Laravel Blade | Backend only | None | Campaign products | No | No | Backend only | Dynamic targeting, price and availability |
| Limited Edition | Scarcity/badge claims | Laravel Blade | Backend only | None | Campaign claims | No | No | N/A | Only governed `public_window_statement`; UI absent |

## Gift Cards, Wishlist and account

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| Gift Cards `/gift-cards` | Hero/presentation | Laravel Blade | Static | None | Page + future Gift Card domain | No | No | No | Managed presentation and imagery |
| Gift Cards | Value/design choices | Protected frontend JavaScript data | Missing | None | Gift Card | No | No | No | No governed gift-card configuration/domain |
| Gift Cards | Purchaser/recipient/message/delivery form | Protected frontend JavaScript data | Missing | None | Gift Card | No | No | N/A | No purchase, token, balance, delivery or redemption |
| Wishlist `/wishlist` | Empty-state copy and CTA | Laravel Blade | Static | None | Website Content | No | No | No | Managed support copy optional |
| Wishlist | Saved-product list/actions | Protected frontend JavaScript data | Missing | None | Customer/Wishlist | No | No | Product media unavailable dynamically | No persistence or customer ownership |
| Login `/login` | Branded visual panel | Laravel Blade | Static | None | Authentication presentation | No | Code deployment only | No | Keep security code-owned; optionally manage support media |
| Login | Login form/help links | Laravel Blade | Yes — client usable | Account security screens | Authentication | N/A | Code deployment only | N/A | Functional auth, not CMS content |
| Account `/dashboard` | Authenticated starter dashboard | Laravel Blade | Missing | None | Customer Account | No | No | N/A | Not an ecommerce account/order dashboard |

## Five product detail routes

The same nine-region composition is independently hard-coded for each of: Taylor Oxford Shirt, Mercerized Cotton Polo, Dar es Salaam Linen Suit, Slim Tapered Chinos, and Executive Overcoat.

| Route/Page | Section | Current Source | Editable Today? | Admin Screen | Domain Owner | Preview Works? | Publication Works? | Media Managed? | Gap |
|---|---|---|---|---|---|---|---|---|---|
| Each of 5 Product routes | Breadcrumb/identity/title | Laravel Blade | Backend only | None | Product | No | No | N/A | Dynamic slug route and Admin workflow |
| Each of 5 Product routes | Gallery/thumbnails | Laravel Blade | Backend only | None | Product Media | No | No | Backend relationship only | Upload recovery, Admin ordering, public reader |
| Each of 5 Product routes | Price/compare-at display | Laravel Blade | Missing | None | Pricing | No | No | N/A | No authoritative money domain |
| Each of 5 Product routes | Badge/availability/campaign statement | Laravel Blade | Backend only | None | Campaign + Product badge | No | No | N/A | Effective governed projection missing |
| Each of 5 Product routes | Colour/size option selectors | Protected frontend JavaScript data | Backend only | None | Variant/options | No | No | Variant media not supported | Domain supports colour/size combinations; no UI/read path |
| Each of 5 Product routes | Quantity/add-to-bag/wishlist | Protected frontend JavaScript data | Missing | None | Cart/Wishlist | No | No | N/A | No cart, stock validation or persistence |
| Each of 5 Product routes | Description/materials/fit/care/features | Laravel Blade | Backend only | None | Product revision | No | No | N/A | Schema exists; Admin/public projection absent |
| Each of 5 Product routes | Delivery/pre-order/scarcity messaging | Laravel Blade | Backend only | None | Campaign/operations policy | No | No | N/A | Claims must derive from governed truth |
| Each of 5 Product routes | Related Products grid | Laravel Blade | Backend only | None | Product relation | No | No | Backend relationship only | Existing ordered relation lacks Admin/public reader |

## Quantitative result

Expanding the nine Product rows across five routes yields 45 Product-detail regions. Together with 47 global/page-specific rows above, the total is **92**.

| Classification | Regions | Interpretation |
|---|---:|---|
| Yes — client usable | 1 | Login behavior only; it is not storefront CMS ownership |
| Technically editable but not client usable | 11 | Six global chrome regions plus five About regions |
| Backend only | 40 | Campaign/catalogue/merchandising foundations without Admin-to-live workflows |
| Static | 26 | Blade/template-owned presentation |
| Missing | 14 | Commerce/customer capabilities with no usable backend |

Therefore **0 of 92 storefront content regions are client-editable end to end**. The one client-usable row is authentication behavior, not editable content. Eleven are technically governed but operationally unusable, and the remaining 80 are static, backend-only, or missing.

## Homepage typed schema recommendation

Use one `Homepage` aggregate with immutable revisions and a fixed, reorder-constrained composition—not a generic page builder:

1. `hero`: eyebrow, heading, copy, primary/secondary CTA, desktop/mobile image or video, poster, focal point.
2. `service_strip`: 3–5 ordered `{icon_key, heading, copy}` items.
3. `product_placement`: slot key and ordered Product references; reuse `homepage-featured-products`, adding separately named slots only where composition proves the need.
4. `collection_placement`: heading, copy, ordered Collection references, optional per-placement presentation override.
5. `campaign_placement`: ordered effective Campaign references with template variant.
6. `editorial_cards`: heading and bounded cards with media, copy, CTA.
7. `editorial_feature`: heading, copy, CTA, desktop/mobile media, alignment.
8. `testimonials`: bounded attributed quotes; approval and consent metadata.
9. `social_gallery`: bounded managed media/link cards initially; do not depend on an external feed for rendering.
10. `journal_cards`: bounded editorial links until a real Journal domain is justified.

Shared announcement, navigation, footer, contact/social and newsletter remain global Site Content. Product, Collection and Campaign data remain references to their own domains.
