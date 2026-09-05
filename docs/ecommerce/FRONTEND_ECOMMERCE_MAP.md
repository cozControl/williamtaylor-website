# Frontend Ecommerce Map

Date: 2026-09-04  
Status: architecture contract; current William Taylor Blade storefront is the design source of truth

The verified storefront has 14 routes and 92 independently counted visible regions. **69/92 (75%) are directly ecommerce, merchandising, campaign, transaction, or customer-state concepts.** Seven shared regions are global settings/communications; the remainder are bounded Homepage or editorial content. None justify a generic page builder.

| Route | Frontend component | Data needed | Proposed domain owner | Existing backend support | Gap |
| --- | --- | --- | --- | --- | --- |
| All routes | Announcement | message, link, variant, active window | Site Settings / Announcement | Site Content revisions and schedule | Replace ordinary publish workflow with direct validated save/effective state |
| All routes | Header, desktop/mobile navigation | brand/logo, ordered links, visibility | Site Settings / Navigation | Site Content and shared presenter | Direct-save Admin and live projection |
| All routes | Mobile bottom navigation | fixed slots and destinations | Navigation settings | Partial projection | Explicit bounded slot mapping |
| All routes | Newsletter | heading, copy, consent, address | Site Settings + Communications | Presentation only | Subscriber persistence/provider later |
| All routes | Footer | brand, contact, legal links, social, newsletter | Site Settings / Navigation | Site Content and Media | Simplified direct-save UI/live mode |
| All routes | WhatsApp action | number, label/message template | Site Settings / Communications | Partial profile projection | Operational template and event ownership |
| `/` | Video hero | eyebrow, heading, copy, two CTAs, media/poster/focal point | Typed Homepage settings | Static Blade | Fixed-schema editor; preserve exact section |
| `/` | Trust strip | 3-5 ordered icon/heading/copy items | Typed Homepage settings | Static Blade | Bounded direct-save fields |
| `/` | New arrivals | heading, ordered eligible Products | Product placement | `ProductPlacement` foundation | Admin selector and public reader |
| `/` | Collection cards | ordered Collections and card Media | Collection placement | Collections and Media relations | Placement Admin/public reader |
| `/` | Pre-Order/Limited Edition panels | effective Campaigns, media, CTA | Campaign placement | Campaign foundation | Simple Campaign Admin/effective reader |
| `/` | Dark editorial Product/story grid | bounded cards, Product links, copy, Media | Typed Homepage settings + Product refs | Static Blade | Fixed-schema section |
| `/` | Occasion/category cards | ordered Collection references | Collection placement | Collection foundation | Admin ordering/public reader |
| `/` | Full-width editorial feature | heading, copy, CTA, responsive Media | Typed Homepage settings | Static Blade | Fixed-schema section |
| `/` | Testimonials | ordered quote, attribution, consent reference | Typed Homepage settings | None | Bounded list, no arbitrary layout |
| `/` | Featured Product block | Product, supporting copy, presentation key | Product placement | Placement slot foundation | Add exact slot/read model |
| `/` | Social gallery | ordered Media/link cards | Typed Homepage settings | Media reusable | Managed cards; external feed optional later |
| `/` | Journal cards | heading, excerpt, image, URL | Typed Homepage settings initially | None | Bounded links until Journal domain justified |
| `/about` | Hero, introduction, principles, editorial split, CTA | editorial copy and Media | Editorial Page | Page/revision foundation and renderer | Simplify ordinary editing; retain exact template |
| `/shop` | Heading/count/pagination | active Product query/count/page | Catalogue query | Product models only | Public query/read model |
| `/shop` | Search, filters and sort | term, category, colour, size, price/order | Catalogue discovery | Colour/size option foundation | Validated query and indexed filtering |
| `/shop` | Product cards | identity, primary Media, price, compare-at, badges, availability | Product + Pricing + Inventory/Campaign | Partial Product/Media/badge models | Exact card presenter and authoritative price/availability |
| `/collections` | Index heading | fixed heading/copy | Collection index settings | Static Blade | Keep code-owned or one direct setting |
| `/collections` | Six cards | Collection identity, card/hero Media, ordering | Collection placement | Collection/revision/Media models | Admin, active state, public routes/read model |
| `/pre-order` | Hero/process | campaign heading/copy/media/process items | Pre-Order Campaign | Campaign/revision foundation | Extend only evidenced fields; direct-save Admin |
| `/pre-order` | Featured Products and prices | ordered Campaign Products, current prices | Campaign + Product + Pricing | Campaign products | Public presenter and Pricing |
| `/pre-order` | Enquiry/action | selected Variant, customer/contact, quantity | Cart/Order intake | None | Defer until price/reservation/order intake exists |
| `/limited-edition` | Hero/copy | title, copy, media, active window | Limited Edition Campaign | Campaign foundation | Direct-save Admin/effective reader |
| `/limited-edition` | Product grid/scarcity | ordered Products, badge, availability, price | Campaign + Product + Inventory/Pricing | Partial products/claims | Truth-backed public presenter |
| `/gift-cards` | Hero/presentation | fixed copy and imagery | Gift Card presentation settings | Static | Documented; implementation deferred |
| `/gift-cards` | Value/design/form | denomination, design, purchaser, recipient, message, delivery | Gift Card | None | Monetary ledger, secure code, delivery and redemption later |
| `/wishlist` | Empty state | bounded support copy/CTA | Site Settings | Static | Optional direct setting |
| `/wishlist` | Saved Products/actions | customer/guest identity and Product references | Wishlist / Customer | None | Persistence, authorization, merge strategy |
| `/login` | Branded panel and secure form | code-owned presentation/authentication | Authentication | Functional | Keep outside ecommerce CMS |
| `/dashboard` | Account overview | customer profile, Orders, receipts, wishlist, gift cards | Customer Account | Admin user dashboard only | Separate customer identity/read models |
| `/products/the-taylor-oxford-shirt` | Full Product composition | identity, gallery, TZS price, colour/size, description/details, badges, availability, cart/wishlist, related | Product/Variant/Media/Pricing/Inventory/Cart | Strong partial Catalogue foundation | **First vertical slice**; exact template presenter and simple Admin |
| `/products/mercerized-cotton-polo` | Same nine-region Product composition | Product-specific content/options/media/commerce state | Same Product domains | Partial | Migrate after first slice |
| `/products/the-dar-es-salaam-linen-suit` | Same nine-region Product composition | Product-specific content/options/media/commerce state | Same Product domains | Partial | Migrate after first slice |
| `/products/slim-tapered-chinos` | Same nine-region Product composition | Product-specific content/options/media/commerce state | Same Product domains | Partial | Migrate after first slice |
| `/products/the-executive-overcoat` | Same nine-region Product composition | Product-specific content/options/media/commerce state | Same Product domains | Partial | Migrate after first slice |

## Product composition contract

Each Product presenter must supply the existing breadcrumb/title, ordered gallery, TZS price/optional compare-at, badges/campaign availability, Colour and Size choices, selected Variant/SKU, quantity/cart/wishlist state, description/materials/fit/care/features, delivery statement and ordered related Products. Missing operational domains may preserve the current static display during migration, but may not fabricate sellable truth.
