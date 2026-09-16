# BE6A1-AUDIT-REMEDIATION-1

Status: remediation implemented; baseline/product decisions remain blocking. Production readiness is not established.

## Failure classification

| Original failure | Classification | Evidence / resolution boundary |
| --- | --- | --- |
| `Tests\Feature\AccountFrontendPagesTest::test_login_preserves_existing_fortify_contract_without_storefront_regions` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\AccountFrontendPagesTest::test_wishlist_reuses_exact_shared_regions_once_and_remains_empty_static_presentation` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Admin\AccessManagementTest::test_users_and_roles_routes_enforce_full_view_boundaries` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\AccessManagementTest::test_role_catalogue_is_registered_read_only_and_reports_drift` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\AdminNavigationRegistryTest::test_registry_has_stable_order_registered_routes_and_registered_permissions` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Admin\AdminNavigationRegistryTest::test_visibility_is_derived_from_permissions_not_role_names` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\CatalogueFrontendPagesTest::test_collections_and_shop_routes_are_named_and_public_for_guests` | 3 - database fixture | Public catalogue now queries canonical Collections; tests omit RefreshDatabase. |
| `Tests\Feature\CatalogueFrontendPagesTest::test_catalogue_pages_preserve_assets_and_single_shared_regions` | 3 - database fixture | Public catalogue now queries canonical Collections; tests omit RefreshDatabase. |
| `Tests\Feature\Catalogue\ProductMediaUsageTest::test_readiness_requires_usable_primary_and_tracks_archive_restore_without_losing_usage` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |
| `Tests\Feature\Demo\DemoWorkspaceTest::test_dashboard_uses_production_language_and_links_to_existing_workspaces` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Demo\DemoWorkspaceTest::test_site_settings_renders_distinct_controls_picker_and_state_aware_workflow` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Factory\FactoryBaselineTest::test_inventory_manager_is_a_reserved_admin_shell_role_only` | 6 / 2 - role contract drift | InventoryLedgerService enforces inventory.manage; Inventory Manager bundle is no longer reserved. |
| `Tests\Feature\Factory\FactoryBaselineTest::test_factory_install_creates_exact_identities_roles_and_published_content_idempotently` | 6 / 2 - role contract drift | InventoryLedgerService enforces inventory.manage; Inventory Manager bundle is no longer reserved. |
| `Tests\Feature\Homepage\HomepageExploreCollectionsManagementTest::test_saved_selections_do_not_activate_unmanaged_mode_and_editor_explains_it` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Homepage\HomepageHeroManagementTest::test_homepage_workspace_lists_exactly_six_sections_in_storefront_order` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Homepage\HomepageHeroManagementTest::test_public_homepage_uses_exact_static_fallback_without_record` | 5 / 6 - Hero contract conflict | Current Hero omits documented visible eyebrow/title/subtitle. Defaults are valid UTF-8; no encoding defect. User asked relevance to Snippe; preserve design pending decision. |
| `Tests\Feature\Identity\RbacFoundationTest::test_registry_contains_the_approved_foundation_media_and_page_permissions` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2d_product_detail_route_is_public_named_and_renders_source_content` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_product_detail_reuses_shared_regions_once_and_preserves_static_controls` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_product_detail_registers_canonical_dynamic_route_without_commerce_mutations` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2e_mercerized_cotton_polo_route_is_public_named_and_renders_source_content` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2e_polo_reuses_shared_regions_and_keeps_commerce_controls_presentational` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2f_dar_es_salaam_linen_suit_route_is_public_named_and_renders_source_content` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2f_linen_suit_reuses_shared_regions_and_keeps_commerce_controls_presentational` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2g_slim_tapered_chinos_route_is_public_named_and_renders_source_content` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2g_chinos_reuses_shared_regions_and_keeps_commerce_controls_presentational` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2h_executive_overcoat_route_is_public_named_and_renders_source_content` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2h_overcoat_reuses_shared_regions_and_keeps_preorder_controls_presentational` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\PublicPageProjection\AboutPageProjectionTest::test_route_is_exact_and_disabled_projection_renders_complete_static_page_without_page_queries` | 4 - query budget | Full-route counter includes canonical Shop navigation and Homepage queries; query attribution required. |
| `Tests\Feature\PublicPageProjection\AboutPageProjectionTest::test_enabled_projection_stays_within_uncached_and_cached_query_budgets` | 4 - query budget | Full-route counter includes canonical Shop navigation and Homepage queries; query attribution required. |
| `Tests\Feature\PublicProjection\PublicSiteContentProjectionTest::test_enabled_homepage_and_product_route_stay_within_five_projection_queries` | 4 - query budget | Full-route counter includes canonical Shop navigation and Homepage queries; query attribution required. |
| `Tests\Feature\PublicTemplatePagesTest::test_supplied_public_pages_render_from_named_laravel_routes` | 3 - database fixture | Public catalogue now queries canonical Collections; tests omit RefreshDatabase. |
| `Tests\Feature\SharedFrontendRegionsTest::test_shared_frontend_partials_exist_and_render_once_on_the_guest_homepage` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\SiteContent\SiteContentPublishingTest::test_preview_is_signed_type_authorized_private_and_resource_bound` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\SpecialCommerceFrontendPagesTest::test_special_commerce_pages_preserve_assets_and_single_regions` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\SpecialCommerceFrontendPagesTest::test_each_page_preserves_its_supplied_form_count_and_controls` | 2 - intentional architecture change / stale expectation | Current canonical commerce/CMS implementation; inspect individual assertion before updating. |
| `Tests\Feature\Admin\AccessManagementTest::test_authorized_livewire_assignment_and_revocation_use_existing_audited_actions` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\AccessManagementTest::test_final_admin_and_self_lockout_safeguards_preserve_state` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_unchanged_confirmed_preview_applies_once` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_direct_permission_change_invalidates_confirmation_without_mutation_or_audit` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_role_and_bundle_changes_each_invalidate_confirmation` | 1 - runtime regression | Missing permission metadata or homepage view data; Gate 1 fixes and focused tests. |
| `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_relation_add_rejects_self_and_duplicates_and_never_creates_reciprocal` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |
| `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_relation_reorder_archive_restore_and_stale_rejection_are_atomic` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |
| `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_placement_assignment_reorder_archive_and_restore_preserve_products` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |
| `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_product_archive_preserves_configuration_and_pure_eligibility_fails` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |
| `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_ordered_domain_queries_and_eligibility_have_bounded_query_counts` | 7 / 3 - readiness fixture | CatalogueReadinessEvaluator requires base_price_minor and primary category; fixtures omit both. |

## Confirmed resolutions

- Gate 1: `products.view/manage` and `inventory.view/manage` were already canonical in PermissionRegistry, RoleRegistry, provisioning and backend authorization. PermissionMetadata omitted all four display entries. Completed metadata; exact registry/metadata equality and navigation/bundle coverage added. No permissions or role grants were added.
- Signed previews now use `HomepageViewData`, shared with HomepageController, after preserving type authorization and resource binding. Visibility is explicitly resolved through the existing service. No Blade defaults added.
- Gate 1 initial access/publishing run: 25 tests, 150 assertions passed; expanded access/publishing/visibility run: 25 tests, 400 assertions passed.
- Campaign presenters normalize final arrays with `array_values`, preserving list contracts and order. Scoped Larastan passed.
- Homepage rendering now shares a bounded snapshot of schema and singleton record across section presenters. Snapshot is discarded after rendering, avoiding cross-request or editor stale state. A focused test checks one singleton read and release.
- Query traces are in `storage/logs/remediation-home-queries.jsonl` and `remediation-about-queries.jsonl`. The former 51-query homepage route included repeated Homepage schema/singleton reads, Campaign discovery and Shop navigation. About's extra nine queries belonged to live Site Settings and canonical Shop navigation. Projection budgets retain 6/3 limits and explicitly count their own tables; full-route totals are not described as those budgets.
- Readiness/merchandising fixtures now include price, primary category, author fields and default Variant SKU. Existing readiness evaluator remains unchanged; fixture assertions expose failure codes.
- Canonical Collections route tests now migrate their isolated database. The Collections index regained the existing mobile navigation and WhatsApp partials.
- Frontend region checks exclude inert templates and script/style contents. Imported demo runtime is absent on canonical Product/Campaign/Collections pages; Shop, Wishlist and Gift Cards retain their existing runtime contract. Cart/checkout route assertions now recognize the approved POST routes. No commerce mutation authorization was weakened.
- Navigation/RBAC/Factory-role expectations now cover canonical inventory and separate production/demo Orders. Permission database rows are compared as a set because migrations may provision inventory permissions before the seeder.
- Homepage workspace count follows the existing ten-section phase. Explore selection tests distinguish disabled Homepage placement from independently visible canonical Collection navigation.
- Site Settings tests reflect the existing direct-save workflow and Change note control; dashboard copy reflects its current William Taylor overview.

## Protected provenance and open decisions

Approved SHA-256: `c6fce29de89652ab86b5b2ec0ab8ed2605d24f52276a5484e5679d55ab4ef40b`, 1582 bytes, recorded in `scripts/validation/protected-php-baseline.json`. Commit `f38244b` contains exactly those bytes. Commit `7d578ee` changed only the Instagram URL from `https://instagram.com/williamtaylor` to `https://instagram.com/williamtaylorbrand`, producing 1587 bytes and SHA-256 `428ee6c5e7a1d2816a153d5e8cb334752dae95e8d3db0e96507bbc019a858546`. Current file equals that later artifact. Provenance is established, but restoring the old account conflicts with the later brand-account choice. User decision requested; neither protected file nor baseline changed.

