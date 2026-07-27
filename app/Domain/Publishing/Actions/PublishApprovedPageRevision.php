<?php

namespace App\Domain\Publishing\Actions;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Models\User;

final class PublishApprovedPageRevision
{
    public function __construct(private PagePublishingWorkflow $workflow) {}

    public function handle(User $actor, Page $page, string $fingerprint): PagePublicationState
    {
        return $this->workflow->publishNow($actor, $page, $fingerprint);
    }
}
