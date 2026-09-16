# UI-FRONTEND-1C completion report

Date: 2026-09-16

Updated after user review: Hot Sale headings and spacing are restored (see the revision below).

Implemented full-width Hot Sale presentation and Follow the Journey visibility as Homepage section 11. Focused tests, responsive browser checks and live managed-layout checks passed. No protected baseline or compiled storefront bundle was changed.

## Hot Sale

The section now starts directly with feature media. Its eyebrow, title, decorative heading icon and heading wrapper are removed from both default and managed public rendering. The dedicated Admin heading fields are retired. Existing stored eyebrow/title values remain unchanged during saves, including through the action; no migration or historical data deletion was needed.

At the established `lg` breakpoint, **1024px and above**, a two-column CSS grid gives the first two features exactly half the available viewport width. The third spans both columns directly beneath them. There is no maximum-width container, outer padding or grid gap.

Each desktop panel uses `height:100svh` with `100vh` fallback and a 560px minimum for short windows. The first row therefore shares one viewport-height surface. The third panel has the same height. Below 1024px, all three stack at full width, using `75svh`/`75vh`, bounded between 420px and 800px. At the tested 900px viewport height, desktop panels were 900px and stacked panels were 675px tall.

Images and video retain their aspect ratio with `object-fit:cover` and top-centered positioning. Managed assets use the existing Media Library `editorial_content` profile: a 1400px limit derivative that preserves source proportions rather than baking in a portrait crop before browser layout. Existing focal coordinates continue through the resolver. Original media is unchanged. Default image/video URLs remain the same.

Feature titles, descriptions, CTA labels, destinations and ordering remain canonical. The existing oxblood gradient and CTA treatment are retained; descriptions are visible without hover for touch and keyboard users, and a restrained text shadow helps readability. Each entire panel remains a link with an inset keyboard-focus outline. Reduced-motion users receive no feature hover transforms/transitions.

The presenter now supplies the existing default three tiles to the same partial used for managed tiles. Both content modes have a visibility-gated inert projection. The existing synchronizer replaces imported Hot Sale markup with that projection, preventing the boxed layout and headings from returning. Native CTA navigation is protected from the imported delegated router through the existing capture listener.

## Follow the Journey

Registered `follow-the-journey` at fixed position **11**, visible by default, after Client Stories. The registry includes its DOM marker and imported-runtime heading/section boundary. Its management route is the existing Homepage workspace: no separate editor or settings page was added because this phase only adds visibility.

The row uses the shared section-summary component, status badges and authorized On/Off switch. That component now permits omission of an editor link, avoiding a redundant or dead action for this visibility-only section. Sections 1–10 retain their existing links and switches.

The switch reuses `HomepageSectionVisibility`, `homepage_section_settings`, the existing PUT endpoint, `settings.manage` authorization, CSRF protection, transaction/hero-row locking, audit records and request-scoped settings resolution. It does not introduce its own boolean or publication state. The existing visibility action has no submitted optimistic-version token; this behavior is preserved.

The entire server-rendered Journey section is gated. Hidden state also removes any recreation by the imported runtime using the shared visibility synchronizer; label synchronization exits when hidden. There is no hidden placeholder or reserved section space. Enabling restores the same gallery, content, links and position. There is no separate inert Journey projection to gate.

## Changed files

- `app/Domain/Homepage/Support/HomepageHotSalePresenter.php`
- `app/Domain/Homepage/Support/HomepageSectionRegistry.php`
- `app/Domain/Homepage/Actions/UpdateHomepageHotSale.php`
- `app/Http/Controllers/Admin/HomepageController.php`
- `resources/views/frontend/partials/homepage-hot-sale.blade.php`
- `resources/views/frontend/partials/homepage-hot-sale-styles.blade.php` (new)
- `resources/views/welcome.blade.php`
- `resources/views/admin/homepage/hot-sale.blade.php`
- `resources/views/admin/homepage/edit.blade.php`
- `resources/views/components/admin/homepage-section-summary.blade.php`
- `tests/Feature/Homepage/HomepageHotSaleManagementTest.php`
- `tests/Feature/Homepage/HomepageSectionVisibilityTest.php`
- `tests/Feature/Homepage/HomepageHeroManagementTest.php`
- `tests/Feature/Homepage/HomepageExploreCollectionsManagementTest.php`
- `scripts/evidence/ui-frontend-1c-browser.mjs` and `ui-frontend-1c-live.mjs` (new)
- This report.

## Validation

Focused PHPUnit: **31 tests, 575 assertions passed** across Hot Sale Management, Section Visibility, Hero Management and Explore Collections Management. Coverage includes the three features and destinations, missing public headings, retired Admin controls, stored-heading preservation, media validation/fallback, section order, all eleven visibility boundaries, unauthorized requests, persisted Journey state, two audited transitions and exact restored Journey markup. Existing heading/count expectations were updated for the authorized design.

