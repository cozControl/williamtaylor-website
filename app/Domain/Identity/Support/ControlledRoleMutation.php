<?php

namespace App\Domain\Identity\Support;

use Closure;
use LogicException;

final class ControlledRoleMutation
{
    private int $depth = 0;

    public function run(Closure $mutation): mixed
    {
        $this->depth++;

        try {
            return $mutation();
        } finally {
            $this->depth--;
        }
    }

    public function assertActive(): void
    {
        if ($this->depth < 1) {
            throw new LogicException('Role changes must use a project-owned identity action.');
        }
    }
}
