# ECOM-VISIBLE-1C Completion Report

## Status

**ECOM-VISIBLE-1C IMPLEMENTATION READY FOR OWNER VERIFICATION**

Physical acceptance is not claimed. No later ecommerce phase has started.

## Collection edit workflow

- Canonical edit route: `GET /admin/collections/{collection}/edit`.
- The route requires authentication, verified access, Admin access and
  `products.manage`; archived Collections return 404 for edit and update.
- The Collections index now makes the Collection name clickable and shows an
  explicit **Edit** button for each active Collection.
- Edit prefills name, slug, description, visibility, selected Collection Media,
  usage-specific alt text, selected Products and each saved display order.
- The reusable Media picker shows the current selection, labels the action
  **Change media**, keeps Cancel non-mutating and applies selection/removal only
  when the form is saved. Removing a usage does not remove its Media Asset.
- Product membership synchronization retains selected Products, archives
  removed memberships, adds new memberships and now preserves the actual unique
  numeric display orders entered by the user.
- Visibility changes use an audited domain action. Existing content, slug,
  Media and membership actions retain their existing audit records.

## Feedback and validation

- Create redirects to `/admin/collections` with
  **Collection created successfully.**
- Update redirects back to the canonical edit route with
  **Collection updated successfully.**
- `<x-admin.flash />` provides the shared restrained success/error presentation.
- Invalid submissions preserve old input and show
  **Please correct the highlighted fields.** once, while individual field
  messages remain beside their controls.
- Collection image accessibility validation remains fail-closed with
  **Alt text is required for this Collection image.** rather than an exception
  page.
- Visible Collections expose **View storefront**; hidden Collections do not
  offer a link to a guaranteed public 404.

## Files changed for 1C

- `app/Domain/Catalogue/Actions/ReorderCollectionProducts.php`
- `app/Domain/Catalogue/Actions/UpdateCollectionVisibility.php`
- `app/Http/Controllers/Admin/CollectionController.php`
- `routes/admin.php`
- `resources/views/admin/collections/form.blade.php`
- `resources/views/admin/collections/index.blade.php`
- `resources/views/components/admin/flash.blade.php`
- `resources/views/components/admin/form-styles.blade.php`
- `resources/views/components/admin/layout.blade.php`
- `resources/views/components/admin/media-picker.blade.php`
- `tests/Feature/Catalogue/CollectionAdminWorkflowTest.php`
- `tests/Feature/Catalogue/EcomCatalogueCoreTest.php`

## Verification

- Focused regression set: 45 tests passed, 394 assertions.
- Scoped Larastan: zero errors.
- Changed PHP files: Pint passed and PHP syntax passed.
- Blade compilation: passed.
- `git diff --check`: passed.
- Route listing confirms index, create, store, edit and update Collection routes.
- Served unauthenticated checks for `/admin/collections` and
  `/admin/collections/create` redirect to `/login`.
- Chrome-backed authenticated browser control was requested, but no Chrome
  browser was exposed to this session. No screenshots, authenticated browser
  actions or physical persistence claims are made.
- The full audit was not run because `AUTHORIZE_BE6A1_FULL_AUDIT` was not
  supplied.

## Owner verification gates

1. **Create feedback:** create `Collection Edit Test`; confirm save, exact created
   success feedback and visibility on the Collections index.
2. **Edit discoverability:** open `/admin/collections`; confirm the Collection and
   obvious **Edit** action, then open it.
3. **Prefilled edit state:** confirm name, slug, description, selected Media, alt
   text, selected Product, display order and visibility are retained.
4. **Update:** change description, Media or alt text, and Product display order;
   save, confirm exact updated success feedback, refresh and confirm persistence.
5. **Validation:** remove the required name or cause another known validation
   error; confirm no 500, the concise summary, a field error, and retained
   Media/Product form state.

Production Orders management remains a future required commerce capability and
is not represented by the hidden demo-only workflow.
