<?php

namespace App\Domain\Admin\Navigation;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class AdminNavigationRegistry
{
    /** @return list<AdminNavigationItem> */
    public function all(): array
    {
        return [
            new AdminNavigationItem('dashboard', 'Dashboard', 'admin.dashboard', PermissionRegistry::ADMIN_ACCESS, 'Overview', 10, 10, 'Administration workspace overview.', 'dashboard', 'admin.dashboard'),
            new AdminNavigationItem('settings', 'Site settings', 'admin.settings.index', PermissionRegistry::SETTINGS_VIEW, 'Website', 15, 4, 'Brand, contact, social and footer settings.', 'settings', 'admin.settings.*'),
            new AdminNavigationItem('pages', 'Pages', 'admin.content.pages.index', PermissionRegistry::PAGES_VIEW, 'Website', 15, 5, 'Website pages and version history.', 'pages', 'admin.content.pages.*'),
            new AdminNavigationItem('page-review', 'Review queue', 'admin.content.pages.review-queue', PermissionRegistry::PAGES_REVIEW, 'Website', 15, 7, 'Pages waiting for review or publishing.', 'review', 'admin.content.pages.review-queue'),
            new AdminNavigationItem('navigation', 'Navigation', 'admin.content.navigation.index', PermissionRegistry::NAVIGATION_VIEW, 'Website', 15, 8, 'Primary and footer navigation.', 'navigation', 'admin.content.navigation.*'),
            new AdminNavigationItem('announcements', 'Announcements', 'admin.content.announcements.index', PermissionRegistry::ANNOUNCEMENTS_VIEW, 'Website', 15, 9, 'Website announcements and schedules.', 'announcements', 'admin.content.announcements.*'),
            new AdminNavigationItem('media', 'Media library', 'admin.media.index', PermissionRegistry::MEDIA_VIEW, 'Website', 15, 10, 'Images and videos used across the website.', 'media', 'admin.media.*'),
            new AdminNavigationItem('orders', 'Orders', 'admin.orders.index', PermissionRegistry::ORDERS_VIEW, 'Commerce', 18, 10, 'Customer Order operations.', 'orders', 'admin.orders.*'),
            new AdminNavigationItem('users', 'Users', 'admin.access.users.index', PermissionRegistry::USERS_VIEW, 'Administration', 20, 10, 'Staff access management.', 'users', 'admin.access.users.*'),
            new AdminNavigationItem('roles', 'Roles', 'admin.access.roles.index', PermissionRegistry::ROLES_VIEW, 'Administration', 20, 20, 'Role and permission management.', 'roles', 'admin.access.roles.*'),
            new AdminNavigationItem('audit', 'Audit log', 'admin.audit.index', PermissionRegistry::AUDIT_VIEW, 'Administration', 20, 30, 'Recorded administration activity.', 'audit', 'admin.audit.*'),
        ];
    }

    /** @return list<AdminNavigationItem> */
    public function visibleFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            $this->all(),
            fn (AdminNavigationItem $item): bool => Gate::forUser($user)->allows($item->permission),
        ));
    }

    /** @return array<string, list<AdminNavigationItem>> */
    public function groupedVisibleFor(?User $user): array
    {
        $groups = [];

        foreach ($this->visibleFor($user) as $item) {
            $groups[$item->group][] = $item;
        }

        return $groups;
    }
}
