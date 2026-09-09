@if($homepageSummerEdit['eligible'])
       <div class="relative z-10 px-4 lg:px-12 py-8 lg:py-10" data-homepage-summer-edit>
        <div class="max-w-screen-xl mx-auto">
         <div class="relative overflow-hidden border border-wt-gold/40">
          <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold to-transparent z-20">
          </div>
          <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold to-transparent z-20">
          </div>
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[180%] bg-[radial-gradient(circle,rgba(201,169,98,0.15),transparent_50%)] z-0">
          </div>
          <div class="relative z-10 flex flex-col items-center text-center px-6 py-10 sm:py-12 lg:py-14">
           <div style="opacity: 1; transform: none;">
            <div class="flex items-center justify-center gap-3 mb-4">
             <div class="h-px w-10 bg-wt-gold/40">
             </div>
             <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
             <div class="h-px w-10 bg-wt-gold/40">
             </div>
            </div>
            <p class="font-label text-[10px] sm:text-xs tracking-[0.35em] uppercase text-wt-gold mb-4">
             {{ $homepageSummerEdit['summer_edit_eyebrow'] }}
            </p>
            <h2 class="font-heading text-wt-cream mb-4" style="font-size: clamp(1.75rem, 4vw, 2.75rem); line-height: 1.1; text-shadow: rgba(0, 0, 0, 0.5) 0px 4px 30px;">
             {{ $homepageSummerEdit['summer_edit_heading'] }}
            </h2>
            <p class="font-body text-sm text-wt-cream/70 font-light max-w-md mx-auto mb-6">
             {{ $homepageSummerEdit['summer_edit_copy_prefix'] }}
             <span class="text-wt-gold font-medium">
              {{ $homepageSummerEdit['summer_edit_highlight'] }}
             </span>
             {{ $homepageSummerEdit['summer_edit_copy'] }}
            </p>
            <a class="btn-gold px-10 py-3.5 text-xs" href="{{ $homepageSummerEdit['destination']['url'] }}">
             {{ $homepageSummerEdit['summer_edit_cta_label'] }}
            </a>
           </div>
          </div>
         </div>
        </div>
       </div>
@else
<div data-homepage-summer-edit hidden></div>
@endif
