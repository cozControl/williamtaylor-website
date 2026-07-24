<?php

namespace App\Domain\Media\Exceptions;

use App\Domain\Media\Models\MediaAsset;
use RuntimeException;

final class ExactDuplicateMediaException extends RuntimeException
{
    public function __construct(public readonly MediaAsset $candidate)
    {
        parent::__construct('An exact media duplicate requires an explicit reuse or override decision.');
    }
}
