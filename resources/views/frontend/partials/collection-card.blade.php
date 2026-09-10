     <article class="group relative overflow-hidden aspect-[3/4] cursor-pointer" data-collection-card style="opacity: 1; transform: none;">
      <a class="block w-full h-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-wt-gold" href="{{ $collection['url'] }}">
       <img alt="{{ $collection['image']['alt'] }}" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="{{ $collection['image']['url'] }}" style="--tw-scale-x: 1.08; --tw-scale-y: 1.08;">
       <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent"></div>
       <div class="absolute inset-0 flex flex-col items-center justify-end p-6 lg:p-8 text-center">
        <div class="group-hover:-translate-y-2 transition-transform duration-300" style="transform: translateY(10px);">
         <h3 class="font-heading text-2xl text-wt-cream mb-2">{{ $collection['title'] }}</h3>
         @if(filled($collection['description']))
          <p class="font-body text-sm text-wt-cream/70 font-light mb-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">{{ $collection['description'] }}</p>
         @endif
         <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">Explore &rarr;</span>
        </div>
       </div>
      </a>
     </article>
