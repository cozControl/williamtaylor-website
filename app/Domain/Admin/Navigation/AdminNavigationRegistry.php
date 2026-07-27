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
            new AdminNavigationItem('pages', 'Pages', 'admin.content.pages.index', PermissionRegistry::PAGES_VIEW, 'Content', 15, 5, 'Typed draft pages and immutable revision history.', 'pages', 'admin.content.pages.*'),
            new AdminNavigationItem('page-review', 'Review queue', 'admin.content.pages.review-queue', PermissionRegistry::PAGES_REVIEW, 'Content', 15, 7, 'Operational Page review and publication governance.', 'review', 'admin.content.pages.review-queue'),
            new AdminNavigationItem('navigation', 'Navigation', 'admin.content.navigation.index', PermissionRegistry::NAVIGATION_VIEW, 'Content', 15, 8, 'Primary and footer navigation governance.', 'navigation', 'admin.content.navigation.*'),
            new AdminNavigationItem('announcements', 'Announcements', 'admin.content.announcements.index', PermissionRegistry::ANNOUNCEMENTS_VIEW, 'Content', 15, 9, 'Independent announcement scheduling and publication.', 'announcements', 'admin.content.announcements.*'),
            new AdminNavigationItem('media', 'Media library', 'admin.media.index', PermissionRegistry::MEDIA_VIEW, 'Content', 15, 10, 'Reusable image and video library.', 'media', 'admin.media.*'),
            new AdminNavigationItem('users', 'Users', 'admin.access.users.index', PermissionRegistry::USERS_VIEW, 'Access and governance', 20, 10, 'User access destination reserved for the next authorized phase.', 'users', 'admin.access.users.*'),
            new AdminNavigationItem('roles', 'Roles', 'admin.access.roles.index', PermissionRegistry::ROLES_VIEW, 'Access and governance', 20, 20, 'Role access destination reserved for the next authorized phase.', 'roles', 'admin.access.roles.*'),
            new AdminNavigationItem('audit', 'Audit log', 'admin.audit.index', PermissionRegistry::AUDIT_VIEW, 'Access and governance', 20, 30, 'Read-only audit destination reserved for a later authorized phase.', 'audit', 'admin.audit.*'),
            new AdminNavigationItem('settings', 'Site settings', 'admin.settings.index', PermissionRegistry::SETTINGS_VIEW, 'System', 30, 10, 'Governed brand, contact, social and footer settings.', 'settings', 'admin.settings.*'),
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
