<picture data-responsive-hero-media style="display:block;width:100%;height:100%">
 @if($homepageHero['mobile_background_url'] ?? null)
  <source media="(max-width: 767px)" srcset="{{ $homepageHero['mobile_background_url'] }}">
 @endif
 <img alt="" class="w-full h-full object-cover object-top" src="{{ $homepageHero['background_url'] }}" fetchpriority="high">
</picture>
