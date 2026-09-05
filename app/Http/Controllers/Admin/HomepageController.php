<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Homepage\Actions\UpdateHomepageHero;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageHeroDestinationRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepageController
{
    public function edit(Request $request, HomepageHeroDestinationRegistry $destinations, ReadyImagePickerQuery $images, MediaProvider $media): View
    {
        $hero = HomepageHero::query()->firstOrCreate(
            ['id' => HomepageHero::SINGLETON_ID],
            [...HomepageHero::defaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $persistedSelectedId = MediaUsage::query()->where('owner_type', HomepageHero::class)
            ->where('owner_identifier', $hero->id)
            ->where('field_role', HomepageHero::MEDIA_ROLE)
            ->value('media_asset_id');
        $oldSelectedId = old('background_media_id');
        $selectedId = is_string($oldSelectedId)
            ? $oldSelectedId
            : (is_string($persistedSelectedId) ? $persistedSelectedId : '');
        $selectedAsset = $selectedId === '' ? null : $images->findEligible($selectedId);
        $selectedMedia = $selectedAsset === null ? [] : [[
            'id' => $selectedAsset->id,
            'title' => $selectedAsset->internal_title,
            'filename' => $selectedAsset->original_filename,
            'alt' => '',
            'thumbnail' => $media->deliveryUrl($selectedAsset->provider_public_id, $selectedAsset->resource_type->value, 'admin_thumbnail', null, null),
        ]];

        return view('admin.homepage.edit', [
            'hero' => $hero,
            'destinations' => $destinations->labels(),
            'selectedMedia' => $selectedMedia,
        ]);
    }

    public function update(Request $request, HomepageHeroDestinationRegistry $destinations, ReadyImagePickerQuery $images, UpdateHomepageHero $update): RedirectResponse
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
        ]);
        $asset = null;
        if (filled($data['background_media_id'] ?? null)) {
            $asset = $images->findEligible((string) $data['background_media_id']);
            if ($asset === null) {
                return back()->withInput()->withErrors(['background_media_id' => 'The selected Hero image is no longer available.']);
            }
        }
        $hero = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->firstOrFail();
        $update->handle($request->user(), $hero, $data, $asset);

        return redirect()->route('admin.homepage.edit')->with('status', 'Homepage Hero updated successfully.');
    }
}
