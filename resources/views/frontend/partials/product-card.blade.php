<div class="group relative" data-storefront-product-card style="opacity: 1; transform: none;">
    <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
        <a href="{{ $card['url'] }}">
            @if ($card['image'])
                <img alt="{{ $card['image']['alt'] }}" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="{{ $card['image']['url'] }}">
            @endif
        </a>
        <div class="absolute top-3 left-3 flex flex-col gap-1">
            @if(!($card['is_available'] ?? false))
                <span data-inventory-out-of-stock class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">OUT OF STOCK</span>
            @endif
            @foreach ($card['badges'] as $badge)
                <span class="{{ $badge === 'new' ? 'bg-wt-gold text-wt-oxblood' : 'bg-wt-oxblood text-wt-cream' }} font-label px-2 py-0.5 text-[10px] tracking-widest">{{ strtoupper($badge) }}</span>
            @endforeach
        </div>
        <button type="button" aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart"><svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="15"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg></button>
    </div>
    <div class="mt-3">
        @if ($card['colours'])
            <div class="flex gap-1 mb-2">
                @foreach ($card['colours'] as $colour)
                    <span class="w-3 h-3 rounded-full border border-gray-300" @if ($colour['swatch_hex']) style="background-color: {{ $colour['swatch_hex'] }};" @endif title="{{ $colour['label'] }}"></span>
                @endforeach
            </div>
        @endif
        <a href="{{ $card['url'] }}"><h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">{{ $card['title'] }}</h3></a>
        <p class="font-label text-sm text-wt-oxblood mt-1"><span class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;"><img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"></span><span>{{ $card['price'] }}</span></span>@if ($card['compare_at_price']) <del class="ml-2 text-gray-500">{{ $card['compare_at_price'] }}</del>@endif</p>
    </div>
</div>
