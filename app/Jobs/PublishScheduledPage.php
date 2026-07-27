<?php

namespace App\Jobs;

use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

final class PublishScheduledPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $pageId, public string $jobIdentity = '')
    {
        $this->jobIdentity = $jobIdentity === '' ? (string) Str::ulid() : $jobIdentity;
        $this->onQueue('publishing');
    }

    public function handle(PagePublishingWorkflow $workflow): void
    {
        $workflow->publishScheduled(Page::query()->findOrFail($this->pageId), $this->jobIdentity);
    }
}
