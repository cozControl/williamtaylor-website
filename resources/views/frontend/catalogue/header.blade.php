      <div class="relative bg-wt-oxblood py-16 px-6 text-center overflow-hidden">
       <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture wt-pattern-breathe pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 300px;'>
       </div>
       <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent">
       </div>
       <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent">
       </div>
       <div class="relative z-10">
        <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-4" src="/website/images/cf030fe26_ICONlight.png" style="width: 22px; height: 22px;"/>
        <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">
         {{ $collection ? 'The Collection' : 'The Atelier' }}
        </p>
        <h1 class="font-heading text-5xl text-wt-cream">
         {{ $collection?->currentDraftRevision?->title ?? 'Shop All' }}
        </h1>
        <p class="font-body text-sm text-wt-cream/60 font-light mt-3">
         {{ $collection?->currentDraftRevision?->short_description ?: $total.' pieces curated for the modern gentleman' }}
        </p>
       </div>
      </div>
