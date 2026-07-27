# BE-4H-A Public Chrome Inventory

Date: 2026-07-25

The public route set remains unchanged. The homepage uses `frontend.partials.header`; migrated secondary routes retain page-local header variants. Announcement and footer partials are shared by every storefront page. Newsletter markup is nested in the shared footer. Mobile bottom navigation is shared where the supplied design uses it, but it is not the primary mobile-menu structure.

| Surface | Current source | Projection strategy |
|---|---|---|
| Homepage header and desktop navigation | `frontend.partials.header` | Typed projected values with exact static fallback |
| Secondary-route headers | Page-local preserved markup | Bounded selector integration per existing variant; page bodies remain untouched |
| Announcement | `frontend.partials.announcement` | One effective typed announcement or exact static fallback |
| Footer navigation/profile/social/contact | `frontend.partials.footer` | Independent typed projections with exact static fallback |
| Newsletter heading/copy | `frontend.partials.newsletter` | Typed copy only; form remains presentation-only |
| Mobile bottom navigation | `frontend.partials.mobile-bottom-navigation` | Existing behavior remains |
| WhatsApp action | `frontend.partials.whatsapp-action` | Typed safe target where profile projection is valid |

The supplied JavaScript and CSS bundles, page bodies, public paths, named routes and 56 protected storefront assets remain unchanged.
