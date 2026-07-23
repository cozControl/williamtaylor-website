# William Taylor Website Implementation Roadmap

## 1. Purpose

This document is the implementation roadmap for converting the client-supplied William Taylor HTML website template into a Laravel 13 and Livewire 4 application with a modern, role-based content management backend.

The roadmap is based on the current project and template inventory:

- Laravel `13.17`, PHP `8.3+`, Livewire `4.1`, Flux UI `2.13`, Tailwind CSS `4`, and Vite `8`.
- The public template is stored in `public/website`.
- The template contains `index.html`, twelve additional HTML pages (`page_2.html` through `page_13.html`), one compiled stylesheet, one compiled JavaScript bundle, and 35 image assets.
- The current public `/` route still renders the default `resources/views/welcome.blade.php`.
- Authentication, email verification, passkeys, two-factor authentication, profile settings, and an authenticated dashboard foundation already exist in the starter kit.

This roadmap is the source of truth for implementation order, scope, acceptance criteria, and design constraints.

## 2. Non-Negotiable Frontend Design Contract

The supplied frontend is client-approved and must be treated as immutable.

### Hard rules

1. Do not redesign, restyle, reinterpret, modernize, simplify, or replace any client-supplied frontend element.
2. Preserve the rendered DOM structure, element order, CSS classes, inline styles, colors, typography, spacing, sizing, imagery, animations, transitions, responsive breakpoints, and interactions.
3. Preserve all desktop, tablet, and mobile layouts already defined by the template.
4. Extracting repeated markup into Blade components is allowed only when the rendered HTML remains equivalent.
5. Replacing literal text, links, and image paths with escaped Laravel variables is allowed only when their output remains visually and behaviorally equivalent.
6. Backend and CMS design may be modern and independent; it must not leak backend styling or JavaScript into public pages.
7. Do not run formatters or automated rewrites against imported template CSS, JavaScript, or markup if they could change output or behavior.
8. Do not upgrade or replace the template's frontend libraries during extraction unless a verified security issue requires it and the client approves the resulting change.
9. Any unavoidable frontend change requires a documented change request, before/after evidence, and written client approval.
10. Accessibility, SEO, security, and performance improvements must first be implemented through semantics, metadata, asset delivery, server behavior, and non-visual attributes. Any improvement that would visibly change the approved design requires approval.

### Fidelity gate

Every public page must pass screenshot comparison at agreed viewport sizes before it is considered complete. Baseline screenshots must be captured from the supplied static HTML and compared with Laravel-rendered pages at, at minimum:

- 375 × 812 (mobile)
- 768 × 1024 (tablet)
- 1440 × 900 (desktop)
- One additional width near each template breakpoint

The goal is pixel-equivalent rendering. Differences must be reviewed and recorded; dynamic content differences must be normalized during comparison.

## 3. Target Architecture

### Public application

- Server-rendered Blade pages for fast initial response, crawlability, and resilience.
- A dedicated frontend layout, for example `resources/views/layouts/frontend.blade.php`.
- Exact Blade partials/components for repeated template regions such as document head, announcement/header/navigation, mobile navigation, footer, newsletter area, consent controls, and floating contact action.
- Page-specific Blade views whose section order and markup follow the supplied HTML exactly.
- Livewire used only where interaction or server state is beneficial. Static presentation must remain Blade-first and must not be converted to Livewire without a reason.
- Template assets kept isolated from backend assets and loaded through stable, versioned URLs.

### Administration application

- A separate authenticated `/admin` area using the existing Laravel/Livewire foundation.
- Backend UI optimized for editors, reviewers, customer-service staff, and administrators.
- Authorization enforced on the server for every route, Livewire action, upload, export, and destructive operation.
- Structured content models rather than one unrestricted HTML field.
- Draft, review, schedule, publish, archive, restore, and revision workflows.
- Audit records for sensitive configuration, permission, publication, and customer-data changes.

### Content rendering boundary

CMS data supplies approved content values; Blade templates own presentation. Editors may change text, images, links, ordering where explicitly supported, SEO fields, and publication state. Editors may not enter CSS classes, scripts, arbitrary layout markup, or styles.

## 4. Proposed Content Domains

The exact names will be finalized during the template page-mapping phase, but the initial domain model should cover:

