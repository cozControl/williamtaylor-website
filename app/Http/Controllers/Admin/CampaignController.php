<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Actions\ApproveCampaignClaim;
use App\Domain\Campaign\Actions\AssignCampaignMedia;
use App\Domain\Campaign\Actions\AssignCampaignProduct;
use App\Domain\Campaign\Actions\CreateCampaign;
use App\Domain\Campaign\Actions\CreateCampaignClaim;
use App\Domain\Campaign\Actions\ScheduleCampaign;
use App\Domain\Campaign\Actions\SubmitCampaignClaim;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Campaign\Models\CampaignRevision;
use App\Domain\Campaign\Support\CampaignClaimRegistry;
use App\Domain\Campaign\Support\CampaignContentSchema;
use App\Domain\Campaign\Support\CampaignEffectiveStateEvaluator;
use App\Domain\Campaign\Support\CampaignReadinessEvaluator;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CampaignController
{
    private const TYPES = ['pre_order', 'limited_edition'];

    public function index(CampaignReadinessEvaluator $readiness, CampaignEffectiveStateEvaluator $states, CampaignTypeRegistry $types): View
    {
        $campaigns = Campaign::query()->whereIn('campaign_type', self::TYPES)
            ->with(['currentDraftRevision', 'products' => fn ($query) => $query->active()->with('product.currentDraftRevision')])
            ->latest()->get();

        return view('admin.campaigns.index', [
            'campaigns' => $campaigns,
            'readiness' => $readiness,
            'effectiveStates' => $campaigns->mapWithKeys(fn (Campaign $campaign): array => [
                $campaign->id => match ($states->evaluate($campaign, CarbonImmutable::now('UTC'))) {
                    'active' => 'Active',
                    'scheduled' => 'Scheduled',
                    'ended' => 'Ended',
                    'archived' => 'Archived',
                    'not_ready' => 'Needs attention',
                    default => 'Draft',
                },
            ])->all(),
            'typeLabels' => collect(self::TYPES)->mapWithKeys(fn (string $type) => [$type => $types->get($type)['label']])->all(),
        ]);
    }

    public function create(Request $request, CatalogueReadinessEvaluator $readiness, CampaignTypeRegistry $types): View
    {
        $type = $request->query('type');
        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            return view('admin.campaigns.type', ['types' => collect(self::TYPES)->map(fn (string $key) => $types->get($key))->all()]);
        }

        return view('admin.campaigns.form', ['campaign' => null, 'campaignType' => $type, 'typeLabel' => $types->get($type)['label'], 'products' => $this->products($readiness), 'selectedMedia' => []]);
    }

    public function store(Request $request, CreateCampaign $create, ScheduleCampaign $schedule, AssignCampaignProduct $assignProduct, AssignCampaignMedia $assignMedia, CreateCampaignClaim $createClaim, SubmitCampaignClaim $submitClaim, CampaignStateFingerprint $states, ReadyImagePickerQuery $images, CampaignTypeRegistry $types): RedirectResponse
    {
        $data = $this->validate($request);
        $type = $data['campaign_type'];
        $product = Product::query()->whereKey($data['product_id'])->whereNull('archived_at')->firstOrFail();
        $asset = $images->findEligible($data['media_asset_id']);
        if ($asset === null) {
            return back()->withInput()->withErrors(['media_asset_id' => 'The selected Campaign image is no longer available.']);
        }
        if (blank($data['media_alt_override'] ?? null) && blank($asset->default_alt_text)) {
            return back()->withInput()->withErrors(['media_asset_id' => 'This Media Asset needs descriptive alt text before it can be used here. Update it in Media Library.']);
        }

        $campaign = DB::transaction(function () use ($request, $data, $type, $create, $schedule, $assignProduct, $assignMedia, $createClaim, $submitClaim, $states, $product, $asset): Campaign {
            $campaign = $create->handle($request->user(), $type, $data['internal_code'], $data);
            $schedule->handle($request->user(), $campaign, $states->identity($campaign->fresh()), $data['starts_at'], $data['ends_at']);
            if ($type === 'pre_order') {
                $campaign->refresh()->forceFill(['estimated_delivery_date' => $data['estimated_delivery_date']])->save();
            }
            $assignProduct->handle($request->user(), $campaign->fresh(), $product, $states->targets($campaign->id));
            $assignMedia->handle($request->user(), $campaign->fresh(), $asset, $states->media($campaign->id), 'card', $data['media_alt_override'] ?? null);
            foreach ($this->claimInputs($type, $data) as $claimInput) {
                $claim = $createClaim->handle($request->user(), $campaign->fresh(), $states->claims($campaign->id), ...$claimInput);
                $submitClaim->handle($request->user(), $campaign->fresh(), $claim, $states->claims($campaign->id));
            }

            return $campaign->fresh();
        });

        return redirect()->route('admin.campaigns.edit', $campaign)->with('status', $types->get($type)['label'].' Campaign created and submitted for approval.');
    }

    public function edit(Campaign $campaign, CatalogueReadinessEvaluator $readiness, MediaProvider $media, CampaignTypeRegistry $types): View
    {
        abort_unless(in_array($campaign->campaign_type, self::TYPES, true), 404);
        $usage = MediaUsage::query()->with('asset')->where('owner_type', Campaign::class)->where('owner_identifier', $campaign->id)->where('field_role', 'card')->first();
        $selectedMedia = $usage?->asset === null ? [] : [['id' => $usage->asset->id, 'title' => $usage->asset->internal_title, 'filename' => $usage->asset->original_filename, 'alt' => (string) $usage->asset->default_alt_text, 'thumbnail' => $media->deliveryUrl($usage->asset->provider_public_id, 'image', 'admin_thumbnail', null, null)]];

        return view('admin.campaigns.form', [
            'campaign' => $campaign->load(['currentDraftRevision', 'products.product', 'claims']),
            'campaignType' => $campaign->campaign_type,
            'typeLabel' => $types->get($campaign->campaign_type)['label'],
            'products' => $this->products($readiness),
            'selectedMedia' => $selectedMedia,
            'mediaAltOverride' => $usage?->alt_text_override,
        ]);
    }

    public function update(Request $request, Campaign $campaign, CampaignContentSchema $content, CampaignClaimRegistry $claims, ReadyImagePickerQuery $images, RecordAuditEvent $audit, CampaignTypeRegistry $types): RedirectResponse
    {
        abort_unless(in_array($campaign->campaign_type, self::TYPES, true), 404);
        $data = $this->validate($request, $campaign);
        $asset = $images->findEligible($data['media_asset_id']);
        if ($asset === null || (blank($data['media_alt_override'] ?? null) && blank($asset->default_alt_text))) {
            return back()->withInput()->withErrors(['media_asset_id' => 'This Campaign image is unavailable or needs descriptive alt text in Media Library.']);
        }
        $normalized = $content->normalize($data);

        DB::transaction(function () use ($request, $campaign, $data, $asset, $normalized, $content, $claims, $audit): void {
            $item = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $before = ['revision_id' => $item->current_draft_revision_id, 'product_id' => $item->products()->active()->value('product_id'), 'starts_at' => $item->starts_at?->toISOString(), 'ends_at' => $item->ends_at?->toISOString(), 'estimated_delivery_date' => $item->getRawOriginal('estimated_delivery_date')];
            $checksum = $content->checksum($normalized);
            if ($item->currentDraftRevision?->checksum !== $checksum) {
                $revision = CampaignRevision::query()->create([...$normalized, 'campaign_id' => $item->id, 'revision_number' => (int) CampaignRevision::query()->where('campaign_id', $item->id)->max('revision_number') + 1, 'checksum' => $checksum, 'created_by' => $request->user()->id, 'created_at' => now('UTC')]);
                $item->current_draft_revision_id = $revision->id;
            }
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['starts_at'], 'Africa/Nairobi')->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['ends_at'], 'Africa/Nairobi')->utc();
            $item->forceFill(['starts_at' => $start, 'ends_at' => $end, 'estimated_delivery_date' => $item->campaign_type === 'pre_order' ? $data['estimated_delivery_date'] : null, 'approved_revision_id' => null, 'lifecycle_status' => 'draft', 'lock_version' => $item->lock_version + 1])->save();

            $target = CampaignProduct::query()->active()->where('campaign_id', $item->id)->lockForUpdate()->first();
            if ($target === null) {
                CampaignProduct::query()->create(['campaign_id' => $item->id, 'product_id' => $data['product_id'], 'position' => 0, 'active_product_key' => CollectionOrderingKeys::active('product', $data['product_id']), 'position_key' => CollectionOrderingKeys::active('position', '0'), 'created_by' => $request->user()->id]);
            } else {
                $target->forceFill(['product_id' => $data['product_id'], 'active_product_key' => CollectionOrderingKeys::active('product', $data['product_id'])])->save();
            }
            $usage = MediaUsage::query()->where('owner_type', Campaign::class)->where('owner_identifier', $item->id)->where('field_role', 'card')->lockForUpdate()->first();
            $mediaValues = ['media_asset_id' => $asset->id, 'alt_text_override' => blank($data['media_alt_override'] ?? null) ? null : trim($data['media_alt_override']), 'decorative_override' => false, 'sort_order' => 0];
            if ($usage === null) {
                MediaUsage::query()->create(['id' => (string) Str::ulid(), 'owner_type' => Campaign::class, 'owner_identifier' => $item->id, 'field_role' => 'card', ...$mediaValues]);
            } else {
                $usage->forceFill($mediaValues)->save();
            }
            foreach ($this->claimInputs($item->campaign_type, $data) as [$key, $value, $reference, $summary]) {
                $claimData = $claims->normalize($item->campaign_type, $key, $value, $reference, $summary);
                $claim = CampaignClaim::query()->where('campaign_id', $item->id)->where('claim_key', $key)->lockForUpdate()->first();
                $values = ['normalized_value' => $claimData['value'], 'value_checksum' => $claimData['checksum'], 'approved_checksum' => null, 'evidence_reference' => $claimData['evidence_reference'], 'evidence_summary' => $claimData['evidence_summary'], 'approval_status' => 'in_review', 'submitted_by' => $request->user()->id, 'submitted_at' => now('UTC'), 'approved_by' => null, 'approved_at' => null, 'last_material_by' => $request->user()->id];
                if ($claim === null) {
                    CampaignClaim::query()->create(['campaign_id' => $item->id, 'claim_key' => $key, 'created_by' => $request->user()->id, ...$values]);
                } else {
                    $claim->forceFill($values)->save();
                }
            }
            $audit->handle('campaign.admin.updated', $item, $request->user(), $before, ['revision_id' => $item->current_draft_revision_id, 'product_id' => $data['product_id'], 'starts_at' => $start->toISOString(), 'ends_at' => $end->toISOString(), 'estimated_delivery_date' => $item->getRawOriginal('estimated_delivery_date')]);
        }, 3);

        return back()->with('status', $types->get($campaign->campaign_type)['label'].' Campaign updated and submitted for approval.');
    }

    public function approve(Request $request, Campaign $campaign, ApproveCampaignClaim $approve, CampaignStateFingerprint $states, CampaignReadinessEvaluator $readiness, CampaignTypeRegistry $types, RecordAuditEvent $audit): RedirectResponse
    {
        $definition = $types->get($campaign->campaign_type);
        DB::transaction(function () use ($request, $campaign, $approve, $states, $definition): void {
            foreach ($definition['required_claims'] as $key) {
                $claim = $campaign->claims()->active()->where('claim_key', $key)->firstOrFail();
                if ($claim->approval_status === 'in_review') {
                    $approve->handle($request->user(), $campaign->fresh(), $claim, $states->claims($campaign->id));
                }
            }
        }, 3);

        $item = $campaign->fresh();
        $item->forceFill(['approved_revision_id' => $item->current_draft_revision_id, 'lifecycle_status' => 'active', 'lock_version' => $item->lock_version + 1])->save();
        if (! $readiness->evaluate($item->fresh())->ready) {
            $item->forceFill(['approved_revision_id' => null, 'lifecycle_status' => 'draft'])->save();

            return back()->withErrors(['campaign' => 'The Campaign still needs attention before it can be published.']);
        }
        $audit->handle('campaign.published', $item, $request->user(), ['lifecycle_status' => 'draft'], ['lifecycle_status' => 'active', 'approved_revision_id' => $item->approved_revision_id]);

        return back()->with('status', $definition['label'].' Campaign approved and published.');
    }

    /** @return array<string, mixed> */
    private function validate(Request $request, ?Campaign $campaign = null): array
    {
        $typeRule = $campaign === null ? Rule::in(self::TYPES) : Rule::in([$campaign->campaign_type]);
        $isPreOrder = $request->input('campaign_type') === 'pre_order';
        $isLimitedEdition = $request->input('campaign_type') === 'limited_edition';
        $data = $request->validate([
            'campaign_type' => ['required', $typeRule],
            'internal_code' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9 _-]+$/', Rule::unique('campaigns', 'internal_code')->ignore($campaign?->id)],
            'headline' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'summary' => ['required', 'string', 'max:2000', 'not_regex:/[<>]/'],
            'cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('archived_at')],
            'media_asset_id' => ['required', 'string'],
            'media_alt_override' => ['nullable', 'string', 'max:320', 'not_regex:/[<>]/'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'estimated_delivery_date' => [$isPreOrder ? 'required' : 'prohibited', 'nullable', 'date', 'after_or_equal:ends_at'],
            'public_window_statement' => ['required', 'string', 'max:300', 'not_regex:/[<>]/'],
            'evidence_reference' => ['required', 'string', 'max:500'],
            'evidence_summary' => ['required', 'string', 'max:500'],
            'edition_statement' => [$isLimitedEdition ? 'required' : 'prohibited', 'nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'edition_evidence_reference' => [$isLimitedEdition ? 'required' : 'prohibited', 'nullable', 'string', 'max:500'],
            'edition_evidence_summary' => [$isLimitedEdition ? 'required' : 'prohibited', 'nullable', 'string', 'max:500'],
        ]);
        $data['starts_at'] = str_replace('T', ' ', $data['starts_at']);
        $data['ends_at'] = str_replace('T', ' ', $data['ends_at']);

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return list<array{string,string,string,string}> */
    private function claimInputs(string $type, array $data): array
    {
        $claims = [['public_window_statement', $data['public_window_statement'], $data['evidence_reference'], $data['evidence_summary']]];
        if ($type === 'limited_edition') {
            $claims[] = ['edition_statement', $data['edition_statement'], $data['edition_evidence_reference'], $data['edition_evidence_summary']];
        }

        return $claims;
    }

    /** @return list<array{id:string,title:string,ready:bool,reasons:list<string>}> */
    private function products(CatalogueReadinessEvaluator $readiness): array
    {
        $products = Product::query()->whereNull('archived_at')->with('currentDraftRevision')->orderBy('slug')->get()->map(function (Product $product) use ($readiness): array {
            $result = $readiness->evaluate($product);
            $labels = [
                'missing_current_revision' => 'Missing Product content',
                'missing_primary_category' => 'Missing category',
                'missing_primary_media' => 'Missing image',
                'unusable_primary_media' => 'Image needs attention',
                'missing_product_price' => 'Missing price',
                'missing_variants' => 'Missing Variants',
                'missing_default_variant' => 'Missing default Variant',
            ];

            return [
                'id' => $product->id,
                'title' => $product->currentDraftRevision->title ?? $product->slug,
                'ready' => $result->ready,
                'reasons' => array_map(
                    fn (string $code, string $message): string => $labels[$code] ?? Str::headline($message),
                    $result->failureCodes,
                    $result->failureMessages,
                ),
            ];
        })->values()->all();

        return array_values($products);
    }
}
