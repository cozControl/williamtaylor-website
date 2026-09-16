# UI-FRONTEND-1B completion report

Date: 2026-09-16

Implemented the smart header, retired the static hero feature strip, and added optional mobile media to the existing Homepage Hero Manager. The user confirmed that mobile, desktop and fallback hero images work as expected in the live/local environment. The authorized full PHP suite passed: 526 tests, 6,972 assertions. Static analysis, formatting, build, dependency audits and harness self-checks also passed. Overall audit acceptance remains open because of the protected Factory checksum mismatch and the Git-dependent canonical fidelity matrix; see the follow-up below.

## Header behavior

The existing centered logo and Search, Cart and Profile utilities retain their design and destinations. The shared header now uses `top`, `visible-scrolled` and `hidden-scrolled` states. A passive scroll listener schedules one animation-frame update, clamps overscroll, and accumulates movement in the current direction. Direction changes reset that accumulation.

- At or below 20px: visible, with the existing transparent/contextual treatment.
- Beyond 96px: 12px of downward movement hides the header.
- 8px of upward movement reveals it with the existing readable scrolled surface.
- Navigation and announcement move with transforms over 240ms; no header-layout height changes are required.
- Reduced-motion users receive no header transition.

Focus anywhere inside the header, an open canonical Cart drawer, or a header-owned expanded control/open dialog keeps it visible. Keyboard focus also reveals it immediately through `:focus-within`. Search remains the existing search page; Profile remains the existing guest/authenticated destination. Cart close and focus restoration are preserved. Checkout and authentication/account shells were not redesigned.

Live product-page testing found that whitespace inside the imported empty notification viewport intercepted header clicks. The existing empty-viewport rule now recognizes whitespace-only containers, including the nested viewport, while leaving populated notifications interactive.

## Removed feature strip

Removed the complete server-rendered Premium Fabrics / Handcrafted Details / Express Delivery / Easy Returns section. The existing homepage synchronizer also removes that exact four-icon composition if the protected runtime recreates it. It is removed from the DOM, not hidden with CSS. Browser measurements show a zero-pixel gap between the hero and the following section.

This was static frontend markup with no dedicated CMS record, visibility setting or Admin editor. No editor or historical data needed removal. Protected compiled bundles remain unchanged.

## Responsive hero and Admin lifecycle

The hero remains the same singleton record. Its existing canonical `MediaUsage` role, `background`, remains the desktop reference; optional `background_mobile` supplies mobile media. The existing reference table supports this without a schema migration or content rewrite.

The current Hero Manager now shows clearly labelled Desktop / Large Screen Image and Mobile / Small Screen Image panels using the same Media Library picker, validation messages, previews and design system. Preview containers use representative landscape/portrait proportions and contain the image; original assets are not cropped or changed. Guidance recommends mobile composition without imposing exact dimensions.

Both selections use the existing ready, confirmed, non-archived image eligibility query. Mobile changes use the same server-side `settings.manage` authorization, CSRF, transaction, optimistic lock and audit action. Audit snapshots include both media references. Replacing/removing a selection changes its usage reference, not the stored asset. Legacy callers omitting the mobile field preserve an existing mobile selection; explicitly clearing it removes that usage.

The existing direct-save lifecycle is retained: this hero has no separate revision/publication/approval workflow to extend. The presenter resolves both references on each request through the existing request snapshot. Focused tests verify that subsequent public output reflects save, replacement and removal. Shared section visibility still gates the entire hero, its projection and runtime restoration; hiding retains both references.

The public background uses a semantic `<picture>` with a mobile `<source media="(max-width: 767px)">` and the canonical desktop `<img>`. The breakpoint follows the homepage's existing 767px/768px responsive convention. Existing `hero_desktop` and `hero_mobile` Media Library derivative profiles are reused. Missing or ineligible mobile media emits no mobile source, so the desktop image is used. Existing desktop-only heroes and the original default desktop background continue working.

Hero layout, CTA destinations/native navigation, shared content and decorative empty alt text are preserved. The synchronizer restores the same responsive picture if the imported runtime replaces it. No separate mobile hero/content record was introduced.

