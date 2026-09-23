(() => {
 const drawer = document.getElementById('wt-cart-drawer');
 if (!drawer) return;
 let count = JSON.parse(document.getElementById('wt-cart-state').textContent).item_count;
 let opener, pending = false;
 const announce = message => { document.getElementById('wt-cart-status').textContent = message; const status = drawer.querySelector('[data-cart-feedback]'); if(status) status.textContent = message; };
 const syncCount = () => {
  document.querySelectorAll('a .lucide-shopping-bag').forEach(icon => {
   const link=icon.closest('a');
   link.href='/cart'; link.dataset.cartOpen=''; link.setAttribute('aria-haspopup','dialog');
   if(!link.querySelector('[data-cart-count]')) {
    const badge=document.createElement('span'); badge.dataset.cartCount=''; badge.dataset.cartBadge=''; badge.className='wt-cart-count-badge'; icon.parentElement.append(badge);
   }
  });
  document.querySelectorAll('[data-cart-count]').forEach(node => { if(node.textContent !== String(count)) node.textContent = count; if(node.hasAttribute('data-cart-badge')) node.hidden=count === 0; });
  document.querySelectorAll('.wt-header-action[data-cart-open]').forEach(node => node.setAttribute('aria-label', `Open cart, ${count} items`));
 };
 const render = data => { count = data.cart.item_count; document.querySelectorAll('[data-cart-content]').forEach(node => {node.innerHTML = data.html;}); syncCount(); announce(data.message); };
 const request = async (url, method='GET', body=null) => {
  const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, ...(body ? {body:JSON.stringify(body)} : {})});
  const data = await response.json();
  if(data.cart) render(data); else announce('Unable to update your bag. Please refresh and try again.');
  return response.ok;
 };
 const open = () => { if (!drawer.open) {opener = document.activeElement; drawer.showModal(); drawer.querySelector('[data-cart-close]').focus();} };
 drawer.addEventListener('close', () => opener?.focus());
 const close = () => {
  if(window.matchMedia('(prefers-reduced-motion: reduce)').matches) {drawer.close(); return;}
  drawer.dataset.closing = '';
  window.setTimeout(() => {drawer.close(); delete drawer.dataset.closing;},350);
 };
 drawer.querySelector('[data-cart-close]').addEventListener('click', close);
 drawer.addEventListener('cancel', event => {event.preventDefault(); close();});
 drawer.addEventListener('click', event => {if(event.target === drawer && event.clientX < drawer.getBoundingClientRect().left) close();});
 const safely = async callback => { if(pending) return; pending=true; try {await callback();} catch {announce('Unable to update your bag. Please refresh and try again.');} finally {pending=false;} };
 document.addEventListener('click', event => {
  const target = event.target.closest('[data-cart-open], a[href="/cart"], [data-cart-add]');
  if(!target) return;
  event.preventDefault(); event.stopImmediatePropagation();
  if(target.hasAttribute('data-cart-add')) {
   if(target.disabled) return;
   const quantity = Number(target.dataset.cartQuantity || 1);
   if(!Number.isInteger(quantity) || quantity < 1 || quantity > 2147483647) {announce('Choose a positive whole quantity.'); return;}
   safely(async () => {if(await request('/cart/items','POST',{variant_id:target.dataset.variantId,quantity})) open();});
  } else {open(); safely(() => request('/cart'));}
 }, true);
 document.addEventListener('submit', event => {
  const form=event.target.closest('[data-cart-form]'); if(!form) return;
  event.preventDefault(); event.stopImmediatePropagation();
  const values = new FormData(form); if(event.submitter?.name) values.set(event.submitter.name,event.submitter.value);
  const method = values.get('_method') || 'POST';
  safely(async () => {await request(form.action, method, Object.fromEntries(values)); drawer.open && drawer.querySelector('[data-cart-close]').focus();});
 },true);
 // Header replacement is owned by the existing storefront synchronizer.
 new MutationObserver(syncCount).observe(document.getElementById('root'),{childList:true,subtree:true});
 syncCount();
 window.addEventListener('pageshow', event => {if(event.persisted) safely(() => request('/cart'));});
})();
