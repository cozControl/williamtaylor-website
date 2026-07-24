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
        ];
    }

    /** @return array{label: string, area: string, description: string} */
    public static function for(string $permission): array
    {
        return self::all()[$permission];
    }
}
