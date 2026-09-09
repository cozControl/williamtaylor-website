<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomepageCollectionPickerController
{
    public function __invoke(Request $request, CollectionCardPresenter $cards): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $page = Collection::query()
            ->active()
            ->where('catalogue_status', 'ready')
            ->whereNotNull('current_draft_revision_id')
            ->with(['currentDraftRevision', 'products.product.currentDraftRevision'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('slug', 'like', "%{$search}%")
                    ->orWhereHas('currentDraftRevision', fn ($revision) => $revision->where('title', 'like', "%{$search}%"));
            }))
            ->orderByDesc('updated_at')->paginate(12);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (Collection $collection): array => $cards->present($collection))
                ->filter(fn (array $collection): bool => $collection['eligible'])
                ->map(fn (array $collection): array => [
                    'id' => $collection['id'],
                    'name' => $collection['title'],
                    'slug' => $collection['slug'],
                    'visibility' => $collection['visibility'],
                    'status' => $collection['status'],
                    'product_count' => $collection['product_count'],
                    'ready_count' => $collection['ready_count'],
                    'image' => $collection['image']['url'],
                ])->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }
}