## Files changed in this phase

Application and presentation:

- `app/Domain/Homepage/Models/HomepageHero.php`
- `app/Domain/Homepage/Actions/UpdateHomepageHero.php`
- `app/Domain/Homepage/Support/HomepageHeroPresenter.php`
- `app/Http/Controllers/Admin/HomepageController.php`
- `resources/views/admin/homepage/hero.blade.php`
- `resources/views/components/admin/media-picker.blade.php`
- `resources/views/frontend/partials/homepage-hero-picture.blade.php` (new)
- `resources/views/frontend/partials/header.blade.php`
- `resources/views/frontend/partials/header-styles.blade.php`
- `resources/views/frontend/partials/shop-navigation-script.blade.php`
- `resources/views/welcome.blade.php`

Focused checks and evidence:

- `tests/Feature/Homepage/HomepageHeroManagementTest.php`
- `tests/Feature/MinimalStorefrontHeaderTest.php`
- `tests/Feature/SharedFrontendRegionsTest.php`
- `tests/Feature/CatalogueFrontendPagesTest.php`
- `tests/Feature/SpecialCommerceFrontendPagesTest.php`
- `scripts/evidence/ui-frontend-1b-browser.mjs` (new)
- `scripts/evidence/ui-frontend-1b-live.mjs` (new)
- This report.

The shared-page tests were updated for the new header state attribute. Catalogue link expectations now check the existing minimal Search/Profile utilities rather than the traditional navigation removed in the preceding phase.

## Verification

The following focused PHPUnit files passed together: Hero Management, Homepage Section Visibility, Minimal Storefront Header, Shared Frontend Regions, Catalogue Frontend Pages and Special Commerce Frontend Pages. **28 tests, 487 assertions passed.** Coverage includes mobile save/replace/remove, legacy omission, asset retention, audit references, invalid selections, unauthorized access, visibility, archived-mobile fallback, responsive markup, static-strip removal and shared utilities.

Pint passed for all nine changed PHP classes/test files. Both browser scripts passed Node syntax checks. PHP emitted a local Xdebug log-file warning; it did not prevent the passing checks.

Playwright checked actual Blade output from isolated test fixtures with the protected runtime enabled. Local images fulfilled the two configured media URLs, keeping responsive delivery checks independent of remote media availability. Evidence is in `storage/app/ui-frontend-1b-evidence/`, including `browser-results.json`, six homepage screenshots, a revealed-header screenshot and `admin-media.png`.

| Viewport width | Selected source | Center drift | Section gap | Horizontal overflow | Scroll/focus/Cart |
| --- | --- | --- | --- | --- | --- |
| 1440 | Desktop | 0px | 0px | No | Passed |
| 1024 | Desktop | 0px | 0px | No | Passed |
| 768 | Desktop | 0px | 0px | No | Passed |
| 390 | Mobile | 0px | 0px | No | Passed |
| 375 | Mobile | 0px | 0px | No | Passed |
| 320 | Mobile | 0px | 0px | No | Passed |

At each width, the selected fixture image loaded and the other configured variant was not requested. Runtime restoration can repeat the selected URL; this is not a claim of exactly one network request. Checks also passed for tiny alternating scroll movements, return-to-top transparency, reduced motion, desktop-only fallback, keyboard focus protection and Cart focus restoration. The Admin picker initialized and removing mobile media preserved desktop media while submitting an empty mobile value. Browser script errors were empty.

Read-only browser checks against the actual local server at 390px passed on `/`, `/collections`, `/products/the-taylor-oxford-shirt`, `/cart`, `/search` and `/pre-order`: HTTP 200, hide/reveal and Cart interaction all passed. See `live-results.json`. No live Admin save, Cart mutation, checkout or payment was performed.

## Limits and audit boundary

