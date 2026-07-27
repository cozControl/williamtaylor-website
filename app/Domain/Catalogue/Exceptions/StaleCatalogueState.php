<?php

namespace App\Domain\Catalogue\Exceptions;

use RuntimeException;

final class StaleCatalogueState extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Catalogue state changed. Refresh and try again.');
    }
}
