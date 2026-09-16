# FRONTEND UI IMPROVEMENT 1 — Minimal storefront header

Date: 2026-09-16

The shared storefront header now has a transparent initial surface, a truly centered canonical logo, and only Search, Cart and Profile at the right. Implementation and focused checks are complete. A complete build and the full audit remain unrun under the repository authorization gate. Local media failures and intermittent HTTP 500 responses limit whole-page visual acceptance.

## Files and ownership

| File | Change |
| --- | --- |
| `resources/views/frontend/partials/header.blade.php` | Shared centered brand and three accessible utility controls; canonical cart count and authentication destination |
| `resources/views/frontend/partials/header-styles.blade.php` | New, scoped transparent header, responsive geometry, focus, contrast and scroll styles |
| `resources/views/frontend/partials/shop-navigation-script.blade.php` | Retains the existing Blade-template/runtime replacement boundary; replaces removed menu handlers with shared surface detection, scroll state and announcement measurement |
| `resources/views/layouts/frontend.blade.php` | Loads header styles in the shared document head |
| `public/website/js/cart.js` | Synchronizes the header's accessible cart count alongside its existing visible badges |
| `app/Http/Controllers/StorefrontSearchController.php` | Approved minimal, read-only catalogue search |
| `resources/views/frontend/search.blade.php` | Search form, existing product cards, empty state and pagination |
| `routes/web.php` | Adds named GET `/search` route |
| `tests/Feature/MinimalStorefrontHeaderTest.php` | New header, account, cart-count and canonical-profile fixture coverage |
| `tests/Feature/Catalogue/StorefrontShopNavigationTest.php` | Updates presentation expectations while retaining navigation data/lifecycle coverage; adds search coverage |
| `tests/Feature/PublicProjection/PublicSiteContentProjectionTest.php` | Published navigation remains resolved but is no longer expected in the header; query budgets retained |
| `scripts/evidence/minimal-header-browser.mjs` | Bounded responsive/interaction evidence; supports `--utilities-only` for an independent utility checkpoint |

No Admin/Site Settings extension, schema change, new asset, dependency or compiled-bundle change was needed. The logo remains `publicSiteChrome->profile->headerLogoUrl`, with the same existing asset fallback and configured brand name. It is rendered in monochrome for contextual contrast, with its aspect ratio preserved. Commerce, inventory, payment, publication and authorization rules were not changed.

## Removed header presentation

Removed the Shop dropdown, Collections, New Arrivals, Pre-Order, Limited Edition and configured editorial/global navigation links from both the active header and its restoration template. The mobile hamburger/drawer, back arrow and page-title label are no longer included in the top header. The currency switch, Gift Cards and Wishlist utilities were also removed from this header.

Routes, navigation configuration, Collection ordering, campaign destinations and the existing navigation presenter remain available. Footer and mobile bottom navigation are outside this top-header scope and retain their existing presentation.

## Layout, states and interactions

- The brand is absolutely positioned at `left:50%` with `translateX(-50%)`. Utilities occupy their own right-aligned area, so their width never changes the brand midpoint.
- Header height is 64px, reducing to 56px on mobile. The logo scales down within the space left by equal implied utility clearance on both sides. Utility targets are 44×44px on larger screens and 36×44px on small screens; visible icons are 20px/18px.
- The initial header has no background fill, border or bar shadow. Shared surface detection selects dark controls over light plain surfaces, or white controls with a fine dark edge over dark/media/gradient surfaces. This avoids the mid-grey failure of pixel-inversion blending. The mechanism reacts to replacement, resize and media loading and requires no page-specific colour settings.
- Above 20px of scroll, a restrained translucent oxblood surface and blur provide stable contrast. Reduced-motion preference disables the colour/background transition.
- The existing announcement is measured separately and offsets the fixed navigation. Dismissing it releases that offset. An empty notification viewport from the imported runtime previously intercepted mobile taps; scoped CSS now lets taps through only its empty shell.
- Search was previously an inert button, with no Laravel search endpoint. The user approved a minimal working search addition. It searches product titles, validates a maximum 100-character query, treats SQL wildcard characters literally and paginates 12 results. It uses the existing `ProductCardPresenter`, canonical readiness/archive rules and availability projection; zero stock does not hide an otherwise eligible product.
- Cart still opens the existing canonical drawer and uses its session-backed count, GET refresh, mutation handlers and focus restoration. No cart business logic changed.
- Guests go to the existing `login` route; authenticated users go to the existing `profile.edit` route. No customer authentication flow was introduced.
- Icon actions and the logo link have accessible names; cart quantity is included in its accessible name and updated with the count. Keyboard focus is visible.
- Pages already using the shared storefront header receive this change, including product, Collection, Cart and campaign pages. Checkout, login and authenticated settings retain their existing separate shells.

