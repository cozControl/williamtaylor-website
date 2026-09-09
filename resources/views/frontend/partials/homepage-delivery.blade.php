@if($homepageDelivery['eligible'])
       <div class="relative z-10 bg-wt-cream border-y border-wt-gold/40 py-5 px-6" data-homepage-delivery>
        <div class="wt-pattern-texture absolute inset-0 opacity-[0.04]" style='background-image: url("/website/images/cf030fe26_ICONlight.png"); background-size: 240px;'>
        </div>
        <div class="relative max-w-screen-xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
         <div class="flex items-center gap-3">
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 26px; height: 26px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
          </span>
          <div>
           <p class="font-label text-[10px] tracking-[0.35em] uppercase text-wt-gold mb-0.5">
            {{ $homepageDelivery['delivery_eyebrow'] }}
           </p>
           <h3 class="font-heading text-lg sm:text-xl text-wt-oxblood">
            {{ $homepageDelivery['delivery_heading'] }}
           </h3>
          </div>
         </div>
         <a class="btn-primary text-xs py-2.5 px-7 flex-shrink-0" href="{{ $homepageDelivery['url'] }}">
          {{ $homepageDelivery['delivery_cta_label'] }}
         </a>
        </div>
       </div>
@else
<div data-homepage-delivery hidden></div>
@endif
