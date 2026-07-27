<?php

namespace App\Domain\Publishing\Actions;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Models\User;

final class SubmitPageForReview
{
    public function __construct(private PagePublishingWorkflow $workflow) {}

    public function handle(User $actor, Page $page, string $note, string $fingerprint, bool $confirmSupersession = false, ?string $reason = null): PagePublicationState
    {
        return $this->workflow->submit($actor, $page, $note, $fingerprint, $confirmSupersession, $reason);
    }
}
