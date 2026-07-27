@if($publicSiteChrome?->navigation)
<div id="public-mobile-navigation" class="hidden lg:hidden fixed inset-x-0 top-14 z-50 bg-wt-oxblood border-t border-wt-gold/30 px-6 py-6 shadow-xl" role="dialog" aria-modal="true" aria-label="Primary navigation">
 <div class="flex items-center justify-between mb-5">
  <span class="font-label text-xs tracking-widest uppercase text-wt-gold">Menu</span>
  <button type="button" data-public-menu-close aria-label="Close menu" class="text-wt-cream hover:text-wt-gold transition-colors">
   <svg fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
  </button>
 </div>
 <nav aria-label="Mobile primary navigation">
  <ul class="space-y-4">
   @foreach($publicSiteChrome->navigation->items as $item)
    @if($item->visibility !== 'desktop')
    <li>
     <a class="font-label text-sm tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors" href="{{ $item->link->url }}" @if($item->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->link->label }}</a>
     @if($item->children)
      <ul class="mt-3 ml-4 space-y-3 border-l border-wt-gold/20 pl-4">
       @foreach($item->children as $child)
        @if($child->visibility !== 'desktop')
        <li><a class="font-body text-sm text-wt-cream/70 hover:text-wt-gold" href="{{ $child->link->url }}" @if($child->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->link->label }}</a></li>
        @endif
       @endforeach
      </ul>
     @endif
    </li>
    @endif
   @endforeach
  </ul>
 </nav>
</div>
<script>
 (() => {
  const menu = document.getElementById('public-mobile-navigation');
  const opener = document.querySelector('[data-public-menu-open]');
  const closer = menu?.querySelector('[data-public-menu-close]');
  if (!menu || !opener || !closer) return;
  const focusable = () => [...menu.querySelectorAll('a[href], button:not([disabled])')];
  const close = () => { menu.classList.add('hidden'); opener.setAttribute('aria-expanded', 'false'); opener.focus(); };
  opener.addEventListener('click', () => { menu.classList.remove('hidden'); opener.setAttribute('aria-expanded', 'true'); closer.focus(); });
  closer.addEventListener('click', close);
  document.addEventListener('keydown', event => {
   if (menu.classList.contains('hidden')) return;
   if (event.key === 'Escape') close();
   if (event.key !== 'Tab') return;
   const items = focusable(); const first = items[0]; const last = items[items.length - 1];
   if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
   if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
 })();
</script>
@endif
