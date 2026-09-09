@if(count($homepageClientStories['stories']))
     <section data-homepage-client-stories class="py-12 lg:py-20 bg-wt-cream">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="text-center mb-10 lg:mb-14">
         <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;">
          <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
         </span>
         <p class="section-subtitle mb-2 text-wt-gold">
          {{ $homepageClientStories['client_stories_eyebrow'] }}
         </p>
         <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
          {{ $homepageClientStories['client_stories_heading'] }}
         </h2>
        </div>

        <div class="wt-client-stories-grid grid grid-cols-1 md:grid-cols-3 gap-8">
         @foreach($homepageClientStories['stories'] as $story)
          <figure class="bg-white p-6 lg:p-8 relative" data-client-story-card style="margin:0;min-width:0;overflow-wrap:anywhere">
           <span aria-hidden="true" class="font-heading text-6xl text-wt-gold leading-none absolute top-4 left-6 opacity-30">&quot;</span>
           <blockquote class="font-body text-sm text-gray-600 font-light leading-relaxed mb-6 relative z-10 pt-4">{{ $story['quote'] }}</blockquote>
           <figcaption class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full overflow-hidden border border-wt-gold/30" style="flex-shrink:0">
             <img alt="{{ $story['image']['alt'] }}" class="w-full h-full object-cover object-top" src="{{ $story['image']['url'] }}">
            </div>
            <div>
             <p class="font-label text-xs font-semibold tracking-wider uppercase text-wt-oxblood">{{ $story['display_name'] }}</p>
             <p class="font-body text-xs text-gray-400 font-light">{{ $story['location'] }}</p>
            </div>
           </figcaption>
          </figure>
         @endforeach
        </div>
       </div>
      </section>
@else
<section data-homepage-client-stories hidden></section>
@endif
