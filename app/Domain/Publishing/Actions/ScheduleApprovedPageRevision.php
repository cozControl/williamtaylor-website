<?php

namespace App\Domain\Publishing\Actions;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Models\User;
use Carbon\CarbonInterface;

final class ScheduleApprovedPageRevision
{
    public function __construct(private PagePublishingWorkflow $workflow) {}

    public function handle(User $actor, Page $page, CarbonInterface $scheduledForUtc, string $fingerprint): PagePublicationState
    {
        return $this->workflow->schedule($actor, $page, $scheduledForUtc, $fingerprint);
    }
}
