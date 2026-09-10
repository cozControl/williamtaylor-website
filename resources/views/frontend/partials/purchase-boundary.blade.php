<script>
// Imported demonstration Cart handlers must not create fake production Cart state.
document.addEventListener('click', function (event) {
 const button = event.target.closest('button');
 if (!button) return;
 if (!button.hasAttribute('data-cart-add') && (button.hasAttribute('data-product-purchase') || /^(add to cart|buy now|select size)$/i.test(button.textContent.trim()))) {
  event.preventDefault(); event.stopImmediatePropagation();
 }
}, true);
</script>
