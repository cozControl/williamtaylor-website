# CMS Dashboard Information Architecture

## Principles

The administration UI is a task workspace, not a database browser. Use the existing Livewire/Flux stack unless the architecture approval finds a concrete gap. Global shell: command/search palette, environment indicator, contextual help, notification/inbox, user/security menu and permission-aware navigation. Desktop is primary for complex editing; tablet supports review/light edits; mobile supports queues, approvals, emergency unpublish and contact handling, not large catalogue/media composition.

## Navigation and workspaces

| Workspace | Primary users and goals | Key screens/actions | Controls and safeguards |
|---|---|---|---|
| Overview | All staff; understand work needing attention | Readiness cards, review queue, schedules, campaign expiry, media/SEO warnings, recent activity | Role-specific cards; no vanity charts |
| Content | Editors/managers; create useful brand/service/editorial content | Pages, services, articles, guides, lookbooks, FAQ, policies, locations | Status/locale/type/owner filters; preview/save/review together; archive separated |
| Catalogue | Catalogue manager/merchandiser | Products, variants, taxonomy, product media, relations, publication readiness | SKU/status/stock/content filters; bulk tagging/assignment, not unsafe bulk price edits |
| Merchandising | Merchandiser/marketing | Collections, placements, campaigns, pre-orders, limited editions | Drag-order with keyboard alternative; schedules/expiry visible in context |
| Media | Media manager/editors | Library, upload queue, asset detail, usage, accessibility issues | Reuse selector; replacement preview; deletion blocked by usage |
| SEO | SEO manager/content owners | Health dashboard, resource SEO, redirects, sitemap/schema/link findings | Search/filter by issue/severity/owner; preview; redirect-loop prevention |
| Publishing | Reviewers/approvers | In review, changes requested, approved, scheduled, recently published | Diff, notes, readiness failures, approve/publish permission separation |
| Customers & Service | Customer service (later) | Enquiries, appointments, reviews, profiles/measurements under scope | Sensitive-data badges, scoped access, retention/deletion controls |
| Analytics & Performance | Managers/analysts | Content/product/campaign outcomes, search visibility, web vitals | Read-only by default; date/locale/channel filters |
| Settings | Site admins | Brand/contact/social, currencies, tax/refund flags, integrations, feature flags | Step-up auth and audit for sensitive changes |
| Access & Audit | Super/site admin, auditor | Users, roles, effective permissions, sessions, immutable activity | No self-lockout; high-risk confirmation; export restricted |

## Editing experience

Use a three-part resource screen: structured navigator/outline; focused field canvas; contextual rail with status, owner, validation, preview, schedule and SEO readiness. Break content into meaningful panels rather than one long form.

- Plain text: titles, labels, CTA, slugs, SKU and short claims.
- Text areas: excerpts and constrained short copy.
- Structured repeaters: cards, FAQ, benefits, product highlights, menu items; enforce limits/order.
- Purpose-built catalogue controls: money/currency, attributes, variants, availability, relationships.
- Date/time with timezone: schedules/campaigns/prices.
- Relationship selectors: searchable, publish-status aware, with quick preview.
- Media selector: library-first, usage and accessibility state visible.
- SEO/social previews: realistic truncation, canonical/index status near readiness.

Recommend a Tiptap-based editor integrated through a bounded Livewire adapter (evaluate maintained Laravel/Livewire integrations during approval). Store sanitized semantic HTML plus optional editor JSON for stable revisions. Permit headings H2-H4, paragraphs, lists, links, quotes, restricted tables, and approved media embeds. Disallow scripts, styles, arbitrary classes, iframes and unrestricted HTML for ordinary editors. Sanitize server-side with an allowlist and validate links/media references. Images are selected from the media library, never silently uploaded into rich text.

## Workflow UX

Statuses: Draft → In review → Changes requested → Approved → Scheduled/Published → Unpublished → Archived. Show readiness failures before submission/publication. Save draft and Preview are primary nearby actions; Submit for review is explicit. Publish/Schedule is visible only to authorized roles after validation. Destructive actions live in a separated danger menu with impact/usage information and confirmation.

Lists support saved filters, full-text search, sortable operational columns, batch assignment/tagging/archive where reversible, and accessible empty states that explain the next action. Status chips distinguish draft/review/scheduled/published/expired/error. Bulk publish, delete, price, inventory and permission operations require tighter permissions and impact previews.

## Screen-specific requirements

Product editing tabs: Essentials, Story, Options & variants, Media, Merchandising, SEO, Availability summary, History. Never make the editor modify raw stock numbers in the content panel. Page editing: Outline/sections, Content, SEO, Publishing, History. Media detail: preview/facts, accessibility, transformations, usage, history. Campaign: objective/content, placements, audience/locale, schedule, readiness/approvals, outcome.

Validation is inline and summarized with links to fields. Preserve drafts after validation failure. Unsaved-change protection is required. Preview must reproduce the exact public template at responsive presets and clearly label draft state.