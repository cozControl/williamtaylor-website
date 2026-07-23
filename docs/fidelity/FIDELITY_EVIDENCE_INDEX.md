# Frontend fidelity evidence index

Generated: 2026-07-23. Browser: Chromium 149.0.7827.55; device scale 1; 100% zoom; `en-US`; Africa/Nairobi; light scheme; reduced motion.

Generated evidence is ignored under `storage/app/fidelity/<page>`. Each page contains 11 static PNGs, 11 Laravel PNGs, 11 diff PNGs, `reports/capture.json`, `reports/comparison.json`, and `reports/comparison.md`.

| Page | Static target | Laravel target | Evidence | Summary |
|---|---|---|---|---|
| Homepage | `/` | `/` | `storage/app/fidelity/homepage` | 11/11 evidence sets; known hero animation/raster variance only |
| Collections | `/collections` aliasing untouched `page_2.html` | `/collections` | `storage/app/fidelity/collections` | 11/11 evidence sets; 6 identical and 5 transient raster/image-edge variances |
| Shop All | `/shop` aliasing untouched `page_3.html` | `/shop` | `storage/app/fidelity/shop` | 11/11 pixel-identical |

## Collections comparison

| Viewport | Pixels | Percent | Classification |
|---|---:|---:|---|
| 375?812 | 0 | 0% | Identical |
| 768?1024 | 5 | 0.000636% | Browser raster variance; isolated 27?15 region |
| 1440?900 | 0 | 0% | Identical |
| 639?900 | 0 | 0% | Identical |
| 640?900 | 0 | 0% | Identical |
| 767?900 | 0 | 0% | Identical |
| 768?900 | 348 | 0.050347% | Transient one-pixel image-edge/raster band |
| 1023?900 | 0 | 0% | Identical |
| 1024?900 | 3,357 | 0.364258% | Transient collection-image/raster variance; structure and dimensions equal |
| 1279?900 | 1,337 | 0.116150% | Transient narrow raster bands |
| 1280?900 | 523 | 0.045399% | Transient narrow raster bands |

The matching DOM counts, document dimensions, interactions, resources, zero local failures, and pixel-identical results at adjacent widths rule out a migration-introduced layout defect. No threshold was changed and no region was masked.

## Shop comparison

All 11 required viewports are pixel-identical (0 changed pixels).

## Browser findings

For Collections and Shop, static and Laravel each recorded 55 console errors: 44 resource messages plus 11 Base44 app-state errors, corresponding to four legacy Base44 API 404s per viewport. Counts and behavior match; FE-2A did not modify the bundle. Failed requests: 0. Failed local assets: 0. Internal-link failures are inherited unresolved template destinations; implemented Laravel destinations reduce the Laravel counts (Collections 22?19; Shop 44?41).

Required controls were present on both targets. Direct load and back/forward passed. Desktop navigation, mobile bottom navigation, account/wishlist, newsletter, footer and WhatsApp were present. Mobile menu, search and bag controls produced no visible state change on either source or Laravel and are classified as deferred/broken imported-template behavior, not ecommerce functionality. Collections recorded six collection links and seven main images. Shop recorded 48 product links, 24 wishlist buttons, one filter trigger and one ordering control. No videos exist on either FE-2A page.

## FE-2B evidence

Evidence directories: `storage/app/fidelity/preorder`, `limited-edition`, and `gift-cards`, each containing 11 static screenshots, 11 Laravel screenshots, 11 diffs, `reports/capture.json`, `comparison.json`, and `comparison.md`. Final results: Pre-Order 7/11 identical with four raster-only differences =0.012654%; Limited Edition 11/11 identical; Gift Cards 11/11 identical. All have zero failed local assets. Regression evidence for Homepage, Collections and Shop was regenerated in the same Chromium 149 environment.
