<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScheduleCampaign
{
    public function __construct(private CampaignTypeRegistry $types, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, string $expected, string $localStart, string $localEnd, string $timezone = 'Africa/Nairobi'): void
    {
        if ($timezone !== 'Africa/Nairobi') {
            throw new InvalidArgumentException('Unsupported business timezone.');
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $localStart, $timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $localEnd, $timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid Campaign local time.');
        }
        if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Campaign end must be after start.');
        }
        DB::transaction(function () use ($actor, $campaign, $expected, $start, $end, $timezone): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! hash_equals($expected, $this->states->identity($c))) {
                throw new StaleCampaignState;
            }$this->types->get($c->campaign_type);
            if ($c->archived_at) {
                throw new InvalidArgumentException('Archived Campaign cannot be scheduled.');
            }$before = ['starts_at' => $c->starts_at?->toISOString(), 'ends_at' => $c->ends_at?->toISOString()];
            $c->forceFill(['starts_at' => $start->utc(), 'ends_at' => $end->utc(), 'business_timezone' => $timezone, 'approved_revision_id' => null, 'lock_version' => $c->lock_version + 1])->save();
            $this->audit->handle($before['starts_at'] ? 'campaign.rescheduled' : 'campaign.scheduled', $c, $actor, $before, ['starts_at' => $start->utc()->toISOString(), 'ends_at' => $end->utc()->toISOString()]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
