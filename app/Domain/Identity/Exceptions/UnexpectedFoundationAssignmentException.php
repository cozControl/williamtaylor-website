<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class UnexpectedFoundationAssignmentException extends RuntimeException
{
    /**
     * @param  array<int, array{permission: string, model_type: string, model_identifier: string}>  $directAssignments
     * @param  array<int, array{permission: string, role: string}>  $unexpectedRoleAssignments
     */
    public function __construct(
        public readonly array $directAssignments,
        public readonly array $unexpectedRoleAssignments,
    ) {
        parent::__construct('Foundation alignment aborted because unexpected permission assignments exist.');
    }
}