- Site identity and global settings
- Header, navigation, announcement, footer, social links, and contact channels
- Home-page sections and section-specific calls to action
- Standard/permanent pages such as About, Contact, delivery, returns, privacy, cookie policy, and terms
- Collections/categories
- Products or catalogue items, including images, variants/options, pricing labels, availability, badges, and related items
- Campaigns, promotions, limited editions, and featured content
- Testimonials
- Lookbook/editorial/media sections if confirmed by page mapping
- Contact enquiries and newsletter subscriptions
- Media library and reusable image metadata
- Redirects
- Per-page SEO and social-sharing metadata
- Customer accounts, profiles, preferences, consent history, and saved information

Where the supplied template represents commerce-like actions but checkout requirements are not yet confirmed, preserve the current action and model the catalogue cleanly without inventing payment or fulfilment scope.

## 5. Role and Permission Model

Use feature-based permissions and roles composed from permissions. Avoid authorization based only on hard-coded role names.

### Initial roles

- **Super Administrator**: unrestricted platform access; tightly limited membership.
- **Administrator**: operational configuration, users, content, media, SEO, and reports, excluding protected system actions as configured.
- **Content Manager**: create, edit, organize, review, schedule, and publish public content.
- **Content Editor**: create and edit drafts; cannot publish unless separately granted.
- **SEO Manager**: metadata, redirects, sitemap controls, structured-data fields, and SEO reporting.
- **Media Manager**: upload, replace, tag, and archive media according to usage rules.
- **Customer Support**: read and update permitted customer and enquiry data; no content publication or permission administration.
- **Viewer/Auditor**: read-only administration and audit access.
- **Customer**: self-service access to only their own profile and permitted account data.

### Permission naming

Use explicit feature/action permissions, for example:

- `pages.view`, `pages.create`, `pages.update`, `pages.review`, `pages.publish`, `pages.delete`
- `products.view`, `products.create`, `products.update`, `products.publish`
- `media.view`, `media.upload`, `media.update`, `media.delete`
- `seo.view`, `seo.update`, `redirects.manage`
- `customers.view`, `customers.update`, `customers.export`, `customers.erase`
- `enquiries.view`, `enquiries.respond`, `enquiries.assign`
- `users.view`, `users.create`, `users.update`, `users.disable`
- `roles.view`, `roles.manage`
- `settings.view`, `settings.update`
- `audit.view`

Policies and middleware must enforce permissions. The UI may hide unavailable actions, but hidden controls are not an authorization boundary. Seed roles and permissions deterministically and cover the permission matrix with automated tests.

## 6. Phased Delivery Plan

### Phase 0 — Discovery, preservation, and baselines

**Goal:** Make the client template measurable and safe to migrate.

**Work**

- Create a page inventory mapping every supplied HTML file to its visible purpose, future route, page model, forms, repeated regions, dependencies, and content owner.
- Inventory all local images, CSS, JavaScript, fonts, icons, external URLs, analytics calls, form actions, and third-party embeds.
- Identify broken or ambiguous relative paths in the nested `html` pages.
- Record template bundle sizes and current behavior.
- Capture baseline screenshots and interaction recordings for all pages at the fidelity viewports.
- Create a frontend design-freeze checklist and change-request record.
- Confirm content ownership, catalogue/commerce scope, languages, markets, currency, contact destinations, analytics provider, email provider, retention rules, and applicable privacy jurisdictions.
- Back up the original template unchanged. Preserve a checksum manifest so source artifacts can be verified later.

**Deliverables**

- Page/route/content mapping
- Asset and dependency manifest
- Screenshot baseline set
- Approved design-fidelity checklist
- Confirmed scope and unresolved-decisions register

**Exit criteria**

- Every supplied page and asset is accounted for.
- Each page has an agreed Laravel route and CMS owner.
- Baseline output can be reproduced locally.

### Phase 1 — Frontend asset foundation and immutable layout extraction

**Goal:** Render the supplied design through Laravel without visual or behavioral change.

**Work**

- Establish a dedicated frontend asset namespace that cannot collide with the admin Vite/Tailwind bundle.
- Copy and normalize asset locations only as needed; do not alter asset content during the initial migration.
- Build the frontend Blade layout from the exact template document shell.
- Extract repeated header/navigation, mobile menu, footer, newsletter, consent placeholder, and floating contact markup into Blade partials/components.
- Replace relative paths with Laravel asset/route helpers while preserving the generated URLs and behavior.
- Preserve script load order, attributes, inline configuration, and initialization timing.
- Add named public routes without changing public-facing behavior.
- Replace the default welcome view with the extracted homepage only after parity is demonstrated.
- Add automated route smoke tests and browser interaction tests for menus, sliders, filters, dialogs, forms, and other supplied behavior.

