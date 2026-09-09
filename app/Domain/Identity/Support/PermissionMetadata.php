<?php

namespace App\Domain\Identity\Support;

final class PermissionMetadata
{
    /** @return array<string, array{label: string, area: string, description: string}> */
    public static function all(): array
    {
        return [
            PermissionRegistry::ADMIN_ACCESS => ['label' => 'Enter administration', 'area' => 'Administration', 'description' => 'Open the protected administration workspace.'],
            PermissionRegistry::USERS_VIEW => ['label' => 'View users', 'area' => 'Access management', 'description' => 'Discover users and inspect their effective access.'],
            PermissionRegistry::USERS_MANAGE => ['label' => 'Manage user access', 'area' => 'Access management', 'description' => 'Participate in approved user role changes.'],
            PermissionRegistry::ROLES_VIEW => ['label' => 'View roles', 'area' => 'Access management', 'description' => 'Inspect code-owned roles and permission bundles.'],
            PermissionRegistry::ROLES_MANAGE => ['label' => 'Manage role assignments', 'area' => 'Access management', 'description' => 'Assign or revoke existing registered roles.'],
            PermissionRegistry::AUDIT_VIEW => ['label' => 'View audit', 'area' => 'Governance', 'description' => 'Access an authorized audit-review workspace.'],
            PermissionRegistry::AUDIT_EXPORT => ['label' => 'Export audit', 'area' => 'Governance', 'description' => 'Export audit evidence when a later phase enables it.'],
            PermissionRegistry::SETTINGS_VIEW => ['label' => 'View settings', 'area' => 'System', 'description' => 'Access an authorized settings workspace.'],
            PermissionRegistry::SETTINGS_MANAGE => ['label' => 'Manage settings', 'area' => 'System', 'description' => 'Change typed system settings when a later phase enables it.'],
            PermissionRegistry::MEDIA_VIEW => ['label' => 'View media', 'area' => 'Media', 'description' => 'Search and inspect reusable media assets.'],
            PermissionRegistry::MEDIA_UPLOAD => ['label' => 'Upload media', 'area' => 'Media', 'description' => 'Create signed upload intents and confirm verified assets.'],
            PermissionRegistry::MEDIA_EDIT => ['label' => 'Edit media metadata', 'area' => 'Media', 'description' => 'Manage editorial, accessibility, rights, usage and focal metadata.'],
            PermissionRegistry::MEDIA_REPLACE => ['label' => 'Replace media', 'area' => 'Media', 'description' => 'Replace a provider binary while preserving logical identity.'],
            PermissionRegistry::MEDIA_ARCHIVE => ['label' => 'Archive media', 'area' => 'Media', 'description' => 'Prevent new usage while preserving existing references.'],
            PermissionRegistry::MEDIA_RESTORE => ['label' => 'Restore media', 'area' => 'Media', 'description' => 'Restore a provider-verified archived asset.'],
            PermissionRegistry::PAGES_VIEW => ['label' => 'View pages', 'area' => 'Content', 'description' => 'Search and inspect draft pages and immutable revision history.'],
            PermissionRegistry::PAGES_CREATE => ['label' => 'Create pages', 'area' => 'Content', 'description' => 'Create an approved typed page with an initial immutable draft.'],
            PermissionRegistry::PAGES_EDIT => ['label' => 'Edit draft pages', 'area' => 'Content', 'description' => 'Create validated immutable draft revisions.'],
            PermissionRegistry::PAGES_PREVIEW => ['label' => 'Preview draft pages', 'area' => 'Content', 'description' => 'Open authenticated short-lived previews of immutable revisions.'],
            PermissionRegistry::PAGES_ARCHIVE => ['label' => 'Archive pages', 'area' => 'Content', 'description' => 'Archive an active draft while preserving revision history.'],
            PermissionRegistry::PAGES_RESTORE => ['label' => 'Restore pages', 'area' => 'Content', 'description' => 'Restore a compatible archived page to active draft.'],
            PermissionRegistry::PAGES_REVIEW => ['label' => 'Review pages', 'area' => 'Content publishing', 'description' => 'Inspect submitted revisions and request editorial changes.'],
            PermissionRegistry::PAGES_APPROVE => ['label' => 'Approve pages', 'area' => 'Content publishing', 'description' => 'Approve a ready immutable candidate revision.'],
            PermissionRegistry::PAGES_PUBLISH => ['label' => 'Publish pages', 'area' => 'Content publishing', 'description' => 'Designate an approved revision as internally published.'],
            PermissionRegistry::PAGES_SCHEDULE => ['label' => 'Schedule pages', 'area' => 'Content publishing', 'description' => 'Schedule or cancel publication of an approved revision.'],
            PermissionRegistry::PAGES_UNPUBLISH => ['label' => 'Unpublish pages', 'area' => 'Content publishing', 'description' => 'Remove the internal published designation with a reason.'],
            PermissionRegistry::ANNOUNCEMENTS_APPROVE => ['label' => 'Approve announcements', 'area' => 'Announcements', 'description' => 'Approve announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_ARCHIVE => ['label' => 'Archive announcements', 'area' => 'Announcements', 'description' => 'Archive announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_CREATE => ['label' => 'Create announcements', 'area' => 'Announcements', 'description' => 'Create announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_EDIT => ['label' => 'Edit announcements', 'area' => 'Announcements', 'description' => 'Edit announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_PREVIEW => ['label' => 'Preview announcements', 'area' => 'Announcements', 'description' => 'Preview announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_PUBLISH => ['label' => 'Publish announcements', 'area' => 'Announcements', 'description' => 'Publish announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_RESTORE => ['label' => 'Restore announcements', 'area' => 'Announcements', 'description' => 'Restore announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_REVIEW => ['label' => 'Review announcements', 'area' => 'Announcements', 'description' => 'Review announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_SCHEDULE => ['label' => 'Schedule announcements', 'area' => 'Announcements', 'description' => 'Schedule announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_UNPUBLISH => ['label' => 'Unpublish announcements', 'area' => 'Announcements', 'description' => 'Unpublish announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::ANNOUNCEMENTS_VIEW => ['label' => 'View announcements', 'area' => 'Announcements', 'description' => 'View announcements through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_APPROVE => ['label' => 'Approve navigation', 'area' => 'Navigation', 'description' => 'Approve navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_EDIT => ['label' => 'Edit navigation', 'area' => 'Navigation', 'description' => 'Edit navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_PREVIEW => ['label' => 'Preview navigation', 'area' => 'Navigation', 'description' => 'Preview navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_PUBLISH => ['label' => 'Publish navigation', 'area' => 'Navigation', 'description' => 'Publish navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_REVIEW => ['label' => 'Review navigation', 'area' => 'Navigation', 'description' => 'Review navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_SCHEDULE => ['label' => 'Schedule navigation', 'area' => 'Navigation', 'description' => 'Schedule navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_UNPUBLISH => ['label' => 'Unpublish navigation', 'area' => 'Navigation', 'description' => 'Unpublish navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::NAVIGATION_VIEW => ['label' => 'View navigation', 'area' => 'Navigation', 'description' => 'View navigation through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_APPROVE => ['label' => 'Approve site settings', 'area' => 'Site settings', 'description' => 'Approve site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_PREVIEW => ['label' => 'Preview site settings', 'area' => 'Site settings', 'description' => 'Preview site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_PUBLISH => ['label' => 'Publish site settings', 'area' => 'Site settings', 'description' => 'Publish site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_REVIEW => ['label' => 'Review site settings', 'area' => 'Site settings', 'description' => 'Review site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_SCHEDULE => ['label' => 'Schedule site settings', 'area' => 'Site settings', 'description' => 'Schedule site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::SETTINGS_UNPUBLISH => ['label' => 'Unpublish site settings', 'area' => 'Site settings', 'description' => 'Unpublish site settings through the governed typed Site Content workflow.'],
            PermissionRegistry::CAMPAIGN_CLAIMS_REVIEW => ['label' => 'Review Campaign claims', 'area' => 'Campaign claims', 'description' => 'Inspect bounded Campaign claim and evidence summaries.'],
            PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE => ['label' => 'Approve Campaign claims', 'area' => 'Campaign claims', 'description' => 'Approve a submitted factual Campaign claim.'],
            PermissionRegistry::CAMPAIGN_CLAIMS_REJECT => ['label' => 'Reject Campaign claims', 'area' => 'Campaign claims', 'description' => 'Reject a submitted Campaign claim with a bounded reason.'],
            PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL => ['label' => 'Withdraw Campaign claim approval', 'area' => 'Campaign claims', 'description' => 'Withdraw current Campaign claim approval with a bounded reason.'],
            PermissionRegistry::ORDERS_VIEW => ['label' => 'View Orders', 'area' => 'Order operations', 'description' => 'Search and inspect protected demo customer Orders.'],
            PermissionRegistry::ORDERS_CREATE => ['label' => 'Create demo Orders', 'area' => 'Order operations', 'description' => 'Create a validated snapshot-based demo Order.'],
            PermissionRegistry::ORDERS_CONFIRM => ['label' => 'Confirm Orders', 'area' => 'Order operations', 'description' => 'Confirm an eligible new Order.'],
            PermissionRegistry::ORDERS_PREPARE => ['label' => 'Prepare Orders', 'area' => 'Order operations', 'description' => 'Start preparation for a confirmed Order.'],
            PermissionRegistry::ORDERS_MARK_READY => ['label' => 'Mark Orders ready', 'area' => 'Order operations', 'description' => 'Mark an Order ready for dispatch.'],
            PermissionRegistry::ORDERS_DISPATCH => ['label' => 'Dispatch Orders', 'area' => 'Order operations', 'description' => 'Record demonstration dispatch.'],
            PermissionRegistry::ORDERS_DELIVER => ['label' => 'Deliver Orders', 'area' => 'Order operations', 'description' => 'Complete an eligible dispatched Order.'],
            PermissionRegistry::ORDERS_CANCEL => ['label' => 'Cancel Orders', 'area' => 'Order operations', 'description' => 'Cancel an eligible Order with a reason.'],
            PermissionRegistry::ORDERS_NOTES_CREATE => ['label' => 'Add Order notes', 'area' => 'Order operations', 'description' => 'Add immutable internal Order notes.'],
            PermissionRegistry::ORDERS_PAYMENT_STATUS_MANAGE => ['label' => 'Manage manual payment status', 'area' => 'Order operations', 'description' => 'Change informational demonstration payment state with a reason.'],
            PermissionRegistry::ORDERS_RECEIPTS_VIEW => ['label' => 'View demo receipts', 'area' => 'Order operations', 'description' => 'View protected non-fiscal demo receipts for manually paid Orders.'],            PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH => ['label' => 'Emergency unpublish', 'area' => 'Publication governance', 'description' => 'Immediately remove an existing public designation and force its code-owned static fallback.'],
        ];
    }

    public static function isSensitive(string $permission): bool
    {
        return in_array($permission, [
            PermissionRegistry::CAMPAIGN_CLAIMS_REVIEW,
            PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE,
            PermissionRegistry::CAMPAIGN_CLAIMS_REJECT,
            PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL,
            PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH,
        ], true);
    }

    /** @return array{label: string, area: string, description: string} */
    public static function for(string $permission): array
    {
        return self::all()[$permission];
    }
}
