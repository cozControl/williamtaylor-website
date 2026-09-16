    <footer data-canonical-storefront-footer class="bg-wt-oxblood border-t border-wt-gold/30 pb-16 lg:pb-0 relative overflow-hidden">
     <div aria-hidden="true" class="absolute inset-0 pointer-events-none wt-footer-pattern" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: auto;'>
     </div>
     @include('frontend.partials.newsletter')
     <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-16 relative z-10">
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-12 gap-10 lg:gap-8">
       <div class="col-span-2 md:col-span-3 lg:col-span-4">
        <img alt="{{ $publicSiteChrome?->profile?->brandName ?: 'William Taylor' }}" class="h-9 w-auto max-w-[170px] object-contain mb-5 mix-blend-screen" src="{{ $publicSiteChrome?->profile?->footerLogoUrl ?: '/website/images/8d99836ea_LOGO-3.png' }}"/>
        <p class="font-body text-sm text-wt-cream/70 font-light leading-relaxed mb-6 max-w-xs">
         {{ $publicSiteChrome?->profile?->footerDescription ?: 'Crafted for the Modern Gentleman. Contemporary menswear designed in Tanzania, worn worldwide.' }}
        </p>
        @if($publicSiteChrome?->profile && $publicSiteChrome->profile->socialLinks)
        <div class="flex items-center gap-3">
         @foreach($publicSiteChrome->profile->socialLinks as $social)
         @continue($social->platform !== 'instagram')
         <a aria-label="{{ $social->label }}" class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="{{ $social->url }}" rel="noopener noreferrer" target="_blank">
          @include('frontend.partials.social-icon', ['platform' => $social->platform])
         </a>
         @endforeach
        </div>
        @else        <div class="flex items-center gap-3">
         <a aria-label="Instagram" class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="https://instagram.com/williamtaylorbrand" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-instagram" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
           <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
           </rect>
           <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
           </path>
           <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
           </line>
          </svg>
         </a>
        </div>
        @endif
       </div>
       <div class="lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">Shop</h4>
         <div class="w-6 h-px bg-wt-gold/40"></div>
        </div>
        <ul class="space-y-2.5" data-catalogue-footer-links>
         @foreach ($shopNavigation['footer_collections'] as $entry)
          <li><a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ $entry['url'] }}"><span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3"></span>{{ $entry['label'] }}</a></li>
         @endforeach
        </ul>
       </div>
       <div class="lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">Pages</h4>
         <div class="w-6 h-px bg-wt-gold/40"></div>
        </div>
        <ul class="space-y-2.5">
         @foreach (['About Us' => route('about'), 'Contact Us' => url('/html/contact.html'), 'FAQ' => url('/html/faq.html')] as $label => $url)
          <li><a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ $url }}"><span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3"></span>{{ $label }}</a></li>
         @endforeach
        </ul>
       </div>
       <div class="col-span-2 md:col-span-1 lg:col-span-4">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">
          Visit
         </h4>
         <div class="w-6 h-px bg-wt-gold/40">
         </div>
        </div>
        <ul class="space-y-3">
         <li class="font-body text-sm text-wt-cream/70 font-light leading-relaxed">
          {{ $publicSiteChrome?->profile?->address ?: 'Dar Village Mall, Dar Es Salaam, Tanzania' }}
         </li>
         <li>
          <a class="font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="mailto:{{ $storefrontContact->email }}">
           {{ $storefrontContact->email }}
          </a>
         </li>
         <li>
          <a class="font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="tel:{{ $storefrontContact->telephone }}">
           {{ $storefrontContact->telephoneDisplay }}
          </a>
         </li>
        </ul>
        <div class="flex flex-wrap gap-x-3 gap-y-1 mt-5">
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="html/privacy.html">
          Privacy
         </a>
         <span class="text-wt-cream/20">
          ·
         </span>
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="html/terms.html">
          Terms
         </a>
         <span class="text-wt-cream/20">
          ·
         </span>
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="{{ route('admin.dashboard') }}">
          Admin
         </a>
        </div>
       </div>
      </div>
      <div class="mt-12 pt-8 border-t border-wt-gold/20 flex flex-col md:flex-row items-center justify-between gap-4">
       <p class="font-label text-xs tracking-wider text-wt-cream/50 uppercase">
        {{ $publicSiteChrome?->profile?->copyright ?: '© 2026 William Taylor. All Rights Reserved.' }}
       </p>
       <div class="flex items-center gap-3">
        <span class="font-label text-xs tracking-wider text-wt-cream/40 uppercase">
         We Accept
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Lipa Number
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         M-Pesa
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Visa
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Mastercard
        </span>
       </div>
      </div>
     </div>
    </footer>
