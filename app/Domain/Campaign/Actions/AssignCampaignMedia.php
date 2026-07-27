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
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignCampaignMedia
{
    public function __construct(private CampaignMediaRoleRegistry $roles, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, MediaAsset $asset, string $expected, string $role = 'card', ?string $alt = null): MediaUsage
    {
        $this->roles->get(Campaign::class, $role);

        return DB::transaction(function () use ($actor, $campaign, $asset, $expected, $role, $alt): MediaUsage {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $usages = MediaUsage::query()->where('owner_type', Campaign::class)->where('owner_identifier', $c->id)->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->media($c->id))) {
                throw new StaleCampaignState;
            }$a = MediaAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $effective = trim($alt ?? (string) $a->default_alt_text);
            if ($a->state !== MediaAssetState::Ready || $a->resource_type !== MediaResourceType::Image || ! $a->confirmed_at || $effective === '' || $effective !== strip_tags($effective) || $usages->contains('field_role', $role)) {
                throw new InvalidArgumentException('Campaign media requires one ready meaningful image.');
            }$u = MediaUsage::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $a->id, 'owner_type' => Campaign::class, 'owner_identifier' => $c->id, 'field_role' => $role, 'alt_text_override' => $alt, 'decorative_override' => false, 'sort_order' => 0]);
            $c->increment('lock_version');
            $this->audit->handle('campaign.media.assigned', $c, $actor, null, ['usage_id' => $u->id, 'role' => $role]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $u;
        }, 3);
    }
}
