# ECOM-HOME-ADMIN-2 — Homepage section visibility

## 1. Status

**ECOM-HOME-ADMIN-2 IMPLEMENTATION READY FOR GENERAL INSPECTION**

The shared visibility system covers all ten implemented sections. Focused tests and Apache served-response checks passed. No full audit or physical browser acceptance is claimed.

## 2–7. Architecture, registry and defaults

Discovery found section-specific managed-content flags and individual content-readiness behavior, but no independent shared section-visibility system.

Added `homepage_section_settings`: ULID, HomepageHero foreign key, code-owned section key, boolean visibility and timestamps. The unique `(homepage_hero_id, section_key)` constraint permits one setting per section. There are no per-section visibility columns on HomepageHero.

`HomepageSectionRegistry` defines the ten existing sections, fixed position, display title, editor route, default visibility and protected-runtime boundary. Existing internal route/presenter names are retained:

| Position | Key | Section |
| --- | --- | --- |
| 1 | `hero` | Homepage Hero |
| 2 | `new-arrivals` | New Arrivals |
| 3 | `hot-sale` | William's Hot Sale |
| 4 | `future-style` | The Future of Style |
| 5 | `limited-edition` | Limited Edition |
| 6 | `explore-collections` | Explore the Collection |
| 7 | `summer-edit` | The Summer Edit |
| 8 | `delivery` | Complimentary Delivery |
| 9 | `handbags` | Women's Handbags |
| 10 | `client-stories` | Client Stories |

All registered sections default to visible. Missing rows resolve to the registry default, and the schema defaults new visibility values to true. The forward migration `2026_09_09_040000_create_homepage_section_settings.php` was applied alone. It did not seed, rewrite or remove content and requires no staff re-enabling of existing sections. The resolver also safely returns defaults before the table exists.

Unknown request keys are rejected; unknown stored keys are ignored. Staff cannot add/delete/reorder sections or choose layouts. Follow the Journey and later sections were not registered. This is not a page builder.

## 8–10. Resolver and public precedence

`HomepageSectionVisibility` is the shared read/write boundary:

- `resolve()` supplies the visibility map.
- `isVisible(key)` validates the registry key and reads that map.
- `setVisible(actor, key, bool)` authorizes and persists visibility only.
- `hiddenRuntimeSections()` supplies code-owned removal instructions for hidden keys.

The map is cached on the current Request, so public rendering and multiple Admin components reuse one settings query. The successful write invalidates that request's map. There is no cross-request or cross-user visibility cache; public visibility is independent of authentication.

Server precedence is now: hidden → omit the complete boundary; visible → run the existing managed/default content logic unchanged. All ten boundaries are gated in `welcome.blade.php`, including inert projection templates. Hidden Hero has no wrapper, image or content payload. Existing Campaign database-backed behavior is preserved rather than changing its content semantics.

Summer Edit and Complimentary Delivery retain their independent inner boundaries. Their shared decorative outer section is omitted only if both are hidden. Hiding one preserves the other without an empty region for the hidden block.

The existing Homepage synchronizer now runs `synchronizeVisibility()` before content synchronization. It reads the inert `homepage-hidden-sections` payload, finds managed markers or the registry's exact protected heading/boundary, and removes recreated hidden elements. Every content synchronizer returns immediately for a hidden key; hidden sections also have no replacement template available. Removed nodes are absent on the next observer pass, keeping removal idempotent. The shared Summer/Delivery container is removed when both keys are hidden.

Hero synchronization now identifies the actual Hero by its marker or main heading instead of assuming the first remaining section is Hero. This prevents hiding Hero from rewriting the following section. No protected compiled assets or SPA ownership were changed. Served markup and code-path checks support the runtime contract; physical mounted-DOM acceptance was not performed.

## 11–13. Workspace, editors and status semantics

UI reference used: the accepted `/admin/homepage` workspace, `x-admin.homepage-section-summary`, existing shared form styles and dedicated Summer Edit/Hero editors.

Each workspace card now shows a separate **Visible/Hidden** badge alongside its existing content/configuration status. Authorized managers get a clear **Hide section** or **Show section** form action without opening the editor. Each action has a section-specific accessible name, CSRF token, PUT method and explicit target visibility. Successful requests redirect to the workspace with feedback naming the changed section. Hidden cards say **Configuration preserved**.

The shared `x-admin.homepage-section-visibility` status panel appears in all ten dedicated editors. It shows current visibility, explains its independence from the content settings, and links to the workspace for changes. Editor content forms do not submit duplicate visibility state.

