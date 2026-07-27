# BE-5C.1 Completion Report

Date: 2026-07-26  
Status: **BE-5C.1 CLOSED**

1. Required architecture, ADR, Catalogue, Media, Publishing, cache, Factory, templates, and prior-phase evidence was reviewed.
2. Initial state was `main` at `a5a190e4a9b75cefd9abb0e3f140deb3d022f39c`; unrelated dirty-worktree changes were preserved.
3. Initial inventory: 40 application routes, two schedules, 14 migrations, no Collection tables/rows.
4. Frontend evidence and classifications are recorded in `BE-5C-1_DISCOVERY.md`.
5. `/collections` proves genuine curated cards; no records were inferred or seeded.
6. Shop All is a query; Pre-Order/Limited Edition are Campaigns; homepage/related Product rails remain BE-5B.
7. `CollectionTypeRegistry` registers only `curated`.
8. It supports manual Product membership/order, requires media, may be ready, and disables nesting/dynamic queries.
9. Campaign keys and arbitrary classes, SQL, scopes, views, routes, CSS, and scripts fail closed.
10. `collections` stores ULID identity, type, unique slug, draft state, revision pointer, archive/lock/actor metadata.
11. `collection_revisions` stores immutable normalized title/short description, number, schema, checksum, note, actor/time.
12. Model guards reject revision update/deletion.
13. `collection_products` stores ULID, restrictive Collection/Product FKs, order, deterministic keys, archive/actor metadata.
14. `CreateCollection` validates type/slug/content and atomically creates revision one/pointer without membership, media, or publication.
15. Slugs are normalized, reserved-segment protected, application/database unique, and race failures are deterministic.
16. `ReviseCollection` locks, stale-checks, rejects identical content, preserves history, and serializes numbering.
17. Revision checksums are deterministic over normalized structured plain text.
18. Slug change preserves revision/membership/media, stays draft, and creates no redirect/history/route.
19. Assignment locks Collection, Product, and the complete membership scope.
20. Archived resources, duplicates, and unsupported types are rejected.
21. Draft Products may be configured; membership never changes Product state/readiness.
22. Reorder requires a complete duplicate-free set and uses two-pass MySQL-safe positions beginning at 60000.
23. Idempotent order creates no mutation/audit.
24. Membership archive requires bounded reason, preserves identity/Product, and compacts positions.
25. Restore revalidates type/lifecycle/uniqueness/order and displaces nothing.
26. Product archive/restore/readiness changes preserve memberships and order.
27. Readiness reports archived or Catalogue-unready Product targets without mutation.
28. Media owner is exactly `Collection`; revisions, memberships, and Campaigns are not registered.
29. Singular roles `card` and `hero` derive from protected storefront evidence.
30. Both require confirmed ready meaningful images, effective alt, maximum one, and contribute to readiness.
31. Media assignment validates owner/role/lifecycle/type/duplicate/stale/accessibility state.
32. Update changes contextual alt only; removal deletes usage only and preserves the asset.
33. No repeatable role is evidenced, so no speculative media reorder action exists.
34. Effective alt resolves override then asset default; missing/whitespace/HTML/decorative alt is rejected.
35. Pure readiness checks identity/type/slug, current revision/content, card/hero accessibility, membership order/duplicates/nonempty, Product lifecycle/readiness.
36. Readiness excludes price, inventory, availability, Campaigns, and commerce.
37. Collection archive/restore preserves revisions, memberships, media/order, Products, and leaves draft.
38. SHA-256 fingerprints cover identity/revision/lifecycle and sorted membership/media sets/order.
39. Stale failures cause no mutation, revision, order/archive change, success audit, or invalidation.
40. All actions use transactions, locked rereads, and three-attempt retry semantics.
41. Private cache key is `catalogue:collection-configuration:v1:{id}` and stores scalar arrays, never models.
42. Invalidation uses `DB::afterCommit`; failed/stale actions retain cache.
43. Typed configuration query returns identity, revision, ordered Products, media, readiness, and archive state.
44. Cache hit adds zero queries; uncached configuration passes the asserted 12-query budget.
45. Lifecycle audit events: `collection.created`, `.revised`, `.slug.changed`, `.archived`, `.restored`.
46. Membership events: `collection.product.assigned`, `collection.products.reordered`, `.product.archived`, `.product.restored`.
47. Media events: `collection.media.assigned`, `.updated`, `.removed`.
48. Audit payloads are bounded identifiers, registered keys, positions, checksum/state, and reasons only.
49. One migration adds three normalized tables and revision-pointer FK; no existing migration changed.
50. SQLite focused: 7 tests/36 assertions, zero failures.
51. MySQL focused: 7/36, zero failures.
52. Catalogue/Merchandising regression: 45/186, zero failures.
53. Full suite: 242/1,529, zero failures.
54. Catalogue and Catalogue/Media/Merchandising Larastan: zero findings.
55. Full Larastan: zero findings.
56. Pint and `git diff --check` pass.
57. `composer ci:check` passes protected PHP 10/10, Pint, Larastan, and 242/1,529 tests.
58. MySQL 8 fresh migration, Collection rollback, re-migration, and status pass.
59. Blade cache and Vite production build pass; optional Fontaine warning is informational.
60. Composer validates with the existing exact-version warning; Composer/npm audits find zero vulnerabilities.
61. Homepage, About, Collections, Pre-Order, Limited Edition, and five Product fidelity harnesses pass.
62. Factory regression: 10/133; protected frontend regression: 20/268; Factory PHP: 10/10.
63. Composer/npm manifest and lock hashes exactly match BE-5B; no package was added.
64. `willy`: 401408 bytes; SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
65. Final MySQL counts: Collections/revisions/memberships/Products/Variants all zero.
66. Routes remain 40; exactly five static Product routes and no dynamic Product route.
67. Schedules remain two.
68. Credential scan finds none in BE-5C.1 code/tests.
69. Boundary scan adds no price/inventory/stock/checkout/payment/Campaign persistence.
70. Encoding scan finds no BOM/replacement characters.
71. No real Collection or Product record was created outside isolated tests.
72. No Factory v3, route, binding, preview, admin UI, or public Collection projection was added.
73. No Product/public readiness rule was changed.
74. No Media provider upload/deletion or provider metadata duplication was introduced.
75. Files created are the migration, Collection models/support/data/query/cache/actions, focused test, and these two reports.
76. Only the phase map is modified, with a brief implementation reference.
77. Every mandatory BE-5C.1 gate passes; Campaigns and all later domains remain separately authorized.

No route, schedule, public template, Factory source, dependency manifest, provider configuration, Product readiness rule, or existing migration was changed.
