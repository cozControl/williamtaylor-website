@if($homepageHandbags['eligible'])
     <section data-homepage-handbags class="py-12 lg:py-20 bg-wt-offwhite">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="flex items-end justify-between mb-8 lg:mb-12">
         <div>
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-2" style="width: 26px; height: 26px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
          </span>
          <p class="section-subtitle mb-2">
           {{ $homepageHandbags['handbags_eyebrow'] }}
          </p>
          <h2 class="section-title">
           {{ $homepageHandbags['handbags_heading'] }}
          </h2>
         </div>
         <a class="btn-outline text-xs py-2 px-6 hidden sm:block" href="{{ $homepageHandbags['cta_url'] }}">
          {{ $homepageHandbags['handbags_cta_label'] }}
         </a>
        </div>
        <div class="wt-handbags-layout grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
         <div class="lg:col-span-4">
          <div class="relative h-full min-h-[400px] lg:min-h-full overflow-hidden group" data-handbags-slideshow>
           @foreach($homepageHandbags['slides'] as $slide)
            <img data-handbags-slide alt="{{ $slide['alt'] }}" src="{{ $slide['url'] }}" class="absolute inset-0 w-full h-full object-cover object-center" style="opacity: {{ $loop->first ? 1 : 0 }}; transform: scale({{ $loop->first ? 1 : 1.05 }}); transition: opacity 1.2s, transform 1.2s;">
           @endforeach
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/90 via-wt-oxblood/30 to-transparent">
           </div>
           @if(count($homepageHandbags['slides']) > 1)
           <div class="absolute bottom-4 right-4 flex gap-2 z-10">
            @foreach($homepageHandbags['slides'] as $slide)
             <button type="button" data-handbags-dot="{{ $loop->index }}" aria-label="Slide {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="w-2 h-2 rounded-full transition-all {{ $loop->first ? 'bg-wt-gold w-6' : 'bg-wt-cream/50' }}"></button>
            @endforeach
           </div>
           @endif
           <div class="relative h-full flex flex-col justify-end p-6 lg:p-8">
            <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-3" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
            <p class="section-subtitle mb-2">
             {{ $homepageHandbags['handbags_hero_eyebrow'] }}
            </p>
            <h3 class="font-heading text-2xl lg:text-3xl text-wt-cream mb-3" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
             {{ $homepageHandbags['handbags_hero_heading'] }}
            </h3>
            <p class="font-body text-sm text-wt-cream/80 font-light mb-5 max-w-xs">
             {{ $homepageHandbags['handbags_hero_copy'] }}
            </p>
            <a class="btn-gold text-xs py-3 px-6 self-start" href="{{ $homepageHandbags['cta_url'] }}">
             {{ $homepageHandbags['handbags_hero_cta_label'] }}
            </a>
           </div>
          </div>
         </div>
         <div class="lg:col-span-8">
          <div class="wt-handbags-products grid grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
           @foreach($homepageHandbags['products'] as $card)
            @include('frontend.partials.product-card', ['card' => $card])
           @endforeach
          </div>
         </div>
        </div>
       </div>
      </section>
@else
<section data-homepage-handbags hidden></section>
@endif
