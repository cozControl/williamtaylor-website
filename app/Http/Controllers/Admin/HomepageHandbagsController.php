<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageHandbagsPresenter;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HomepageHandbagsController
{
    public function edit(Request $request, HomepageHandbagsPresenter $presenter, CollectionCardPresenter $cards, ReadyImagePickerQuery $images, MediaProvider $media): View
    {
        $homepage = HomepageHero::firstOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), ...HomepageHero::handbagsDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $id = $request->old('handbags_collection_id', $homepage->handbags_collection_id);
        $collection = is_string($id) ? Collection::find($id) : null;
        $selectedCollection = $collection === null ? null : $cards->present($collection);
        $selectedMedia = [];
        $alts = [];
        foreach (HomepageHero::HANDBAGS_MEDIA_ROLES as $position => $role) {
            $usage = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $homepage->id)->where('field_role', $role)->first();
            $selectedId = $request->old("handbags_image_{$position}", $usage?->media_asset_id);
            $asset = is_string($selectedId) ? $images->findEligible($selectedId) : null;
            $selectedMedia[$position] = $asset === null ? [] : [['id' => $asset->id, 'title' => $asset->internal_title, 'filename' => $asset->original_filename, 'alt' => $asset->default_alt_text, 'thumbnail' => $media->deliveryUrl($asset->provider_public_id, 'image', 'admin_thumbnail', null, null)]];
            $alts[$position] = $usage?->alt_text_override;
        }

        return view('admin.homepage.handbags', ['homepage' => $homepage, 'section' => $presenter->present(), 'selectedCollection' => $selectedCollection, 'selectedMedia' => $selectedMedia, 'alts' => $alts]);
    }

    public function update(Request $request, CollectionCardPresenter $cards, ReadyImagePickerQuery $images, RecordAuditEvent $audit): RedirectResponse
    {
        $request->merge(['handbags_managed' => $request->boolean('handbags_managed')]);
        $rules = ['lock_version' => ['required', 'integer'], 'handbags_managed' => ['required', 'boolean']];
        foreach (['eyebrow' => 120, 'heading' => 160, 'cta_label' => 80, 'hero_eyebrow' => 120, 'hero_heading' => 160, 'hero_copy' => 500, 'hero_cta_label' => 80] as $key => $max) {
            $rules['handbags_'.$key] = ['required', 'string', 'max:'.$max, 'not_regex:/[<>]/'];
        }
        $rules['handbags_collection_id'] = ['nullable', 'required_if:handbags_managed,true', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($cards): void {
            $collection = is_string($value) ? Collection::find($value) : null;
            if ($collection === null || ! $cards->present($collection)['eligible']) {
                $fail('Choose a visible Collection with a ready card image.');
            }
        }];
        foreach (HomepageHero::HANDBAGS_MEDIA_ROLES as $position => $role) {
            $imageId = $request->input("handbags_image_{$position}");
            $selectedImage = is_string($imageId) ? $images->findEligible($imageId) : null;
            $rules["handbags_alt_{$position}"] = [Rule::requiredIf($selectedImage !== null && blank($selectedImage->default_alt_text)), 'nullable', 'string', 'max:500', 'not_regex:/[<>]/'];
            $rules["handbags_image_{$position}"] = ['nullable', 'required_if:handbags_managed,true', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($images): void {
                $asset = is_string($value) ? $images->findEligible($value) : null;
                if ($asset === null) {
                    $fail('Choose a ready image.');
                }
            }];
        }
        $data = $request->validate($rules, [
            'handbags_alt_1.required' => 'This image has no default alt text. Enter an override here or add default alt text in Media Library.',
            'handbags_alt_2.required' => 'This image has no default alt text. Enter an override here or add default alt text in Media Library.',
        ]);
        DB::transaction(function () use ($request, $data, $audit): void {
            $homepage = HomepageHero::query()->lockForUpdate()->findOrFail(HomepageHero::SINGLETON_ID);
            if ($homepage->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }
            $keys = array_keys(HomepageHero::handbagsDefaults());
            $before = $homepage->only($keys);
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $data[$key] ?? null;
            }
            $homepage->forceFill([...$values, 'updated_by' => $request->user()->id, 'lock_version' => $homepage->lock_version + 1])->save();
            foreach (HomepageHero::HANDBAGS_MEDIA_ROLES as $position => $role) {
                MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $homepage->id)->where('field_role', $role)->delete();
                if (filled($data["handbags_image_{$position}"] ?? null)) {
                    MediaUsage::create(['media_asset_id' => $data["handbags_image_{$position}"], 'owner_type' => HomepageHero::class, 'owner_identifier' => $homepage->id, 'field_role' => $role, 'alt_text_override' => $data["handbags_alt_{$position}"] ?? null, 'decorative_override' => false, 'sort_order' => $position - 1]);
                }
            }
            $audit->handle('homepage.handbags.updated', $homepage, $request->user(), $before, $homepage->only($keys));
        });

        return redirect()->route('admin.homepage.handbags.edit')->with('status', "Women's Handbags updated successfully.");
    }
}