**Deliverables**

- Frontend layout and shared components
- Stable asset pipeline/location strategy
- Laravel-rendered homepage
- Initial browser and visual-regression tests

**Exit criteria**

- Homepage matches the static template at all fidelity viewports.
- No missing asset, browser-console error, broken link introduced by migration, or admin-style collision.
- Existing template interactions work with normal navigation and Livewire page lifecycle events where relevant.

### Phase 2 — Migrate all supplied pages

**Goal:** Move all remaining static pages into named Laravel routes with exact fidelity.

**Work**

- Migrate pages one at a time according to the page map.
- Preserve page-specific markup and only extract a component after equivalence is proven on every consumer.
- Add route model binding and canonical slugs where dynamic entities are required.
- Replace static cross-page links with named route helpers.
- Add route-specific title, description, canonical, Open Graph, Twitter, and indexing fields while keeping the visible design unchanged.
- Produce a comparison report for each migrated page.
- Retain the original static source outside the served route surface for reference until final acceptance.

**Exit criteria**

- All thirteen supplied HTML pages have an implemented route or a documented reason for exclusion.
- All pages pass visual, responsive, interaction, broken-link, and console-error checks.
- A route returns a real 404 for unknown resources rather than a misleading successful page.

### Phase 3 — CMS data model, media library, and publishing workflow

**Goal:** Make every approved text, image, link, label, and SEO value manageable without exposing presentation controls.

**Work**

- Design migrations and models for global settings, pages, page sections, catalogue domains, campaigns, testimonials, navigation, footer, contacts, SEO, redirects, and form submissions as confirmed in Phase 0.
- Seed current template content into structured records so the initial dynamic render is identical to the approved static render.
- Build a media library with validated file type/size/dimensions, descriptive alt text, focal point/crop metadata where the existing design needs it, usage references, and safe deletion checks.
- Preserve originals and generate optimized derivatives asynchronously; never replace source media destructively.
- Build draft/review/publish/schedule/archive states.
- Add revision history, comparisons, restore, preview links, autosave or draft protection, and audit events.
- Use constrained section editors. Do not provide arbitrary HTML/CSS/JavaScript editing.
- Sanitize any intentionally supported rich text with a strict allowlist.
- Add cache invalidation when published content or global settings change.

**Exit criteria**

- All in-scope visible text and images can be managed by an authorized user.
- Publishing reproduces the supplied design with no layout control exposed.
- Draft content is never visible publicly without an authorized preview.
- Used media cannot be accidentally deleted.
- Revisions and actor history can be inspected and restored.

### Phase 4 — Administration dashboard, RBAC, and user management

**Goal:** Deliver a secure, usable operations backend.

**Work**

- Create a separate admin navigation organized by permitted features.
- Implement user listing, search, invitation/creation, profile editing, role assignment, activation/suspension, verified-email status, and security-state visibility.
- Retain and integrate existing two-factor authentication and passkey capabilities.
- Require appropriate re-authentication for high-risk actions.
- Implement permission-aware dashboards, editorial queues, scheduled content, recent enquiries, media issues, and audit activity.
- Add account lockout/rate limiting, session management, password/security policies, and protected last-super-admin rules.
- Record changes to roles, permissions, users, publication state, customer data, and configuration.
- Add feature tests for unauthorized, authorized, cross-tenant/ownership-style, and Livewire action access.

**Exit criteria**

- Permission matrix is enforced at route, policy, and action levels.
- No privileged action relies only on a hidden button.
- Administrators can trace who changed and published content.
- Critical user-management scenarios have automated coverage.

### Phase 5 — Enquiries, newsletter, customer profiles, and privacy controls

**Goal:** Support customer interaction and responsible personal-data management.

**Work**

- Implement contact/newsletter forms with validation, CSRF protection, rate limits, bot protection/honeypots, accessible errors, queued notifications, and submission status.
- Add enquiry assignment, internal notes, status, tags, and permitted export.
- Build customer self-service profile features based on confirmed business scope: identity, contact information, communication preferences, consent, saved addresses or preferences, and security settings.
- Ensure customers can access only their own records.
- Record consent version, purpose, timestamp, source, and withdrawal.
- Provide account/data export and deletion/anonymization workflows subject to legal retention rules.
- Define retention schedules for enquiries, logs, analytics identifiers, accounts, and exports.
- Encrypt sensitive data where appropriate, redact logs, and restrict exports.
- Avoid dark patterns; newsletter and optional marketing consent must not be preselected.

