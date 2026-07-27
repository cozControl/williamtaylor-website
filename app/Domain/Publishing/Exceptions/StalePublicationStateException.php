<?php

namespace App\Domain\Publishing\Exceptions;

use RuntimeException;

final class StalePublicationStateException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Publishing state changed after confirmation. Review the refreshed workflow and confirm again.');
    }
}
