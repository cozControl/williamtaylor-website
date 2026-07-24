<?php

namespace App\Domain\Identity\Support;

final readonly class FoundationRegistryAlignmentPlan
{
    /**
     * @param  list<string>  $additions
     * @param  list<string>  $removals
     * @param  array<string, array{before: array<int, string>, after: array<int, string>}>  $roleBundleChanges
     * @param  array<int, array{permission: string, model_type: string, model_identifier: string}>  $directAssignments
     * @param  array<int, array{permission: string, role: string}>  $unexpectedRoleAssignments
     * @param  array<int, string>  $cmsManagerUserIdentifiers
     */
    public function __construct(
        public array $additions,
        public array $removals,
        public array $roleBundleChanges,
        public array $directAssignments,
        public array $unexpectedRoleAssignments,
        public array $cmsManagerUserIdentifiers,
        public bool $applied = false,
    ) {}

    public function hasChanges(): bool
    {
        return $this->additions !== [] || $this->removals !== [] || $this->roleBundleChanges !== [];
    }

    public function hasUnexpectedAssignments(): bool
    {
        return $this->directAssignments !== [] || $this->unexpectedRoleAssignments !== [];
    }
}