**Exit criteria**

- Forms are reliable, abuse-resistant, and observable.
- Customer authorization and privacy scenarios have automated tests.
- Consent can be demonstrated and withdrawn.
- Data export/deletion procedures are documented and testable.

### Phase 6 — SEO, sitemap, structured data, and social sharing

**Goal:** Make the server-rendered site discoverable and correctly represented without visual change.

**Work**

- Add editable unique titles, meta descriptions, canonical URLs, robots directives, Open Graph/Twitter values, and share images.
- Generate XML sitemap indexes and domain-specific sitemaps from published canonical records only; use last-modified timestamps based on meaningful content changes.
- Provide `robots.txt` with environment-safe behavior so staging cannot be indexed and production is not accidentally blocked.
- Add validated JSON-LD types appropriate to confirmed content, such as Organization, WebSite, BreadcrumbList, Product, CollectionPage, and Article.
- Enforce one meaningful page heading and sensible semantic structure where this can be done without visual change; escalate visible changes.
- Add redirect management for changed slugs and prevent redirect chains/loops.
- Add image alt-text management, stable clean URLs, pagination rules, and 404/410 behavior.
- Integrate search-engine verification and submission only after production-domain approval.

**Exit criteria**

- Sitemap contains only published canonical URLs and validates.
- Metadata is unique or intentionally inherited.
- Structured data validates against the intended schema.
- Staging and preview content cannot be indexed.

### Phase 7 — Performance, responsive assurance, accessibility, and resilience

**Goal:** Meet modern delivery standards while maintaining the approved design.

**Work**

- Establish performance budgets for HTML, CSS, JavaScript, images, fonts, requests, and Core Web Vitals.
- Audit the current large compiled JavaScript bundle and reduce delivery cost only through behavior-preserving techniques proven by regression tests.
- Apply caching headers, compression, immutable versioned assets, CDN support, route/config/view caching, database indexes, eager loading, and response caching where safe.
- Generate AVIF/WebP derivatives with correct dimensions and responsive `srcset`/`sizes` when output remains visually equivalent.
- Prevent layout shift by retaining intrinsic dimensions/aspect ratios.
- Lazy-load below-the-fold media and prioritize the actual largest-contentful asset.
- Self-host/subset fonts where licensing permits and visual output is verified.
- Test keyboard navigation, focus behavior, labels, contrast, reduced motion, screen-reader names, zoom, and touch targets. Non-visual fixes may proceed; visible fixes require design-change approval.
- Test responsive output on current Chromium, Firefox, and Safari/WebKit equivalents and representative real devices.
- Add friendly error pages that respect the supplied visual language only after client approval.

**Exit criteria**

- Agreed performance budgets pass on representative mobile and desktop profiles.
- No visual regression results from optimization.
- No critical/high accessibility defects remain; approved exceptions are documented.
- Public pages remain usable if non-essential JavaScript fails.

### Phase 8 — Cookie consent, analytics, security, and operations

**Goal:** Prepare the application for compliant and supportable production operation.

**Work**

- Maintain a cookie/technology register with provider, purpose, category, lifetime, and data destination.
- Implement consent categories such as necessary, preferences, analytics, and marketing according to legal advice and actual integrations.
- Block non-essential scripts until valid consent; make reject and accept choices comparably accessible.
- Store consent proof and make preferences easy to revisit.
- Version privacy, cookie, and terms content and associate accepted versions where required.
- Apply Content Security Policy, security headers, secure cookie settings, trusted-proxy configuration, upload hardening, dependency audits, secret management, and least-privilege production credentials.
- Add queues with retries/failure handling, scheduled jobs, health checks, centralized error reporting, log retention, uptime monitoring, backup/restore procedures, and deployment rollback.
- Create database and media backups and perform a restore rehearsal.
- Add CI checks for tests, linting, static analysis, asset build, dependency audit, and browser smoke tests.

**Exit criteria**

- Non-essential trackers do not run before consent.
- Security and dependency review has no unresolved critical issue.
- Monitoring, backups, restore, queue failure, and rollback procedures are verified.
- Production readiness checklist is signed off.

### Phase 9 — AI website assistant (later release)

**Goal:** Add a grounded customer assistant based on approved website data after the core CMS is stable.

**Prerequisites**

- Stable published content model and canonical URLs
- Content classification and ownership
- Privacy/security assessment and approved provider
- Defined use cases, escalation paths, budget, and success measures

