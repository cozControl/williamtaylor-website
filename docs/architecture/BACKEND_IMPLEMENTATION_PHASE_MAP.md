# Backend Implementation Phase Map

Date: 2026-07-23
Status: Approved ordering; implementation phases remain separately authorized

Every ADR is approved as architectural direction. Every implementation phase remains separately authorized, and unresolved legal, financial, provider, infrastructure or operational details must be confirmed in the affected phase. Exclusions are hard boundaries. Material changes require a superseding ADR.

| # / phase | Dependency and purpose | In scope | Explicit exclusions | Required gate |
|---|---|---|---|---|
| 1 Architecture approval (ARCH-3B) | ARCH-3A complete | ADRs, register, approval package and roadmap | Runtime code, schema, packages | Stakeholder decisions recorded |
| 2 Minimal identity, RBAC and audit foundation | Approved 001-003,005-008,014,018,020-022,024 | Exact nine-permission foundation registry including `admin.access`; initial roles; policies; bootstrap; alignment command; explicit audit | Admin shell/UI; CMS and `pages.*`; catalogue | Registry impact preview, security matrix, final-admin, cache, audit, MySQL and rollback tests |
| 3 Administration shell/navigation | Phase 2 | `/admin` layout, real permission-aware navigation, overview placeholders, responsive/a11y shell | Domain CRUD; role mutation | Policy/route/browser/WCAG approval |
| 4 Access-management screens | Phases 2-3 | Users, role bundles, effective access, controlled assignments, audit viewer | CMS/catalogue CRUD | Escalation, self-lockout, export/read audit tests |
| 5 Media/Cloudinary foundation | Phases 2-4; approved 011-012,021-022 | Provider contract, signed uploads, asset/usage metadata, transforms, safe lifecycle | CMS/product editing; customer AI images | Contract, upload security, quota/recovery approval |
| 6 Core CMS and publishing | Phases 2-5; approved 008-010,015 | Pages/services/policies/FAQ/editorial, typed sections, revisions, preview, review/schedule | Global nav, SEO execution, catalogue | Sanitization, workflow, preview, fidelity approval |
| 7 Global navigation/settings | Phases 2,6 | Menus, announcements, footer, contact/social/WhatsApp, scheduling | Commerce control behavior | Link/tree/schedule/cache/fidelity approval |
| 8 SEO and redirect foundation | Phases 6-7; approved 016-017 | SEO profiles, canonical/robots/OG, code schema, history/redirects, sitemap | Fabricated domain facts; analytics integration | Canonical/schema/loop/sitemap approval |
| 9 Product catalogue foundation | Phases 2,5,8; approved 013,017-018 | Product identity, taxonomy, attributes, canonical route, static transition | Price, inventory, cart/payment | Constraints, route, query budget and product fidelity |
| 10 Variants, product media and merchandising | Phases 5,9 | Sellable Variants, galleries/options, badges, relations/placements | Stock, pricing engine, cart | Combination/media/order/fidelity approval |
| 11 Collections and campaigns | Phases 6,9-10 | Collections, pre-order/limited campaigns, schedules and claims | Reservations/orders/payments | Claim, schedule, cache and legal approval |
| 12 Publication rollout and revisions hardening | Phases 6-11 | Resource-by-resource migration, diffs, rollback, emergency unpublish | Commerce versioning | Concurrency/idempotency/fidelity/governance approval |
| 13 Pricing and inventory | Phases 9-10,12 | TZS/USD prices, effective windows, locations, stock/reservations/availability | Cart/checkout/payment | Money, concurrency, import, audit and finance approval |
| 14 Wishlist, bag/cart and gift-card ledger foundation | Phases 9-10,13 | Anonymous/auth persistence, merge, quantities, stored-value ledger design | Checkout/payment/order | Token, merge, ledger, expiry and browser approval |
| 15 Checkout, delivery, tax and Pesapal | Phases 8,13-14 plus business/legal decisions | Addresses, shipping, optional tax/refund policy flags, Pesapal adapter/webhooks, gift-card tender | Fulfilment automation; AI | Signature/idempotency/currency/reconciliation approval |
| 16 Orders, fulfilment and refunds | Phase 15 | Order/payment references, shipping/fulfilment/refund authorization, transactional mail | Advanced ERP | State, privacy, refund and finance approval |
| 17 Analytics and SEO health | Phases 8,12,16 as applicable | Internal analytics, SEO findings and performance dashboards | Ranking promises; AI | Privacy and metric-owner approval |
| 18 Production hardening and launch | All launched domains | Redis readiness, caches, workers/scheduler, backup/restore, observability, load/security | New product capabilities | Full launch-readiness sign-off |
| 19 Post-launch AI discovery | Stable production | Research, privacy/threat/cost model, tool contracts/evaluation | Runtime AI | Executive/legal/security approval |
| 20 Post-launch AI shopping pilot | Phase 19 | Read-only grounded help and separately confirmed cart tool | Payments/orders/refunds/images | Limited pilot evaluation |
| 21 Post-launch styling/image pilot | Phases 5,19-20 plus legal approval | Private consented images, moderation, signed output and deletion | Fit guarantee, training reuse, unrestricted generation | Separate DPIA-like/legal/security pilot approval |

## Cross-phase rules

- One domain owns each mutation; projections are rebuildable and external providers sit behind contracts.
- New packages pass ADR-021 in their authorized phase; MySQL is the release-equivalent database.
- Every phase names the ADRs and ADR-024 test layers it exercises.
- Public rendering changes require preserved template checksums and screenshot fidelity evidence.
- Migrations require backup/rollback and expand-contract planning; consequential jobs dispatch after commit and are idempotent.
- No next phase begins until its dependency approvals and business/legal decisions are recorded.
- AI remains post-launch and separately permissioned.
## BE-4C implementation reference

The separately authorized user-role assignment and effective-access phase is implemented by the Users/Role routes, `EffectiveUserAccessQuery`, existing audited identity actions, and the safeguards documented in `docs/backend/BE-4C_COMPLETION_REPORT.md`. Later domains remain unstarted.

## BE-4D implementation reference

The media foundation is implemented through MediaProvider, ULID assets/versions/usages, signed upload verification, named transformations and the protected Media library. Later CMS and catalogue phases remain separately authorized.

## BE-4E implementation reference

BE-4E completes only the draft-oriented first slice of phase 6: typed Pages, immutable draft revisions, restricted rich text, Media usages, secure preview, and archive/restore. Review, approval, publication, scheduling, public CMS rendering, services/policies/FAQ/editorial resources, navigation, SEO, and later domains remain separately authorized. See `docs/backend/BE-4E_COMPLETION_REPORT.md`.