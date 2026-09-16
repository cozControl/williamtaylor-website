<section data-homepage-hot-sale aria-label="Hot Sale" class="wt-hot-sale">
    <div class="text-center mb-10 lg:mb-14 px-4">
        <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width:29px;height:29px"><img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width:20px;height:20px"></span>
        <p class="section-subtitle mb-2 text-wt-gold">{{ $homepageHotSale['hot_sale_eyebrow'] }}</p>
        <h2 class="section-title text-wt-oxblood" style='font-family:Avenir,"Avenir Next","Helvetica Neue",sans-serif;font-weight:300'>{{ $homepageHotSale['hot_sale_heading'] }}</h2>
    </div>
    <div class="wt-hot-sale-grid">
            @foreach ($homepageHotSale['tiles'] as $tile)
                <article data-hot-sale-tile="{{ $tile['position'] }}" class="group relative overflow-hidden cursor-pointer" style="opacity: 1; transform: none;">
                    <a href="{{ $tile['url'] }}">
                        @if ($tile['media_type'] === 'video')
                            <video aria-label="{{ $tile['alt'] }}" autoplay muted loop playsinline class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="{{ $tile['media'] }}"></video>
                        @else
                            <img alt="{{ $tile['alt'] }}" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="{{ $tile['media'] }}">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent"></div>
                        <div class="absolute top-3 left-3 w-7 h-7 rounded-full bg-wt-gold/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300"><span class="font-label text-[9px] font-bold text-wt-oxblood">{{ str_pad((string) $tile['position'], 2, '0', STR_PAD_LEFT) }}</span></div>
                        <div class="absolute inset-0 flex flex-col items-center justify-end p-4 lg:p-6 text-center">
                            <div class="group-hover:-translate-y-2 transition-transform duration-300">
                                <h3 class="font-heading text-xl lg:text-2xl text-wt-cream mb-1.5">{{ $tile['title'] }}</h3>
                                <p class="font-body text-xs lg:text-sm text-wt-cream/70 font-light mb-3 ">{{ $tile['copy'] }}</p>
                                <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">{{ $tile['cta_label'] }} →</span>
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
    </div>
</section>
