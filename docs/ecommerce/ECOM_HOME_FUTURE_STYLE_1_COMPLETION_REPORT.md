# ECOM-HOME-FUTURE-STYLE-1

## Findings and limits

The inspected local database does **not** contain the reported saved duplicate composition. The `homepage_heroes` singleton `01M1H0ME000000000000000000` has `future_style_managed = false`, `future_style_campaign_1_id = null`, and `future_style_campaign_2_id = null`. This was verified through a read-only application bootstrap; no editorial data was changed.

That state selects the supplied static section in `welcome.blade.php`. Both The Executive Overcoat and Summer Linen Trousers, including the latter's PRE-ORDER presentation, are literal default markup. They are not results from Campaign Library, an active/latest query, or a campaign cache. The static defaults remain unchanged to preserve the established disabled-managed-content semantics and supplied frontend.

The reported successful duplicate save was not reproduced. The existing endpoint already rejects duplicate IDs using Laravel's `different` rule. Its view previously hid the specific error behind a generic alert. A failed request can therefore display duplicate old input without those IDs having been persisted. This is a possible explanation of the screenshots, not confirmation of their request history or database.

## Persistence and canonical rendering

Persistence was already correct for valid requests and is unchanged. The Admin PUT validates, then `UpdateHomepageFutureStyle` locks the singleton, verifies `lock_version`, writes the two campaign ULIDs and text fields, increments the version, and records an audit event in a transaction. These are ordinary columns, not JSON or slug references. The managed flag has a boolean cast.

The public route invokes `HomepageController`, then `HomepageFutureStylePresenter`. With managed content enabled, it reads the same singleton and resolves each stored ID in position order through `PreOrderCampaignPresenter` and `CampaignPublicProjection`. The existing Blade section and card partial render those results. Missing or ineligible positions are omitted; there is no discovery replacement. No alternate composition service was introduced.

The managed flag controls the entire section: text and campaign slots together. When disabled, stored selections remain stored but the supplied static section is shown. The editor now explains this behavior explicitly.

## Changes

- `app/Http/Controllers/Admin/HomepageController.php`: both the selector and server validation now use the existing public Pre-Order presenter to enforce publication, schedule, archive, approved claims, Product readiness, Media readiness, and delivery-date requirements. Deterministic selector ordering adds ID as a tie-breaker.
- `resources/views/admin/homepage/future-style.blade.php`: displays actual validation messages using the existing Admin alert style, and explains whole-section management, distinct slots, and omission.
- `app/Domain/Homepage/Support/HomepageFutureStylePresenter.php`: documents the existing whole-section flag and exact-ID/omit-only resolution policy. Runtime resolution is preserved.
- `tests/Feature/Homepage/HomepageFutureStyleManagementTest.php`: adds successful A+B persistence, next-response A+C replacement, duplicate rejection without composition changes, wrong-type/archive/unpublished/future/expired rejection and public omission, empty-slot omission, and managed text/flag coverage.
- This report.

Duplicate selections remain prohibited. No eligible saved campaign is substituted. Legacy duplicate database values are not silently deduplicated by the public reader. Limited Edition records cannot pass either the managed public presenter or the tightened Admin save. Campaign CRUD, publication policy, permissions, routes, public markup, CSS, JavaScript, and supplied assets were not redesigned.

## Caching

The homepage composition and public campaign presenter query current database state on each request. They do not call `CampaignConfigurationQuery` or its cache. Consequently no homepage cache key needs invalidation and no global cache clearing was added. The separate campaign configuration cache retains its existing per-campaign `DB::afterCommit` invalidation for campaign, claim, schedule, target, and Media mutations. Compiled Blade caches executable templates, not the selected campaign records.

## Verification

Tests run against the configured disposable SQLite in-memory database, not the inspected editorial database.

- Final `php vendor/phpunit/phpunit/phpunit tests/Feature/Homepage/HomepageFutureStyleManagementTest.php`: 5 passed, 136 assertions.
- Nearby `HomepageLimitedEditionManagementTest`, `CampaignFoundationTest`, and `CampaignAuthorityCloseoutTest`: all 19 tests passed in the combined 24-test run. That run had one Future Style error-message assertion failure; after correcting the test to render the editor with the captured validation error bag, the entire five-test Future Style file passed as recorded above. No combined all-green rerun is claimed.
- Changed-file Pint check passed for the controller, presenter, and test.
- `php artisan view:cache` passed.

The tests confirm successful requests store the exact generated A/B ULIDs, subsequent saves replace only the selected slot, rejected duplicates retain the prior composition, and campaign eligibility changes affect the next homepage response without cache clearing. The template assertion verifies actual validation messages are rendered; it does not establish physical browser redirect behavior.

The supported in-app browser runtime exposed no browser surfaces. Physical Admin save, redirect/error presentation, and storefront refresh at desktop/tablet/mobile remain unverified. In particular, the owner's screenshot environment needs confirmation before declaring the reported successful-save defect reproduced and closed.

No full audit, complete build, dependency audit, full suite, full Larastan, or MySQL migration cycle was run.
