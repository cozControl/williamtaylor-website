# Full audit: 2026-09-15

## Decision

**Audit failed. Production readiness is not established.** The user explicitly overrode the exact-token requirement and authorized this run. This report records results, not remediation or baseline approval.

## Results

| Check | Result |
| --- | --- |
| Production Vite build | Passed |
| Full-project Pint | Passed |
| Fidelity harness self-check | Passed: 65/65 |
| Full Laravel suite | 517 tests: 471 passed, 36 failures, 10 errors; 6,275 assertions; 836.7 seconds |
| Full Larastan | Failed: two Campaign presenter return-type errors |
| Protected Factory PHP check / composer ci:check | Failed before the chained lint/type/test stages; those stages were run independently |
| npm advisory audit | 25 affected packages: 2 high, 23 moderate |
| Composer advisory audit | 13 advisories across three packages: 9 high, 4 medium |
| Full browser/fidelity command | Failed at security stage on two attempts; preview, visual and verification stages not reached |

## Highest-priority findings

1. **Admin access screens throw exceptions.** Role/user permission views index missing `products.manage` / `inventory.manage` definitions. Two route tests return HTTP 500; five further access-management tests error. These are runtime failures, not merely stale count assertions.
2. **Signed content preview returns HTTP 500.** `SiteContentPublishingTest::test_preview_is_signed_type_authorized_private_and_resource_bound` reports undefined `$homepageVisibility` in the homepage view.
3. **Dependency advisories require remediation and applicability review.** Installed Guzzle 7.15.1, CommonMark 2.8.3, Livewire 4.3.3, Tiptap core 3.28.0, and nanoid 3.3.16 are among reported affected dependencies. Advisory presence alone is not proof of application exploitability.
4. **Protected-source integrity fails.** `database/factory/william-taylor-v1/site-profile.php` is 1,587 bytes versus baseline 1,582. Current bytes equal the committed version; that committed version also fails the approved SHA-256. LF normalization does not resolve it. Neither protected source nor baseline was edited during this audit.
5. **Static analysis fails.** `LimitedEditionCampaignPresenter::all()` line 23 and `PreOrderCampaignPresenter::all()` line 26 declare list results but Larastan infers an integer-keyed array. Both files match their committed versions.
6. **Broader regression expectations need review.** Failures cover shared-region counts, historical no-commerce assertions, old permission/navigation/section counts, query budgets, missing database tables in public-page test setups, product readiness, and merchandising eligibility. Do not remove assertions merely to obtain a green run.

## Payment scope

No failures or errors in the full-run result belong to the Payments, Checkout, Inventory, or Cart test directories. This supports the earlier focused payment checks but does not override the overall audit failures. No real Snippe API payment, credential acceptance, or charge was attempted.

## Browser execution and limitations

- Initial run: `be6a1-20260915120338-7d578ee7`. Security exceeded its 600,000 ms deadline. Windows WMIC/CIM inspection was unavailable/denied, so process ownership proof failed. Two leftover disposable PHP servers were identified by parent PID and exact command paths and stopped.
- Elevated retry: `be6a1-20260915121501-7d578ee7`. Security again exceeded its 600,000 ms deadline. Process proof and descendant cleanup passed; the stage did not emit a result or completion marker.
- The retry also failed immutable working-tree verification. The only checksum-covered file found modified during the run was `.phpunit.result.cache`, written by the concurrently finishing test suite. A future fidelity run must be isolated from test-cache writes. This checksum interference does not explain away the independent security-stage timeout.
- The canonical command stops at security failure. Preview, screenshot matrix, visual comparison and final verification were not executed. No baseline candidate was approved and no visual acceptance is claimed.
- Tests used configured SQLite `:memory:`; browser harnesses used disposable SQLite. No MySQL fresh, rollback, re-migration or live commerce-data reset was performed.

## Dependency evidence

