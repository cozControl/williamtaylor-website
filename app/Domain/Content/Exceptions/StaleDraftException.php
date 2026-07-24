<?php

namespace App\Domain\Content\Exceptions;

use RuntimeException;

final class StaleDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This page has a newer draft revision. Reload before saving; your unsaved fields remain available.');
    }
}
