<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignRevision;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignContentSchema;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReviseCampaign
{
    public function __construct(private CampaignContentSchema $content, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    /** @param array<string,mixed> $content */
    public function handle(User $actor, Campaign $campaign, string $expected, array $content): CampaignRevision
    {
        $n = $this->content->normalize($content);

        return DB::transaction(function () use ($actor, $campaign, $expected, $n): CampaignRevision {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! hash_equals($expected, $this->states->identity($c))) {
                throw new StaleCampaignState;
            } if ($c->archived_at) {
                throw new InvalidArgumentException('Archived Campaigns are read-only.');
            }
            $checksum = $this->content->checksum($n);
            if ($c->currentDraftRevision?->checksum === $checksum) {
                throw new InvalidArgumentException('Identical Campaign revision.');
            }
            $r = CampaignRevision::query()->create([...$n, 'campaign_id' => $c->id, 'revision_number' => (int) CampaignRevision::query()->where('campaign_id', $c->id)->max('revision_number') + 1, 'checksum' => $checksum, 'created_by' => $actor->id, 'created_at' => now('UTC')]);
            $c->forceFill(['current_draft_revision_id' => $r->id, 'approved_revision_id' => null, 'lock_version' => $c->lock_version + 1])->save();
            $this->audit->handle('campaign.revised', $c, $actor, null, ['revision_number' => $r->revision_number, 'checksum' => $checksum]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $r;
        }, 3);
    }
}