The Hero's older CMS contract requires visible eyebrow/title/subtitle; current markup intentionally or accidentally omits them while retaining data and CTAs. No sufficient approval evidence for replacing that contract was found. User questioned its relevance to Snippe; clarified it is unrelated and preserved the current design. This remains a product/fidelity decision, not a payment defect.

## Dependencies

| Family | Before | After | Applicability |
| --- | --- | --- | --- |
| Guzzle | 7.15.1 | 7.15.2 | Laravel HTTP and Snippe transport use it. Snippe constructs configured endpoints and disables redirects; no arbitrary public URL fetch path found. Patch still applied. |
| CommonMark | 2.8.3 | 2.10.0 | Laravel Markdown/mail dependency. No application Attributes/Footnote/SmartPunct/XML conversion path found in app code; framework functionality remains present. |
| Livewire | 4.3.3 | 4.3.4 | Livewire is used throughout Admin and public components; affected client-state handling is relevant. |
| Tiptap core/starter kit | 3.28.0 | 3.30.5 | Authenticated CMS HTML editor uses StarterKit. No Markdown extension configured, but attribute helper vulnerability warrants patch. |
| nanoid | 3.3.16 | 3.3.19 | Transitive frontend tooling; no app custom generator with user-supplied size found. |
| PostCSS | affected 8.5.x | 8.5.28 | Build-time CSS processing; no public attacker-CSS compilation endpoint found. |

