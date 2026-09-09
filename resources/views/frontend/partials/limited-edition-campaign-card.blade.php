@if($homepage ?? false)
 <article class="group relative" data-limited-edition-campaign="{{ $campaign['id'] }}">
  <div class="relative overflow-hidden aspect-[3/4]">
   <a href="{{ $campaign['url'] }}"><img alt="{{ $campaign['alt'] }}" class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700" src="{{ $campaign['image'] }}"></a>
   <div class="absolute top-4 right-4">
    <div class="w-16 h-16 bg-wt-gold flex items-center justify-center flex-col group-hover:scale-110 transition-transform duration-300 text-center px-1" style="clip-path: polygon(50% 0%, 93% 25%, 93% 75%, 50% 100%, 7% 75%, 7% 25%);">
     <p class="font-label text-[8px] tracking-widest text-wt-oxblood font-bold uppercase leading-tight">{{ $campaign['edition_statement'] }}</p>
    </div>
   </div>
   <div class="absolute top-4 left-4"><span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-1">LIMITED EDITION</span></div>
  </div>
  <div class="mt-4">
   <a href="{{ $campaign['url'] }}"><h3 class="font-heading text-lg text-wt-cream hover:text-wt-gold transition-colors" style="text-shadow: rgba(0, 0, 0, 0.5) 0px 2px 8px;">{{ $campaign['headline'] }}</h3></a>
   <p class="font-label text-sm text-wt-gold mt-2"><span class="inline-flex items-center gap-1.5"><img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"><span>{{ $campaign['product']['price'] }}</span></span></p>
   <p class="font-body text-sm text-wt-cream/60 font-light mt-2">{{ $campaign['summary'] }}</p>
   <a class="mt-4 font-label border border-wt-cream text-wt-cream hover:bg-wt-cream hover:text-wt-oxblood transition-all duration-300 w-full py-3 text-xs tracking-widest uppercase block text-center" href="{{ $campaign['url'] }}">{{ $campaign['cta_label'] }}</a>
  </div>
 </article>
@else
 <article class="group relative" data-limited-edition-campaign="{{ $campaign['id'] }}" style="opacity: 1; transform: none;">
  <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
   <a href="{{ $campaign['url'] }}"><img alt="{{ $campaign['alt'] }}" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="{{ $campaign['image'] }}"></a>
   <div class="absolute top-3 left-3 flex flex-col gap-1"><span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">{{ $campaign['badge'] }}</span></div>
   <div class="absolute bottom-3 left-3"><span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-0.5">{{ $campaign['edition_statement'] }}</span></div>
   <button type="button" aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
    <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2-3.04 0-5.5 2.46-5.5 5.5 0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
   </button>
  </div>
  <div class="mt-3">
   @if(!empty($campaign['product']['options']['colour']))
    <div class="flex gap-1 mb-2">@foreach($campaign['product']['options']['colour'] as $colour)@if($colour['swatch_hex'])<span class="w-3 h-3 rounded-full border border-gray-300" style="background-color: {{ $colour['swatch_hex'] }}" title="{{ $colour['label'] }}"></span>@endif @endforeach</div>
   @endif
   <a href="{{ $campaign['url'] }}"><h3 class="font-heading text-base text-wt-cream leading-snug hover:text-wt-gold transition-colors">{{ $campaign['headline'] }}</h3></a>
   <p class="font-label text-sm text-wt-gold mt-1"><span class="inline-flex items-center gap-1.5"><img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"><span>{{ $campaign['product']['price'] }}</span></span></p>
  </div>
 </article>
@endif
