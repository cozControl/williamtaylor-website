<?php

namespace App\Domain\Identity\Exceptions;

use InvalidArgumentException;

final class UnregisteredRoleException extends InvalidArgumentException
{
    public function __construct(string $role)
    {
        parent::__construct("Role [{$role}] is not registered by the application.");
    }
}
