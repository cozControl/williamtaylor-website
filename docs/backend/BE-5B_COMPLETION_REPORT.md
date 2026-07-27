# BE-5B Completion Report

Date: 2026-07-26  
Status: **BE-5B CLOSED**

1. **Architecture reviewed.** BE-5A.1/5A.2 reports, protected-source policy, phase map, CMS proposal/discovery, ADR-018, Catalogue/Media actions and models, fingerprints, readiness, archive conventions, audit conventions, Factory manifests, five Product pages, homepage rails, and fidelity/checksum harnesses were reviewed.
2. **Initial repository state.** Branch `main`, commit `a5a190e4a9b75cefd9abb0e3f140deb3d022f39c`; substantial existing modified/untracked work was preserved.
3. **Initial inventories.** 40 application routes, two schedules, 13 migrations, zero Product/Variant rows, and zero existing merchandising tables.
4. **Initial dependency hashes.** Composer/package manifests and locks were recorded and remain exact after BE-5B.
5. **Initial preservation.** Protected Factory PHP 10/10; Factory regression 10/133; `willy` exact at 401408 bytes.
6. **Badge inventory.** `NEW`, `LIMITED`, `PRE-ORDER`, price-sale treatment, and availability/sold-out treatment were classified in `BE-5B_DISCOVERY.md`.
7. **Badge ownership.** `new` is deferred to Analytics/Campaigns; `limited` and `pre-order` to Campaigns; `sale` to Pricing; `sold-out` to Inventory.
8. **Assignable Catalogue badges.** None. No approved frontend evidence supports a stable Catalogue-owned classification, so no key was invented.
9. **Badge registry.** `ProductBadgeRegistry` is deterministic, code-owned, and fail-closed; arbitrary keys, labels, HTML, CSS, URLs, scripts, and later-domain claims cannot be assigned.
10. **Badge persistence.** `product_badges` stores ULID, Product, registered key, position, deterministic active/position keys, archive metadata, and timestamps only.
11. **Badge actions.** Assign, reorder, archive, and restore actions are implemented in Catalogue. Current assignment/restore correctly rejects every deferred definition.
12. **Badge ordering.** Complete active order required; duplicate/missing/foreign IDs rejected; idempotent orders do not audit or mutate.
13. **Badge archive.** Preserves identity and reason, releases deterministic active keys, and compacts remaining positions.
14. **Badge restore.** Revalidates registry ownership, Product state, uniqueness, limit, and ordering; deferred badges remain rejected.
15. **Relation inventory.** Static `You May Also Like` rails prove one ordered relation kind: `related`.
16. **Relation registry.** `related` is directional, ordered, maximum four, eligibility-gated, preservation-oriented, and nonreciprocal.
17. **Relation schema.** `product_relations` stores source/target Product IDs, kind, position, deterministic active/position keys, archive metadata, and timestamps.
18. **Relation assignment.** Source and target are locked and re-read; existence, self-link, registry, eligibility, duplicate, limit, and stale state are validated.
19. **Self-link prevention.** Application validation rejects source=target before mutation.
20. **Duplicate prevention.** Application validation plus a deterministic unique active key prevent duplicate active source/target/kind relations on MySQL and SQLite.
21. **Reciprocal behavior.** A→B never creates, removes, archives, or restores B→A.
22. **Relation reorder.** Complete scope required; two-pass collision-free updates normalize zero-based positions and emit one bounded event.
23. **Relation archive/restore.** Archive preserves the record and compacts only its direction; restore revalidates source, target, kind, eligibility, uniqueness, capacity, and position.
24. **Placement discovery.** The homepage’s explicit five-card Product rail is included as `homepage-featured-products`.
25. **Deferred surfaces.** Shop All is a query surface; Related Products are relations; Pre-Order/Limited Edition are Campaign-owned; collection/search/cart/wishlist surfaces remain deferred.
26. **Placement registry.** Product-only, minimum zero, maximum five, ordered, no duplicates, eligibility required, no schedule/audience/locale/campaign support.
27. **Placement schema.** `product_placements` stores slot, Product, position, deterministic active/position keys, archive metadata, and timestamps only.
28. **Placement assignment.** Revalidates slot, Product existence/state/readiness, duplicate and capacity under locks; never fills gaps or mutates Product state.
29. **Placement reorder.** Complete slot order, stale check, deterministic two-pass MySQL-safe update, idempotency, and single audit.
30. **Placement archive/restore.** Records and Products are preserved; active positions compact; restore revalidates slot, Product eligibility, uniqueness, capacity, and position without displacement.
31. **Configuration eligibility.** Pure `ProductMerchandisingEligibilityEvaluator` requires an existing, unarchived, Catalogue-ready Product, including valid Product media.
32. **Public projection boundary.** Persisted configuration grants no public visibility. Future projection must separately re-evaluate Catalogue/publication/campaign rules.
33. **Catalogue-readiness separation.** No badge, relation, or placement requirement was added to `CatalogueReadinessEvaluator`.
34. **Product archive.** Badges, source relations, target relations, and placements remain stored; eligibility fails and nothing is replaced or cascaded.
35. **Product restore.** Configurations remain stored, Product remains draft, and eligibility must be explicitly re-evaluated.
36. **Readiness loss/recovery.** Records are neither deleted nor recreated; the pure evaluator reports current eligibility without changing Product status.
37. **Fingerprints.** SHA-256 fingerprints cover Product badge state/order, source/kind relation state/order, and slot placement state/order.
38. **Stale behavior.** Typed Catalogue or Merchandising stale exceptions yield no mutation, ordering change, archive change, or success audit.
39. **Transactions.** All twelve mutation actions use database transactions with three-attempt retry semantics.
40. **Locking.** Products and complete relevant active ordering scopes are re-read and locked before mutation.
41. **MySQL ordering strategy.** Two-pass updates use temporary positions beginning at 60000, safely inside `UNSIGNED SMALLINT`, then normalize to contiguous zero-based positions.
42. **Active uniqueness.** Non-null SHA-256 `active_key` and `position_key` values enforce active identity and order independently of MySQL NULL uniqueness; archive replaces both with identity-specific archival values.
43. **Audit events.** `product.badge.assigned`, `product.badges.reordered`, `product.badge.archived`, `product.badge.restored`, `product.relation.added`, `product.relations.reordered`, `product.relation.archived`, `product.relation.restored`, `product.placement.assigned`, `product.placements.reordered`, `product.placement.archived`, and `product.placement.restored`.
44. **Audit bounds.** Only identifiers, registered keys, positions, state, and bounded archive reasons are recorded; no descriptions, media/provider payloads, credentials, HTML, or arbitrary metadata.
45. **Migration.** One migration creates three explicit non-polymorphic tables with restrictive Product FKs, actor FKs, ordering indexes, unique keys, and archive fields.
46. **Fresh migration.** SQLite-compatible migration and MySQL 8 fresh migration pass.
47. **Rollback/re-migration.** MySQL step rollback drops all three tables in dependency-safe order; re-migration and final status pass.
48. **SQLite focused tests.** 10 tests, 43 assertions, zero failures.
49. **MySQL focused tests.** 10 tests, 43 assertions, zero failures.
50. **MySQL defect caught and corrected.** Initial MySQL testing rejected temporary position 100000 for `UNSIGNED SMALLINT`; the safe 60000 range now passes both engines without partial mutation.
51. **Query budgets.** Ordered badge, eager-loaded relation, eager-loaded placement, and eligibility reads complete within the asserted combined maximum of 15 queries.
52. **Catalogue/Media/Merchandising regression.** 52 tests, 218 assertions, zero failures.
53. **Full suite.** 235 tests, 1,493 assertions, zero failures.
54. **Catalogue Larastan.** Zero findings.
55. **Merchandising Larastan.** Zero findings.
56. **Full Larastan.** Zero findings.
57. **Pint and CI.** Pint passes; `composer ci:check` passes protected-source verification, Pint, Larastan, and full 235/1,493 tests.
58. **Syntax/whitespace.** Changed PHP files pass `php -l`; `git diff --check` exits successfully.
59. **Blade/Vite.** Blade compilation and production Vite build pass; existing optional Fontaine warning remains informational.
60. **Audits.** Composer validates with its pre-existing exact-version warning; Composer audit and npm audit report zero vulnerabilities.
61. **Homepage/About fidelity.** Harnesses exit successfully; About reports 12 captures, zero failures, and zero pixel/geometry differences at three viewports.
62. **Product fidelity.** Taylor Oxford Shirt, Mercerized Cotton Polo, Dar es Salaam Linen Suit, Slim Tapered Chinos, and Executive Overcoat harnesses all exit successfully.
63. **Protected assets.** Factory checksum regression remains 56/56; protected Factory PHP remains 10/10.
64. **Factory preservation.** Factory v1 and v2 files were not modified; Factory regression remains 10 tests/133 assertions.
65. **Route inventory.** 40 application routes, exactly five static Product routes, and zero dynamic Product routes.
66. **Scheduler inventory.** Two existing publishing tasks; no BE-5B schedule.
67. **Credential scan.** Zero candidate files in BE-5B code/docs.
68. **Boundary scan.** Zero canonical price, stock, reservation, checkout, payment, campaign-ID, or collection-ID persistence matches.
69. **Encoding scan.** Zero BOM and replacement-character findings.
70. **Dependency comparison.** Composer/npm manifests and lock hashes exactly match the recorded BE-5B baseline; no package was added.
71. **`willy` result.** Begin/end guard passes: 401408 bytes, SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
72. **Final database state.** Products 0, Variants 0, Product badges 0, Product relations 0, Product placements 0.
73. **No real data.** No Product, Variant, SKU, badge, relation, or placement record was created outside isolated tests.
74. **No public expansion.** No Factory v3, route, route-model binding, preview, dynamic Product rendering, or admin UI was added.
75. **No later domain.** Collections, Campaigns, Pricing, Inventory, Engagement, and Commerce remain unstarted.
76. **Known limitation.** Badge assignment has no currently assignable definition by design; business approval of a genuinely stable Catalogue classification is required before one can be added.
77. **Closure recommendation.** Every mandatory BE-5B gate passes. BE-5B may close; Collections and Campaigns remain separately authorized and unstarted.

