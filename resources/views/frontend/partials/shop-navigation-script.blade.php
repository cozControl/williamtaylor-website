<template id="public-shop-header-template">@include('frontend.partials.header')</template>
<script>
(() => {
 const root = document.getElementById('root');
 if (!root) return;
 let boundHeader;
 let scrollFrame = 0, lastY = Math.max(0, window.scrollY), direction = 0, distance = 0;
 const updateTone = () => {
  if (!boundHeader) return;
  if (window.scrollY > 20) {
   boundHeader.querySelectorAll('[data-header-tone]').forEach(control => { control.dataset.headerTone = 'dark'; });
   return;
  }
  const media = [...root.querySelectorAll('main img, main video')].filter(node => node.tagName === 'VIDEO' ? node.readyState > 0 : node.complete && node.naturalWidth > 0);
  // Decorative backgrounds deliberately ignore pointer events, so they are
  // absent from elementsFromPoint even though they are visually above the page.
  const decorations = [...root.querySelectorAll('[style]')].filter(node => {
   if (boundHeader.contains(node)) return false;
   const style = getComputedStyle(node);
   return style.pointerEvents === 'none' && (style.backgroundImage !== 'none' || !['transparent', 'rgba(0, 0, 0, 0)'].includes(style.backgroundColor));
  }).reverse();
  boundHeader.querySelectorAll('.wt-header-brand,.wt-header-action').forEach(control => {
   const bounds = control.getBoundingClientRect(), x = bounds.x + bounds.width / 2, y = bounds.y + bounds.height / 2;
   let light = false;
   // Media can contain changing light and dark regions. Use white with a fine
   // dark edge instead of pixel inversion, which disappears over mid-grey.
   if (window.scrollY <= 20) {
    light = media.some(node => { const box = node.getBoundingClientRect(); return box.left <= x && box.right >= x && box.top <= y && box.bottom >= y; });
    if (!light) {
     const covering = decorations.filter(node => { const box = node.getBoundingClientRect(); return box.left <= x && box.right >= x && box.top <= y && box.bottom >= y; });
     for (const node of [...covering, ...document.elementsFromPoint(x, y)]) {
      if (boundHeader.contains(node)) continue;
      const style = getComputedStyle(node);
      if (style.backgroundImage !== 'none') { light = true; break; }
      const rgba = style.backgroundColor.match(/[\d.]+/g)?.map(Number);
      if (rgba && (rgba[3] ?? 1) >= .9) {
       light = .2126 * rgba[0] + .7152 * rgba[1] + .0722 * rgba[2] < 150;
       break;
      }
     }
    }
   }
   control.dataset.headerTone = light ? 'light' : 'dark';
  });
 };
 const interacting = () => boundHeader?.contains(document.activeElement)
  || document.querySelector('#wt-cart-drawer[open]')
  || boundHeader?.querySelector('[aria-expanded="true"],dialog[open]');
 const updateScroll = () => {
  scrollFrame = 0;
  if (!boundHeader) return;
  const y = Math.max(0, Math.min(window.scrollY, document.documentElement.scrollHeight - window.innerHeight));
  const delta = y - lastY;
  lastY = y;
  const scrolled = y > 20;
  const toneChanged = boundHeader.hasAttribute('data-scrolled') !== scrolled;
  boundHeader.toggleAttribute('data-scrolled', scrolled);
  let state = boundHeader.dataset.smartHeader || 'top';
  if (scrolled && state === 'top') state = 'visible-scrolled';
  if (!scrolled || interacting()) {
   state = scrolled ? 'visible-scrolled' : 'top';
   direction = 0; distance = 0;
  } else if (delta !== 0) {
   const nextDirection = Math.sign(delta);
   distance = nextDirection === direction ? distance + Math.abs(delta) : Math.abs(delta);
   direction = nextDirection;
   if (direction > 0 && y > 96 && distance >= 12) state = 'hidden-scrolled';
   else if (direction < 0 && distance >= 8) state = 'visible-scrolled';
   else if (state === 'top') state = 'visible-scrolled';
  }
  if (boundHeader.dataset.smartHeader !== state) boundHeader.dataset.smartHeader = state;
  if (toneChanged || !scrolled) updateTone();
 };
 const queueScroll = () => { if (!scrollFrame) scrollFrame = requestAnimationFrame(updateScroll); };
 const measureAnnouncement = () => {
  const height = boundHeader?.querySelector('[data-header-announcement]')?.getBoundingClientRect().height || 0;
  boundHeader?.style.setProperty('--wt-announcement-height', `${height}px`);
  updateTone();
 };
 const announcementObserver = new ResizeObserver(measureAnnouncement);
 const synchronize = () => {
  let header = root.querySelector('header');
  if (!header) return;
  if (!header.hasAttribute('data-canonical-shop-header')) {
   const canonical = document.getElementById('public-shop-header-template')?.content.firstElementChild?.cloneNode(true);
   if (!canonical) return;
   header.replaceWith(canonical); header = canonical;
  }
  if (boundHeader === header) { updateTone(); return; }
  announcementObserver.disconnect();
  boundHeader = header;
  const announcement = header.querySelector('[data-header-announcement]');
  announcementObserver.observe(announcement);
  announcement.querySelector('button')?.addEventListener('click', () => { announcement.hidden = true; measureAnnouncement(); });
  measureAnnouncement();
  updateScroll();
 };
 window.addEventListener('scroll', queueScroll, {passive:true});
 document.addEventListener('focusin', queueScroll);
 document.addEventListener('focusout', queueScroll);
 new MutationObserver(queueScroll).observe(document.body, {subtree:true,attributes:true,attributeFilter:['open','aria-expanded']});
 window.addEventListener('resize', updateTone, {passive:true});
 root.addEventListener('load', updateTone, true);
 root.addEventListener('loadeddata', updateTone, true);
 new MutationObserver(synchronize).observe(root, {childList:true,subtree:true});
 synchronize();
})();
</script>

<script>
 (() => {
  const collectionUrls = @json($shopNavigation['collection_urls']);
  const collectionIndex = @json(route('collections.index'));
  const collectionDestinations = new Set(@json(array_values($shopNavigation['collection_urls'])).concat(@json(route('collections.index'))).map(url => { const target = new URL(url, location.origin); return target.origin + target.pathname; }));
  const canonicalize = link => {
   const target = new URL(link.href, location.origin);
   if (target.origin !== location.origin) return;
   const legacy = target.pathname.match(/^\/html\/(mens-wear|womens-wear|unisex|accessories|shoes|handbags|new-arrivals)\.html$/);
   const collection = target.pathname.match(/^\/collections\/([^/]+)$/);
   const slug = legacy?.[1] || collection?.[1] || (target.pathname === '/shop' ? target.searchParams.get('collection') : null);
   if (!slug) return;
   target.searchParams.delete('collection');
   const destination = (collectionUrls[slug] || collectionIndex) + target.search + target.hash;
   if (link.href !== destination) link.href = destination;
  };
  const root = document.getElementById('root');
  const synchronize = () => root?.querySelectorAll('a[href]').forEach(canonicalize);
  if (root) new MutationObserver(synchronize).observe(root, {childList:true,subtree:true});
  synchronize();
  // Imported SPA handlers must not replace canonical Collection navigation.
  document.addEventListener('click', event => {
   const link = event.target.closest('a[href]');
   if (!link) return;
   canonicalize(link);
   const target = new URL(link.href, location.origin);
   if (collectionDestinations.has(target.origin + target.pathname)) event.stopImmediatePropagation();
  }, true);
 })();
</script>
