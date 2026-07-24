<?php

namespace App\Domain\Identity\Data;

final readonly class EffectiveUserAccess
{
    /**
     * @param  list<string>  $assignedRoles
     * @param  list<string>  $effectivePermissions
     * @param  array<string, list<string>>  $permissionSources
     * @param  list<string>  $duplicateGrants
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public array $assignedRoles,
        public array $effectivePermissions,
        public array $permissionSources,
        public array $duplicateGrants,
        public bool $hasAdministrativeAccess,
        public bool $usesSuperAdministratorBypass,
        public array $warnings,
    ) {}
}