## Focused validation

The final targeted PHPUnit run passed **27 tests / 327 assertions**:

```text
php vendor/bin/phpunit tests/Feature/MinimalStorefrontHeaderTest.php tests/Feature/Catalogue/StorefrontShopNavigationTest.php tests/Feature/SharedFrontendRegionsTest.php tests/Feature/PublicProjection/PublicSiteContentProjectionTest.php tests/Feature/Cart/CartTest.php tests/Feature/AccountFrontendPagesTest.php
```

Pint passed for the changed PHP controller/tests. PHP lint passed for the controller. Node syntax checking passed for `cart.js`, the extracted inline header script and the browser checkpoint script. Blade rendering is exercised by the targeted HTTP/view tests. Scoped whitespace checks passed.

The in-app browser reported `Browser is not available: iab`, and discovery returned no browsers. Existing Playwright Chromium was used as the fallback. No package installation or complete browser/fidelity matrix was run.

| Width | Logo midpoint drift | Minimum logo-to-utility gap | Horizontal overflow | Cart / Escape / focus return / scroll state |
| --- | --- | --- | --- | --- |
| 1440px | 0px | 392.06px | None | Passed |
| 1024px | 0px | 220.15px | None | Passed |
| 768px | 0px | 130.13px | None | Passed |
| 390px | 0px | 8px | None | Passed |
| 375px | 0px | 8px | None | Passed |
| 320px | 0px | 8px | None | Passed |

Screenshots were visually inspected for brand centering, aspect ratio, utility order/stroke/spacing, transparent versus scrolled surfaces, focus outlines, mobile clearance and inner-page content spacing. The approved existing-system brief was used directly; no replacement logo or generated design concept was introduced. Above-header menu copy was removed as requested; the existing announcement copy was retained. A missing compiled spacing utility on the new search page was replaced with explicit local spacing.

Light, dark, mid-grey and mixed-gradient contrast fixtures and an announcement show/dismiss fixture passed. These were browser-only DOM fixtures; they did not modify application settings or media records. They do not constitute a real video playback check.

Representative Collection, product, Cart, Pre-Order and Limited Edition pages returned HTTP 200 with one active shared header. A separate final utility checkpoint passed: header Search → submit `Oxford` → one canonical product result → guest Profile → login.

Evidence is retained in `storage/app/minimal-header-evidence/`, including native-width homepage screenshots, scrolled captures, contrast fixtures, announcement, inner pages, `search-results.png`, `results.json` and `utilities.json`.

## Remaining limitations and audit gate

- The local homepage hero image and some catalogue images fail to load. The same hero failure was observed before this change. Header geometry and plain-surface readability were verified, but the actual remote hero/video imagery cannot receive full visual sign-off from these captures. No unrelated media repair was attempted.
- Repeated local-browser runs intermittently received HTTP 500. Laravel logs report `MissingAppKeyException: No application encryption key has been specified`. Subsequent fresh requests succeeded. The final combined browser run completed responsive, contrast, announcement and representative-page checks, then encountered this error at Search. Its `results.json` honestly records `completed:false` and the HTTP 500. The independent final utility run passed and is recorded in `utilities.json`. This is not an all-green continuous-browser-run claim; `.env` and runtime configuration were not changed.
- No header drift or collision remains at the inspected widths. Real production imagery, real video playback and other browser engines remain outside the verified evidence.
- A complete frontend build was **not run**. These header styles/scripts are delivered through Blade and the existing unbundled cart script, and were exercised directly in the browser. `AGENTS.md` explicitly gates complete builds and full audits on `AUTHORIZE_BE6A1_FULL_AUDIT`; the token has not been supplied. Protected baselines were not regenerated.
