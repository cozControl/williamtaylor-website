<?php

namespace App\Domain\Admin\Navigation;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\Gate;

final class AdminNavigationRegistry
{
    /** @return list<AdminNavigationItem> */
    public function all(): array
    {
        return [
            new AdminNavigationItem('dashboard', 'Dashboard', 'admin.dashboard', PermissionRegistry::ADMIN_ACCESS, 'Overview', 10, 10, 'Administration workspace overview.', 'dashboard', 'admin.dashboard'),
            new AdminNavigationItem('homepage', 'Homepage', 'admin.homepage.edit', PermissionRegistry::SETTINGS_VIEW, 'Website', 30, 2, 'Manage the storefront Homepage Hero.', 'pages', 'admin.homepage.*'),
            new AdminNavigationItem('settings', 'Site settings', 'admin.settings.index', PermissionRegistry::SETTINGS_VIEW, 'Website', 30, 4, 'Brand, contact, social and footer settings.', 'settings', 'admin.settings.*'),
            new AdminNavigationItem('pages', 'Pages', 'admin.content.pages.index', PermissionRegistry::PAGES_VIEW, 'Website', 30, 5, 'Informational website pages.', 'pages', 'admin.content.pages.*'),
            new AdminNavigationItem('navigation', 'Navigation', 'admin.content.navigation.index', PermissionRegistry::NAVIGATION_VIEW, 'Website', 30, 8, 'Primary and footer navigation.', 'navigation', 'admin.content.navigation.*'),
            new AdminNavigationItem('announcements', 'Announcements', 'admin.content.announcements.index', PermissionRegistry::ANNOUNCEMENTS_VIEW, 'Website', 30, 9, 'Website announcements and schedules.', 'announcements', 'admin.content.announcements.*'),
            new AdminNavigationItem('media', 'Media library', 'admin.media.index', PermissionRegistry::MEDIA_VIEW, 'Website', 30, 10, 'Images and videos used across the website.', 'media', 'admin.media.*'),
            new AdminNavigationItem('products', 'Products', 'admin.products.index', PermissionRegistry::PRODUCTS_VIEW, 'Catalogue', 20, 10, 'Products, options, variants and images.', 'products', 'admin.products.*'),
            new AdminNavigationItem('product-categories', 'Categories', 'admin.product-categories.index', PermissionRegistry::PRODUCTS_VIEW, 'Catalogue', 20, 20, 'Product category hierarchy.', 'navigation', 'admin.product-categories.*'),
            new AdminNavigationItem('collections', 'Collections', 'admin.collections.index', PermissionRegistry::PRODUCTS_VIEW, 'Catalogue', 20, 30, 'Storefront ranges and featured edits.', 'pages', 'admin.collections.*'),
            new AdminNavigationItem('orders', 'Orders', 'admin.orders.index', PermissionRegistry::ORDERS_VIEW, 'Commerce', 25, 10, 'Customer Order operations.', 'orders', 'admin.orders.*'),
            new AdminNavigationItem('users', 'Users', 'admin.access.users.index', PermissionRegistry::USERS_VIEW, 'Administration', 40, 10, 'Staff access management.', 'users', 'admin.access.users.*'),
            new AdminNavigationItem('roles', 'Roles', 'admin.access.roles.index', PermissionRegistry::ROLES_VIEW, 'Administration', 40, 20, 'Role and permission management.', 'roles', 'admin.access.roles.*'),
            new AdminNavigationItem('audit', 'Audit log', 'admin.audit.index', PermissionRegistry::AUDIT_VIEW, 'Administration', 40, 30, 'Recorded administration activity.', 'audit', 'admin.audit.*'),
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
            fn (AdminNavigationItem $item): bool => Gate::forUser($user)->allows($item->permission)
                && ($item->key !== 'orders' || app(DemoMode::class)->configured()),
        ));
    }

    /** @return array<string, list<AdminNavigationItem>> */
    public function groupedVisibleFor(?User $user): array
    {
        $groups = [];

        $items = $this->visibleFor($user);
        usort($items, fn (AdminNavigationItem $left, AdminNavigationItem $right): int => [$left->groupOrder, $left->order] <=> [$right->groupOrder, $right->order]);

        foreach ($items as $item) {
            $groups[$item->group][] = $item;
        }

        return $groups;
    }
}
