<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Jobs\PublishScheduledPage;
use Illuminate\Console\Command;

final class PublishScheduledPagesCommand extends Command
{
    protected $signature = 'content:publish-scheduled-pages';

    protected $description = 'Dispatch idempotent publication jobs for due Page revisions.';

    public function handle(): int
    {
        $count = 0;
        PagePublicationState::query()
            ->where('candidate_state', CandidateState::Scheduled->value)
            ->where('scheduled_for', '<=', now('UTC'))
            ->orderBy('scheduled_for')
            ->select(['id', 'page_id', 'state_version'])
            ->chunkById(100, function ($states) use (&$count): void {
                foreach ($states as $state) {
                    PublishScheduledPage::dispatch(
                        $state->page_id,
                        'scheduled:'.$state->getKey().':'.$state->state_version,
                    );
                    $count++;
                }
            });
        $this->info("Dispatched {$count} scheduled Page publication job(s).");

        return self::SUCCESS;
    }
}
