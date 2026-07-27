# BE-6A.1 Completion Report

Status: **BE-6A.1 CLOSED**

1. Phase decision: **BE-6A.1 CLOSED**.
2. Root run ID: `be6a1-20260727012425-a5a190e4`.
3. Architecture: prepare, security, preview, visual, and verify execute as process-isolated stages.
4. Coherence: every stage is governed by one immutable root manifest.
5. Commit: `a5a190e4a9b7` on `main`.
6. Working tree: checksum recorded and revalidated before every stage.
7. Dependencies: Composer, npm, Vite, Playwright, and Chromium identities were immutable.
8. Storefront protection manifest: SHA-256 `ec429a0f631381bb6a06b1dfa214f3171c05298bad92da0afd1e9c16173a5d4a`.
9. Factory protection manifest: SHA-256 `18f400528adc4f916a8d1a92ada6c663ce29c6174e076c0b45c2204c9f54f912`.
10. Browser: repository Playwright 1.61.1.
11. Chromium: 149.0.7827.55.
12. Lifecycle: each stage owns its Chromium instance, servers, database, cookies, and contexts.
13. Preview root cause: the former monolithic run coupled later work to Windows context shutdown.
14. Resolution: preview exits before a separate visual process launches.
15. Cleanup: page, context, and browser closes are bounded and recorded.
16. Cleanup result: the authoritative run required no assertion waiver.
17. Orphan result: no owned browser or PHP server remained.
18. Security stage: passed.
19. Privileged routes: 45/45 passed.
20. Static footer: 2/2 passed.
21. Projected footer: 2/2 passed.
22. Logout/back/direct navigation: passed.
23. Preview stage: 7/7 passed.
24. Preview controls: marker, noindex/nofollow, no-store, authorization, and cache isolation passed.
25. Visual stage: passed independently.
26. Verify stage: passed.
27. Aggregate result: passed with zero failures.
28. MIME preflight: JavaScript, CSS, image, and font types passed on both origins.
29. Static/Laravel router fallback: no JavaScript-as-HTML response.
30. Hydration: protected application activity was observed for every qualifying SPA capture.
31. Readiness: root, landmark, scripts, mode, height, assets, fonts, and three stable frames passed.
32. Captures: exactly 612.
33. Comparisons: exactly 306.
34. Repeatability groups: exactly 102.
35. Repeatability result: 102/102 passed.
36. Missing captures: zero.
37. Intermediate captures: zero accepted.
38. Required local asset failures: zero.
39. Unclassified material differences: zero.
40. Classifications: 88 exact and 14 deterministic subpixel-rasterization groups.
41. Wishlist raster instability: resolved by disabling GPU/LCD text raster paths.
42. Wishlist result: 7/7 exact groups.
43. Pre-Order 1280: passed; bounded deterministic subpixel rasterization, maximum 0.01640625%.
44. Slim Tapered Chinos 1440: exact.
45. Executive Overcoat 375: exact.
46. Homepage matrix: 11/11 groups passed.
47. Homepage 1024: 0, 18, and 20 differing pixels across the three same-run comparisons.
48. Homepage 1024 maximum: 0.002170139%, bounded 2x1-pixel runs.
49. Homepage 1279: 35, 40, and 13 differing pixels.
50. Homepage 1279 maximum: 0.003474937%, bounded 8x4-pixel runs.
51. Homepage semantics, geometry, image identity, and responsive mode: equivalent.
52. About matrix: 7/7 passed.
53. About static mode: protected static composition retained.
54. About shadow mode: public output remains static while projection builds privately.
55. About enabled isolated mode: governed projection remains reversible.
56. Global kill and emergency-disabled modes: static fallback retained.
57. About resource key: `page`.
58. Collections: 7/7 passed.
59. Shop: 7/7 passed.
60. Pre-Order: 7/7 passed.
61. Limited Edition: 7/7 passed.
62. Gift Cards: 7/7 passed.
63. Wishlist: 7/7 passed.
64. Taylor Oxford: 7/7; internal bootstrap uses `taylor-oxford-shirt`.
65. Mercerized Polo: 7/7 passed.
66. Dar es Salaam Suit: 7/7 passed.
67. Slim Tapered Chinos: 7/7 passed.
68. Executive Overcoat: 7/7 passed.
69. Static Product routes remain code-owned and query no Product records.
70. Admin destination remains protected `/admin`; no public alias exists.
71. Console/network matrix: passed with zero required local failures.
72. Harness self-check: 26/26 lifecycle, orchestration, and fidelity assertions passed.
73. Candidate package: `storage/app/evidence/be6a1-baseline-candidate/`.
74. Candidate label: unapproved; formal approval is still mandatory before BE-6A.2.
75. Focused SQLite publication/projection tests: 33 tests, 259 assertions.
76. Focused MySQL publication/projection tests: 33 tests, 259 assertions.
77. MySQL target: WAMP MySQL 8.4.7 with dedicated `william_taylor_be6a1`.
78. MySQL lifecycle: fresh migration, full rollback, and re-migration passed.
79. Full Laravel suite: 266 tests, 1,779 assertions.
80. Publication Larastan: zero findings.
81. Identity/RBAC Larastan: zero findings.
82. Public Projection Larastan: zero findings.
83. Full Larastan: zero findings.
84. Pint: passed.
85. `composer ci:check`: passed, including 10/10 protected Factory PHP.
86. Blade compilation and Vite production build: passed.
87. Composer validation: valid with the documented exact-version warning.
88. Composer and npm audits: zero vulnerabilities/advisories.
89. Protected storefront assets: 56/56; protected Factory PHP: 10/10.
90. Routes: 40; scheduler: two; no rollout scheduler.
91. Permissions: 56; roles: four.
92. Real business records: Products, Variants, SKUs, Collections, Campaigns, claims, Pricing, Inventory, Reservations, Orders, and Payments are zero/absent.
93. `willy`: 401408 bytes; SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
94. Boundary: BE-6A.2 remains unstarted; no Factory v3, dynamic Catalogue route, added projection, Pricing, Inventory, or Commerce was introduced.

## Closure recommendation

Close BE-6A.1. Baseline candidates remain unapproved, and formal baseline approval remains a prerequisite before any separately authorized BE-6A.2 work.