**Work**

- Index only approved, published, non-sensitive website content and explicitly approved support material.
- Use retrieval with source references and content-version tracking; re-index on publish/unpublish.
- Instruct the assistant to answer only from approved sources, acknowledge uncertainty, and provide links to relevant site pages.
- Add human handoff to the existing contact/WhatsApp/enquiry path.
- Protect against prompt injection, unauthorized data retrieval, abusive traffic, and accidental personal-data capture.
- Do not place customer profile data into the knowledge base. Access to personalized data requires explicit authentication, narrow tools, authorization checks, and audit logging.
- Define retention and consent for chat transcripts; redact sensitive values.
- Build an offline evaluation set for factuality, refusal behavior, brand tone, safety, retrieval quality, and outdated/unpublished content.
- Start behind a feature flag with staff testing, then a limited rollout.
- Monitor unanswered questions, citations, latency, cost, feedback, and escalation rates.

**Exit criteria**

- Assistant answers are grounded in current published content and expose supporting links.
- Unpublished, deleted, restricted, or customer data cannot be retrieved.
- Safety and quality evaluation thresholds are met.
- The assistant can be disabled immediately without affecting the website.

## 7. Testing Strategy

Testing is part of every phase, not a final activity.

- Unit tests for content state, permissions, SEO generation, media rules, and consent behavior.
- Feature tests for public routes, admin actions, publication visibility, ownership, forms, redirects, sitemaps, and customer privacy.
- Livewire component tests for validation, authorization, events, file uploads, and state changes.
- Browser tests for critical admin and public journeys.
- Screenshot regression tests against the immutable template.
- Accessibility automation plus manual keyboard and screen-reader checks.
- Performance tests with stored budgets and repeatable profiles.
- Security tests for IDOR/broken access control, CSRF, XSS, upload attacks, rate limits, session handling, and sensitive-data exposure.
- Backup restore, queue failure, scheduler, deployment, and rollback tests before launch.

## 8. Definition of Done for Every Feature

A feature is complete only when:

- Its acceptance criteria and permission rules are documented.
- Server-side authorization and validation are implemented.
- The public design remains equivalent to the supplied template.
- Relevant unit, feature, Livewire, browser, and visual tests pass.
- Empty, loading, error, unauthorized, and validation states are handled.
- Responsive, keyboard, accessibility, SEO, privacy, and performance effects are reviewed.
- Audit and revision behavior exists where the feature changes important data.
- Cache invalidation and queued work are reliable.
- Documentation and seed data are updated.
- No unrelated template, generated asset, or user-owned code was changed.

## 9. Release Strategy

Use small, reviewable releases rather than a single full conversion:

1. Static Laravel homepage parity
2. All static page parity
3. Read-only CMS-backed rendering
4. Editorial workflow and media management
5. RBAC/user management
6. Forms/customer/privacy features
7. SEO/performance/consent hardening
8. Production launch
9. AI assistant pilot

Each release should have a database migration/rollback plan, content migration verification, screenshots, automated test results, dependency/build results, and a deployment checklist.

## 10. Decisions Required Before Their Phase Begins

The team must obtain explicit decisions for:

- Meaning and destination of each `page_2`–`page_13` template page
- Whether the site is catalogue-only, enquiry/pre-order, or full e-commerce
- Product variants, inventory, currency, tax, delivery, payment, refund, and order requirements if commerce is included
- Supported languages and markets
- Who may approve and publish content
- Media storage/CDN provider and image-retention policy
- Email, newsletter, analytics, tag management, maps, payment, and chat providers
- Privacy jurisdictions, age requirements, retention periods, and legal copy
- Customer-profile scope
- Production domain, hosting, queue, database, cache, storage, monitoring, backup, and recovery targets
- Performance budgets and supported browser/device matrix
- AI provider, knowledge sources, transcript policy, human handoff, and operating budget

These decisions should not be guessed when they materially affect data design, privacy, payments, customer rights, or production infrastructure.

## 11. Immediate Next Implementation Milestone

Begin with Phase 0 and Phase 1 only:

1. Produce the full page and asset map.
2. Capture static-template baselines.
3. Preserve the original source and checksum manifest.
4. Extract the shared frontend layout without changing output.
5. Serve the homepage from Laravel.
6. Prove responsive, visual, and interaction parity.

Do not begin CMS schema expansion or migrate every page until homepage parity establishes a repeatable extraction method.
