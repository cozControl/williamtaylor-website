<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageClientStory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageClientStoriesPresenter;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HomepageClientStoriesController
{
    public function edit(Request $request, HomepageClientStoriesPresenter $presenter, ReadyImagePickerQuery $images, MediaProvider $media): View
    {
        $homepage = HomepageHero::firstOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), ...HomepageHero::clientStoriesDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $stories = HomepageClientStory::where('homepage_hero_id', $homepage->id)->get()->keyBy('position');
        $selectedMedia = [];
        $alts = [];
        foreach (range(1, HomepageClientStory::CAPACITY) as $position) {
            $story = $stories->get($position);
            $usage = $story === null ? null : MediaUsage::where('owner_type', HomepageClientStory::class)->where('owner_identifier', $story->id)->where('field_role', HomepageClientStory::MEDIA_ROLE)->first();
            $selectedId = $request->old("stories.{$position}.image_id", $usage?->media_asset_id);
            $asset = is_string($selectedId) ? $images->findEligible($selectedId) : null;
            $selectedMedia[$position] = $asset === null ? [] : [['id' => $asset->id, 'title' => $asset->internal_title, 'filename' => $asset->original_filename, 'alt' => $asset->default_alt_text, 'thumbnail' => $media->deliveryUrl($asset->provider_public_id, 'image', 'admin_thumbnail', null, null)]];
            $alts[$position] = $usage?->alt_text_override;
        }

        return view('admin.homepage.client-stories', ['homepage' => $homepage, 'section' => $presenter->present(), 'stories' => $stories, 'selectedMedia' => $selectedMedia, 'alts' => $alts]);
    }

    public function update(Request $request, ReadyImagePickerQuery $images, RecordAuditEvent $audit): RedirectResponse
    {
        $request->merge(['client_stories_managed' => $request->boolean('client_stories_managed')]);
        $rules = ['lock_version' => ['required', 'integer'], 'client_stories_managed' => ['required', 'boolean'], 'client_stories_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'], 'client_stories_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'], 'stories' => ['required', 'array:1,2,3', 'size:3']];
        $messages = [];
        foreach (range(1, HomepageClientStory::CAPACITY) as $position) {
            $prefix = "stories.{$position}.";
            $rules["stories.{$position}"] = ['required', 'array:is_visible,display_name,location,quote,image_id,alt'];
            $rules[$prefix.'is_visible'] = ['required', 'boolean'];
            $visible = $request->boolean($prefix.'is_visible');
            foreach (['display_name' => 120, 'location' => 160, 'quote' => 1000] as $key => $length) {
                $rules[$prefix.$key] = [Rule::requiredIf($visible), 'nullable', 'string', 'max:'.$length, 'not_regex:/[<>]/'];
                $messages[$prefix.$key.'.required'] = 'Add '.match ($key) {
                    'display_name' => 'the client name', 'location' => 'the location', default => 'the quote'
                }.' for this visible story.';
            }
            $id = $request->input($prefix.'image_id');
            $asset = is_string($id) ? $images->findEligible($id) : null;
            $rules[$prefix.'image_id'] = [Rule::requiredIf($visible), 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($images): void {
                if (! is_string($value) || $images->findEligible($value) === null) {
                    $fail('Choose a ready portrait image.');
                }
            }];
            $rules[$prefix.'alt'] = [Rule::requiredIf($visible && $asset !== null && blank($asset->default_alt_text)), 'nullable', 'string', 'max:500', 'not_regex:/[<>]/'];
            $messages[$prefix.'image_id.required'] = 'Choose a portrait for this visible story.';
            $messages[$prefix.'alt.required'] = 'This portrait has no default alt text. Enter an override here or add default alt text in Media Library.';
        }
        $data = $request->validate($rules, $messages);
        DB::transaction(function () use ($request, $data, $audit): void {
            $homepage = HomepageHero::query()->lockForUpdate()->findOrFail(HomepageHero::SINGLETON_ID);
            if ($homepage->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }
            $before = ['section' => $homepage->only(array_keys(HomepageHero::clientStoriesDefaults())), 'stories' => HomepageClientStory::where('homepage_hero_id', $homepage->id)->get()->toArray()];
            $homepage->forceFill(['client_stories_managed' => $data['client_stories_managed'], 'client_stories_eyebrow' => $data['client_stories_eyebrow'], 'client_stories_heading' => $data['client_stories_heading'], 'updated_by' => $request->user()->id, 'lock_version' => $homepage->lock_version + 1])->save();
            foreach (range(1, HomepageClientStory::CAPACITY) as $position) {
                $values = $data['stories'][$position];
                $story = HomepageClientStory::updateOrCreate(['homepage_hero_id' => $homepage->id, 'position' => $position], ['display_name' => $values['display_name'] ?? null, 'location' => $values['location'] ?? null, 'quote' => $values['quote'] ?? null, 'is_visible' => $values['is_visible']]);
                MediaUsage::where('owner_type', HomepageClientStory::class)->where('owner_identifier', $story->id)->where('field_role', HomepageClientStory::MEDIA_ROLE)->delete();
                if (filled($values['image_id'] ?? null)) {
                    MediaUsage::create(['media_asset_id' => $values['image_id'], 'owner_type' => HomepageClientStory::class, 'owner_identifier' => $story->id, 'field_role' => HomepageClientStory::MEDIA_ROLE, 'alt_text_override' => $values['alt'] ?? null, 'decorative_override' => false, 'sort_order' => 0]);
                }
            }
            $audit->handle('homepage.client_stories.updated', $homepage, $request->user(), $before, $data);
        });

        return redirect()->route('admin.homepage.client-stories.edit')->with('status', 'Client Stories updated successfully.');
    }
}