- During the automated live browser run, the homepage selected its configured mobile URL but that remote image did not load. The user subsequently confirmed that the current live/local environment correctly loads the mobile image on mobile, the desktop image on desktop, and the desktop fallback. The earlier failure is retained as historical test evidence and is no longer listed as an unresolved delivery issue. This confirmation is user-observed; no new automated run was performed for this report correction.
- Existing CTA styling was preserved. The bright mobile fixture photography exposes weaker CTA contrast; arbitrary editorial image readability is not guaranteed by these checks. Final selected campaign photography needs visual review.
- The in-app browser exposed no available browser instance; the installed Playwright browser supplied the recorded evidence. Physical-device and authenticated account-shell acceptance were not performed.
- No migration, complete build, full suite, full fidelity matrix, full Larastan or dependency audit ran. `AGENTS.md` requires the exact token `AUTHORIZE_BE6A1_FULL_AUDIT` before those checks.
- `.env`, Snippe/payment logic, pricing, inventory, Orders, authentication and campaign business rules were unchanged by this phase.

## Authorized audit follow-up — 2026-09-16

The updated `AGENTS.md` token, `AUTHORIZE_BE6AXB_FULL_AUDIT`, appeared exactly in the user's latest active-selection context. Broader checks were therefore authorized. The existing no-Git instruction remains in force.

The canonical `fidelity:be6a1` orchestrator invokes Git for its manifest and working-tree identity (`be6a1-orchestrator.mjs`, lines 113–115 and 154–155). It was not invoked, modified or supplied fabricated identity. Consequently this follow-up cannot claim a complete canonical fidelity matrix or aggregate acceptance. `composer ci:check` was not invoked as an aggregate; its checks were run individually so a protected-baseline failure would not suppress the remaining diagnostic results.

Audit results:

- Full PHP suite, final settled-file run: **526/526 tests passed, 6,972 assertions**, native exit code 0. PHPUnit used its configured isolated SQLite test database with Snippe disabled. Xdebug instrumentation was disabled for the final run.
- Full Larastan: passed, zero errors after correcting Search's paginator type mismatch. The paginator retains Product models; the view receives a separate projected card collection. Search filtering and pagination behavior are unchanged.
- Full Pint: passed after normalizing imports/qualification in `routes/web.php`.
- Vite production build: passed, native exit code 0. The existing optional `fontaine` warning remains; generated build assets and manifests were refreshed.
- Composer audit: no advisories or abandoned packages.
- npm audit: zero vulnerabilities across 153 reported dependencies.
- Dependency audits initially encountered sandbox network restrictions and succeeded after approved unrestricted network retries.
- Candidate transaction self-check: passed all five scenarios.
- Strict behavioral harness self-check: passed 65/65.
- Protected Factory PHP check: failed for `database/factory/william-taylor-v1/site-profile.php`. Current SHA-256 is `428ee6c5e7a1d2816a153d5e8cb334752dae95e8d3db0e96507bbc019a858546`, 1587 bytes. This matches the previously documented Instagram URL decision in `BE6A1_AUDIT_REMEDIATION_1_REPORT.md`. Neither the protected file nor its baseline was changed.
- Protected `willy` database: matches approved SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`, 401408 bytes.

The first full PHP run completed with 526 tests, 524 passing and two failures. One Search request overlapped the controller/view correction and used inconsistent versions; a fresh focused run passed all six Search/navigation tests (124 assertions). The other assertion expected a saved-but-unpublished Collection URL in the old header. It now verifies that the unpublished homepage selection does not expose the URL, consistent with the minimal header and the test's publication boundary. The fresh full run against the settled files passed all 526 tests.

Additional audit corrections are limited to `StorefrontSearchController.php`, `frontend/search.blade.php`, formatting in `routes/web.php`, and the single obsolete assertion in `HomepageExploreCollectionsManagementTest.php`. No protected content or baseline was updated. No live MySQL fresh/rollback/re-migration, payment or scheduler execution was performed.

Final suite evidence: `storage/logs/ui-frontend-1b-full-audit-final-tests.log` and `storage/logs/ui-frontend-1b-full-audit-final-tests.xml`. Build evidence: `storage/logs/ui-frontend-1b-full-audit-build.log`. Earlier logs retain the initial failures and sandbox-network errors; the successful static-analysis, formatting, dependency-audit and harness retries are recorded in this session and summarized above. No complete canonical fidelity acceptance or production-readiness claim is made.
