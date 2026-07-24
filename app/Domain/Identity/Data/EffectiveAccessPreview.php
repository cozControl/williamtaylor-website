<?php

namespace App\Domain\Identity\Data;

final readonly class EffectiveAccessPreview
{
    /**
     * @param  list<string>  $currentRoles
     * @param  list<string>  $proposedRoles
     * @param  list<string>  $currentEffectivePermissions
     * @param  list<string>  $proposedEffectivePermissions
     * @param  list<string>  $gainedPermissions
     * @param  list<string>  $lostPermissions
     * @param  array<string, list<string>>  $currentPermissionSources
     * @param  array<string, list<string>>  $proposedPermissionSources
     * @param  list<string>  $duplicateGrants
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $currentRoles,
        public array $proposedRoles,
        public array $currentEffectivePermissions,
        public array $proposedEffectivePermissions,
        public array $gainedPermissions,
        public array $lostPermissions,
        public array $currentPermissionSources,
        public array $proposedPermissionSources,
        public array $duplicateGrants,
        public bool $administrativeAccessChanges,
        public bool $willHaveAdministrativeAccess,
        public bool $superAdministratorChanges,
        public bool $willBeSuperAdministrator,
        public array $warnings,
        public string $fingerprint,
    ) {}
}
