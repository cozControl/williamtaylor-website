<template id="public-shop-header-template">@include('frontend.partials.header')</template>
<style>
 [data-canonical-shop-header] [hidden]{display:none!important}
 .wt-shop-dropdown{max-height:min(70vh,36rem);overflow-y:auto;overscroll-behavior:contain;width:12rem;animation:wt-shop-enter .15s ease-out}
 [data-canonical-shop-header] [aria-current="page"],[data-shop-active]{color:var(--color-wt-gold,#c4a35a)}
 [data-canonical-shop-header] a:focus-visible,[data-canonical-shop-header] button:focus-visible{outline:2px solid #c4a35a;outline-offset:3px}
 .wt-mobile-navigation{position:fixed;inset:0;z-index:60}
 .wt-mobile-panel{width:85%;max-width:24rem;z-index:1;animation:wt-drawer-enter .3s ease-out}
 .wt-mobile-panel a,.wt-mobile-panel button{min-height:44px}
 [data-public-menu-close]{display:flex;align-items:center;justify-content:center;min-width:44px}
 @keyframes wt-shop-enter{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
 @keyframes wt-drawer-enter{from{transform:translateX(-100%)}to{transform:translateX(0)}}
 @media(prefers-reduced-motion:reduce){.wt-shop-dropdown,.wt-mobile-panel{animation:none}}
 @media(min-width:1024px){.wt-mobile-navigation{display:none}}
</style>
<script>
(() => {
 const root = document.getElementById('root');
 if (!root) return;
 let boundHeader;
 const synchronize = () => {
  let header = root.querySelector('header');
  if (!header) return;
  if (!header.hasAttribute('data-canonical-shop-header')) {
   const canonical = document.getElementById('public-shop-header-template')?.content.firstElementChild?.cloneNode(true);
   if (!canonical) return;
   header.replaceWith(canonical); header = canonical;
  }
  if (boundHeader === header) return;
  boundHeader = header;
  const group = header.querySelector('[data-shop-dropdown]');
  const toggle = group.querySelector('[data-shop-toggle]');
  const dropdown = group.querySelector('.wt-shop-dropdown');
  let timer;
  const show = open => { clearTimeout(timer); dropdown.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); };
  group.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') show(true); });
  group.addEventListener('pointerleave', event => { if (event.pointerType !== 'mouse') return; timer = setTimeout(() => { if (!group.contains(document.activeElement)) show(false); }, 150); });
  toggle.addEventListener('click', () => show(dropdown.hidden));
  group.addEventListener('focusout', event => { if (!group.contains(event.relatedTarget)) show(false); });
  group.addEventListener('keydown', event => {
   if (event.key === 'Escape') { show(false); toggle.focus(); }
   if (event.key === 'ArrowDown' && event.target === toggle) { event.preventDefault(); show(true); dropdown.querySelector('a')?.focus(); }
  });
  const menu = header.querySelector('#public-mobile-navigation');
  const opener = header.querySelector('[data-public-menu-open]');
  const closer = menu.querySelector('[data-public-menu-close]');
  const close = (restore = true) => { menu.hidden = true; opener.setAttribute('aria-expanded', 'false'); if (restore) opener.focus(); };
  opener.addEventListener('click', () => { menu.hidden = false; opener.setAttribute('aria-expanded', 'true'); closer.focus(); });
  closer.addEventListener('click', () => close());
  menu.querySelector('[data-public-menu-backdrop]').addEventListener('click', () => close());
  const accordion = menu.querySelector('[data-mobile-shop-toggle]');
  const links = menu.querySelector('#public-mobile-shop-links');
  accordion.addEventListener('click', () => { links.hidden = !links.hidden; accordion.setAttribute('aria-expanded', String(!links.hidden)); });
  menu.addEventListener('click', event => { if (event.target.closest('a')) close(false); });
  menu.addEventListener('keydown', event => {
   if (event.key === 'Escape') { event.preventDefault(); close(); }
   if (event.key !== 'Tab') return;
   const items = [...menu.querySelectorAll('a[href],button:not([disabled])')].filter(item => !item.closest('[hidden]'));
   const first = items[0], last = items[items.length - 1];
   if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
   if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  header.querySelector('[data-public-back]')?.addEventListener('click', event => { if (document.referrer && new URL(document.referrer).origin === location.origin) { event.preventDefault(); history.back(); } });
  updateScroll();
 };
 const updateScroll = () => {
  const nav = boundHeader?.querySelector('nav');
  if (nav) nav.style.boxShadow = window.scrollY > 20 ? '0 4px 30px rgba(0,0,0,.25)' : 'none';
 };
 document.addEventListener('click', event => {
  const group = boundHeader?.querySelector('[data-shop-dropdown]');
  if (group && !group.contains(event.target)) { group.querySelector('.wt-shop-dropdown').hidden = true; group.querySelector('[data-shop-toggle]').setAttribute('aria-expanded', 'false'); }
 });
 window.addEventListener('scroll', updateScroll, {passive:true});
 window.addEventListener('resize', () => { if (window.innerWidth >= 1024 && boundHeader) { boundHeader.querySelector('#public-mobile-navigation').hidden = true; boundHeader.querySelector('[data-public-menu-open]').setAttribute('aria-expanded','false'); } });
 new MutationObserver(synchronize).observe(root, {childList:true,subtree:true});
 synchronize();
})();
</script>
