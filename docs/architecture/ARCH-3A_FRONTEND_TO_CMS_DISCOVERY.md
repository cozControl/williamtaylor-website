# ARCH-3A Frontend-to-CMS Discovery

Date: 2026-07-23
Status: architecture discovery only; no production implementation

## Evidence reviewed

The review covered the homepage and every migrated route: Collections, Shop All, Pre-Order, Limited Edition, Gift Cards, Login, Wishlist, and the five product details (Taylor Oxford Shirt, Mercerized Cotton Polo, Dar es Salaam Linen Suit, Slim Tapered Chinos, Executive Overcoat). It also covered Phase 2 registers, FE-2A through FE-2H reports, the frontend layout and shared partials, page-local desktop/mobile navigation, Fortify/Livewire starter-kit code, routes, middleware, database/cache/queue/storage configuration, Vite, Composer/npm dependencies and PHPUnit tests.

## Current foundation

Laravel 13.17, PHP 8.3, Livewire 4.1, Flux 2.13 and Fortify 1.37 form a suitable base. Authentication already supports registration, login, password reset/confirmation, email verification, passkeys and two-factor flows. The application has one `User` model and no roles, policies, CMS, catalogue, media, SEO or commerce implementation. Web routes are literal `Route::view` entries. SQLite is the development default; MySQL/PostgreSQL are configured alternatives. Database cache and queue are defaults, failed-job/batch tables exist, local/private/public/S3 storage is configured, and Redis is available as a growth path. Tests are PHPUnit feature tests. Vite/Tailwind provide the application asset pipeline while protected template bundles remain separate.

## Component inventory and management boundary

| Component | Pages/scope | Future ownership | Field/media needs | Workflow needs | Keep in code |
|---|---|---|---|---|---|
| Document head and brand defaults | All | Site settings + per-resource SEO | Structured text, OG media | Approval for indexed changes | Head markup, schema renderer |
| Announcement bar | All | Announcement/campaign | Short desktop/mobile copy, link, schedule, targeting | Schedule, approve, expire | Layout and animation hooks |
| Desktop header/navigation | All, page-local variants | Navigation + brand settings | Menu tree, labels, links, logo | Review/publish | Responsive markup and interaction |
| Mobile header/bottom navigation | All | Same navigation source with explicit slots | Labels, icons chosen from approved set, links | Review/publish | Slot count, icons/layout |
| Search/account/wishlist/bag controls | All | Commerce/application configuration | Labels/feature flags only | Operational approval | Behavior and authorization |
| Homepage hero/video | Homepage | Page sections/campaign | Headline, copy, CTA, image/video, focal point | Schedule, preview, approve | Hero variants and responsive design |
| Homepage editorial/promotional blocks | Homepage | Structured page sections | Titles, copy, CTA, reusable media/product relationships | Approval, analytics tags | Block templates/order constraints |
| Featured products/collections | Homepage | Merchandising | Ordered relationships, optional campaign label | Schedule/approve | Card presentation |
| Collections index/cards | Collections | Catalogue + merchandising | Collection title/copy/media/order | Publication | Card/layout variants |
| Shop grid/filter/sort presentation | Shop All | Catalogue query configuration | Product relationships, taxonomy; no free-form query SQL | Catalogue publish | Grid/filter UI |
| Pre-order campaign | Pre-Order + Overcoat | Campaign + catalogue pre-order policy | Badge, dates, copy, products, CTA | Schedule/expiry/approval | Visual template |
| Limited-edition campaign | Limited Edition/products | Campaign + product badge/availability | Campaign dates, products, claims | Approval and claim audit | Design and scarcity placement |
| Gift-card page | Gift Cards | CMS + future gift-card domain | Amount rules, designs, copy, recipient fields | Financial approval later | Form layout and validation display |
| Login | Login | Authentication | Minimal managed support/legal copy only | Security review | Fortify form/validation/security |
| Wishlist empty state | Wishlist | Engagement content | Heading, copy, CTA | Content approval | Persistence behavior/layout |
| Product gallery | Product pages | Catalogue/media | Ordered media, alt, role, focal point | Product approval | Gallery behavior and ratios |
| Product identity/copy | Product pages | Catalogue | Name, SKU, short/long copy, attributes | Product publication | Product template |
| Price/availability/inventory | Product pages | Separate catalogue/pricing/inventory truths | Money by currency, sale windows, stock/availability | Restricted approval | Formatting/presentation |
| Color/size/options | Product pages | Variants/attributes | Structured values and variant availability | Catalogue approval | Selectors and accessibility |
| Pre-order/scarcity/delivery claims | Product/campaign | Campaign + operational policies | Typed claim, evidence/source, start/end | Mandatory approval/audit | Claim presentation |
| Purchase/wishlist actions | Product pages | Commerce | Product/variant context | Transaction authorization | Button UI and client interaction |
| Product tabs | Product pages | Catalogue structured copy | Restricted rich text for description/care/fit; policy relationships | Approval | Tab set/layout |
| Related products | Product pages | Merchandising | Ordered product relationships | Publish with product | Card design |
| Newsletter | Footer/campaign pages | Engagement | Consent copy, list/source identifier | Privacy/marketing approval | Form UI and abuse controls |
| Footer menus/contact | All | Navigation + organization settings | Menu groups, address, phone, email, payment labels | Review/publish | Footer structure |
| Social profiles | Footer | Site settings | Network, URL, enabled/order | Review | Approved icon mapping |
| WhatsApp action | All | Contact settings | Number, templated message, availability | Restricted edit | Icon/position/URL encoder |
| Policies/FAQ/services/locations | Referenced, not supplied | CMS content types | Structured fields plus restricted rich text/media | Legal/service approval | Page templates |
| Reviews/testimonials | Product/editorial future | Genuine review domain | Rating, text, verified source, moderation | Review/moderation | Schema eligibility rules |
| Analytics metadata | Managed resources | Analytics configuration | Stable internal keys/campaign tags | Restricted | Instrumentation implementation |

