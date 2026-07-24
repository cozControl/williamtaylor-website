# Administration Navigation Registry

`App\Domain\Admin\Navigation\AdminNavigationRegistry` is the only source of administration destination metadata.

Each immutable item has a stable key, label, named route, required permission, group, deterministic order, description, code-owned icon key, and active-route pattern.

Visibility is calculated with `Gate::forUser($user)->allows($permission)`. Templates do not check role names. An unauthenticated or unauthorized user receives no entries.

Current order:

1. Dashboard
2. Users
3. Roles
4. Audit log
5. Settings

Tests require every item to reference a registered named route and an entry in `PermissionRegistry`. Adding a destination therefore requires an authorized phase, a registered permission, a protected route, registry metadata, and focused access tests.

## BE-4C destination status

Users and Roles now lead to operational access-management and read-only inspection workspaces. Their registry keys, ordering, routes and view permissions remain unchanged. Audit and Settings remain placeholders.

## BE-4D Content navigation

Media library is the sole BE-4D navigation addition under Content. It targets admin.media.index and requires media.view.

## BE-4E Content navigation

Pages is added before Media library in the Content group. It targets `admin.content.pages.index`, requires `pages.view`, and is hidden when authorization fails. No Articles, Services, Policies, FAQs, Navigation, Announcements, SEO, Products, Collections, or Campaigns destination was added.

The effective navigation order is Dashboard; Content: Pages, Media library; Access: Users, Roles; Governance: Audit log; System: Settings.