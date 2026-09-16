@if($selectedCategory || $selectedSizes || filled($state['q'] ?? null))
    <div data-catalogue-active-filters class="flex flex-wrap gap-2 mb-6 font-label text-xs text-wt-oxblood" aria-label="Active filters">
        @if($selectedCategory)<a class="border border-gray-200 px-3 py-2" href="{{ $listingUrl(['category' => null]) }}" aria-label="Remove category {{ $selectedCategory->name }}">{{ $selectedCategory->name }} ×</a>@endif
        @foreach($selectedSizes as $size)<a class="border border-gray-200 px-3 py-2" href="{{ $listingUrl(['size' => array_values(array_diff($selectedSizes, [$size]))]) }}" aria-label="Remove size {{ $size }}">{{ $sizes->firstWhere('key', $size)?->label }} ×</a>@endforeach
        @if(filled($state['q'] ?? null))<a class="border border-gray-200 px-3 py-2" href="{{ $listingUrl(['q' => null]) }}">{{ $state['q'] }} ×</a>@endif
        <a class="underline px-3 py-2" href="{{ $listingUrl(['category' => null, 'size' => null, 'q' => null]) }}">Clear filters</a>
    </div>
@endif
<p class="sm:hidden font-label text-xs text-gray-400 uppercase tracking-wider mb-4">{{ $products->total() }} {{ Str::plural('Result', $products->total()) }}</p>
<div data-catalogue-grid class="{{ ($state['view'] ?? 'grid') === 'list' ? 'space-y-4' : 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6' }}">
    @foreach ($productCards as $card)
        @include('frontend.partials.product-card', ['card' => $card])
    @endforeach
</div>
@if($products->isEmpty())
    <div data-catalogue-empty class="text-center py-20"><h2 class="font-heading text-2xl text-wt-oxblood mb-2">No pieces found</h2><p class="font-body text-sm text-gray-500 font-light mb-4">{{ $total === 0 ? 'No products are currently available.' : 'Try adjusting your filters.' }}</p>@if($total > 0)<a href="{{ $listingUrl(['category' => null, 'size' => null, 'q' => null]) }}" class="font-label text-xs tracking-widest uppercase text-wt-oxblood underline">Clear filters</a>@endif</div>
@endif
@if($products->hasPages())
    <nav data-catalogue-pagination aria-label="Product pages" class="flex flex-wrap items-center justify-center gap-4 mt-10 font-label text-xs uppercase text-wt-oxblood">
        @if($products->previousPageUrl())<a class="border border-wt-oxblood px-4 py-2" rel="prev" href="{{ $products->previousPageUrl() }}">Previous</a>@endif
        <span>Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
        @if($products->nextPageUrl())<a class="border border-wt-oxblood px-4 py-2" rel="next" href="{{ $products->nextPageUrl() }}">Next</a>@endif
    </nav>
@endif
