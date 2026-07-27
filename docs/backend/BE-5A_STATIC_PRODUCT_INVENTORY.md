# BE-5A Static Product Inventory

Date: 2026-07-26
Status: BE-5A.0 discovery complete

## 1. Scope and method

Exactly five explicit Laravel product routes, their Blade views, source HTML pages 9-13, imported JavaScript, Phase 2 fidelity reports, screenshots, and Factory v1 Media manifest were inspected. Repository text is authoritative; image appearance and filenames were not treated as product facts. Line references below use the current Blade files.

## 2. Evidence rules

Facts are marked explicit when visibly rendered, and derived only where a labelled selector unambiguously establishes an option. Disabled selector states do not establish inventory. Prices, availability, purchase controls, review counts, shipping, returns, and pre-order claims are outside catalogue truth. Independent selectors do not establish a Cartesian variant matrix.

## 3. Cross-product summary

| Product | Route | Slug | Options evidenced | Variant classification | Media count | SKU | Barcode | Proposed status |
|---|---|---|---:|---|---:|---|---|---|
| The Taylor Oxford Shirt | `/products/the-taylor-oxford-shirt` | `the-taylor-oxford-shirt` | 2 | B | 4 | WT-SH-001 | Not evidenced | draft |
| Mercerized Cotton Polo | `/products/mercerized-cotton-polo` | `mercerized-cotton-polo` | 2 | B | 4 | WT-KN-001 | Not evidenced | draft |
| The Dar es Salaam Linen Suit | `/products/the-dar-es-salaam-linen-suit` | `the-dar-es-salaam-linen-suit` | 1 | B | 5 | WT-SU-001 | Not evidenced | draft |
| Slim Tapered Chinos | `/products/slim-tapered-chinos` | `slim-tapered-chinos` | 2 | B | 4 | WT-TR-001 | Not evidenced | draft |
| The Executive Overcoat | `/products/the-executive-overcoat` | `the-executive-overcoat` | 1 | B | 4 | WT-JK-001 | Not evidenced | draft |

All proposed stable keys equal the established slug. Product type is `apparel` with high confidence.

## 4. The Taylor Oxford Shirt

Route name `products.taylor-oxford-shirt`; view `frontend.products.taylor-oxford-shirt`; source `public/website/html/page_9.html`. No alias or server redirect exists; a page-local compatibility script temporarily presents the imported singular route and restores the plural URL (`taylor-oxford-shirt.blade.php:3-12`). Title and SKU are explicit at lines 202-206. Lead copy at 214-215 is the preferred short description. Long copy at 368-371 describes the collection cornerstone, hand finishing in Dar es Salaam, camp collar, oversized boxy cut, crossover detail, and cross-season use. Explicit typed facts at 375-384: 100% Italian textured cotton; breathable, structured, pre-washed; boxy and relaxed fit; model 6'1 wearing M. These belong in materials, fit, rich description, and ordered features. Colour Ivory/Noir and Size XS/S/M/L/XL/XXL/3XL are explicit selectors at 219-264, safely deriving `colour` then `size`. No exact combination, default combination, or media-to-value mapping exists. Classification B; no factory variants.

## 5. Mercerized Cotton Polo

Route name `products.mercerized-cotton-polo`; view/source page 10. Title/SKU are explicit at 196-200; lead copy at 208-209 is canonical short copy. Long copy at 362-365 explicitly describes oversized jacquard weave, portrait-inspired tonal-earth print, mercerization for sheen/durability, polo collar, and relaxed hem. Colour Camel/Sand and Size XS/S/M/L/XL/XXL/3XL are explicit; XS is visibly disabled (`238-240`) only as a selector state. Classification B; no combinations/default evidenced and no variants recommended.

## 6. The Dar es Salaam Linen Suit

Route name `products.dar-es-salaam-linen-suit`; view/source page 11. Title/SKU at 202-206; short copy at 214-215. Long copy at 370-376 explicitly states a relaxed double-breasted wrap silhouette, self-tie belt, Belgian linen blend, pre-washed softness, and limited seasonal production. Limited-production and availability implications are not catalogue readiness. Size S/M/L/XL/XXL is the only option; S and XXL are visibly disabled, with inventory meaning deferred. No colour option, combinations, default, or variant media mapping exists. Classification B; no variants recommended.

