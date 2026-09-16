<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\PreOrderCampaignPresenter;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Actions\UpdateHomepageExploreCollections;
use App\Domain\Homepage\Actions\UpdateHomepageFutureStyle;
use App\Domain\Homepage\Actions\UpdateHomepageHero;
use App\Domain\Homepage\Actions\UpdateHomepageHotSale;
use App\Domain\Homepage\Actions\UpdateHomepageLimitedEdition;
use App\Domain\Homepage\Actions\UpdateHomepageNewArrivals;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageExploreCollectionsPresenter;
use App\Domain\Homepage\Support\HomepageFutureStylePresenter;
use App\Domain\Homepage\Support\HomepageHeroDestinationRegistry;
use App\Domain\Homepage\Support\HomepageHotSaleDestinationRegistry;
use App\Domain\Homepage\Support\HomepageHotSalePresenter;
use App\Domain\Homepage\Support\HomepageLimitedEditionPresenter;
use App\Domain\Homepage\Support\HomepageNewArrivalsPresenter;
use App\Domain\Homepage\Support\ReadyHotSaleMediaQuery;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HomepageController
{
    public function edit(Request $request, HomepageNewArrivalsPresenter $newArrivals, HomepageHotSalePresenter $hotSale, HomepageFutureStylePresenter $futureStyle, HomepageLimitedEditionPresenter $limitedEdition, HomepageExploreCollectionsPresenter $exploreCollections): View
    {
        $hero = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [
                ...HomepageHero::defaults(),
                ...HomepageHero::newArrivalsDefaults(),
                ...HomepageHero::hotSaleDefaults(),
                ...HomepageHero::futureStyleDefaults(),
                ...HomepageHero::limitedEditionDefaults(),
                ...HomepageHero::exploreCollectionsDefaults(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );

        return view('admin.homepage.edit', [
            'hero' => $hero,
            'heroHasImage' => MediaUsage::query()
                ->where('owner_type', HomepageHero::class)
                ->where('owner_identifier', $hero->id)
                ->where('field_role', HomepageHero::MEDIA_ROLE)
                ->exists(),
            'newArrivals' => $newArrivals->present(),
            'hotSale' => $hotSale->present(),
            'futureStyle' => $futureStyle->present(),
            'limitedEdition' => $limitedEdition->present(),
            'exploreCollections' => $exploreCollections->present(),
        ]);
    }

    public function editHero(Request $request, HomepageHeroDestinationRegistry $destinations, ReadyImagePickerQuery $images, MediaProvider $media): View
    {
        $hero = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $usages = MediaUsage::query()->where('owner_type', HomepageHero::class)
            ->where('owner_identifier', $hero->id)
            ->whereIn('field_role', [HomepageHero::MEDIA_ROLE, HomepageHero::MOBILE_MEDIA_ROLE])
            ->pluck('media_asset_id', 'field_role');
        $selections = [];
        foreach (['background_media_id' => HomepageHero::MEDIA_ROLE, 'mobile_background_media_id' => HomepageHero::MOBILE_MEDIA_ROLE] as $field => $role) {
            $selectedId = $request->session()->hasOldInput($field) ? old($field) : ($usages[$role] ?? null);
            $selectedAsset = filled($selectedId) && is_string($selectedId) ? $images->findEligible($selectedId) : null;
            $selections[$field] = $selectedAsset === null ? [] : [[
                'id' => $selectedAsset->id,
                'title' => $selectedAsset->internal_title,
                'filename' => $selectedAsset->original_filename,
                'alt' => '',
                'thumbnail' => $media->deliveryUrl($selectedAsset->provider_public_id, $selectedAsset->resource_type->value, 'admin_thumbnail', null, null),
            ]];
        }

        return view('admin.homepage.hero', [
            'hero' => $hero,
            'destinations' => $destinations->labels(),
            'selectedMedia' => $selections['background_media_id'],
            'selectedMobileMedia' => $selections['mobile_background_media_id'],
        ]);
    }

    public function updateHero(Request $request, HomepageHeroDestinationRegistry $destinations, ReadyImagePickerQuery $images, UpdateHomepageHero $update): RedirectResponse
    {
        $request->merge(['scroll_indicator_enabled' => $request->boolean('scroll_indicator_enabled')]);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'title' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'subtitle' => ['required', 'string', 'max:240', 'not_regex:/[<>]/'],
            'primary_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'primary_cta_destination' => ['required', Rule::in(array_keys($destinations->labels()))],
            'secondary_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'secondary_cta_destination' => ['required', Rule::in(array_keys($destinations->labels()))],
            'scroll_indicator_enabled' => ['required', 'boolean'],
            'background_media_id' => ['nullable', 'string'],
            'mobile_background_media_id' => ['sometimes', 'nullable', 'string'],
        ]);
        $asset = null;
        if (filled($data['background_media_id'] ?? null)) {
            $asset = $images->findEligible((string) $data['background_media_id']);
            if ($asset === null) {
                return back()->withInput()->withErrors(['background_media_id' => 'The selected Hero image is no longer available.']);
            }
        }
        $mobileAsset = null;
        if (filled($data['mobile_background_media_id'] ?? null)) {
            $mobileAsset = $images->findEligible((string) $data['mobile_background_media_id']);
            if ($mobileAsset === null) {
                return back()->withInput()->withErrors(['mobile_background_media_id' => 'The selected mobile Hero image is no longer available.']);
            }
        }
        $hero = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $hero, $data, $asset, $mobileAsset);

        return redirect()->route('admin.homepage.hero.edit')->with('status', 'Homepage Hero updated successfully.');
    }

    public function editNewArrivals(Request $request, HomepageNewArrivalsPresenter $presenter): View
    {
        $homepage = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), ...HomepageHero::newArrivalsDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return view('admin.homepage.new-arrivals', [
            'homepage' => $homepage,
            'section' => $presenter->present(),
        ]);
    }

    public function updateNewArrivals(Request $request, UpdateHomepageNewArrivals $update): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'new_arrivals_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'new_arrivals_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'new_arrivals_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'new_arrivals_collection_id' => [
                'required', 'string',
                Rule::exists('collections', 'id')->where(fn ($query) => $query->whereNull('archived_at')->where('catalogue_status', 'ready')->whereNotNull('current_draft_revision_id')),
            ],
        ]);
        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $collection = Collection::query()->whereKey($data['new_arrivals_collection_id'])->sole();
        $update->handle($request->user(), $homepage, $collection, $data);

        return redirect()->route('admin.homepage.new-arrivals.edit')->with('status', 'New Arrivals updated successfully.');
    }

    public function editHotSale(Request $request, HomepageHotSaleDestinationRegistry $destinations, ReadyHotSaleMediaQuery $images, MediaProvider $media, HomepageHotSalePresenter $presenter): View
    {
        $homepage = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), ...HomepageHero::newArrivalsDefaults(), ...HomepageHero::hotSaleDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $usages = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $homepage->id)
            ->whereIn('field_role', HomepageHero::HOT_SALE_MEDIA_ROLES)->pluck('media_asset_id', 'field_role');
        $selectedMedia = [];
        $mediaAltOverrides = [];
        foreach (HomepageHero::HOT_SALE_MEDIA_ROLES as $position => $role) {
            $selectedId = old("hot_sale_tile_{$position}_media_id", $usages->get($role));
            $asset = is_string($selectedId) ? $images->findEligible($selectedId) : null;
            $usage = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $homepage->id)->where('field_role', $role)->first();
            $mediaAltOverrides[$position] = old("hot_sale_tile_{$position}_alt_override", $usage?->alt_text_override);
            $selectedMedia[$position] = $asset === null ? [] : [[
                'id' => $asset->id, 'title' => $asset->internal_title, 'filename' => $asset->original_filename,
                'alt' => (string) $asset->default_alt_text,
                'type' => $asset->resource_type->value,
                'thumbnail' => $media->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, $asset->resource_type->value === 'video' ? 'video_poster' : 'admin_thumbnail', null, null),
            ]];
        }

        return view('admin.homepage.hot-sale', ['homepage' => $homepage, 'section' => $presenter->present(), 'destinations' => $destinations->labels(), 'selectedMedia' => $selectedMedia, 'mediaAltOverrides' => $mediaAltOverrides]);
    }

    public function updateHotSale(Request $request, HomepageHotSaleDestinationRegistry $destinations, ReadyHotSaleMediaQuery $images, UpdateHomepageHotSale $update): RedirectResponse
    {
        $rules = [
            'hot_sale_eyebrow' => ['sometimes', 'required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'hot_sale_heading' => ['sometimes', 'required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'lock_version' => ['required', 'integer'],
        ];
        foreach ([1, 2, 3] as $position) {
            $rules["hot_sale_tile_{$position}_title"] = ['required', 'string', 'max:160', 'not_regex:/[<>]/'];
            $rules["hot_sale_tile_{$position}_copy"] = ['required', 'string', 'max:320', 'not_regex:/[<>]/'];
            $rules["hot_sale_tile_{$position}_cta_label"] = ['required', 'string', 'max:80', 'not_regex:/[<>]/'];
            $rules["hot_sale_tile_{$position}_destination"] = ['required', Rule::in(array_keys($destinations->labels()))];
            $rules["hot_sale_tile_{$position}_media_id"] = ['required', 'string'];
            $rules["hot_sale_tile_{$position}_alt_override"] = ['nullable', 'string', 'max:320', 'not_regex:/[<>]/'];
        }
        $data = $request->validate($rules);
        $assets = [];
        foreach ([1, 2, 3] as $position) {
            $field = "hot_sale_tile_{$position}_media_id";
            $asset = $images->findEligible((string) $data[$field]);
            if ($asset === null) {
                return back()->withInput()->withErrors([$field => "The selected media for tile {$position} is no longer available."]);
            }
            $override = trim((string) ($data["hot_sale_tile_{$position}_alt_override"] ?? ''));
            if ($override === '' && blank($asset->default_alt_text)) {
                return back()->withInput()->withErrors([$field => 'This Media Asset needs descriptive alt text before it can be used here. Update it in Media Library.']);
            }
            $assets[$position] = $asset;
        }
        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $homepage, $data, $assets);

        return redirect()->route('admin.homepage.hot-sale.edit')->with('status', "William's Hot Sale updated successfully.");
    }

    public function editFutureStyle(Request $request, HomepageFutureStylePresenter $presenter): View
    {
        $homepage = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), ...HomepageHero::newArrivalsDefaults(), ...HomepageHero::hotSaleDefaults(), ...HomepageHero::futureStyleDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        $campaigns = Campaign::query()->where('campaign_type', 'pre_order')->whereNull('archived_at')->with(['currentDraftRevision', 'approvedRevision'])->orderBy('starts_at')->orderBy('id')->get()
            ->filter(fn (Campaign $campaign): bool => app(PreOrderCampaignPresenter::class)->present($campaign) !== null);

        return view('admin.homepage.future-style', ['homepage' => $homepage, 'section' => $presenter->present(), 'campaigns' => $campaigns]);
    }

    public function updateFutureStyle(Request $request, UpdateHomepageFutureStyle $update): RedirectResponse
    {
        $request->merge(['future_style_managed' => $request->boolean('future_style_managed')]);
        $eligibleCampaign = function (string $attribute, mixed $value, \Closure $fail): void {
            $campaign = is_string($value) ? Campaign::query()->with('approvedRevision')->find($value) : null;
            if ($campaign === null || app(PreOrderCampaignPresenter::class)->present($campaign) === null) {
                $fail('Choose a currently public, published Pre-Order Campaign.');
            }
        };
        $data = $request->validate([
            'lock_version' => ['required', 'integer'], 'future_style_managed' => ['required', 'boolean'],
            'future_style_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'future_style_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'future_style_intro' => ['required', 'string', 'max:320', 'not_regex:/[<>]/'],
            'future_style_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'future_style_campaign_1_id' => ['bail', 'nullable', 'string', 'different:future_style_campaign_2_id', $eligibleCampaign],
            'future_style_campaign_2_id' => ['bail', 'nullable', 'string', 'different:future_style_campaign_1_id', $eligibleCampaign],
        ]);
        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $homepage, $data);

        return redirect()->route('admin.homepage.future-style.edit')->with('status', 'The Future of Style updated successfully.');
    }

    public function editLimitedEdition(Request $request, HomepageLimitedEditionPresenter $presenter): View
    {
        $homepage = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), ...HomepageHero::newArrivalsDefaults(), ...HomepageHero::hotSaleDefaults(), ...HomepageHero::futureStyleDefaults(), ...HomepageHero::limitedEditionDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $campaigns = Campaign::query()->where('campaign_type', 'limited_edition')->whereNull('archived_at')->with('currentDraftRevision')->orderBy('starts_at')->get();

        return view('admin.homepage.limited-edition', ['homepage' => $homepage, 'section' => $presenter->present(), 'campaigns' => $campaigns]);
    }

    public function updateLimitedEdition(Request $request, UpdateHomepageLimitedEdition $update): RedirectResponse
    {
        $request->merge(['limited_edition_managed' => $request->boolean('limited_edition_managed')]);
        $rules = [
            'lock_version' => ['required', 'integer'],
            'limited_edition_managed' => ['required', 'boolean'],
            'limited_edition_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'limited_edition_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'limited_edition_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
        ];
        foreach (range(1, HomepageLimitedEditionPresenter::CAPACITY) as $position) {
            $others = collect(range(1, HomepageLimitedEditionPresenter::CAPACITY))->reject(fn (int $other) => $other === $position)->map(fn (int $other) => "limited_edition_campaign_{$other}_id")->implode(',');
            $rules["limited_edition_campaign_{$position}_id"] = ['nullable', "different:{$others}", Rule::exists('campaigns', 'id')->where(fn ($query) => $query->where('campaign_type', 'limited_edition')->whereNull('archived_at'))];
        }
        $data = $request->validate($rules);
        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $homepage, $data);

        return redirect()->route('admin.homepage.limited-edition.edit')->with('status', 'Limited Edition updated successfully.');
    }

    public function editExploreCollections(Request $request, HomepageExploreCollectionsPresenter $presenter, CollectionCardPresenter $cards): View
    {
        $homepage = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [
                ...HomepageHero::defaults(),
                ...HomepageHero::newArrivalsDefaults(),
                ...HomepageHero::hotSaleDefaults(),
                ...HomepageHero::futureStyleDefaults(),
                ...HomepageHero::limitedEditionDefaults(),
                ...HomepageHero::exploreCollectionsDefaults(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );
        $selectedCollections = [];
        foreach (range(1, HomepageExploreCollectionsPresenter::CAPACITY) as $position) {
            $field = "explore_collection_{$position}_id";
            $id = old($field, $homepage->getAttribute($field));
            if (! is_string($id) || $id === '') {
                $selectedCollections[$position] = null;

                continue;
            }

            $collection = Collection::query()
                ->with(['currentDraftRevision', 'products.product.currentDraftRevision'])
                ->find($id);
            $selectedCollections[$position] = $collection === null
                ? ['id' => $id, 'title' => 'Unavailable Collection', 'slug' => '', 'visibility' => 'Unavailable', 'status' => 'Needs attention', 'product_count' => 0, 'ready_count' => 0, 'image' => null]
                : $cards->present($collection);
        }

        return view('admin.homepage.explore-collections', [
            'homepage' => $homepage,
            'section' => $presenter->present(),
            'selectedCollections' => $selectedCollections,
        ]);
    }

    public function updateExploreCollections(Request $request, UpdateHomepageExploreCollections $update, CollectionCardPresenter $cards): RedirectResponse
    {
        $request->merge(['explore_collections_managed' => $request->boolean('explore_collections_managed')]);
        $rules = [
            'lock_version' => ['required', 'integer'],
            'explore_collections_managed' => ['required', 'boolean'],
            'explore_collections_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'explore_collections_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
        ];
        foreach (range(1, HomepageExploreCollectionsPresenter::CAPACITY) as $position) {
            $otherFields = collect(range(1, HomepageExploreCollectionsPresenter::CAPACITY))
                ->reject(fn (int $other): bool => $other === $position)
                ->map(fn (int $other): string => "explore_collection_{$other}_id")
                ->implode(',');
            $rules["explore_collection_{$position}_id"] = [
                'nullable',
                "different:{$otherFields}",
                Rule::exists('collections', 'id')->where(fn ($query) => $query
                    ->whereNull('archived_at')
                    ->where('catalogue_status', 'ready')
                    ->whereNotNull('current_draft_revision_id')),
            ];
        }
        $data = $request->validate($rules, [
            'explore_collection_*_id.different' => 'Choose each Collection only once.',
            'explore_collection_*_id.exists' => 'The selected Collection is no longer available for the storefront.',
        ]);

        foreach (range(1, HomepageExploreCollectionsPresenter::CAPACITY) as $position) {
            $field = "explore_collection_{$position}_id";
            if (! filled($data[$field] ?? null)) {
                $data[$field] = null;

                continue;
            }

            $collection = Collection::query()
                ->with(['currentDraftRevision', 'products.product.currentDraftRevision'])
                ->whereKey((string) $data[$field])
                ->sole();
            if (! $cards->present($collection)['eligible']) {
                throw ValidationException::withMessages([$field => 'This Collection needs a ready image before it can be featured here.']);
            }
        }

        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $homepage, $data);

        return redirect()->route('admin.homepage.explore-collections.edit')->with('status', 'Explore the Collection updated successfully.');
    }
}