- **guzzlehttp/guzzle (high)**: [Guzzle: Noncanonical host can bypass host-based checks](https://github.com/advisories/GHSA-v5mv-p594-2x33).
- **guzzlehttp/guzzle (medium)**: [Guzzle: Noncanonical cookie domain keeps subdomain scope](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r).
- **league/commonmark (high)**: [league/commonmark: Denial of service via distinctly-named attributes in the Attributes extension](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh).
- **league/commonmark (high)**: [league/commonmark: Denial of service in the SmartPunct and Attributes extensions](https://github.com/advisories/GHSA-jjv6-8j6v-6j52).
- **league/commonmark (high)**: [league/commonmark XSS: `on*` event-handler filter in `AttributesExtension` bypassed with a U+000C form feed](https://github.com/advisories/GHSA-f8fg-pg57-v4j8).
- **league/commonmark (high)**: [league/commonmark: Denial of service via crafted code fences, reference links, and emphasis delimiters](https://github.com/advisories/GHSA-j8pm-gj4c-rq4x).
- **league/commonmark (medium)**: [league/commonmark: Denial of service via deeply nested XML output](https://github.com/advisories/GHSA-mj63-m3rc-8ppr).
- **league/commonmark (high)**: [league/commonmark: Denial of service via colliding heading slugs](https://github.com/advisories/GHSA-mh25-x5hq-wrqp).
- **league/commonmark (high)**: [league/commonmark:  Denial of service via duplicate footnote definitions](https://github.com/advisories/GHSA-jfm3-95jq-q3rf).
- **league/commonmark (high)**: [league/commonmark: Denial of service via adjacent inline attribute blocks](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph).
- **league/commonmark (high)**: [league/commonmark: Quadratic-time denial of service when parsing crafted Markdown](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv).
- **league/commonmark (medium)**: [league/commonmark: AttributesExtension href/src unsafe-link filter bypass via embedded control bytes](https://github.com/advisories/GHSA-29pj-957v-52mc).
- **livewire/livewire (medium)**: [Livewire DOM-based cross-site scripting during client-side state handling](https://github.com/advisories/GHSA-g3hc-697w-wm82).

High-severity npm package findings:

- **@tiptap/core**: [Tiptap: Quadratic ReDoS in block and inline Markdown attribute parsing](https://github.com/advisories/GHSA-j95f-988m-3j2f). npm reports an available fix.
- **nanoid**: [nanoid: custom generators can loop indefinitely when size is zero](https://github.com/advisories/GHSA-2v37-7h3g-55p8). npm reports an available fix.

## Full-suite failure inventory

The raw test report is stored locally in `storage/logs/full-audit-tests.log`. The compact summary is `storage/logs/full-audit-test-summary.json`. Each failing/error test is listed below without response bodies or stack dumps.

- **failures**: `Tests\Feature\AccountFrontendPagesTest::test_login_preserves_existing_fortify_contract_without_storefront_regions`.
- **failures**: `Tests\Feature\AccountFrontendPagesTest::test_wishlist_reuses_exact_shared_regions_once_and_remains_empty_static_presentation`.
- **failures**: `Tests\Feature\Admin\AccessManagementTest::test_users_and_roles_routes_enforce_full_view_boundaries`.
- **failures**: `Tests\Feature\Admin\AccessManagementTest::test_role_catalogue_is_registered_read_only_and_reports_drift`.
- **failures**: `Tests\Feature\Admin\AdminNavigationRegistryTest::test_registry_has_stable_order_registered_routes_and_registered_permissions`.
- **failures**: `Tests\Feature\Admin\AdminNavigationRegistryTest::test_visibility_is_derived_from_permissions_not_role_names`.
- **failures**: `Tests\Feature\CatalogueFrontendPagesTest::test_collections_and_shop_routes_are_named_and_public_for_guests`.
- **failures**: `Tests\Feature\CatalogueFrontendPagesTest::test_catalogue_pages_preserve_assets_and_single_shared_regions`.
- **failures**: `Tests\Feature\Catalogue\ProductMediaUsageTest::test_readiness_requires_usable_primary_and_tracks_archive_restore_without_losing_usage`.
- **failures**: `Tests\Feature\Demo\DemoWorkspaceTest::test_dashboard_uses_production_language_and_links_to_existing_workspaces`.
- **failures**: `Tests\Feature\Demo\DemoWorkspaceTest::test_site_settings_renders_distinct_controls_picker_and_state_aware_workflow`.
- **failures**: `Tests\Feature\Factory\FactoryBaselineTest::test_inventory_manager_is_a_reserved_admin_shell_role_only`.
- **failures**: `Tests\Feature\Factory\FactoryBaselineTest::test_factory_install_creates_exact_identities_roles_and_published_content_idempotently`.
- **failures**: `Tests\Feature\Homepage\HomepageExploreCollectionsManagementTest::test_saved_selections_do_not_activate_unmanaged_mode_and_editor_explains_it`.
- **failures**: `Tests\Feature\Homepage\HomepageHeroManagementTest::test_homepage_workspace_lists_exactly_six_sections_in_storefront_order`.
- **failures**: `Tests\Feature\Homepage\HomepageHeroManagementTest::test_public_homepage_uses_exact_static_fallback_without_record`.
- **failures**: `Tests\Feature\Identity\RbacFoundationTest::test_registry_contains_the_approved_foundation_media_and_page_permissions`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2d_product_detail_route_is_public_named_and_renders_source_content`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_product_detail_reuses_shared_regions_once_and_preserves_static_controls`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_product_detail_registers_canonical_dynamic_route_without_commerce_mutations`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2e_mercerized_cotton_polo_route_is_public_named_and_renders_source_content`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2e_polo_reuses_shared_regions_and_keeps_commerce_controls_presentational`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2f_dar_es_salaam_linen_suit_route_is_public_named_and_renders_source_content`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2f_linen_suit_reuses_shared_regions_and_keeps_commerce_controls_presentational`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2g_slim_tapered_chinos_route_is_public_named_and_renders_source_content`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2g_chinos_reuses_shared_regions_and_keeps_commerce_controls_presentational`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2h_executive_overcoat_route_is_public_named_and_renders_source_content`.
- **failures**: `Tests\Feature\ProductDetailFrontendPageTest::test_fe_2h_overcoat_reuses_shared_regions_and_keeps_preorder_controls_presentational`.
- **failures**: `Tests\Feature\PublicPageProjection\AboutPageProjectionTest::test_route_is_exact_and_disabled_projection_renders_complete_static_page_without_page_queries`.
- **failures**: `Tests\Feature\PublicPageProjection\AboutPageProjectionTest::test_enabled_projection_stays_within_uncached_and_cached_query_budgets`.
- **failures**: `Tests\Feature\PublicProjection\PublicSiteContentProjectionTest::test_enabled_homepage_and_product_route_stay_within_five_projection_queries`.
- **failures**: `Tests\Feature\PublicTemplatePagesTest::test_supplied_public_pages_render_from_named_laravel_routes`.
- **failures**: `Tests\Feature\SharedFrontendRegionsTest::test_shared_frontend_partials_exist_and_render_once_on_the_guest_homepage`.
- **failures**: `Tests\Feature\SiteContent\SiteContentPublishingTest::test_preview_is_signed_type_authorized_private_and_resource_bound`.
- **failures**: `Tests\Feature\SpecialCommerceFrontendPagesTest::test_special_commerce_pages_preserve_assets_and_single_regions`.
- **failures**: `Tests\Feature\SpecialCommerceFrontendPagesTest::test_each_page_preserves_its_supplied_form_count_and_controls`.
- **error_details**: `Tests\Feature\Admin\AccessManagementTest::test_authorized_livewire_assignment_and_revocation_use_existing_audited_actions`.
- **error_details**: `Tests\Feature\Admin\AccessManagementTest::test_final_admin_and_self_lockout_safeguards_preserve_state`.
- **error_details**: `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_unchanged_confirmed_preview_applies_once`.
- **error_details**: `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_direct_permission_change_invalidates_confirmation_without_mutation_or_audit`.
- **error_details**: `Tests\Feature\Admin\EffectiveAccessPreviewIntegrityTest::test_role_and_bundle_changes_each_invalidate_confirmation`.
- **error_details**: `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_relation_add_rejects_self_and_duplicates_and_never_creates_reciprocal`.
- **error_details**: `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_relation_reorder_archive_restore_and_stale_rejection_are_atomic`.
- **error_details**: `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_placement_assignment_reorder_archive_and_restore_preserve_products`.
- **error_details**: `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_product_archive_preserves_configuration_and_pure_eligibility_fails`.
- **error_details**: `Tests\Feature\Merchandising\MerchandisingFoundationTest::test_ordered_domain_queries_and_eligibility_have_bounded_query_counts`.

## Evidence paths

- `storage/logs/full-audit-ci.log`
- `storage/logs/full-audit-build.log`
- `storage/logs/full-audit-pint.log`
- `storage/logs/full-audit-types.log`
- `storage/logs/full-audit-harness.log`
- `storage/logs/full-audit-npm.json`
- `storage/logs/full-audit-composer.json`
- `storage/logs/full-audit-fidelity.log`
- `storage/logs/full-audit-fidelity-retry.log`
- `storage/app/evidence/be6a1-browser/be6a1-20260915120338-7d578ee7/`
- `storage/app/evidence/be6a1-browser/be6a1-20260915121501-7d578ee7/`

## Changes made by this audit

Generated the frontend build and audit evidence/report. No application code, dependency version, protected asset, or approved baseline was changed to hide or resolve failures. Existing payment implementation and other working changes remain in place.
