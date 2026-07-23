# SEO Architecture

## First-class resource model

Attach a polymorphic `SeoProfile` to indexable pages, products, collections, services, locations, articles, campaigns, pre-order/limited-edition/gift-card and policy resources. Fields: SEO title, meta description, canonical override (exceptional), robots directives, OG title/description/image, social metadata, indexation state/reason, breadcrumb label/parent, sitemap inclusion, schema configuration, and optional operational priority/change hints. Defaults are deterministic templates but editors see and may override them with permission.

Slug history and redirects are separate audited resources. Validate normalized paths, reserved slugs, loops, chains and collisions. Canonicals default to the canonical route, never request parameters. Generate sitemaps from published/indexable resources, split by type/size, cache and regenerate asynchronously after publication. Do not expose drafts.

## Structured data

Code owns schema shapes; domain truth supplies values. Homepage: Organization, WebSite and genuine SearchAction after search exists. Locations: LocalBusiness/Store with verified address, hours and contacts. Products: Product plus Offer only from effective pricing and genuine availability; AggregateRating/Review only from moderated genuine data. Content: Article, FAQPage only when FAQs are visible, Service for genuine services, BreadcrumbList on supported pages. Never encode promotional copy as stock, price, ratings or delivery truth.

## Local/regional strategy

Tanzania-first architecture supports country/region/city relationships, genuine location/service pages, TZS/USD display policy and delivery zones. Build authoritative content around Dar es Salaam tailoring, wedding attire, corporate/formalwear, bespoke process, alterations, measurements, fabric care and styling—only where the business genuinely provides expertise/services. East African expansion adds verified locations/delivery/service differences and locale/currency policies rather than duplicated keyword pages.

Prepare field-level locale variants and locale-aware canonical/hreflang only after real translated experiences exist. Initial candidates are English and Swahili; regional locales/countries require approval. Do not auto-translate or generate thin city pages.

## Media SEO/accessibility

Require descriptive internal titles/file names at ingestion, asset default alt, contextual usage alt overrides, captions/credits where useful and explicit decorative classification. Render intrinsic dimensions, responsive variants, modern formats, appropriate lazy/eager loading and stable URLs. Health checks cover missing/conflicting alt, oversized media, missing social images, duplicates and unused assets. Laravel retains all semantic/accessibility metadata regardless of transformed Cloudinary URLs.

## SEO health workspace

Findings have severity, resource, owner, detected/resolved timestamps and dismiss-with-reason audit. Checks include missing/long/weak/duplicate titles and descriptions, canonical problems, accidental noindex, broken links, redirect chains/loops, missing alt/social images, oversized media, schema validity/readiness, orphan pages, stale content, unpublished changes, sitemap errors and Core Web Vitals awareness. After integrations, add Search Console coverage/query trends, Analytics conversions and pages losing organic traffic. Do not implement integrations in ARCH-3A.

Run lightweight readiness synchronously at publish; crawl/link/schema/media checks in queues/schedules. Publication blocks only critical defects (invalid canonical, missing required product truth, unsafe schema, missing critical alt), while lower-severity guidance remains actionable warnings.

## Performance and governance

Cache metadata/schema with the public resource and invalidate on publication, price/availability changes relevant to schema, redirect changes and navigation changes. Search engines see one canonical product route `/products/{slug}`; legacy `/product/{slug}` and slug-history aliases issue single-hop 301 redirects. Pagination/facets need explicit canonical/index rules before dynamic catalogue launch.

SEO Manager manages metadata, redirects and diagnostics but cannot fabricate catalogue truth. Catalogue owners fix product facts; media owners fix asset issues; content owners fix copy/internal links. Every published SEO change is revisioned/audited. Rankings are not guaranteed; architecture supports crawlability, performance, relevance, expertise and operational governance.