<?php

namespace App\Domain\Identity\Support;

final class RoleRegistry
{
    public const SUPER_ADMINISTRATOR = 'Super Administrator';

    public const CMS_MANAGER = 'CMS Manager';

    /** @return array<string, list<string>> */
    public static function permissionBundles(): array
    {
        return [
            self::SUPER_ADMINISTRATOR => PermissionRegistry::all(),
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
                PermissionRegistry::PAGES_VIEW,
                PermissionRegistry::PAGES_CREATE,
                PermissionRegistry::PAGES_EDIT,
                PermissionRegistry::PAGES_PREVIEW,
                PermissionRegistry::PAGES_ARCHIVE,
                PermissionRegistry::PAGES_RESTORE,
            ],
        ];
    }

    public static function contains(string $role): bool
    {
        return array_key_exists($role, self::permissionBundles());
    }
}
