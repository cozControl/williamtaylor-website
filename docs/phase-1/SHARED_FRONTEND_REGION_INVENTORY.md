# FE-1B Shared Frontend Region Inventory

Date: 2026-07-23

All entries are parameterless Blade includes. Markup remains literal and escaped Blade output rules remain unchanged.

| Partial | Original source boundary | Current consumers | Expected future consumers | JavaScript-sensitive selectors/structure | Accepts data | Fidelity status |
|---|---|---|---|---|---|---|
| `frontend/partials/document-head.blade.php` | Contents of `layouts/frontend.blade.php` `<head>` | Frontend layout, homepage, Collections and Shop | All pages using frontend layout | Module script must load once before stylesheet in the supplied order; inline history/page-log script remains once | No | Passed all 11 viewports |
| `frontend/partials/announcement.blade.php` | Homepage original lines 7–28, first header child | Homepage header; Collections and Shop page-local headers include this region | All equivalent public storefront headers | `.overflow-hidden.transition-all`, close button `aria-label`, `.lucide-x`; retained as first header child | No | Passed all 11 viewports |
| `frontend/partials/header.blade.php` | Homepage original lines 6–139 | Homepage | All public storefront pages | `<header>`, `nav.frosted-nav`, `.lg:hidden`, `.hidden.lg:flex`, Lucide menu/search/user/heart/bag structures; announcement include remains first child | No | Passed all 11 viewports |
| `frontend/partials/newsletter.blade.php` | Homepage original lines 1672–1690, first footer content block | Footer on homepage, Collections and Shop | Public pages with equivalent global footer | `form`, `input[type=email]`, submit button and visible initial state | No | Passed all 11 viewports |
| `frontend/partials/footer.blade.php` | Homepage original lines 1669–1943 | Homepage, Collections and Shop | All public storefront pages | `footer`, `.wt-footer-pattern`, newsletter-first ordering, social anchors and navigation hierarchy | No | Passed all 11 viewports |
| `frontend/partials/mobile-bottom-navigation.blade.php` | Homepage original lines 1944–2022 | Homepage | All public storefront pages | `.lg:hidden.fixed.bottom-0`, `.frosted-nav`, exact five-link order and Lucide SVG hierarchy | No | Passed all 11 viewports |
| `frontend/partials/whatsapp-action.blade.php` | Homepage original lines 2023–2028 | Homepage, Collections and Shop | All public storefront pages | `[aria-label="Chat on WhatsApp"]`, fixed positioning classes, target/rel and SVG structure | No | Passed all 11 viewports |

## Deliberately not extracted

- Mobile header was not split from the shared header because it shares a single responsive `<nav>` hierarchy with desktop branding and actions. Splitting inside that structure adds no stable reusable boundary.
- No separate mobile-menu panel exists in the supplied rendered markup. Only the menu trigger exists inside the header; no panel was invented.
- Search, account, wishlist, gift-card, currency and bag actions remain inside the header because they form one shared action cluster.
- Footer identity, navigation, social links, legal copy and payment labels remain one footer partial. Splitting its grid would create premature micro-partials without independent consumers.
- No cookie or consent placeholder exists in the supplied markup, so none was created.
- Homepage product cards, campaigns, sliders, testimonials and Instagram content were not extracted.

## Boundary and script notes

The imported bundle contains compiled React/template behavior rather than a stable public selector API. Browser checks therefore protect exact element order, class strings, SVG hierarchy, link destinations, and one-time script loading. No imported CSS or JavaScript was edited. No Livewire navigation directive or component lifecycle was added.

## FE-2A reuse decision

Collections and Shop consume document head, announcement, newsletter/footer and WhatsApp. Their full headers remain local because the supplied secondary variants add a mobile back control and page title. Their mobile-bottom navigation remains local because active-tab markup differs (Shop is active on Shop All). The verified homepage header and mobile-bottom partials were not parameterized.

## FE-2B shared-region review

Pre-Order, Limited Edition and Gift Cards reuse `document-head`, `announcement`, `newsletter` through `footer`, and `whatsapp-action` because rendered equivalence was verified. Their full headers and mobile bottom navigation remain page-local due distinct mobile back/title controls and active states. Campaign, product, scarcity, countdown and gift-card form regions were not extracted.