## 7. Slim Tapered Chinos

Route name `products.slim-tapered-chinos`; view/source page 12. Title/SKU at 196-200; canonical short copy at 208-209 explicitly says fluid wide-leg trousers and satin-finish fabric, which conflicts with the display title's `Slim Tapered` wording and requires editorial confirmation. Colour Sage/Cream and Size XS/S/M/L/XL/XXL/3XL are explicit. No disabled initial sizes, exact combinations, default, or media mapping exists. Classification B; no variants recommended.

## 8. The Executive Overcoat

Route name `products.executive-overcoat`; view/source page 13. Imported source alias is `/product/executive-overcoat`; Laravel retains `/products/the-executive-overcoat`. Title/SKU at 199-203; short copy at 214-215. Long copy at 348-351 explicitly describes an architectural collarless silhouette, cognac contrast suede shoulder patches, generous volume, and belted waist. Size S/M/L/XL/XXL is explicit. PRE-ORDER, shipping date, delivery, return, secure-payment, and reservation copy are commerce/operations facts and intentionally deferred. Classification B; no combinations/default evidenced and no variants recommended.

## 9. Cross-product option analysis

| Product | Option label | Proposed key | Values | Evidence level | Variant implication |
|---|---|---|---|---|---|
| Oxford Shirt | Colour | colour | Ivory, Noir | Derived from explicit selector structure | definitions/values only |
| Oxford Shirt | Size | size | XS, S, M, L, XL, XXL, 3XL | Derived from explicit selector structure | definitions/values only |
| Polo | Colour | colour | Camel, Sand | Derived from explicit selector structure | definitions/values only |
| Polo | Size | size | XS, S, M, L, XL, XXL, 3XL | Derived; XS visibly disabled | definitions/values only |
| Linen Suit | Size | size | S, M, L, XL, XXL | Derived; S/XXL visibly disabled | definitions/values only |
| Chinos | Colour | colour | Sage, Cream | Derived from explicit selector structure | definitions/values only |
| Chinos | Size | size | XS, S, M, L, XL, XXL, 3XL | Derived from explicit selector structure | definitions/values only |
| Overcoat | Size | size | S, M, L, XL, XXL | Derived from explicit selector structure | definitions/values only |

Selectors are presentation-only under the Phase 2 reports. No selector has an evidenced backend action. Quantity is not a Product option.

## 10. Cross-product variant evidence

| Product | Classification | Explicit combinations | Default evidenced | Factory variants recommended | Reason |
|---|---|---|---|---|---|
| Oxford Shirt | B | none | no | none | independent selectors only |
| Polo | B | none | no | none | independent selectors only |
| Linen Suit | B | none | no | none | size values do not identify stable variants |
| Chinos | B | none | no | none | independent selectors only |
| Overcoat | B | none | no | none | size values do not identify stable variants |

Visible Product-level SKUs do not identify combinations. Barcode is not evidenced for any product.

## 11. Cross-product Media-role map

All assets are image resources in Factory v1 and map one-to-one to existing Cloudinary logical keys. First gallery asset is `primary`; remaining assets are ordered `gallery`. Repeated main/first-thumbnail use references one asset. Ownership is product-revision; no variant role is supported.

| Product | Logical Media keys in display order | Proposed roles | Alt status | Confidence |
|---|---|---|---|---|
| Oxford Shirt | `storefront-0368482bc-image`, `storefront-6d46d522f-image`, `storefront-2946546dd-image`, `storefront-ee86da3ae-image` | primary, gallery x3 | explicit contextual title/number; improve numbered alt editorially | high |
| Polo | `storefront-db23eed31-image`, `storefront-9c691e236-image`, `storefront-b079734c1-image`, `storefront-b9c0b1bdf-image` | primary, gallery x3 | explicit contextual title/number | high |
| Linen Suit | `storefront-da608a583-image`, `storefront-9c30daca8-image`, `storefront-b8589561c-image`, `storefront-84c5dcdc1-image`, `storefront-6e114010e-image` | primary, gallery x4 | explicit contextual title/number | high |
| Chinos | `storefront-8d7122778-image`, `storefront-c37b38a71-image`, `storefront-601f1442e-image`, `storefront-ebe8fd1d1-image` | primary, gallery x3 | explicit contextual title/number | high |
| Overcoat | `storefront-81f56a965-image`, `storefront-98e3f82c0-image`, `storefront-8a8dd5e57-image`, `storefront-93216c40a-image` | primary, gallery x3 | explicit contextual title/number | high |

