<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class FinalSuperAdministratorException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The final Super Administrator role assignment cannot be removed.');
    }
}
