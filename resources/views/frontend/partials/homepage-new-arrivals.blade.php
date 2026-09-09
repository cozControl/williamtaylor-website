<section data-homepage-new-arrivals data-homepage-new-arrivals-source="{{ $homepageNewArrivals['collection_id'] }}" class="py-12 lg:py-20 bg-wt-offwhite">
 <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
  <div class="flex items-end justify-between mb-8 lg:mb-12">
   <div>
    <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-2" style="width: 26px; height: 26px;"><img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"></span>
    <p class="section-subtitle mb-2">{{ $homepageNewArrivals['new_arrivals_eyebrow'] }}</p>
    <h2 class="section-title">{{ $homepageNewArrivals['new_arrivals_heading'] }}</h2>
   </div>
   <div class="flex items-center gap-4">
    <div class="hidden md:flex gap-2">
     <button type="button" aria-label="Previous Products" class="w-10 h-10 border border-wt-oxblood flex items-center justify-center hover:bg-wt-oxblood hover:text-wt-cream transition-all"><svg class="lucide lucide-chevron-left" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="18"><path d="m15 18-6-6 6-6"></path></svg></button>
     <button type="button" aria-label="Next Products" class="w-10 h-10 border border-wt-oxblood flex items-center justify-center hover:bg-wt-oxblood hover:text-wt-cream transition-all"><svg class="lucide lucide-chevron-right" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="18"><path d="m9 18 6-6-6-6"></path></svg></button>
    </div>
    <a class="btn-outline text-xs py-2 px-6 hidden sm:block" href="{{ $homepageNewArrivals['cta_url'] }}">{{ $homepageNewArrivals['new_arrivals_cta_label'] }}</a>
   </div>
  </div>
  <div class="wt-new-arrivals-product-grid flex gap-4 overflow-x-auto scrollbar-hide pb-4 md:overflow-visible md:grid md:grid-cols-4 md:gap-6" style="scroll-snap-type: x mandatory;">
   @foreach($homepageNewArrivals['products'] as $card)
    <div data-homepage-product-card class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
     @include('frontend.partials.product-card', ['card' => $card])
    </div>
   @endforeach
  </div>
 </div>
</section>
