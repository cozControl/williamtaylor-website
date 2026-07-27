<?php

namespace App\Domain\Publishing\Contracts;

interface PublicationPolicy
{
    public function allowsSelfApproval(string $pageType): bool;

    public function checksum(): string;
}
