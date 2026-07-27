<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignMediaRoleRegistry;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateCampaignMediaUsage
{
    public function __construct(private CampaignMediaRoleRegistry $roles, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, MediaUsage $usage, string $expected, ?string $alt): MediaUsage
    {
        return DB::transaction(function () use ($actor, $campaign, $usage, $expected, $alt): MediaUsage {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = MediaUsage::query()->with('asset')->lockForUpdate()->findOrFail($usage->id);
            if (! hash_equals($expected, $this->states->media($c->id))) {
                throw new StaleCampaignState;
            }
            $this->roles->get(Campaign::class, $item->field_role);
            $effective = trim($alt ?? (string) $item->asset?->default_alt_text);
            if ($item->owner_type !== Campaign::class || $item->owner_identifier !== $c->id || ! $item->asset || $item->asset->state !== MediaAssetState::Ready || $item->asset->resource_type !== MediaResourceType::Image || ! $item->asset->confirmed_at || $effective === '' || $effective !== strip_tags($effective)) {
                throw new InvalidArgumentException('Campaign media usage is invalid.');
            }
            $before = ['alt_text_override' => $item->alt_text_override];
            $item->forceFill(['alt_text_override' => $alt === null ? null : trim($alt), 'decorative_override' => false])->save();
            $c->increment('lock_version');
            $this->audit->handle('campaign.media.updated', $c, $actor, $before, ['usage_id' => $item->id, 'alt_text_override' => $item->alt_text_override]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $item;
        }, 3);
    }
}
