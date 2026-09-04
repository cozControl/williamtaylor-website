# DEMO-1A discovery

## Existing foundation

- `/admin` and current administration destinations require authentication, verified email, `admin.access`, and feature permissions.
- Site Content is the existing aggregate for primary navigation, footer navigation, announcements, and the site profile.
- The workspace provides immutable drafts, stale-state checks, review, approval, scheduling, publishing, unpublishing, semantic comparison, and history.
- Navigation, announcements, contact/profile fields, social links, footer copy, signed preview, audit transitions, and after-commit cache invalidation already exist.
- Public Site Content projection and rollout controls default off/static; Product, Collection, and Campaign remain static.
- The Media library already records readiness, type, dimensions, filename, accessibility metadata, and usage.

## Missing or incomplete

- No explicit code-owned demo-mode boundary or demo-only Admin banner.
- The dashboard has no focused Client Demo status/readiness section.
- Site profile Media fields expose raw ULIDs instead of a ready-asset selector.
- Revision history lacks open/restore controls, although immutable history and semantic comparison exist.
- No single focused browser workflow covers the complete demo lifecycle.
- Required client and completion documentation is absent.

## Placeholders

- Audit is a placeholder destination.
- Newsletter persistence is explicitly not implemented.

## Backend-owned fields and visible regions

Site Content owns announcement content and timing; primary/footer navigation; brand details; email, telephone, WhatsApp, address, and hours; social links; footer copy; and supported global Media. These can project into the announcement, header/navigation, and footer/contact regions. Protected imported storefront content and Product, Collection, Campaign, Pricing, Inventory, and Commerce regions remain static/code-owned.

## Permissions and routes

Existing `navigation.*`, `announcements.*`, `settings.*`, `media.*`, and `admin.access` permissions apply. Relevant routes are `admin.dashboard`, `admin.content.navigation.*`, `admin.content.announcements.*`, `admin.settings.index`, `admin.media.*`, and `preview.site-content.show`.

## Existing tests

Focused coverage exists under `tests/Feature/SiteContent`, `tests/Feature/PublicProjection`, `tests/Feature/Media`, `tests/Feature/Content`, `tests/Feature/Admin`, and `tests/Feature/Publication`.

## Proposed minimal implementation

1. Add fail-closed `DEMO_MODE` configuration accepted only in demo/testing and subordinate to the global kill switch.
2. Add an Admin-only demo banner and focused Client Demo dashboard section.
3. Replace raw Site Content Media IDs with a selector restricted to ready assets.
4. Add focused boundary/UI tests and required documentation.
5. Preserve the existing aggregate, permissions, workflow, projection, and static fallback.
