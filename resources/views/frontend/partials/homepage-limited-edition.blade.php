<section class="py-12 lg:py-20 bg-wt-oxblood" data-homepage-limited-edition>
 <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
  <div class="text-center mb-10 lg:mb-14">
   <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-3" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;">
   <p class="section-subtitle mb-2 text-wt-gold">{{ $homepageLimitedEdition['limited_edition_eyebrow'] }}</p>
   <h2 class="section-title text-wt-cream" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'><span class="inline-flex items-center gap-3 text-wt-cream"><img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;">{{ $homepageLimitedEdition['limited_edition_heading'] }}<img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"></span></h2>
  </div>
  <div class="flex items-center justify-center gap-3 max-w-xs mx-auto mb-10 lg:mb-14"><div class="flex-1 h-px bg-wt-gold/30"></div><img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 14px; height: 14px;"><div class="flex-1 h-px bg-wt-gold/30"></div></div>
  @if(!empty($homepageLimitedEdition['campaigns']))
   <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @foreach($homepageLimitedEdition['campaigns'] as $campaign)
     @include('frontend.partials.limited-edition-campaign-card', ['campaign' => $campaign, 'homepage' => true])
    @endforeach
   </div>
  @endif
  <div class="text-center mt-12"><a class="border border-wt-gold text-wt-gold hover:bg-wt-gold hover:text-wt-oxblood transition-all duration-300 px-10 py-4 font-label text-xs tracking-widest uppercase" href="{{ route('limited-edition.index') }}">{{ $homepageLimitedEdition['limited_edition_cta_label'] }}</a></div>
 </div>
</section>
