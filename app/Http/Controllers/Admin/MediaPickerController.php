<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaPickerController extends Controller
{
    public function __invoke(Request $request, ReadyImagePickerQuery $images, MediaProvider $media): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $page = $images->paginate($search);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($asset): array => [
                'id' => $asset->id,
                'title' => $asset->internal_title,
                'filename' => $asset->original_filename,
                'alt' => (string) $asset->default_alt_text,
                'has_alt' => filled($asset->default_alt_text),
                'thumbnail' => $media->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, 'admin_thumbnail', $asset->focal_x === null ? null : (float) $asset->focal_x, $asset->focal_y === null ? null : (float) $asset->focal_y),
            ])->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }
}
