<div class="{{ ($homepage ?? false) ? 'bg-wt-oxblood overflow-hidden flex flex-col md:flex-row' : 'grid grid-cols-1 lg:grid-cols-2 gap-0 bg-white overflow-hidden shadow-sm' }}" data-pre-order-campaign data-countdown-target="{{ $campaign['countdown_target'] }}">
 <div class="{{ ($homepage ?? false) ? 'md:w-64 aspect-[4/5] md:aspect-auto overflow-hidden flex-shrink-0' : 'aspect-[4/3] lg:aspect-auto overflow-hidden' }}"><img alt="{{ $campaign['alt'] }}" class="w-full h-full object-cover object-top hover:scale-105 transition-transform duration-700" src="{{ $campaign['image'] }}"></div>
 <div class="{{ ($homepage ?? false) ? 'flex flex-col justify-center p-8' : 'p-10 lg:p-14 flex flex-col justify-center' }}">
  <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-3 py-1 self-start {{ ($homepage ?? false) ? 'mb-4' : 'mb-6' }}">{{ $campaign['badge'] }}</span>
  <h3 class="font-heading {{ ($homepage ?? false) ? 'text-xl text-wt-cream mb-2' : 'text-3xl text-wt-oxblood mb-3' }}">{{ $campaign['headline'] }}</h3>
  <p class="font-body text-sm {{ ($homepage ?? false) ? 'text-wt-cream/60' : 'text-gray-500' }} font-light mb-3">{{ $campaign['summary'] }}</p>
  <p class="font-label {{ ($homepage ?? false) ? 'text-base text-wt-gold mb-2' : 'font-heading text-2xl text-wt-oxblood mb-3' }}">{{ $campaign['product']['price'] }}</p>
  <p class="font-label text-xs tracking-wider {{ ($homepage ?? false) ? 'text-wt-cream/50 mb-3' : 'text-wt-gold mb-6' }} uppercase">{{ ($homepage ?? false) ? 'Ships' : 'Estimated Delivery:' }} {{ $campaign['delivery_date'] }}</p>
  <div class="flex gap-2 sm:gap-3 mt-3" data-countdown>
   @foreach(['days' => 'Days', 'hours' => 'Hrs', 'minutes' => 'Min', 'seconds' => 'Sec'] as $key => $label)<div class="text-center"><div class="bg-wt-gold text-wt-oxblood font-label font-bold {{ ($homepage ?? false) ? 'text-sm sm:text-base w-9 h-9 sm:w-10 sm:h-10' : 'text-lg w-12 h-12' }} flex items-center justify-center" data-countdown-unit="{{ $key }}">{{ str_pad((string) $campaign['remaining'][$key], 2, '0', STR_PAD_LEFT) }}</div><p class="font-label text-[9px] tracking-wider uppercase {{ ($homepage ?? false) ? 'text-wt-cream/60' : 'text-gray-500' }} mt-1">{{ $label }}</p></div>@endforeach
  </div>
  <a class="{{ ($homepage ?? false) ? 'btn-gold self-start mt-6 text-xs py-3 px-8' : 'btn-primary self-start mt-8 px-8 py-4 text-xs' }}" href="{{ $campaign['url'] }}">{{ $campaign['cta_label'] }}</a>
 </div>
</div>
