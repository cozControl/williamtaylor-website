<div data-catalogue-filter-backdrop hidden></div>
<aside id="catalogue-filters" class="wt-catalogue-filters w-56 flex-shrink-0" aria-label="Filter products" @if(!isset($state['filters'])) hidden @endif>
    <div class="wt-catalogue-filter-content space-y-8">
        <button type="button" data-catalogue-filter-close class="font-label text-xs uppercase text-wt-oxblood lg:hidden">Close filters <span aria-hidden="true">×</span></button>
        <div>
            <h2 class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold mb-4">Category</h2>
            <div class="space-y-2">
                <a href="{{ $listingUrl(['category' => null, 'filters' => '1']) }}" class="block w-full text-left font-body text-sm py-1 text-wt-oxblood" @if(!$selectedCategory) aria-current="true" @endif>All</a>
                @foreach ($categories as $category)
                    <a data-category-filter href="{{ $listingUrl(['category' => $categoryKey($category), 'filters' => '1']) }}" @if($selectedCategory?->id === $category->id) aria-current="true" @endif class="block w-full text-left font-body text-sm py-1 transition-colors {{ $selectedCategory?->id === $category->id ? 'text-wt-oxblood font-medium' : 'text-gray-500 hover:text-wt-oxblood font-light' }}">{{ $category->name }}@unless($collection)<span class="block text-xs text-gray-400">{{ $category->collection->currentDraftRevision?->title ?? $category->collection->slug }}</span>@endunless</a>
                @endforeach
            </div>
        </div>
        @if($sizes->isNotEmpty())
            <div><h2 class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold mb-4">Size</h2><div class="flex flex-wrap gap-2">
                @foreach ($sizes as $size)
                    @php($chosen = in_array($size->key, $selectedSizes, true))
                    <a href="{{ $listingUrl(['size' => $chosen ? array_values(array_diff($selectedSizes, [$size->key])) : [...$selectedSizes, $size->key], 'filters' => '1']) }}" @if($chosen) aria-current="true" @endif aria-label="Size {{ $size->label }}" class="wt-catalogue-size h-9 font-label text-xs border transition-all {{ $chosen ? 'bg-wt-oxblood text-wt-cream border-wt-oxblood' : 'border-gray-300 text-gray-600 hover:border-wt-oxblood' }}">{{ $size->label }}</a>
                @endforeach
            </div></div>
        @endif
    </div>
</aside>
