<div id="public-mobile-navigation" hidden class="wt-mobile-navigation lg:hidden" role="dialog" aria-modal="true" aria-label="Primary navigation">
 <div class="fixed inset-0 bg-black/60" data-public-menu-backdrop></div>
 <div class="wt-mobile-panel fixed top-0 left-0 h-full bg-wt-oxblood flex flex-col">
  <div class="flex items-center justify-between p-5 border-b border-wt-gold/20">
   <a href="{{ route('home') }}"><img src="/website/images/cf030fe26_ICONlight.png" alt="{{ $publicSiteChrome?->profile?->brandName ?: 'William Taylor' }}" class="h-9 w-auto mix-blend-screen"></a>
   <button type="button" data-public-menu-close aria-label="Close menu" class="text-wt-cream hover:text-wt-gold"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
  </div>
  <nav class="flex-1 overflow-y-auto py-4" aria-label="Mobile primary navigation" data-shop-navigation="mobile">
   <button type="button" data-mobile-shop-toggle aria-expanded="true" aria-controls="public-mobile-shop-links" class="w-full flex items-center justify-between px-6 py-3.5 font-body text-lg uppercase text-wt-cream" @if($shopNavigation['active']) data-shop-active @endif>{{ $shopNavigation['label'] }}<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button>
   <div id="public-mobile-shop-links" class="bg-black/20">
    @foreach([$shopNavigation['all_collections'], ...$shopNavigation['collections'], ...$shopNavigation['special']] as $entry)
     <a href="{{ $entry['url'] }}" @if($entry['active']) aria-current="page" @endif class="block px-10 py-2.5 font-label text-xs tracking-wider uppercase text-wt-cream/60 hover:text-wt-gold">{{ $entry['label'] }}</a>
    @endforeach
   </div>
   @foreach($shopNavigation['top_links'] as $entry)
    <a class="block px-6 py-3.5 font-body text-lg uppercase text-wt-cream hover:text-wt-gold" href="{{ $entry['url'] }}" @if($entry['active']) aria-current="page" @endif>{{ $entry['label'] }}</a>
   @endforeach
   @foreach($shopNavigation['editorial'] as $item)
    @if($item->visibility !== 'desktop')
     <a class="block px-6 py-3.5 font-body text-lg uppercase text-wt-cream hover:text-wt-gold" href="{{ app(\App\Domain\Catalogue\Support\StorefrontShopNavigationPresenter::class)->canonicalCollectionLink($item->link->url) }}" @if($item->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->link->label }}</a>
     @foreach($item->children as $child)
      @if($child->visibility !== 'desktop')
       <a class="block px-10 py-2.5 font-label text-xs uppercase text-wt-cream/60 hover:text-wt-gold" href="{{ app(\App\Domain\Catalogue\Support\StorefrontShopNavigationPresenter::class)->canonicalCollectionLink($child->link->url) }}" @if($child->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->link->label }}</a>
      @endif
     @endforeach
    @endif
   @endforeach
   <div class="border-t border-wt-gold/20 mt-4 pt-4">
    <a class="block px-6 py-3 font-label text-xs uppercase text-wt-cream/60" href="{{ route('gift-cards.index') }}">Gift Cards</a>
    <a class="block px-6 py-3 font-label text-xs uppercase text-wt-cream/60" href="{{ route('login') }}">My Account</a>
    <a class="block px-6 py-3 font-label text-xs uppercase text-wt-cream/60" href="{{ route('wishlist.index') }}">Wishlist</a>
   </div>
  </nav>
  <div class="p-5 border-t border-wt-gold/20 font-label text-xs tracking-widest uppercase text-wt-gold">William Taylor</div>
 </div>
</div>
