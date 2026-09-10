@if($publicSiteChrome?->navigation || $publicSiteChrome?->footerGroups || $publicSiteChrome?->announcement || $publicSiteChrome?->profile)
<template id="public-projected-footer-template">@include('frontend.partials.footer')</template>
<template id="public-projected-whatsapp-template">@include('frontend.partials.whatsapp-action')</template>
<script>
 (() => {
  let synchronized = false;
  const replaceFromTemplate = (selector, templateId) => {
   const current = document.querySelector(`#root ${selector}`);
   const template = document.getElementById(templateId);
   const replacement = template?.content.firstElementChild?.cloneNode(true);
   if (!current || !replacement) return false;
   current.replaceWith(replacement);
   return true;
  };
  const synchronize = () => {
   if (synchronized || !document.querySelector('#root header') || !document.querySelector('#root footer')) return;
   const footer = replaceFromTemplate('footer', 'public-projected-footer-template');
   replaceFromTemplate('a[aria-label="Chat on WhatsApp"]', 'public-projected-whatsapp-template');
   if (footer) { synchronized = true; observer.disconnect(); }
  };
  const observer = new MutationObserver(synchronize);
  observer.observe(document.getElementById('root'), { childList: true, subtree: true });
  window.addEventListener('load', () => requestAnimationFrame(() => requestAnimationFrame(synchronize)), { once: true });
 })();
</script>
@endif

<script>
 (() => {
  const adminDestination = @json(route('admin.dashboard'));
  const bindAdminDestination = () => {
   const link = [...document.querySelectorAll('#root footer a')].find(candidate => candidate.textContent?.trim() === 'Admin');
   if (!link) return;
   if (link.getAttribute('href') !== adminDestination) link.setAttribute('href', adminDestination);
   if (link.dataset.publicAdminBound) return;
   link.dataset.publicAdminBound = 'true';
   link.addEventListener('click', event => {
    event.preventDefault();
    event.stopImmediatePropagation();
    window.location.assign(adminDestination);
   }, true);
  };
  const root = document.getElementById('root');
  if (!root) return;
  new MutationObserver(bindAdminDestination).observe(root, { childList: true, subtree: true });
  window.addEventListener('load', () => requestAnimationFrame(() => requestAnimationFrame(bindAdminDestination)), { once: true });
  bindAdminDestination();
 })();
</script>
