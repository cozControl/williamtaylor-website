<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignRevision;
use App\Domain\Campaign\Support\CampaignContentSchema;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateCampaign
{
    public function __construct(private CampaignTypeRegistry $types, private CampaignContentSchema $content, private RecordAuditEvent $audit) {}

    /** @param array<string,mixed> $content */
    public function handle(User $actor, string $type, string $code, array $content): Campaign
    {
        $this->types->get($type);
        $code = Str::slug($code, '_');
        if (strlen($code) < 3) {
            throw new InvalidArgumentException('Campaign code is invalid.');
        }
        $n = $this->content->normalize($content);
        try {
            return DB::transaction(function () use ($actor, $type, $code, $n): Campaign {
                $c = Campaign::query()->create(['campaign_type' => $type, 'internal_code' => $code, 'lifecycle_status' => 'draft', 'business_timezone' => 'Africa/Nairobi', 'created_by' => $actor->id]);
                $r = CampaignRevision::query()->create([...$n, 'campaign_id' => $c->id, 'revision_number' => 1, 'checksum' => $this->content->checksum($n), 'created_by' => $actor->id, 'created_at' => now('UTC')]);
                $c->forceFill(['current_draft_revision_id' => $r->id])->save();
                $this->audit->handle('campaign.created', $c, $actor, null, ['type' => $type, 'revision_number' => 1, 'checksum' => $r->checksum]);

                return $c->refresh();
            }, 3);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new InvalidArgumentException('Campaign code is already in use.', previous: $e);
            } throw $e;
        }
    }
}
