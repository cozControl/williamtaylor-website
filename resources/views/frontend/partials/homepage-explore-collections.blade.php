<section class="py-12 lg:py-20 bg-white" data-homepage-explore-collections>
 <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
  <div class="text-center mb-10 lg:mb-14">
   <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;">
    <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;">
   </span>
   <p class="section-subtitle mb-2 text-wt-gold">{{ $homepageExploreCollections['explore_collections_eyebrow'] }}</p>
   <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>{{ $homepageExploreCollections['explore_collections_heading'] }}</h2>
  </div>
  @if(!empty($homepageExploreCollections['collections']))
   <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach($homepageExploreCollections['collections'] as $collection)
     <article class="group relative overflow-hidden aspect-[3/4] cursor-pointer" data-homepage-explore-collection="{{ $collection['position'] }}" style="opacity: 1; transform: none;">
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
    @endforeach
   </div>
  @endif
 </div>
</section>
