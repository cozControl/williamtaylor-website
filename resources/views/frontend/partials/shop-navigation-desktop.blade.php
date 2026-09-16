<div class="hidden lg:flex items-center gap-8" data-shop-navigation="desktop">
 <div class="relative" data-shop-dropdown>
  <button type="button" data-shop-toggle aria-expanded="false" aria-controls="public-shop-dropdown" class="flex items-center gap-1 font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" @if($shopNavigation['active']) data-shop-active @endif>
   {{ $shopNavigation['label'] }}
   <svg fill="none" width="12" height="12" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
  </button>
  <div id="public-shop-dropdown" hidden class="wt-shop-dropdown absolute top-full left-0 mt-2 w-48 bg-wt-oxblood border border-wt-gold/20 shadow-2xl py-2 z-50">
   @foreach([$shopNavigation['all_collections'], ...$shopNavigation['collections'], ...$shopNavigation['special']] as $entry)
    <a href="{{ $entry['url'] }}" @if($entry['active']) aria-current="page" @endif class="block px-4 py-2.5 font-label text-xs tracking-wider uppercase text-wt-cream hover:text-wt-gold hover:bg-white/5 transition-colors">{{ $entry['label'] }}</a>
   @endforeach
  </div>
 </div>
 @foreach($shopNavigation['top_links'] as $entry)
  <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ $entry['url'] }}" @if($entry['active']) aria-current="page" @endif>{{ $entry['label'] }}</a>
 @endforeach
 @foreach($shopNavigation['editorial'] as $item)
  @if($item->visibility !== 'mobile')
   <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ $item->link->url }}" @if($item->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->link->label }}</a>
  @endif
 @endforeach
</div>
