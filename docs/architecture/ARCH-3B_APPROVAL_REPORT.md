# ARCH-3B Architecture Approval Report

Date: 2026-07-23
Status: Approved by project stakeholder on 2026-07-23; implementation remains separately authorized

## 1. Repository and documents reviewed

Reviewed all eight ARCH-3A architecture deliverables; the Phase 2 status, route migration and deferred-behavior records; Composer/npm manifests and locks; Laravel database, cache, queue, session, filesystem, auth and application configuration; Fortify provider and `User`; routes/middleware bootstrap; Livewire/Flux conventions; feature tests; and current Git state. The review found one Laravel 13 application, Fortify authentication, no RBAC/CMS/catalogue backend, and no confirmed production hosting services. No duplicated Publishing and Permissions document exists; terminology inside the single document was normalized.

## 2. ADRs created

Created ADR-001 through ADR-024 under `docs/architecture/decisions`. Each contains status/date/context/drivers/alternatives/decision/consequences/security/operations/testing/migration/reconsideration/phases/approval. All 24 were approved by the project stakeholder on 2026-07-23, subject to the recorded qualifications and unresolved implementation details.

## 3. Approved decisions

The complete indexed summary is in `ARCHITECTURE_DECISION_REGISTER.md`. In order: modular Laravel deployable; MySQL 8; ULID/exact-data conventions; Livewire/Flux admin; Spatie-backed explicit RBAC; access foundation before shell; project audit; immutable publishing workflow; typed sections; Tiptap JSON/sanitized projection; official Cloudinary adapter; contextual media metadata; Scout/database search; database queue then readiness-gated Redis; English-first localization readiness; domain-sourced SEO; plural canonical product routes; single-writer domain truth; post-launch permissioned AI; per-class retention; dependency gates; isolated environment topology; WCAG 2.2 AA; layered quality gates.

## 4. Alternatives rejected

Microservices and a separate admin SPA add unjustified operational/authentication boundaries. SQLite production lacks the planned concurrency profile. Custom RBAC and gate-only roles duplicate durable access facilities. A temporary shell gate creates removal risk. Generic audit/model events lack business intent. Unrestricted builders/raw HTML permit fidelity and XSS failures. Community provider wrappers/direct REST increase lock-in or protocol burden. Immediate external search and Redis introduce unmeasured operations. Partial locale fallback creates mixed/duplicate pages. Free-form schema can fabricate commerce truth. Universal 30-day or indefinite retention is legally and operationally unsound. AI database access/public image reuse is prohibited.

## 5. Approved direction and unresolved implementation details

The architectural direction is approved. Detailed confirmation remains required for Tanzanian statutory/privacy periods and legal holds; order/payment/Pesapal retention; ordinary CMS Manager self-approval and sensitive approver ownership; hosting/managed MySQL and Redis services; Cloudinary account structure, limits, quota/cost and private-media contract; exact package versions after compatibility/advisory/licence review; traffic/browser targets; Swahili operational ownership; tax/refund/shipping and gift-card financial policy; customer-image consent and retention; and every post-launch AI pilot.

## 6. Production database recommendation

MySQL 8 with InnoDB, utf8mb4 and strict modes. Main CI, concurrency and restore evidence run on MySQL. SQLite remains only for equivalent lightweight tests.

## 7. RBAC recommendation

Use a current Laravel-compatible `spatie/laravel-permission` release after the Phase 2 package gate. Roles are bundles, permissions are code-owned `resource.action`, policies own resource decisions, the web guard is used, direct grants are exceptional/audited, and final-Super-Administrator/self-lockout controls are mandatory. CMS Manager may review/publish ordinary content; sensitive powers are excluded by default.

## 8. Audit recommendation

Use project-owned append-only explicit business events with actor/effective access/action/resource/context/reason/correlation and proportionate request facts. Keep immutable Published Revisions separate. Restrict and audit sensitive reads/exports; never record secrets or full payment data.

## 9. Tiptap and sanitization recommendation

Store versioned Tiptap JSON in immutable revisions and a server-generated sanitized semantic HTML projection. Integrate through bounded Livewire/Alpine, reference Media Asset IDs, prohibit inline uploads/raw HTML and enforce the allowlist server-side. Recommend a project Sanitizer contract initially backed by Symfony HTML Sanitizer, only after its later package gate.

## 10. Cloudinary recommendation

Use the official PHP SDK behind a project `MediaProvider` contract. Create short-lived signed direct-upload intents, verify confirmation/webhooks server-side, use code-owned transformation names, keep URLs as projections, delay destructive provider deletion, export originals/metadata, isolate production in a separate Cloudinary product environment and keep customer images private.

