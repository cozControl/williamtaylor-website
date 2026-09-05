# ECOM-VISIBLE-1B Completion Report

## Status

**ECOM-VISIBLE-1B IMPLEMENTATION READY FOR OWNER VERIFICATION**

Physical acceptance is not claimed. No next implementation phase was started.

## Collection save failure and validation fix

The Collection form validated only the selected Media Asset ID. It did not
collect or validate a usage-specific alt-text override and called
`AssignCollectionMedia` with `null`. An Asset without default alt text therefore
reached `CollectionMediaAccessibility`, correctly failed its domain invariant,
and escaped through the web boundary as an `InvalidArgumentException` and HTTP
500.

Collection validation now resolves the selected Asset through the canonical
eligible-image query before any Collection mutation. Effective alt text is:

```text
trimmed Collection usage override OR trimmed Media Asset default alt text
```

It must be non-empty plain text. Otherwise the form returns the inline error
`Alt text is required for this Collection image.` with old input, selected
Media, selected Products, and ordering retained. The domain invariant remains
in place. Known media assignment/update invariant failures are converted to the
same bounded validation error, and Collection create/update mutations run in a
transaction.

## Reusable Media picker

`x-admin.media-picker` is the canonical Blade/Alpine picker used by Category,
Collection, Product primary/gallery, and Product colour imagery.

- Initial editor HTML contains only currently selected Media.
- Results are requested only when the native modal opens.
- `ReadyImagePickerQuery` centralizes eligibility: confirmed, READY image, not
  archived.
- The permission-protected JSON endpoint returns 24 results per page.
- Server search covers internal title, original filename, and default alt text.
- Previous/Next provide bounded server pagination; Refresh repeats the query.
- Single mode is used for Category, Collection, and Product primary selection.
- Multi mode is reused for Product gallery and colour imagery.
- Cancel restores the committed form selection; Use selected updates only the
  parent form and does not persist until Save.
- The modal indicates whether default alt text exists without exposing provider
  identifiers.
- Upload new media opens the existing Media Library in a new tab. Refresh makes
  newly READY assets discoverable.
- Empty results provide an explicit Media Library upload action.

Collection owns its contextual alt field. Product gallery cards retain ordering
and contextual alt overrides outside the modal. Removing a selection removes
only its usage when the parent form is saved; it does not delete the Media Asset.

The served marker is now:

```html
data-admin-ui-revision="ecom-visible-1b"
```

## Storefront Media reality check

This was a source and served-route check, not a broad fidelity audit.

- The representative Oxford Product page exposes one main image plus an ordered
  thumbnail gallery. Its static fallback contains four images; canonical data
  uses the assigned primary and gallery usages.
- Colour selection already replaces the main/gallery imagery when canonical
  `colour_primary` / `colour_gallery` usages exist.
- Collection pages use Collection imagery as a hero and Product primary imagery
  on cards. Category imagery is stored for category/range presentation.
- Current Product cards use one primary thumbnail with a scale-on-hover effect,
  not a distinct second hover asset.
- Roles needed now are Product `primary` and ordered `gallery`, colour
  `colour_primary` / `colour_gallery`, and Collection `card`/`hero`. A distinct
  Product-card hover role can safely wait because it is not currently rendered.

## Evidence

### Automated

- Focused PHPUnit: 45 passed, 351 assertions.
- Scoped Larastan: zero errors.
- Changed PHP files passed Pint.
- PHP syntax checks passed.
- Inline Media picker JavaScript passed `node --check`.
- Blade templates cached successfully.
- `git diff --check` passed, with one existing line-ending warning.

### Served-route

Apache-served unauthenticated requests to `/admin/media-picker`,
`/admin/collections/create`, and `/admin/product-categories/create` each returned
the expected 302 to Login. The Media picker route is registered and its
authenticated eligibility, permissions, pagination, and search behavior are
covered by focused HTTP tests.

### Browser

Browser verification unavailable. The Chrome connection exposed no controllable
browser in this session. No authenticated modal or visual-rendering claim is
made.

## Owner verification gates

1. **Collection modal:** open `/admin/collections/create`, choose Media, search,
   select, confirm, and verify the selected image appears in the form.
2. **Collection alt validation:** select an image with no default alt, leave the
   contextual alt blank and verify inline validation with no 500; then enter
   meaningful alt text and save.
3. **Persistence:** refresh the saved Collection and verify Media, alt text,
   Products, and Product order persist.
4. **Category modal:** create/edit a Category, choose Media through the same
   modal, save, and refresh.
5. **Product Media modal:** verify separate primary and gallery controls, modal
   selection, and that the full Media Library is absent from initial page HTML.

Another phase may begin only after the owner states:

> **ECOM-VISIBLE-1B PHYSICAL PASS**
