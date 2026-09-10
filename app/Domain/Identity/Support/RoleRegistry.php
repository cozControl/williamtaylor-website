<?php

namespace App\Domain\Identity\Support;

final class RoleRegistry
{
    public const SUPER_ADMINISTRATOR = 'Super Administrator';

    public const CMS_MANAGER = 'CMS Manager';

    public const INVENTORY_MANAGER = 'Inventory Manager';

    public const CAMPAIGN_CLAIMS_APPROVER = 'Campaign Claims Approver';

    /** @return array<string, list<string>> */
    public static function permissionBundles(): array
    {
        return [
            self::SUPER_ADMINISTRATOR => PermissionRegistry::all(),
            self::CAMPAIGN_CLAIMS_APPROVER => [
                PermissionRegistry::CAMPAIGN_CLAIMS_REVIEW,
                PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE,
                PermissionRegistry::CAMPAIGN_CLAIMS_REJECT,
                PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL,
            ],
            self::INVENTORY_MANAGER => [
                PermissionRegistry::ADMIN_ACCESS,
                PermissionRegistry::PRODUCTS_VIEW,
                PermissionRegistry::INVENTORY_VIEW,
                PermissionRegistry::INVENTORY_MANAGE,
            ],
            self::CMS_MANAGER => [
                PermissionRegistry::ADMIN_ACCESS,
                PermissionRegistry::AUDIT_VIEW,
                PermissionRegistry::SETTINGS_VIEW,
                PermissionRegistry::MEDIA_VIEW,
                PermissionRegistry::MEDIA_UPLOAD,
                PermissionRegistry::MEDIA_EDIT,
                PermissionRegistry::MEDIA_REPLACE,
                PermissionRegistry::MEDIA_ARCHIVE,
                PermissionRegistry::MEDIA_RESTORE,
                PermissionRegistry::PRODUCTS_VIEW,
                PermissionRegistry::PRODUCTS_MANAGE,
                PermissionRegistry::PAGES_VIEW,
                PermissionRegistry::PAGES_CREATE,
                PermissionRegistry::PAGES_EDIT,
                PermissionRegistry::PAGES_PREVIEW,
                PermissionRegistry::PAGES_ARCHIVE,
                PermissionRegistry::PAGES_RESTORE,
                PermissionRegistry::PAGES_REVIEW,
                PermissionRegistry::PAGES_APPROVE,
                PermissionRegistry::PAGES_PUBLISH,
                PermissionRegistry::PAGES_SCHEDULE,
                PermissionRegistry::PAGES_UNPUBLISH,
                PermissionRegistry::SETTINGS_MANAGE,
                PermissionRegistry::NAVIGATION_VIEW,
                PermissionRegistry::NAVIGATION_EDIT,
                PermissionRegistry::NAVIGATION_PREVIEW,
                PermissionRegistry::NAVIGATION_REVIEW,
                PermissionRegistry::NAVIGATION_APPROVE,
                PermissionRegistry::NAVIGATION_PUBLISH,
                PermissionRegistry::NAVIGATION_SCHEDULE,
                PermissionRegistry::NAVIGATION_UNPUBLISH,
                PermissionRegistry::ANNOUNCEMENTS_VIEW,
                PermissionRegistry::ANNOUNCEMENTS_CREATE,
                PermissionRegistry::ANNOUNCEMENTS_EDIT,
                PermissionRegistry::ANNOUNCEMENTS_PREVIEW,
                PermissionRegistry::ANNOUNCEMENTS_REVIEW,
                PermissionRegistry::ANNOUNCEMENTS_APPROVE,
                PermissionRegistry::ANNOUNCEMENTS_PUBLISH,
                PermissionRegistry::ANNOUNCEMENTS_SCHEDULE,
                PermissionRegistry::ANNOUNCEMENTS_UNPUBLISH,
                PermissionRegistry::ANNOUNCEMENTS_ARCHIVE,
                PermissionRegistry::ANNOUNCEMENTS_RESTORE,
                PermissionRegistry::SETTINGS_PREVIEW,
                PermissionRegistry::SETTINGS_REVIEW,
                PermissionRegistry::SETTINGS_APPROVE,
                PermissionRegistry::SETTINGS_PUBLISH,
                PermissionRegistry::SETTINGS_SCHEDULE,
                PermissionRegistry::SETTINGS_UNPUBLISH,
            ],
        ];
    }

    public static function contains(string $role): bool
    {
        return array_key_exists($role, self::permissionBundles());
    }
}
