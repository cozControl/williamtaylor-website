# William Taylor static-template baselines

Baseline screenshots belong in this directory and must be captured from the untouched files under `public/website`.

Naming convention:

`<page-key>__<width>x<height>.png`

Examples:

- `home__375x812.png`
- `collections__768x1024.png`
- `product-taylor-oxford-shirt__1440x900.png`

Required page keys:

- `home`
- `collections`
- `shop`
- `pre-order`
- `limited-edition`
- `gift-cards`
- `login`
- `wishlist`
- `product-taylor-oxford-shirt`
- `product-mercerized-cotton-polo`
- `product-dar-es-salaam-linen-suit`
- `product-slim-tapered-chinos`
- `product-executive-overcoat`

Do not overwrite an approved baseline when Laravel output changes. Capture Laravel output separately and compare it to the immutable baseline.

Status on 2026-07-23: capture pending because the supported in-app browser could not start under the current Windows sandbox.
