<template id="public-projected-footer-template">@include('frontend.partials.footer')</template>
<template id="public-projected-whatsapp-template">@include('frontend.partials.whatsapp-action')</template>
<script>
 (() => {
  const root = document.getElementById('root');
  if (!root) return;
  const replaceFromTemplate = (selector, templateId) => {
   const current = document.querySelector(`#root ${selector}`);
   const template = document.getElementById(templateId);
   const replacement = template?.content.firstElementChild?.cloneNode(true);
   if (!current || !replacement) return false;
   replacement.dataset.projectedChrome = 'true';
   current.replaceWith(replacement);
   return true;
  };
  const synchronize = () => {
   const footer = root.querySelector('footer');
   if (footer && !footer.hasAttribute('data-canonical-storefront-footer')) {
    replaceFromTemplate('footer', 'public-projected-footer-template');
   }
   const whatsapp = root.querySelector('a[aria-label="Chat on WhatsApp"]');
   if (whatsapp && !whatsapp.hasAttribute('data-projected-chrome')) {
    replaceFromTemplate('a[aria-label="Chat on WhatsApp"]', 'public-projected-whatsapp-template');
   }
  };
  const observer = new MutationObserver(synchronize);
  observer.observe(root, { childList: true, subtree: true });
  synchronize();
  window.addEventListener('load', () => requestAnimationFrame(() => requestAnimationFrame(synchronize)), { once: true });
 })();
</script>

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
