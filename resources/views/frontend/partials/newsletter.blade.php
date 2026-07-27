     <div class="bg-wt-oxblood py-16 px-6">
      <div class="max-w-xl mx-auto text-center">
       <p class="font-label text-xs tracking-widest uppercase text-wt-gold mb-4">
        Inner Circle
       </p>
       <h2 class="font-heading text-3xl text-wt-cream mb-3">
        {{ $publicSiteChrome?->profile?->newsletterHeading ?: 'Sign the Ledger' }}
       </h2>
       <p class="font-body text-sm text-wt-cream/60 font-light mb-8">
        {{ $publicSiteChrome?->profile?->newsletterCopy ?: 'Be the first to access Limited Editions, private sales, and the latest from the atelier.' }}
       </p>
       <form class="flex gap-0 max-w-sm mx-auto">
        <input class="flex-1 bg-white/10 border border-wt-gold/40 text-wt-cream placeholder-wt-cream/40 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold transition-colors" placeholder="Your email address" required="" type="email" value=""/>
        <button class="btn-gold px-6 py-3 text-xs" type="submit">
         Join
        </button>
       </form>
      </div>
     </div>