Localization should be modeled at content-resource/field level where business value exists, initially English and later Swahili. Do not translate identifiers, SKU, media facts or operational state. Scheduling applies to announcements, campaigns, promotions and publication; targeting should initially be limited to explicit locale/region/audience rules, never arbitrary editor code.

## Content boundaries

Use typed sections, not one database field per visible sentence and not a universal page-builder. A page owns an ordered list of approved section variants (hero, editorial split, promotional cards, product rail, collection rail, service summary, FAQ, CTA). Each variant has a validated schema and maps to a fidelity-preserving Blade component. Product identity, pricing, inventory, SEO and media remain separate truths referenced by sections. Global navigation/contact/social data is not copied into pages.

Rich text is justified for editorial articles, policy bodies, service descriptions, product long description/care/fit and FAQ answers. Short labels, headings, claims, CTA copy and card text remain plain structured fields. Layout classes, HTML attributes, icons, routes, JavaScript hooks and unrestricted HTML remain code-controlled.

## Principal decisions requiring approval

1. Use `/products/{slug}` as canonical; permanently redirect legacy `/product/{slug}` aliases.
2. Adopt domain-oriented modules in the existing Laravel app, not microservices.
3. Use a constrained section system rather than an unrestricted page builder.
4. Separate CMS content, catalogue, price, inventory, presentation, SEO and media truths.
5. Adopt a two-person publish option for legally/financially sensitive content while allowing Super Administrator and explicitly authorized CMS Manager review/publish per current requirements.
6. Select production database (recommended PostgreSQL or MySQL), search engine, Cloudinary account/preset policy, audit implementation and rich-text editor during architecture approval.
7. Initial locales, Tanzania retention interpretation per data class, inventory source, tax behavior, refund toggle semantics and Pesapal contract remain later decisions.

## Recommendation

Approve the architecture decisions before building. The next phase should be the administration shell/navigation only, with no domain CRUD beyond read-only placeholders and permission-aware navigation.