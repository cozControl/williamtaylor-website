<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Http\Response;

final class PagePreviewController
{
    public function __invoke(Page $page, ContentRevision $revision): Response
    {
        abort_unless($revision->resource_type === Page::class && $revision->resource_id === $page->getKey(), 404);
        $assetIds = [];
        $sections = $revision->payload['sections'] ?? [];
        foreach (is_array($sections) ? $sections : [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $data = is_array($section['data'] ?? null) ? $section['data'] : [];
            foreach (['desktop_media', 'mobile_media', 'media', 'background_media'] as $field) {
                if (is_array($data[$field] ?? null) && is_string($data[$field]['asset_id'] ?? null)) {
                    $assetIds[] = $data[$field]['asset_id'];
                }
            }
            foreach (is_array($data['cards'] ?? null) ? $data['cards'] : [] as $card) {
                if (is_array($card) && is_array($card['media'] ?? null) && is_string($card['media']['asset_id'] ?? null)) {
                    $assetIds[] = $card['media']['asset_id'];
                }
            }
        }
        $media = MediaAsset::query()->whereKey(array_values(array_unique($assetIds)))->get()->keyBy('id');

        return response()
            ->view('content.preview.page', compact('page', 'revision', 'media'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
