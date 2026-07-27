<?php

namespace App\Domain\Merchandising\Exceptions;

use RuntimeException;

final class StaleMerchandisingState extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Merchandising state changed. Refresh and try again.');
    }
}
