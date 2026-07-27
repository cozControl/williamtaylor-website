<?php

namespace App\Domain\Publishing\Actions;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Models\User;

final class RequestPageChanges
{
    public function __construct(private PagePublishingWorkflow $workflow) {}

    public function handle(User $actor, Page $page, string $note): PagePublicationState
    {
        return $this->workflow->requestChanges($actor, $page, $note);
    }
}