Compatible Composer constraints preserve Laravel 13, Livewire 4 and Flux 2. Updates are restricted to named families. npm updates followed existing compatible transitive ranges. Final npm and Composer audit responses contain zero advisories. This does not claim exploitation was present or prove all application security.

Upstream evidence: [Guzzle](https://github.com/advisories/GHSA-v5mv-p594-2x33), [CommonMark 2.10.0](https://github.com/thephpleague/commonmark/releases/tag/2.10.0), [Livewire](https://github.com/advisories/GHSA-g3hc-697w-wm82), [Tiptap Markdown](https://github.com/advisories/GHSA-j95f-988m-3j2f), [Tiptap attributes](https://github.com/ueberdosis/tiptap/security/advisories/GHSA-cp6q-959q-f8rh), [nanoid](https://github.com/advisories/GHSA-2v37-7h3g-55p8), [PostCSS](https://github.com/postcss/postcss/security/advisories/GHSA-fxqj-rqcc-2cmp).

Guzzle payment verification: 47 tests, 1486 assertions passed. CommonMark follow-up: 49 tests passed. Livewire follow-up across access, Site Content, Content, Payments and Homepage visibility: 116 tests, 2160 assertions passed.

## Harness changes under verification

- Bounded previously unbounded CIM identity lookup, synchronous PHP setup commands and health-fetch attempts. Disabled local Xdebug for disposable PHP processes; added startup and route progress files.
- PHPUnit bootstrap and fidelity orchestrator share an atomic validation directory lock. A concurrent fidelity prepare attempt failed before preparing evidence while PHPUnit owned the lock. No arbitrary checksum file exclusions were added. Crashed owners fail closed and require ownership investigation.
- Windows creation-time/process ownership checks and cleanup proof remain enforced. No visual baseline approval or replacement is authorized by these fixes.

## Final verification so far

| Check | Result |
| --- | --- |
| Production Vite build | Passed, verified process exit 0; optional Fontaine fallback warning remains informational |
| Full-project Pint | Passed |
| Full Larastan | Passed, zero errors |
| Full Laravel | 519 tests: 518 passed, 1 failure, 0 errors, 6840 assertions |
| npm audit | Zero advisories |
| Composer audit | Zero advisories, no abandoned packages reported |
| Payment regression after Guzzle | 47 passed, 1486 assertions |
| Livewire/payment/content/visibility follow-up | 116 passed, 2160 assertions |
| Protected Factory check | Failed on the documented Instagram URL conflict; exact approved artifact recovered, no automatic restoration/approval |

The sole full-suite failure is `HomepageHeroManagementTest::test_public_homepage_uses_exact_static_fallback_without_record`. It requires visible Hero copy absent from the current design. The failure remains deliberately visible pending a product decision. No Payments, Checkout, Inventory or Cart failures remain.

## Files changed

Application: `PermissionMetadata.php`; shared `HomepageViewData.php` and `HomepageRenderSnapshot.php`; HomepageController and SiteContentController; the ten Homepage section presenters (shared snapshot reads); the two Campaign presenters (list normalization); `resources/views/frontend/collections.blade.php` (missing shared actions).

Validation: `tests/bootstrap.php`, `phpunit.xml`, `tests/Support/StorefrontMarkup.php`; focused access, navigation, RBAC, Factory, Demo, Catalogue, merchandising, frontend, Homepage and projection tests listed in the classification; `scripts/evidence/be6a1-browser.mjs` and `be6a1-orchestrator.mjs`.

Dependencies/artifacts: `package.json`, `package-lock.json`, `composer.lock`, generated `public/build` assets, this report and `storage/logs/remediation-*` evidence. Snippe source, idempotency, signatures, finality and canonical inventory/order writes were not changed.

## Browser root cause and final result

Two harness defects were established:

1. `inspectProcess()` joined PowerShell `if` and `else` with semicolons; PowerShell executed `else` as an unknown command. Reproduced directly. The script now uses newline-separated statements and UTF-16 encoded arguments, with a bounded inspection timeout.
2. Chromium `--deterministic-mode` prevented Playwright screenshots. A blank-page probe timed out with the nine harness flags, passed with default flags, and passed when only `--deterministic-mode` was removed. Removed that incompatible launch flag. Repeated exact-raster comparisons, semantic checks, normalization and thresholds remain intact; no baseline was approved.

Health requests, PHP setup and readiness evaluation now have bounds. Frame sweeps have timer fallbacks. Progress files identify startup and security-route progress. The earlier bounded diagnostic runs were edited after their failure result was emitted but before their stuck child had exited; they are diagnostic-only, not immutable fidelity evidence. Remaining children were terminated only after confirming their exact audit parent and script path.

Final isolated run: `be6a1-20260916050717-7d578ee7`.

- Exit 1, **no stage timeout**.
- Stage result and completion markers present and valid.
- Orchestrator process proof/descendant cleanup **passed**.
- Immutable working-tree verification **passed**.
- Security route loop reached all 45 entries; the aggregate security stage failed during Homepage readiness before returning its route payload, so no overall security acceptance is claimed.
- Actual failure: `Semantic/visual readiness failed: expected-route-landmark-missing`. The current image-only Hero removes the H1 required by the immutable fidelity contract. This is the same product decision identified by the sole full-suite failure.
- Stage cleanup records an open browser context at pre-close inventory because readiness threw; final orchestrator ownership/termination proof passed and the lock was released.
- Preview, complete screenshot matrix, comparisons and final verification were not reached because the canonical sequence fails closed at security. No visual acceptance or production readiness claimed.

Harness self-check passed 65/65 before the final launch-flag/process-script refinements. Final JS syntax checks passed and the canonical run validated process/result/immutability handling, but the full harness self-check was not repeated after those refinements.

## Unresolved decisions

1. Preserve the image-only Hero and explicitly revise the approved Hero/fidelity contract, or restore its documented visible copy. Current design and failing assertion are preserved. This has no bearing on Snippe payment correctness.
2. Restore the checksum-proven Factory Instagram URL or formally approve the later brand-account URL through the project's baseline process. Current brand URL and approved checksum are both preserved.

These two decisions prevent a fully green baseline. All other original Laravel failures are fixed; final suite remains 518/519 passing with zero errors. No real Snippe payment was attempted.

Final evidence includes `storage/logs/remediation-full-tests.log`, `remediation-full-tests.xml`, `remediation-full-types.log`, `remediation-full-pint.log`, `remediation-build-final.log`, `remediation-npm-audit.json`, `remediation-composer-audit.json`, `remediation-harness-check.log`, `remediation-fidelity-capture-fix.log`, and the final run directory above.
