<section class="py-12 lg:py-20 bg-wt-offwhite" data-homepage-future-style>
 <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
  <div class="text-center mb-10 lg:mb-14">
   <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;"><img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"></span>
   <p class="section-subtitle mb-2 text-wt-gold">{{ $homepageFutureStyle['future_style_eyebrow'] }}</p>
   <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>{{ $homepageFutureStyle['future_style_heading'] }}</h2>
   <p class="font-body text-base text-gray-500 font-light mt-4 max-w-md mx-auto">{{ $homepageFutureStyle['future_style_intro'] }}</p>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
   @foreach($homepageFutureStyle['campaigns'] as $campaign)
    @include('frontend.partials.pre-order-campaign-card', ['campaign' => $campaign, 'homepage' => true])
   @endforeach
  </div>
  <div class="text-center mt-10"><a class="btn-outline px-10 py-4" href="{{ route('preorders.index') }}">{{ $homepageFutureStyle['future_style_cta_label'] }}</a></div>
 </div>
</section>
