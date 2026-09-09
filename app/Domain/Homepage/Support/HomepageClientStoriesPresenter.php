<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Homepage\Models\HomepageClientStory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaUsage;
use Illuminate\Support\Facades\Schema;

final class HomepageClientStoriesPresenter
{
    public function __construct(private MediaProvider $media) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $result = [...HomepageHero::clientStoriesDefaults(), 'managed' => false, 'stories' => [], 'positions' => [], 'attention_count' => 0];
        if (! Schema::hasColumns('homepage_heroes', ['client_stories_managed']) || ! Schema::hasTable('homepage_client_stories')) {
            return $result;
        }
        $homepage = HomepageHero::find(HomepageHero::SINGLETON_ID);
        if ($homepage === null) {
            return $result;
        }
        $result = [...$result, ...$homepage->only(array_keys(HomepageHero::clientStoriesDefaults())), 'managed' => (bool) $homepage->client_stories_managed];
        $stories = HomepageClientStory::where('homepage_hero_id', $homepage->id)->whereBetween('position', [1, HomepageClientStory::CAPACITY])->orderBy('position')->get();
        $usages = MediaUsage::with('asset')->where('owner_type', HomepageClientStory::class)->whereIn('owner_identifier', $stories->modelKeys())->where('field_role', HomepageClientStory::MEDIA_ROLE)->get()->keyBy('owner_identifier');
        foreach ($stories as $story) {
            $usage = $usages->get($story->id);
            $asset = $usage?->asset;
            $alt = trim((string) ($usage?->alt_text_override ?: $asset?->default_alt_text));
            $reason = null;
            if (blank($story->display_name) || blank($story->location) || blank($story->quote)) {
                $reason = 'Add the quote, client name and location.';
            } elseif ($asset === null || $asset->state !== MediaAssetState::Ready || $asset->resource_type !== MediaResourceType::Image || $asset->confirmed_at === null || $asset->archived_at !== null) {
                $reason = 'Choose a ready portrait image.';
            } elseif ($alt === '') {
                $reason = 'Add portrait alt text here or in Media Library.';
            }
            $result['positions'][$story->position] = ['visible' => $story->is_visible, 'ready' => $reason === null, 'reason' => $reason];
            if (! $story->is_visible) {
                continue;
            }
            if ($reason !== null) {
                $result['attention_count']++;

                continue;
            }
            $result['stories'][] = ['position' => $story->position, 'display_name' => $story->display_name, 'location' => $story->location, 'quote' => $story->quote, 'image' => ['url' => $this->media->deliveryUrl($asset->provider_public_id, 'image', 'admin_thumbnail', null, null), 'alt' => $alt]];
        }

        return $result;
    }
}
