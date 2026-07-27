<?php

namespace App\Jobs;

use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PublishScheduledSiteContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $siteContentId, public string $jobIdentity) {}

    public function handle(SiteContentWorkflow $workflow): void
    {
        $workflow->publishScheduled(SiteContent::query()->findOrFail($this->siteContentId), $this->jobIdentity);
    }
}
