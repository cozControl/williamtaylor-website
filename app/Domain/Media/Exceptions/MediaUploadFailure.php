<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;
use Throwable;

final class MediaUploadFailure extends RuntimeException
{
    public function __construct(public readonly string $failureCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
