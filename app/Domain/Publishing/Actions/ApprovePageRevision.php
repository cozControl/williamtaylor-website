<?php

namespace App\Domain\Publishing\Actions;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Models\User;

final class ApprovePageRevision
{
    public function __construct(private PagePublishingWorkflow $workflow) {}

    public function handle(User $actor, Page $page, string $note, string $fingerprint): PagePublicationState
    {
        return $this->workflow->approve($actor, $page, $note, $fingerprint);
    }
}
