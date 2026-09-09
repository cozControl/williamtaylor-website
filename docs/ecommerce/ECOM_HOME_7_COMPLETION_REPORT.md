# ECOM-HOME-7 — The Summer Edit

1. **Status:** Implementation and bounded verification complete. Physical UI/visual acceptance remains paused because no browser surface was available. Local settings are restored to unmanaged mode with no destination selected.

2. **Protected composition:** Immediately after Explore the Collection, inside a burgundy section shared structurally with the subsequent delivery strip. Summer Edit itself is a centered, gold-bordered banner with top/bottom gradient rules, radial glow, brand medallion, animated repeating William Taylor icon texture, uppercase eyebrow, responsive `clamp(1.75rem, 4vw, 2.75rem)` heading, gold-highlighted promotional phrase and `btn-gold` CTA. The original CTA is `products.index?filter=sale`. There is no editorial photograph or video. The outer texture uses the supplied brand icon at 280px, not Collection card Media.

3. **Admin reference:** Inspected `/admin/homepage` source, the accepted Hot Sale editor (`resources/views/admin/homepage/hot-sale.blade.php`), Explore Collections editor and Collection form. The repository AGENTS.md currently contains the audit gate; the phase's explicit UI requirements were followed.

4. **Ownership:** Collection-referenced Homepage editorial feature. The static sale-filter destination has no new managed sale engine behind it; editors instead choose the actual Collection containing their seasonal selection. No destination was automatically chosen. Campaign types represent Pre-Order/Limited Edition rather than this promotional placement, so no Campaign lifecycle is reused or fabricated.

5. **Models:** No SummerEdit model, generic blocks table, page builder or duplicate domain was created. Fields extend `homepage_heroes` through one forward migration, successfully applied locally.

6. **Homepage fields:** Managed flag, eyebrow, heading, promotional opening, highlighted phrase, remaining promotional copy, CTA label and nullable `summer_edit_collection_id`. The copy is split to preserve the exact existing inline gold emphasis without editable HTML.

7. **Destination fields:** Collection retains its identity, slug, Products, revision, public eligibility and page. The Homepage stores only its ID; the presenter derives its current name and named route.

8. **Media:** No section-specific Media is present in the original composition, so no MediaUsage or feature-image picker was added. The existing decorative brand texture is retained.

9. **Alt:** Decorative brand icons keep empty alt and aria-hidden. Collection eligibility reuses CollectionCardPresenter and its existing Media/effective-alt rules; no duplicate alt input or filename-derived alternative text is introduced.

10. **CTA:** `route('collections.show', $collection->slug)` from the selected eligible Collection, with editorial CTA label. Arbitrary URLs and copied slugs are not stored.

11. **Pricing:** No discount calculations, Product mutations, coupons, Inventory logic or pricing engine. Promotional claims remain editor-owned wording.

12. **State:** Off retains the exact supplied banner. On displays managed copy only when the Collection is eligible. Missing/hidden/archived/unusable destinations produce an inert hidden managed marker and no misleading CTA. Admin reports Needs attention. No individual managed field falls back to static content.

13. **Workspace:** Added position 7 directly after Explore the Collection, with seasonal editorial summary, destination name when configured, status and Manage The Summer Edit action.

14. **Editor:** `/admin/homepage/summer-edit` GET/PUT, saved section status, managed checkbox, content panel, Collection destination and Back-left/Save-right actions. View homepage opens the public page. GET requires settings.view; PUT requires settings.manage, within existing authenticated verified Admin middleware.

15. **Shared UI:** `x-admin.layout`, `x-admin.flash`, `x-admin.field`, `x-admin.form-actions`, `x-admin.homepage-section-summary`, `admin-panel`, `admin-section-heading`, `admin-choice-row`, `admin-form-grid`, `admin-field-help`, `admin-secondary-button`, `admin-primary-button`. Standard Collection select with identity/slug; no new widget, stylesheet or interaction framework.

16. **Validation:** Required bounded plain-text fields; managed mode requires an eligible destination using the existing CollectionCardPresenter. Errors preserve input. Unavailable saved/failed-input IDs remain visible as an unavailable option. Save locks the Homepage, checks lock_version, increments it and records an audit event. No silent activation.

17. **Projection:** Homepage controller -> HomepageSummerEditPresenter -> existing banner-derived partial -> visible markup plus inert template. Presenter guards missing migration columns. It reads fresh database values without a new cache.

18. **Apache managed evidence:** A short local verification temporarily set the heading to `Summer Edit Apache verification`, enabled management and referenced the eligible New Arrivals Collection. `GET http://william.taylor/` returned that heading, `http://william.taylor/collections/new-arrivals`, two banner markers (visible + template), the inert projection and the new synchronizer call. A `finally` block restored all original Summer Edit fields; a second request verified the static fallback. Test content was not left published.

19. **Runtime:** The existing inline Homepage synchronizer now invokes `synchronizeSummerEdit`. It replaces only the banner wrapper around the original Summer Edit h2, never its outer shared section. A managed dataset guard makes repeated calls no-ops; the existing observer/load callbacks handle runtime replacement. The actually served inline function passed `node --check`. Browser mount/remount behavior remains unverified, so physical runtime acceptance is not claimed.

20. **Unmanaged:** Existing static markup and sale-filter CTA remain intact. The restored database has `summer_edit_managed=false` and `summer_edit_collection_id=null`.

21. **Responsive:** Original widths, padding, clamp title, max-width copy, alignment, brand texture, radial glow, borders and button classes are retained. No body overflow clipping or responsive redesign. Desktop/1024/768/430 visual QA remains pending.

22. **Delivery:** Complimentary Delivery markup, CTA and styling were not modified or incorporated into the editor. Managed invalid-state omission affects the Summer Edit child only.

23. **Tests:** `HomepageSummerEditManagementTest`: **2 passed, 46 assertions**. Covers authorization, default/prefill, validation/old input, valid save, persisted ID/flag, managed response, ordering, canonical Collection route, existing campaign projection marker, unavailable destination, Admin attention and unmanaged fallback. No infrastructure or full Homepage suite was run.

24. **Static analysis:** Scoped Larastan/PHPStan on the new presenter/controller and changed Homepage model/public controller: **zero errors**.

25. **Checks:** Changed-file Pint passed; PHP syntax passed; Blade compilation passed; served Summer Edit JS syntax passed; scoped git diff --check passed with an existing CRLF normalization warning for welcome.blade.php. No build required.

26. **HTTP:** Actual Apache Homepage requests succeeded for temporary managed and restored unmanaged state. The canonical Collection route returned 200 in focused tests. No full HTTP/browser matrix run.

27. **Browser:** One availability attempt returned `[]`; no retries. Initial Admin styling uses the accepted components but visual acceptance is not claimed.

28. **Previous sections:** No redesign. Only the shared synchronizer registration and new Homepage data entry were extended; existing sections retain their behavior.

29. **Women's Handbags:** Not started.

30. **Commerce and other phases:** Inventory, Cart, Checkout, payments, pricing engine, full Shop migration and later Homepage sections were not started. No full audit, full test suite, dependency audit, full Larastan or complete build was run.

ECOM-HOME-7 IMPLEMENTATION READY FOR GENERAL INSPECTION