Shared UI reused: Admin panels, status badges, secondary buttons, flash feedback, spacing and responsive workspace cards. New shared additions are the visibility status component and a small wrapping badge-row style. No floating checkboxes or icon-only visibility controls were introduced.

Examples remain distinguishable: Visible + Configured, Visible + Using storefront default, Hidden + Configured, Hidden + Using storefront default. Hiding does not itself produce Needs attention; existing content readiness remains separately reported.

## 14–17. Authorization, audit, performance and permanent guidance

The quick action is `PUT /admin/homepage/sections/{section}/visibility`, under existing authenticated, verified Admin access and settings-manage middleware. `setVisible()` also authorizes `SETTINGS_MANAGE` directly, protecting non-controller callers. No new permission was created. View-only users retain status information without the mutation button.

Writes lock the Homepage row to serialize setting updates, then update only the section-setting row. They do not change Homepage content fields, managed flags, lock version, MediaUsage, canonical relationships or content timestamps. Explicit repeated requests for the current value are no-ops.

Audit uses existing `RecordAuditEvent` with action `homepage.section_visibility.updated`, the setting resource, actor and minimal before/after summaries containing section key and visibility. No unrelated Homepage payload or second history mechanism is introduced.

The visibility layer performs one table-existence guard and one bounded settings read per request, rather than ten reads. A focused query-count assertion verified one settings SELECT while rendering all ten workspace cards. Existing content presenters were not refactored for this phase.

`AGENTS.md` now contains a permanent Homepage visibility contract requiring registry participation, deliberate defaults, independent content mode, the shared service/UI, full server and runtime boundaries, preserved configuration and focused coverage for every future section. Later sections remain subject to their own phase authorization.

## 18–21. Verification and actual served evidence

Focused PHPUnit: **5 tests, 142 assertions passed** in `HomepageSectionVisibilityTest`.

Coverage includes default-visible behavior with zero settings rows; ten registry entries and editor routes; workspace controls; one-query resolution; unauthenticated/unauthorized mutation rejection; direct service authorization; invalid/unknown input; audit summaries; unchanged Homepage data; hidden unmanaged content; hidden managed content; showing preserved managed quote and MediaUsage; all ten hidden boundaries and runtime guards; Hero safety; and independent Summer Edit/Delivery visibility.

One bounded Apache scenario used the existing managed **Explore the Collection** configuration without modifying its selections or mode:

| State | HTTP | Active managed sections | Projection templates | Hidden runtime key |
| --- | --- | --- | --- | --- |
| Visible | 200 | 1 | 1 | Absent |
| Hidden | 200 | 0 | 0 | Present |
| Restored visible | 200 | 1 | Restored with normal rendering | Absent by restored setting |

Both responses contained the visibility guard and removal-before-content synchronization code. The test restored the original section-setting row (or its original absence) in `finally`. The complete Homepage content record was byte-for-value unchanged. Resulting state is the original **visible, managed** Explore section.

Evidence: `storage/app/homepage-visibility-visible.html`, `storage/app/homepage-visibility-hidden.html`, and `storage/app/homepage-visibility-evidence.json`. Active counts were parsed from the main DOM, not inferred from database values or matches inside script strings.

Scoped PHPStan: **passed, zero errors** for the new setting model, registry, visibility service/controller and changed public Homepage controller. Changed-file Pint, PHP syntax, modified synchronizer JavaScript syntax, Blade compilation and scoped `git diff --check`: **passed**. Git reported only existing Windows line-ending notices, not whitespace errors.

No browser attempt was made in this phase; its requested bounded Apache evidence was used. No screenshots, live browser interaction or physical fidelity acceptance are claimed. No full suite, full Larastan, dependency audit, complete build or fidelity matrix ran.

## 22–24. Boundaries

No Homepage content, public section design, Product/Collection/Campaign selection, Media, pricing or CTA destination changed. Follow the Journey was not started. Dynamic top navigation was not started. No other phase was begun.

Root `AGENTS.md` still requires the exact `AUTHORIZE_BE6A1_FULL_AUDIT` token before any full audit. Checkpoint success is not authorization.

**ECOM-HOME-ADMIN-2 IMPLEMENTATION READY FOR GENERAL INSPECTION**

## Boxy visibility toggle refinement

Replaced the workspace Show/Hide buttons with square-edged On/Off switches using the existing ink, gold and border tokens. The submit button exposes `role="switch"` and the saved `aria-checked` state, with a section-specific accessible name, a 44px minimum hit area, keyboard focus outline and reduced-motion support. The existing CSRF-protected visibility action is reused. Focused validation: 2 tests, 81 assertions passed; Blade compilation and scoped whitespace checks passed.
