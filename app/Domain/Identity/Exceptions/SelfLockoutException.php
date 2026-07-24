<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class SelfLockoutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You cannot remove your own access to administration.');
    }
}
