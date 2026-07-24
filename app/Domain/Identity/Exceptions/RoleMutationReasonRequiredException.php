<?php

namespace App\Domain\Identity\Exceptions;

use InvalidArgumentException;

final class RoleMutationReasonRequiredException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('A reason is required for every role change.');
    }
}
