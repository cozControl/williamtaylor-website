<div data-catalogue-toolbar class="flex items-center justify-between mb-8 gap-4">
    <button type="button" data-catalogue-filter-toggle aria-controls="catalogue-filters" aria-expanded="{{ isset($state['filters']) ? 'true' : 'false' }}" class="flex items-center gap-2 border border-wt-oxblood text-wt-oxblood px-4 py-2 font-label text-xs tracking-widest uppercase hover:bg-wt-oxblood hover:text-wt-cream transition-all">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 4h-7M10 4H3m18 8H12m-4 0H3m18 8h-5m-4 0H3M14 2v4M8 10v4m8 4v4"/></svg>Filters
    </button>
    <div class="flex items-center gap-3 ml-auto min-w-0">
        <span data-catalogue-count class="font-label text-xs text-gray-400 uppercase tracking-wider hidden sm:block">{{ $products->total() }} {{ Str::plural('Result', $products->total()) }}</span>
        <form method="GET" action="{{ $baseUrl }}" data-catalogue-sort-form>
            @foreach ($state as $key => $value)
                @if (!in_array($key, ['sort', 'page']))
                    @foreach (is_array($value) ? $value : [$value] as $item)<input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">@endforeach
                @endif
            @endforeach
            <select name="sort" aria-label="Sort products" class="border border-gray-200 text-wt-oxblood px-3 py-2 font-label text-xs tracking-wider uppercase outline-none bg-white">
                @foreach (['featured' => 'Featured', 'newest' => 'Newest First', 'price-asc' => 'Price: Low to High', 'price-desc' => 'Price: High to Low', 'bestselling' => 'Best Selling'] as $value => $label)
                    <option value="{{ $value }}" @selected(($state['sort'] ?? 'featured') === $value)>{{ $label }}</option>
                @endforeach
            </select><noscript><button type="submit">Apply sort</button></noscript>
        </form>
        <div class="hidden sm:flex gap-1" aria-label="Product layout">
            @foreach (['grid' => 'Grid view', 'list' => 'List view'] as $mode => $label)
                <a href="{{ $listingUrl(['view' => $mode]) }}" aria-label="{{ $label }}" @if(($state['view'] ?? 'grid') === $mode) aria-current="true" @endif class="p-2 border transition-all {{ ($state['view'] ?? 'grid') === $mode ? 'bg-wt-oxblood text-wt-cream border-wt-oxblood' : 'border-gray-200 text-gray-400 hover:border-wt-oxblood' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">@if($mode === 'grid')<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>@else<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 4h7M14 9h7M14 15h7M14 20h7"/>@endif</svg>
                </a>
            @endforeach
        </div>
    </div>
</div>
