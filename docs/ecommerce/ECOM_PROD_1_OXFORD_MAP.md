# ECOM-PROD-1 Oxford frontend map

The existing Blade page remains the visual and interaction contract. Only the Oxford route is connected to catalogue data; the other four Product routes remain static.

| Frontend region | Current static source | Backend owner | Field/relation | Migrated this phase? |
| --- | --- | --- | --- | --- |
| Header, announcement, navigation | Shared/static Oxford Blade chrome | Public site chrome | Existing projection | No change |
| Breadcrumb Product name | `taylor-oxford-shirt.blade.php` | Product revision | `title` | Yes, with static fallback |
| Primary image | `/website/images/0368482bc_image.jpg` | Product Media | `primary` Media Usage | Yes, with static fallback |
| Gallery | Four imported images | Product Media | ordered `gallery` Media Usages | Yes, with static fallback |
| Badges | `NEW`, `BESTSELLER` | Compatibility | Static template | No; `BESTSELLER` is not in the canonical registry |
| Product title | Static heading | Product revision | `title` | Yes |
| SKU | `WT-SH-001` | Default/current Variant | `sku` | Yes |
| Price | `TZS 285,000` | Compatibility | Static template literal | No; Pricing is deferred |
| Short copy | Static paragraph | Product revision | `short_description` | Yes |
| Colour | Ivory, Noir | Product options | `colour` values | Yes |
| Size | XS, S, M, L, XL, XXL, 3XL | Product options | `size` values | Yes |
| Variant resolution | Presentational selectors | Product Variants | canonical option-value IDs | Yes |
| Main description | Static prose | Product revision | structured `description_document` / sanitized HTML | Yes |
| Fabric | Static prose | Product revision | `materials` | Yes |
| Fit | Static prose | Product revision | `fit` | Yes |
| Detail | Static prose | Product revision | typed `features` | Yes |
| Care | No Oxford value evidenced | Product revision | `care` | Supported; initialized blank |
| Related Products | Four static cards | Compatibility | Existing static routes/cards | No; incomplete Product records were not fabricated |
| Footer/mobile navigation | Existing shared/static content | Existing frontend | Existing Blade | No change |

## Canonical bridge

`OxfordProductPresenter` loads the Product revision, ordered ready Media Usage records, active options/values, Variants and default Variant in bounded queries. It emits canonical IDs to the existing selector JavaScript. Hidden, archived, missing, incomplete, or not-yet-migrated data falls back to the supplied static presentation.

## Bootstrap evidence

Run explicitly with an existing administrator:

```powershell
php artisan catalogue:bootstrap-oxford --user=administrator@example.com
```

The command creates the Product only when the slug is absent, records the supplied administrator as actor, creates the two evidenced options and 14 combinations, assigns deterministic SKU identities, and sets the Ivory/XS Variant as default. It does not attach or alter Media Assets and never overwrites an existing Product.
