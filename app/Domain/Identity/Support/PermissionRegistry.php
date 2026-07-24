<?php

namespace App\Domain\Identity\Support;

final class PermissionRegistry
{
    public const GUARD = 'web';

    public const ADMIN_ACCESS = 'admin.access';

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_MANAGE = 'roles.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const AUDIT_EXPORT = 'audit.export';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const MEDIA_VIEW = 'media.view';

    public const MEDIA_UPLOAD = 'media.upload';

    public const MEDIA_EDIT = 'media.edit';

    public const MEDIA_REPLACE = 'media.replace';

    public const MEDIA_ARCHIVE = 'media.archive';

    public const MEDIA_RESTORE = 'media.restore';

    public const PAGES_VIEW = 'pages.view';

    public const PAGES_CREATE = 'pages.create';

    public const PAGES_EDIT = 'pages.edit';

    public const PAGES_PREVIEW = 'pages.preview';

    public const PAGES_ARCHIVE = 'pages.archive';

    public const PAGES_RESTORE = 'pages.restore';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ADMIN_ACCESS,
            self::USERS_VIEW,
            self::USERS_MANAGE,
            self::ROLES_VIEW,
            self::ROLES_MANAGE,
            self::AUDIT_VIEW,
            self::AUDIT_EXPORT,
            self::SETTINGS_VIEW,
            self::SETTINGS_MANAGE,
            self::MEDIA_VIEW,
            self::MEDIA_UPLOAD,
            self::MEDIA_EDIT,
            self::MEDIA_REPLACE,
            self::MEDIA_ARCHIVE,
            self::MEDIA_RESTORE,
            self::PAGES_VIEW,
            self::PAGES_CREATE,
            self::PAGES_EDIT,
            self::PAGES_PREVIEW,
            self::PAGES_ARCHIVE,
            self::PAGES_RESTORE,
        ];
    }

    public static function contains(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }
}