## Files created

- `database/migrations/2026_07_26_120000_create_merchandising_foundation_tables.php`
- `app/Domain/Catalogue/Models/ProductBadge.php`
- `app/Domain/Catalogue/Support/ProductBadgeRegistry.php`
- `app/Domain/Catalogue/Support/ProductBadgeSetFingerprint.php`
- `app/Domain/Catalogue/Actions/{Assign,Reorder,Archive,Restore}ProductBadge*.php`
- `app/Domain/Merchandising/Actions/*.php`
- `app/Domain/Merchandising/Data/MerchandisingEligibilityResult.php`
- `app/Domain/Merchandising/Exceptions/StaleMerchandisingState.php`
- `app/Domain/Merchandising/Models/{ProductRelation,ProductPlacement}.php`
- `app/Domain/Merchandising/Support/*.php`
- `tests/Feature/Merchandising/MerchandisingFoundationTest.php`
- `docs/backend/BE-5B_DISCOVERY.md`
- `docs/backend/BE-5B_COMPLETION_REPORT.md`

## Files modified

- `docs/architecture/BACKEND_IMPLEMENTATION_PHASE_MAP.md` — brief BE-5B implementation reference only.

No route, scheduler, public template, Factory source, provider configuration, dependency manifest, Catalogue readiness rule, or existing migration was changed.
