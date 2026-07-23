# Phase 0 Discovery and Page Map

Date: 2026-07-23

## Current application

- Laravel 13.17 on PHP 8.3+, Livewire 4.1, Flux 2.13, Tailwind 4, and Vite 8.
- `/` currently renders Laravel's default `welcome` view.
- Authenticated dashboard, verified-email middleware, profile/security settings, two-factor authentication, and passkey support are present.
- The supplied template remains served as static source under `public/website`.

## Template inventory

- 13 HTML documents: the homepage plus 12 secondary pages.
- 1 compiled CSS bundle: `css/index-X8-QjRMe.css` (100,993 bytes).
- 1 compiled JavaScript module: `js/index-DxdnTNDA.js` (2,113,959 bytes).
- 35 local image files.
- 2 externally hosted homepage videos.
- A remotely hosted favicon/Open Graph image on `media.base44.com`.
- Inline SVG icons are embedded in the HTML.

## Page map

Routes are proposed and must be confirmed before migration. Product slugs are inferred from the visible H1.

| Static source | Visible purpose | Proposed Laravel route | Elements | Initial CMS owner |
|---|---|---|---:|---|
| `index.html` | Homepage | `/` (`home`) | 100 links, 71 images, 1 form | Home sections, featured catalogue, campaigns, testimonials, Instagram, newsletter |
| `html/page_2.html` | Collections | `/collections` (`collections.index`) | 45 links, 9 images, 1 form | Collections |
| `html/page_3.html` | Shop All | `/shop` (`products.index`) | 87 links, 51 images, 1 form | Products, filters, ordering |
| `html/page_4.html` | Pre-Order | `/pre-order` (`preorders.index`) | 43 links, 4 images, 2 forms | Pre-order campaign and enquiry/order intent |
| `html/page_5.html` | Limited Edition | `/limited-edition` (`limited-edition.index`) | 49 links, 12 images, 1 form | Limited-edition campaign/products |
| `html/page_6.html` | Gift Cards | `/gift-cards` (`gift-cards.index`) | 39 links, 3 images, 2 forms | Gift-card presentation and purchase/enquiry flow |
| `html/page_7.html` | Customer login | `/login` (`login`) | 2 links, 1 image, 1 form | Existing Fortify authentication with exact frontend presentation |
| `html/page_8.html` | Wishlist | `/wishlist` (`wishlist.index`) | 40 links, 3 images, 1 form | Customer wishlist |
| `html/page_9.html` | Taylor Oxford Shirt | `/products/the-taylor-oxford-shirt` | 49 links, 15 images, 1 form | Product |
| `html/page_10.html` | Mercerized Cotton Polo | `/products/mercerized-cotton-polo` | 49 links, 15 images, 1 form | Product |
| `html/page_11.html` | Dar es Salaam Linen Suit | `/products/the-dar-es-salaam-linen-suit` | 49 links, 16 images, 1 form | Product |
| `html/page_12.html` | Slim Tapered Chinos | `/products/slim-tapered-chinos` | 49 links, 15 images, 1 form | Product |
| `html/page_13.html` | Executive Overcoat | `/products/the-executive-overcoat` | 49 links, 15 images, 1 form | Product |

## Repeated regions to extract after equivalence checks

- Announcement/promotion bar
- Desktop header and navigation
- Mobile header, menu, and bottom navigation
- Search/account/wishlist/bag actions
- Product cards and wishlist action
- Newsletter form
- Footer identity, navigation, social links, and legal copy
- WhatsApp floating contact action
- Product detail recommendations

Extraction does not authorize markup cleanup. Each partial must produce equivalent HTML before being used by another page.

## External dependencies and links

| Dependency | Current use | Required action |
|---|---|---|
| `media.base44.com` image | Favicon and default social image | Obtain/source a local client-owned original before launch |
| `media.base44.com` videos | Two autoplay homepage videos | Confirm ownership, download approved originals, preserve encoding and visual behavior |
| Instagram | Brand/social and homepage gallery links | Confirm exact account URL |
| Facebook, Twitter/X, YouTube | Footer placeholders/general URLs | Replace only after client confirms official profiles |
| WhatsApp | Floating enquiry link to `+255 656 464 876` | Confirm number, message text, consent/privacy treatment, and business ownership |

The XML namespace URL appearing in inline SVG markup is a namespace identifier, not a runtime third-party request.

## Asset/path risks

- Nested files under `public/website/html` refer to `images/...`, `css/...`, and `js/...` as though they share the root document location. Directly opening those nested files can therefore resolve assets incorrectly.
- The 2.1 MB JavaScript module is large and should be behaviorally audited later, but must not be rewritten during baseline preservation.
- Two logo files have different names but identical SHA-256 content.
- Several image names are opaque/generated and need a human-readable media mapping before CMS import.
- Remote video and favicon availability can change independently of this repository.
- Static links currently need a complete route and broken-link audit during Phase 1/2.

## Forms requiring behavioral mapping

- Newsletter form repeated across most public pages
- Pre-order page's second form
- Gift-card page's second form
- Customer login
- Product forms/actions
- Wishlist actions

No backend behavior should be inferred solely from the presence of a form. Each submission, destination, validation rule, notification, persistence rule, and authorization requirement must be confirmed.

## Open business decisions

1. Is the site catalogue-only, pre-order/enquiry based, or full e-commerce?
2. If commerce is required, which currency, payments, inventory, tax, delivery, refund, and order rules apply?
3. Are gift cards informational, enquiry-based, or purchasable/stored-value products?
4. Do wishlist and bag require authentication, anonymous persistence, or both?
5. Which official social profile URLs replace placeholders?
6. Are all supplied images and remote videos licensed and approved for production?
7. Which roles can review and publish content?
8. Which privacy jurisdictions and retention periods apply?
9. Which newsletter, transactional email, analytics, storage/CDN, and monitoring providers will be used?
10. Are additional standard pages missing from the supplied set, including About, Contact, delivery, returns, privacy, cookies, and terms?

## Phase 0 status

- [x] Page inventory created.
- [x] Local asset inventory created.
- [x] SHA-256 preservation manifest created.
- [x] Repeated regions identified.
- [x] External dependency risks identified.
- [x] Initial route/content-owner map created.
- [x] Open-decision register created.
- [ ] Baseline screenshots captured at all fidelity viewports.
- [ ] Interaction recordings/checklist completed against rendered pages.
- [ ] Client/business decisions confirmed.

Screenshot capture is pending because the available in-app browser process could not start under the current Windows sandbox. No alternate renderer was substituted, because the baseline must come from the supported browser surface and be reproducible.
