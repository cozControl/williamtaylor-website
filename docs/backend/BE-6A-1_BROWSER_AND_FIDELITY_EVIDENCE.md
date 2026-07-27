# BE-6A.1 Browser and Fidelity Evidence

Decision: **BE-6A.1 CLOSED**

Authoritative run: `be6a1-20260727012425-a5a190e4`

## Coherent process-isolated execution

The canonical `npm run fidelity:be6a1` command generated one immutable manifest and ran security, preview, visual, and verification in separate Node/Chromium processes. Before each stage it revalidated the commit, working-tree checksum, dependencies, Vite manifest, Playwright/Chromium versions, protected manifests, inventories, and `willy`. Preview teardown therefore cannot block fidelity capture.

The prior Wishlist alternation was Chromium GPU/LCD glyph raster state. Disabling those browser text-raster paths produced exact Wishlist screenshots at all seven viewports without changing application content, thresholds, protected assets, or comparison exclusions.

## Results

- Security: privileged routes 45/45; static footer 2/2; projected footer 2/2; logout/cache passed.
- Signed preview: 7/7 with marker, robots, no-store, permission, cache, and navigation isolation.
- MIME preflight: passed on both origins.
- Hydration/readiness: all qualifying captures initialized the protected SPA and reached three stable frames.
- Captures: 612/612.
- Static/Laravel comparisons: 306/306.
- Repeatability: 102/102.
- Classifications: 88 exact; 14 deterministic bounded subpixel-rasterization; zero unresolved.
- Required local asset, console, or network failures: zero.
- Cleanup/orphans: bounded cleanup completed; no owned browser/server process remained.

Homepage 1024 produced 0, 18, and 20 differing pixels (maximum 0.002170139%); Homepage 1279 produced 35, 40, and 13 (maximum 0.003474937%). Semantics, geometry, images, and responsive mode matched. Pre-Order 1280 passed at a maximum 0.01640625% bounded subpixel rasterization. Slim Tapered Chinos 1440 and Executive Overcoat 375 were exact.

Every route group passed: Homepage 11/11; About, Collections, Shop, Pre-Order, Limited Edition, Gift Cards, Login, Wishlist, Taylor Oxford, Mercerized Polo, Dar es Salaam Suit, Slim Tapered Chinos, and Executive Overcoat each 7/7.

## Candidate package

`storage/app/evidence/be6a1-baseline-candidate/` contains the same-run static and Laravel screenshots, manifests, checksums, semantic/pixel results, classifications, Admin-href exception, and review checklist.

> Baseline candidates generated from the checksum-protected static source. Not yet formally approved.

Formal approval remains mandatory before BE-6A.2. BE-6A.2 was not started.