Source evidence is each view's lines 175-191 and `database/factory/william-taylor-v1/media.php`. Factory default alt (`William Taylor storefront image`) is generic, so contextual storefront alt should be retained and editorially improved. None is marked decorative in product context. No product video exists.

## 12. Missing and ambiguous information

All products: missing barcode, combination matrix, stable variant identifiers, default combination, explicit variant/media mapping, complete care instructions, and business-approved canonical structured features. Disabled states are ambiguous and inventory meaning is deferred. Prices/currency/tax, availability, scarcity, reviews, shipping, returns, reservation/pre-order, wishlist/cart/payment are outside BE-5A catalogue truth. Chinos have conflicting title versus wide-leg description. Linen blend composition is incomplete. Overcoat base fabric/care is missing. Polo exact composition percentage/care is missing. Oxford exposes the strongest material/fit evidence but no variant matrix.

## 13. Catalogue-readiness matrix

| Product | Content valid | Primary Media | Options valid | Variant evidenced | Default evidenced | Ready without invention | Proposed status |
|---|---:|---:|---:|---:|---:|---:|---|
| Oxford Shirt | yes | yes | yes | no | no | no | draft |
| Polo | yes | yes | yes | no | no | no | draft |
| Linen Suit | yes | yes | yes | no | no | no | no | draft |
| Chinos | editorial conflict | yes | yes | no | no | no | draft |
| Overcoat | yes | yes | yes | no | no | no | draft |

All fail the authorized at-least-one-valid-variant and exactly-one-default requirements. Resolution requires a merchandising decision, not pricing, inventory, or publication work.

## 14. Factory-v3 recommendation

Create all five Products and one immutable draft revision each. Create only the exact option definitions/values in section 9. Create no variants, assign no default, and assign the exact product-revision Media in section 11. Seed each as `draft`. Preserve evidenced SKU as Product-page evidence for later variant allocation; because the authorized schema places SKU on Variant, do not store it until the business confirms which combination or product-level treatment is correct. Leave barcode null. Do not seed prices, inventory, availability, collections, SEO, or commerce facts.

## 15. Proposed migration inputs

Evidence requires at most two options, specifically `colour` and `size`, ordered values, and archived/disabled-state separation from inventory. Draft Products without variants must be supported. Nullable SKU and barcode are required at Variant level because no variant-level identifier is evidenced. Product-revision Media roles required are `primary` and ordered `gallery`; variant Media has no present evidence. Revision inputs require title, short description, restricted long description, materials, fit, care (nullable), and ordered features. Readiness must report missing variants/default and unresolved content conflicts. MySQL uniqueness must cover stable key, slug, populated SKU/barcode, option key per Product, value key per option, and combination fingerprint per Product, although combination rows are not factory-supported by this evidence.

## 16. Blocking questions

| Product | Missing decision | Why it matters | Safe default |
|---|---|---|---|
| All | Which exact combinations are valid and which is default? | Variant creation/readiness | create no variants; remain draft |
| All | Are visible SKUs product-level or tied to a combination? | SKU ownership/uniqueness | do not assign to variants |
| Oxford/Polo/Chinos | Do colours map to specific gallery assets? | variant media ownership | keep Media product-level |
| Polo/Suit | What do disabled sizes mean? | option archival versus inventory | record visual state only; do not archive/infer stock |
| Chinos | Is the product slim-tapered or fluid wide-leg? | canonical revision truth | retain source wording with conflict flag; draft |
| Suit | Exact Belgian linen blend composition? | typed materials | retain `Belgian linen blend`; do not invent percentages |
| Overcoat | Base composition and care? | typed materials/care | leave fields null |
| All | Are numbered gallery alts approved? | accessibility quality | preserve current contextual alt pending editorial review |

## 17. Recommendation on whether catalogue migrations may begin

Migrations may begin only if they support draft Products with zero variants and do not interpret option values as combinations. Factory v3 may later create five draft identities/revisions/options/product-level Media, but no variants or ready status until merchandising answers the combination/default questions. This checkpoint implemented no schema, model, permission, admin interface, or factory.