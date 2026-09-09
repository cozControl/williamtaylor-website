<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use Illuminate\Support\Facades\Schema;

final class LimitedEditionCampaignPresenter
{
    public function __construct(private CampaignPublicProjection $projection) {}

    /** @return list<array<string, mixed>> */
    public function all(?int $limit = null): array
    {
        if (! Schema::hasTable('campaigns') || ! Schema::hasTable('campaign_claims')) {
            return [];
        }

        $campaigns = Campaign::query()->where('campaign_type', 'limited_edition')->whereNull('archived_at')
            ->with(['approvedRevision', 'currentDraftRevision', 'products.product.currentDraftRevision', 'claims', 'cardMedia.asset'])->orderBy('starts_at')->orderBy('id')->lazy(20)
            ->map(fn (Campaign $campaign) => $this->present($campaign))->filter();

        return ($limit === null ? $campaigns : $campaigns->take($limit))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function present(Campaign $campaign): ?array
    {
        $base = $this->projection->resolve($campaign, 'limited_edition');
        if ($base === null) {
            return null;
        }
        $editionStatement = trim((string) ($base['claims']['edition_statement'] ?? ''));
        if ($editionStatement === '') {
            return null;
        }

        return [...$base, 'badge' => 'LIMITED', 'edition_statement' => $editionStatement];
    }
}
