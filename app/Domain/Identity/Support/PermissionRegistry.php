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

    public const PRODUCTS_VIEW = 'products.view';

    public const PRODUCTS_MANAGE = 'products.manage';

    public const PAGES_VIEW = 'pages.view';

    public const PAGES_CREATE = 'pages.create';

    public const PAGES_EDIT = 'pages.edit';

    public const PAGES_PREVIEW = 'pages.preview';

    public const PAGES_ARCHIVE = 'pages.archive';

    public const PAGES_RESTORE = 'pages.restore';

    public const PAGES_REVIEW = 'pages.review';

    public const PAGES_APPROVE = 'pages.approve';

    public const PAGES_PUBLISH = 'pages.publish';

    public const PAGES_SCHEDULE = 'pages.schedule';

    public const PAGES_UNPUBLISH = 'pages.unpublish';

    public const NAVIGATION_VIEW = 'navigation.view';

    public const NAVIGATION_EDIT = 'navigation.edit';

    public const NAVIGATION_PREVIEW = 'navigation.preview';

    public const NAVIGATION_REVIEW = 'navigation.review';

    public const NAVIGATION_APPROVE = 'navigation.approve';

    public const NAVIGATION_PUBLISH = 'navigation.publish';

    public const NAVIGATION_SCHEDULE = 'navigation.schedule';

    public const NAVIGATION_UNPUBLISH = 'navigation.unpublish';

    public const ANNOUNCEMENTS_VIEW = 'announcements.view';

    public const ANNOUNCEMENTS_CREATE = 'announcements.create';

    public const ANNOUNCEMENTS_EDIT = 'announcements.edit';

    public const ANNOUNCEMENTS_PREVIEW = 'announcements.preview';

    public const ANNOUNCEMENTS_REVIEW = 'announcements.review';

    public const ANNOUNCEMENTS_APPROVE = 'announcements.approve';

    public const ANNOUNCEMENTS_PUBLISH = 'announcements.publish';

    public const ANNOUNCEMENTS_SCHEDULE = 'announcements.schedule';

    public const ANNOUNCEMENTS_UNPUBLISH = 'announcements.unpublish';

    public const ANNOUNCEMENTS_ARCHIVE = 'announcements.archive';

    public const ANNOUNCEMENTS_RESTORE = 'announcements.restore';

    public const SETTINGS_PREVIEW = 'settings.preview';

    public const SETTINGS_REVIEW = 'settings.review';

    public const SETTINGS_APPROVE = 'settings.approve';

    public const SETTINGS_PUBLISH = 'settings.publish';

    public const SETTINGS_SCHEDULE = 'settings.schedule';

    public const SETTINGS_UNPUBLISH = 'settings.unpublish';

    public const CAMPAIGN_CLAIMS_REVIEW = 'campaigns.claims.review';

    public const CAMPAIGN_CLAIMS_APPROVE = 'campaigns.claims.approve';

    public const CAMPAIGN_CLAIMS_REJECT = 'campaigns.claims.reject';

    public const CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL = 'campaigns.claims.withdraw-approval';

    public const PUBLICATION_EMERGENCY_UNPUBLISH = 'publication.emergency-unpublish';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_CREATE = 'orders.create';

    public const ORDERS_CONFIRM = 'orders.confirm';

    public const ORDERS_PREPARE = 'orders.prepare';

    public const ORDERS_MARK_READY = 'orders.mark-ready';

    public const ORDERS_DISPATCH = 'orders.dispatch';

    public const ORDERS_DELIVER = 'orders.deliver';

    public const ORDERS_CANCEL = 'orders.cancel';

    public const ORDERS_NOTES_CREATE = 'orders.notes.create';

    public const ORDERS_PAYMENT_STATUS_MANAGE = 'orders.payment-status.manage';

    public const ORDERS_RECEIPTS_VIEW = 'orders.receipts.view';

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
            self::PRODUCTS_VIEW,
            self::PRODUCTS_MANAGE,
            self::PAGES_VIEW,
            self::PAGES_CREATE,
            self::PAGES_EDIT,
            self::PAGES_PREVIEW,
            self::PAGES_ARCHIVE,
            self::PAGES_RESTORE,
            self::PAGES_REVIEW,
            self::PAGES_APPROVE,
            self::PAGES_PUBLISH,
            self::PAGES_SCHEDULE,
            self::PAGES_UNPUBLISH,
            self::NAVIGATION_VIEW,
            self::NAVIGATION_EDIT,
            self::NAVIGATION_PREVIEW,
            self::NAVIGATION_REVIEW,
            self::NAVIGATION_APPROVE,
            self::NAVIGATION_PUBLISH,
            self::NAVIGATION_SCHEDULE,
            self::NAVIGATION_UNPUBLISH,
            self::ANNOUNCEMENTS_VIEW,
            self::ANNOUNCEMENTS_CREATE,
            self::ANNOUNCEMENTS_EDIT,
            self::ANNOUNCEMENTS_PREVIEW,
            self::ANNOUNCEMENTS_REVIEW,
            self::ANNOUNCEMENTS_APPROVE,
            self::ANNOUNCEMENTS_PUBLISH,
            self::ANNOUNCEMENTS_SCHEDULE,
            self::ANNOUNCEMENTS_UNPUBLISH,
            self::ANNOUNCEMENTS_ARCHIVE,
            self::ANNOUNCEMENTS_RESTORE,
            self::SETTINGS_PREVIEW,
            self::SETTINGS_REVIEW,
            self::SETTINGS_APPROVE,
            self::SETTINGS_PUBLISH,
            self::SETTINGS_SCHEDULE,
            self::SETTINGS_UNPUBLISH,
            self::CAMPAIGN_CLAIMS_REVIEW,
            self::CAMPAIGN_CLAIMS_APPROVE,
            self::CAMPAIGN_CLAIMS_REJECT,
            self::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL,
            self::PUBLICATION_EMERGENCY_UNPUBLISH,
            self::ORDERS_VIEW,
            self::ORDERS_CREATE,
            self::ORDERS_CONFIRM,
            self::ORDERS_PREPARE,
            self::ORDERS_MARK_READY,
            self::ORDERS_DISPATCH,
            self::ORDERS_DELIVER,
            self::ORDERS_CANCEL,
            self::ORDERS_NOTES_CREATE,
            self::ORDERS_PAYMENT_STATUS_MANAGE,
            self::ORDERS_RECEIPTS_VIEW,        ];
    }

    public static function contains(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }
}