Pint passed on the changed PHP files. Focused Larastan passed on the four changed application classes with zero errors. Browser scripts passed Node syntax checking.

The in-app browser returned no available browser instance. Installed Playwright Chromium was used instead. The seven-width run used actual Blade HTML exported by isolated PHPUnit requests, with the imported runtime enabled and existing media. It checked:

| Width | Composition | Panel height | Media loaded | Overflow |
| --- | --- | --- | --- | --- |
| 1440 | 720 / 720, then 1440 | 900px | All three | None |
| 1366 | 683 / 683, then 1366 | 900px | All three | None |
| 1024 | 512 / 512, then 1024 | 900px | All three | None |
| 768 | Three stacked, 768 wide | 675px | All three | None |
| 390 | Three stacked, 390 wide | 675px | All three | None |
| 375 | Three stacked, 375 wide | 675px | All three | None |
| 320 | Three stacked, 320 wide | 675px | All three | None |

All viewports had a zero left inset, full viewport section width, no section heading and cover media. Desktop first-row heights/positions matched exactly; the third began immediately below them. The first CTA navigated to its expected native URL. Browser JavaScript errors were empty.

The default video initially failed inside the network sandbox. Its original URL returned HTTP 200 outside the sandbox, and the authorized network-enabled browser rerun loaded and played it at every width. The final recorded results supersede that initial transport limitation.

Journey hide/show was exercised through actual authorized HTTP PUTs in the isolated test database; the resulting hidden and restored pages were then verified visually after runtime initialization. The Admin capture shows section 11 Off, matching its persisted fixture state. No live CMS visibility was changed for verification.

Read-only browser checks against the actual local homepage confirmed its managed feature titles and expected layout at 1440px and 390px, with HTTP 200 and no overflow. This is separate from the default-content seven-width fixture coverage.

Screenshots were inspected against the supplied 6/6 + 12 specification for equal widths, shared heights, edge alignment, third-panel span, media coverage, overlay readability, mobile stacking and matching Admin controls. Public copy changes are limited to removing the requested section heading area and exposing existing feature descriptions without hover. No generated concept was needed for this bounded existing-system change.

Evidence is under `storage/app/ui-frontend-1c-evidence/`: `browser-results.json`, `live-results.json`, seven section captures, viewport captures, Journey hidden/restored captures and `admin-section-11.png`. Captures of a tall section can include fixed storefront controls at their current scroll position; viewport captures provide the normal on-screen view.

## Scope and limits

No physical-device testing was performed. Existing low-resolution default photography can soften when enlarged; no replacement media was invented. Managed-media editorial framing should be reviewed when editors select different photos. Live Admin interaction was not used to mutate customer-facing state.

Header, responsive Hero media, Search/Cart/Profile behavior and unrelated commerce rules were preserved. No pricing, inventory, Orders, payment/Snippe, authentication, protected baseline or compiled storefront changes were made. No complete build, full suite, full fidelity matrix or full Larastan was run for this phase. Per `AGENTS.md`, those checks require `AUTHORIZE_BE6AXB_FULL_AUDIT` after implementation and checkpoint validation.


## User-requested heading and spacing revision

Restored the canonical Hot Sale eyebrow, heading and decorative icon, plus their existing heading margins. Restored the corresponding Admin fields and validated save behavior; omitted fields preserve stored values for compatibility. The section again has white background and 48px top/bottom padding, increasing to 80px at 1024px. The full-width feature grid now has a 16px gap between columns and rows, including between the upper pair and third feature. Desktop columns share the remaining width equally: `(viewport - 16px) / 2`. Mobile stacked features also have 16px separation. Panel heights and section-11 visibility are unchanged.

This revision supersedes the earlier heading-removal and zero-gap descriptions and measurements. Browser harness expectations were updated accordingly.

Revision validation: Hot Sale tests passed (5 tests, 75 assertions). Live browser checks passed at 1440px and 390px: one section heading, full-width section, equal desktop columns, 16px row separation and no horizontal overflow. Earlier seven-width captures describe the initial revision.

The revision browser run verified geometry only; remote managed media did not load in its sandboxed capture. No new media-delivery acceptance is claimed.

## Final spacing adjustment

Per the subsequent user request, the feature grid now has no column gap and a 1px white row divider. The top two desktop features each occupy exactly half the viewport; the third begins 1px below them. Mobile stacked features use the same 1px divider. Restored headings and outer section padding remain unchanged. This supersedes the 16px feature-gap revision above.
