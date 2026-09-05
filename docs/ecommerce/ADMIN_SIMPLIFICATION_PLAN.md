# Admin Simplification Plan

## ECOM-ADMIN-SIMPLIFY-1 implementation

The ordinary Website administration now uses direct Save actions. Site settings,
navigation and announcements save a validated immutable revision and make it the
effective storefront revision in one transaction. Pages use the same approach
when set to Visible, or remove the effective pointer when set to Hidden.

The Review queue route and historical workflow services remain available
internally, but the queue and its review, approval, publication, projection and
readiness language are absent from normal navigation and editing screens.

## Client-facing navigation

- **Overview:** Dashboard
- **Website:** Navigation, Announcements, Media library, Site settings, true editorial Pages
- **Catalogue:** Products
- **Inventory:** Stock, Locations, Movements
- **Commerce:** Orders, Customers, Gift Cards
- **Communications:** Templates, Delivery history
- **Administration:** Users, Roles, Audit log, System settings

Only implemented and authorized destinations appear. Homepage is intentionally
omitted until a real typed editor exists. Empty commerce destinations are not
advertised.

The Catalogue group now contains functional Products and Categories. Desktop
navigation scrolls independently between a fixed brand region and deliberate
footer; the mobile drawer scrolls without obscuring bottom destinations.

## Ordinary workflows

| Area | Default workflow | Hidden infrastructure |
| --- | --- | --- |
| Site settings | Edit -> Save changes | revision creation, audit, cache invalidation |
| Navigation/Announcement | Edit -> optional preview -> Save live | history and emergency fallback |
| Homepage | Edit fixed sections/references -> Preview -> Save | history, validation, cache |
| Product | Edit details/media/options/variants -> Save -> View storefront | immutable content snapshot and audit |
| Collection | Edit identity/media/products/order -> Save | history and ordering fingerprint |
| Merchandising | Search -> Select -> Reorder -> Save | eligibility and stale-write check |
| Campaign | Edit -> Save; active by status/window | claim approval only for regulated/evidence-sensitive wording |

Remove Draft version, Version in review, candidate state, projection mode, review queue, Approve, Publish candidate and high-impact reason from ordinary low-risk screens. Preserve an optional History/Restore surface and move rollout/emergency controls to restricted System settings.

Orders, payment corrections, stock adjustments, refunds, access control and sensitive campaign claims remain reasoned/audited operations because their business risk is real.

## Form standard

Each editor uses visible labels and controls, concise help, inline validation, one primary Save action, contextual Media selectors and a View storefront link. Advanced history never dominates the editing task. State labels use Active/Hidden, In stock/Low stock/Out of stock, Scheduled and Archived rather than internal projection terminology.
