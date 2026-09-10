(() => {
 const control = document.querySelector('[data-product-quantity]');
 if (!control) return;
 const display = control.querySelector('[data-quantity-value]');
 const decrease = control.querySelector('[data-quantity-change="-1"]');
 const increase = control.querySelector('[data-quantity-change="1"]');
 let quantity = 1;
 const render = () => {
  display.textContent = String(quantity);
  decrease.disabled = quantity === 1;
  increase.disabled = quantity === 2147483647;
  document.querySelectorAll('[data-product-purchase]').forEach(button => {button.dataset.cartQuantity = String(quantity);});
 };
 control.addEventListener('click', event => {
  const button = event.target.closest('[data-quantity-change]');
  if (!button || !control.contains(button) || button.disabled) return;
  quantity = Math.min(2147483647, Math.max(1, quantity + Number(button.dataset.quantityChange)));
  render();
 });
 render();
})();
