<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Jobs\PublishScheduledSiteContent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class PublishScheduledSiteContentCommand extends Command
{
    protected $signature = 'site-content:publish-scheduled';

    protected $description = 'Dispatch due governed Site Content publication';

    public function handle(): int
    {
        SiteContentPublicationState::query()
            ->where('candidate_state', CandidateState::Scheduled->value)
            ->where('scheduled_for', '<=', now('UTC'))
            ->each(fn (SiteContentPublicationState $state) => PublishScheduledSiteContent::dispatch(
                $state->site_content_id,
                'site-content-'.Str::ulid(),
            )->afterCommit());

        return self::SUCCESS;
    }
}
