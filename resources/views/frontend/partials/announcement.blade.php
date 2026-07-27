@if(isset($publicSiteChrome) && $publicSiteChrome->announcement)
     <div class="overflow-hidden transition-all duration-300 max-h-24 opacity-100">
      <div class="bg-wt-oxblood text-wt-cream py-2 px-8 text-center relative z-50 overflow-hidden">
       <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 120px; opacity: 0.05;'>
       </div>
       <p class="relative font-label text-[10px] sm:text-xs tracking-widest uppercase truncate">
        @if($publicSiteChrome->announcement->cta)
         <a href="{{ $publicSiteChrome->announcement->cta->url }}" @if($publicSiteChrome->announcement->cta->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $publicSiteChrome->announcement->message }}</a>
        @else
         {{ $publicSiteChrome->announcement->message }}
        @endif
       </p>
       @if($publicSiteChrome->announcement->dismissible)
        <button aria-label="{{ $publicSiteChrome->announcement->accessibilityLabel ?? 'Close announcement' }}" class="absolute right-3 top-1/2 -translate-y-1/2 text-wt-cream/60 hover:text-wt-cream transition-colors">
         <svg class="lucide lucide-x" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        </button>
       @endif
      </div>
     </div>
@else     <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
      <div class="bg-wt-oxblood text-wt-cream py-2 px-8 text-center relative z-50 overflow-hidden">
       <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 120px; opacity: 0.05;'>
       </div>
       <p class="relative font-label text-[10px] sm:text-xs tracking-widest uppercase truncate">
        <span class="hidden sm:inline">
         Free Shipping on Orders Over TZS 500,000  ·  Express Delivery in Dar es Salaam
        </span>
        <span class="sm:hidden">
         Free Shipping Over TZS 500,000 · Express in Dar
        </span>
       </p>
       <button aria-label="Close announcement" class="absolute right-3 top-1/2 -translate-y-1/2 text-wt-cream/60 hover:text-wt-cream transition-colors">
        <svg class="lucide lucide-x" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
         <path d="M18 6 6 18">
         </path>
         <path d="m6 6 12 12">
         </path>
        </svg>
       </button>
      </div>
     </div>
@endif
