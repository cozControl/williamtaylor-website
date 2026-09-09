# ECOM-CAMPAIGNS-FRONTEND-DB-1

## Source and root cause

The homepage `welcome.blade.php` contained complete literal campaign sections behind the disabled `future_style_managed` and `limited_edition_managed` branches. The Executive Overcoat and Summer Linen Trousers in Future of Style came from that copied HTML, including static images, prices and dates. The database presenters were bypassed when the flags were off. This was not a campaign approval or cache failure.

The standalone `/pre-order` and `/limited-edition` routes already invoked their respective database presenters. They did not need replacement controllers or queries.

## Runtime correction

Both static homepage campaign branches have been removed. The existing database partials and their browser projection templates are now included regardless of the text-management flags. No campaign fixture is used when results are empty. Existing section headings and navigation remain, with zero cards.

The flags now select managed section text versus default section text. They no longer select static campaign cards. Both editors and the homepage overview explain the database behavior. This intentionally supersedes the whole-section flag semantics documented by ECOM-HOME-FUTURE-STYLE-1, as required by this task.

Pre-Order path: `/` -> `HomepageController` -> `HomepageFutureStylePresenter` -> saved Campaign IDs or `PreOrderCampaignPresenter::all(2)` -> `CampaignPublicProjection` -> existing Future Style/card Blade partials.

Limited Edition path: `/` -> `HomepageController` -> `HomepageLimitedEditionPresenter` -> saved Campaign IDs or `LimitedEditionCampaignPresenter::all(3)` -> `CampaignPublicProjection` -> existing Limited Edition/card Blade partials.

Any saved slot makes its section an explicit composition. IDs are fetched together and rendered in slot order, including when managed text is disabled. Missing or ineligible references are omitted without discovery replacement. When all slots are empty, eligible campaigns of that section's type are discovered by `starts_at`, then `id`. There is no cross-type fallback. Existing duplicate-save validation is unchanged.

## Canonical field mapping

| Card field | Source |
| --- | --- |
| Heading | Approved Campaign revision `headline`; previously the dynamic cards incorrectly displayed Product title |
| Description and CTA label | Approved revision `summary` and `cta_label` |
| Image and alt | Campaign card MediaUsage, ready MediaAsset, provider delivery URL, usage alt override / asset alt |
| Price and currency | ProductPresenter using Product base price and existing ProductPrice formatting |
| Countdown | Campaign `ends_at`, existing remaining-time calculation and data attribute |
| Ships date | Campaign `estimated_delivery_date` |
| Link | Named `products.show` route for the resolved Product |
| Pre-Order badge | Existing type-specific presenter |
| Edition statement | Approved, checksum-valid `edition_statement` claim |

Publication, scheduling, archive, claim, Product and Media readiness rules remain unchanged. Tests revise immutable Campaign content through the existing revision action and approval endpoint rather than editing approved revisions in place.

No literal campaign title, image, price or date remains in these homepage campaign sections. Shared brand icons, section copy and type labels remain static where appropriate. Other unrelated storefront sections still contain supplied content; they were outside scope.

## Queries and caching

Saved Campaign IDs are fetched in one query. Discovery reads batches of 20 with eager-loaded approved/current revisions, products, claims and card Media; projection stops once the homepage capacity is filled. Card mapping consumes these loaded relationships. The existing readiness and Product presenters still perform their own validation queries; this is not a claim of a query-free or fully constant-query pipeline, and those shared policies were not refactored.

These public presenters do not use the separate campaign configuration cache. Every request resolves current database data. No global cache clearing or new cached campaign query was introduced; existing per-campaign configuration invalidation remains unchanged.

## Live read-only result

After the change, the local homepage presenters returned:

- Pre-Order: `01m1vn141wprc88kv5h0m4v1yb` / The Executive Overcoat; `01m230m433ejd588vkfj7kth69` / Campaign ouo.
- Limited Edition: `01m1vn7sbxadem7fwqcnqs2qsp` / Summer Linen Trousers.

No live campaign data or approval state was mutated.

## Files changed

- `app/Domain/Campaign/Models/Campaign.php`
- `app/Domain/Campaign/Support/CampaignPublicProjection.php`
- `app/Domain/Campaign/Support/PreOrderCampaignPresenter.php`
- `app/Domain/Campaign/Support/LimitedEditionCampaignPresenter.php`
- `app/Domain/Homepage/Support/HomepageFutureStylePresenter.php`
- `app/Domain/Homepage/Support/HomepageLimitedEditionPresenter.php`
- `resources/views/welcome.blade.php`
- `resources/views/frontend/partials/pre-order-campaign-card.blade.php`
- `resources/views/frontend/partials/limited-edition-campaign-card.blade.php`
- `resources/views/admin/homepage/future-style.blade.php`
- `resources/views/admin/homepage/limited-edition.blade.php`
- `resources/views/admin/homepage/edit.blade.php`
- `tests/Feature/Homepage/HomepageFutureStyleManagementTest.php`
- `tests/Feature/Homepage/HomepageLimitedEditionManagementTest.php`
- This report.

## Validation

Focused Future Style, Limited Edition, Campaign Foundation and Campaign Authority Closeout files: **26 passed, 346 assertions**. Nearby Homepage Hero/overview regression: **8 passed, 97 assertions**. Coverage includes database discovery without managed configuration, approved headline updates, prices, Media URLs, end/delivery dates, CTA routes, type separation, exact slots, invalid-selection omission, and empty results. Changed PHP files passed Pint formatting. Blade compilation passed.

No supported browser surface was exposed. Physical Admin edit/approve/refresh and desktop/tablet/mobile visual verification remain pending. Existing card classes, split layouts, countdown behavior, CSS and supplied JavaScript bundles were preserved; pixel equivalence is not claimed without browser evidence.

No full suite, full audit, full Larastan, complete build, dependency audit or MySQL migration cycle was run.
