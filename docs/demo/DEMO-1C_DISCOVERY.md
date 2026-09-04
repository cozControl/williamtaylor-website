# DEMO-1C discovery

## Existing Page features

The repository already has a viable Page aggregate and must not receive a second Page model. Existing capabilities include ULID Page identity, unique locale/slug governance, typed Page types/templates/sections, immutable `ContentRevision` records, restricted rich text, optimistic stale-draft rejection, bounded audit records, ready-Media usages attached to exact revisions, signed exact-revision previews, structured revision comparison, pure publication readiness, review/change-request/approval/schedule/publish/unpublish workflows, separation of duties, public projection caching, static fallback, emergency unpublish, and rollback-to-new-draft provenance.

Existing Admin routes are `/admin/content/pages`, create, detail, edit, and review queue. Preview uses `/preview/pages/{page}/{revision}` behind authentication, verified email, a temporary signature, and `pages.preview`.

## Homepage ownership

- The imported `/` homepage composition is protected static Laravel content and is not represented by a Page record.
- Global homepage chrome already owned by Site Content is editable: announcement, primary/footer navigation, brand/profile, contact, WhatsApp, social links, footer copy, and supported global Media.
- No Page-owned homepage body sections currently exist.
- Hero, collection/product presentation, merchandising placements, and imported visual composition remain protected static. DEMO-1C will not fabricate new sections or migrate the protected homepage wholesale.

The Admin therefore labels the existing Site Content workspace as **Homepage and global content**, while the governed About Page remains the Page pilot.

## About and public projection

The public route `/about` resolves through `ResolvePublicPage`. The rollout registry supports only the `about` route/template and the `page` resource key. Static mode returns `frontend.about-static`; shadow mode builds privately while returning static; enabled mode may render `frontend.about-projected`; the global kill and emergency-disabled state force fallback. Product, Collection, and Campaign resources remain static-only.

## Existing Page content and Media

Supported Page sections are `hero`, `editorial_split`, `promotional_cards`, `rich_text`, and `cta`. Existing Media fields are desktop/mobile hero Media, editorial Media, CTA background Media, and card Media. Media usage belongs to the immutable revision, with contextual alt/decorative state. Only ready assets are selectable; publication readiness rejects no-longer-ready assets.

## SEO

There was no Page-owned SEO payload or client editor. DEMO-1C adds a bounded typed `seo` object to Page revisions: SEO title, meta description, canonical path, robots directive, Open Graph title/description, and optional social image. It uses the existing ready-Media rules and does not add schema-less HTML or executable content.

## Missing Admin functionality

- Dashboard did not clearly separate **Manage Website Content** and **Manage Customer Orders**.
- Page index lacked publication/readiness/SEO/Media summaries and filters.
- Page editor lacked typed SEO controls and the ready-Media card presentation introduced in DEMO-1A.
- Revision history did not expose rollback-to-new-draft.
- Some UI copy incorrectly said public Page projection was not active even though the About resolver exists.

## Minimal implementation

Reuse the existing Page components/actions and add only presentation and typed SEO gaps. Preserve Site Content ownership for homepage chrome. Keep About as the only public Page route. Add demo gating to enabled Page resolution without changing the global rollout kill switch. Expose the existing rollback action from Page detail with stale current-revision binding.

## Deferred types

Homepage body migration, arbitrary pages/routes, Product content, Collections, Campaigns, Pricing, Inventory, checkout, payments, customer Order portal, visual page building, arbitrary HTML/CSS/JavaScript, and production publication authorization remain deferred.