## 11. Search recommendation

Use Laravel Scout as the replaceable contract and its database engine first. Preserve structured catalogue filters. Consider an external engine only after measured latency/scale/relevance thresholds and operational readiness.

## 12. Queue, cache and session recommendation

Use database queues early with post-commit/idempotent jobs. Staging mirrors launch. Use managed monitored Redis for production cache, sessions, rate limits and queues before high-traffic launch only after failover/runbooks pass; otherwise launch requires explicit capacity approval or remains blocked.

## 13. Locale recommendation

English is initial and unprefixed. Prepare locale-specific independently published revisions for future Swahili at `/sw/...`; do not emit incomplete fallback URLs, `hreflang` or sitemaps. Nonlinguistic identifiers and financial/audit facts are not translated.

## 14. Publishing and self-approval recommendation

Use the approved-state recommendation from ADR-008 with immutable revisions and signed noindex preview. CMS Managers may self-review/publish ordinary editorial resources; Content Editors cannot. Sensitive resources require a distinct approver unless a monitored Super Administrator acts. Stakeholder governance approval is still required.

## 15. Routes and slugs

Use canonical `/products/{slug}`, unique per locale, code-owned reserved paths, normalized slugs and immutable history. Legacy `/product/{slug}` issues only a single-hop 301 to the canonical route and never renders a duplicate. Preview is separate, signed and noindex.

## 16. Data-retention register

ADR-020 records purpose/owner/trigger/duration or unresolved state/disposal/backups/access/audit for accounts, authentication logs, audit, drafts, Published Revisions, media, newsletters, enquiries, measurements, customer/generated images, carts, orders, payments and backups. Customer images have a proposed 30-day maximum; orders/payments remain legally unresolved. No universal 30-day rule applies.

## 17. Corrected ordering

Option A resolves the dependency: (1) architecture decisions, (2) minimal identity/RBAC/audit foundation, (3) permission-aware admin shell, (4) full access-management screens. There is no temporary broad customer-visible gate.

## 18. Updated roadmap

`BACKEND_IMPLEMENTATION_PHASE_MAP.md` now contains 21 bounded phases, adds package/environment/quality dependencies, separates access foundation from access UI, keeps CMS/media/SEO/catalogue/commerce deferred, and retains all AI work post-launch.

## 19. Files created and updated

Created 24 ADRs, `ARCHITECTURE_DECISION_REGISTER.md` and this report. Updated `BACKEND_IMPLEMENTATION_PHASE_MAP.md`, `PUBLISHING_AND_PERMISSIONS_MODEL.md` terminology, and `docs/phase-2/IMPLEMENTATION_STATUS.md`. No runtime file is in scope.

## 20. Test and build results

- `php artisan test`: passed, 57 tests and 430 assertions.
- `php artisan view:cache`: passed; Blade templates cached.
- 
pm.cmd run build`: passed with Vite 8.1.5; the existing optional Fontaine optimization notice is non-fatal.
- 
pm.cmd audit`: passed, 0 vulnerabilities.
- `git diff --check`: passed; Git emitted only the existing line-ending normalization warning for the Phase 2 status file.
- `php artisan route:list --except-vendor`: 15 application routes; all existing public named routes remain present.

## 21. Protected-template checksum

Passed: all 56 SHA-256 entries in `docs/phase-0/template-sha256.txt` match `public/website`.

## 22. Dependency and lock confirmation

Baseline SHA-256: `composer.lock` `617582CABF3222838D751C34F262025A9FB0AA87106CC31B4DB7705F41655022`; `package-lock.json` `EB5591525C0778ADF149CA763BA3ADD8A0AC6C4C2C0B77ADA32545423DE2C170`. Final hashes exactly match the baselines. No install command was run, and neither lock file changed.

## 23. Implementation boundary

No package, migration, model, policy, middleware, route, admin/CMS component, catalogue/commerce behavior, Cloudinary/Scout/Tiptap integration, localization, SEO execution, API, Pesapal or AI implementation was introduced.

## 24. Approval status and confirmations still required

ADR-001 through ADR-024 are Approved as architectural direction. This does not resolve explicitly deferred legal, financial, provider, infrastructure or operational choices. Phase 2 still requires confirmation of first-Super-Administrator ownership, production MySQL/environment responsibility and the exact package/security gates. Later phases must resolve their recorded detailed confirmations and remain separately authorized. Material changes require a superseding ADR.

## 25. Next separately bounded phase

The next separately authorized phase may be only the minimal identity, RBAC and audit foundation described in roadmap Phase 2. The administration shell remains blocked until that foundation passes its security and MySQL gates. Approval of ARCH-3B does not itself start Phase 2.