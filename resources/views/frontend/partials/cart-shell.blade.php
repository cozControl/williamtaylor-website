@php
 $cart = $cart ?? app(\App\Domain\Cart\CartService::class)->viewSnapshot();
@endphp
<div id="wt-cart-status" role="status" aria-live="polite"></div>
<dialog id="wt-cart-drawer" aria-labelledby="wt-cart-title">
 <div class="wt-cart-drawer-header">
  <div><h2 id="wt-cart-title" class="font-heading text-xl text-wt-cream">Your Selection</h2><p class="font-label text-xs text-wt-gold uppercase"><span data-cart-count>{{ $cart['item_count'] }}</span> pieces</p></div>
  <button type="button" data-cart-close aria-label="Close cart">×</button>
 </div>
 <p data-cart-feedback role="status" aria-live="polite" class="wt-cart-issue"></p>
 <div data-cart-content>@include('frontend.partials.cart-content')</div>
</dialog>
<script type="application/json" id="wt-cart-state">@json(['item_count' => $cart['item_count']])</script>
 <style>
 .wt-cart-count-badge{position:absolute;top:-6px;right:-8px;background:#c4a35a;color:#4b0715;min-width:16px;height:16px;border-radius:50%;font-size:9px;display:flex;align-items:center;justify-content:center;padding:0 3px}.wt-cart-count-badge[hidden]{display:none}
 #wt-cart-drawer{position:fixed;inset:0 0 0 auto;margin:0;width:min(100%,448px);max-width:100%;height:100dvh;max-height:100dvh;padding:0;border:0;background:#faf9f6;color:#4b0715;box-shadow:0 20px 60px #0005}
 #wt-cart-drawer[open]{display:flex;flex-direction:column;animation:wt-cart-enter .35s ease-out}#wt-cart-drawer::backdrop{background:#0008}
 .wt-cart-drawer-header{display:flex;align-items:center;justify-content:space-between;padding:24px;background:var(--color-wt-oxblood,#4b0715);color:#f8f3e8}
 [data-cart-close]{font-size:28px;min-width:44px;min-height:44px}#wt-cart-drawer [data-cart-content]{overflow-y:auto;flex:1;min-height:0}
 .wt-cart-lines{padding:24px;display:grid;gap:16px}.wt-cart-line{display:flex;gap:16px;background:white;padding:12px;border:1px solid #f0f0f0}
 .wt-cart-image{width:80px;height:96px;flex-shrink:0;background:#f3f3f3}.wt-cart-image img{width:100%;height:100%;object-fit:cover;object-position:top}
 .wt-cart-identity{min-width:0;flex:1;overflow-wrap:anywhere}.wt-cart-line-heading,.wt-cart-line-bottom{display:flex;justify-content:space-between;gap:8px;align-items:center}.wt-cart-line-heading{align-items:start}.wt-cart-line-bottom{margin-top:8px;flex-wrap:wrap}
 .wt-cart-remove{font-size:20px;color:#888;min-width:28px;min-height:28px}.wt-cart-quantity{display:flex;align-items:center;border:1px solid #ddd}.wt-cart-quantity button{width:28px;height:28px}.wt-cart-quantity span{min-width:32px;text-align:center;font-size:12px}
 .wt-cart-quantity button:disabled{opacity:.35}.wt-cart-summary{border-top:1px solid #ddd;padding:24px;background:white;display:grid;gap:12px}.wt-cart-subtotal{display:flex;justify-content:space-between;gap:12px}.wt-cart-checkout,.wt-cart-full{width:100%;text-align:center;padding:16px}.wt-cart-checkout:disabled{opacity:.5;cursor:not-allowed}
 .wt-cart-issue{font-size:12px;color:#8a172c;margin-top:10px;line-height:1.5}.wt-cart-empty{padding:64px 24px;display:flex;flex-direction:column;align-items:center}.wt-cart-empty svg{color:#ccc;margin-bottom:16px}
 .wt-cart-page-only{display:none}.wt-cart-page{max-width:1200px;margin:auto;padding:130px 24px 80px}.wt-cart-page .wt-cart-composition{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:32px;align-items:start}.wt-cart-page .wt-cart-lines{padding:0}.wt-cart-page .wt-cart-summary{border:1px solid #eee;position:sticky;top:112px}.wt-cart-page .wt-cart-full{display:none}.wt-cart-page .wt-cart-page-only{display:block}.wt-cart-page .wt-cart-empty{grid-column:1/-1}
 #wt-cart-status:not(:empty){position:fixed;bottom:24px;left:24px;right:24px;z-index:80;background:#4b0715;color:#fff;padding:12px 20px;max-width:448px;box-shadow:0 4px 24px #0003}
 [data-cart-count]{font-variant-numeric:tabular-nums}#wt-cart-drawer button:focus-visible,.wt-cart-page button:focus-visible{outline:2px solid #c4a35a;outline-offset:3px}
 .wt-cart-page{max-width:none;padding:96px 0 0}.wt-cart-page-banner{background:#4b0715;text-align:center;padding:48px 24px}.wt-cart-page-inner{max-width:1280px;margin:auto;padding:48px}.wt-cart-page .wt-cart-composition{gap:48px}.wt-cart-page .wt-cart-line{padding:16px;gap:24px}.wt-cart-page .wt-cart-image{width:96px;height:128px}.wt-cart-page .wt-cart-line h3{font-size:18px}.wt-cart-page .wt-cart-line-bottom{margin-top:16px}.wt-cart-page .wt-cart-quantity button{width:32px;height:32px}.wt-cart-page .wt-cart-quantity span{min-width:40px;font-size:14px}
 @keyframes wt-cart-enter{from{transform:translateX(100%)}to{transform:translateX(0)}}
 #wt-cart-drawer[data-closing]{animation:wt-cart-enter .35s ease-in reverse}
 @media(max-width:1024px){.wt-cart-page .wt-cart-composition{gap:24px}}@media(max-width:768px){.wt-cart-page .wt-cart-composition{grid-template-columns:1fr}.wt-cart-page .wt-cart-summary{position:static}}@media(max-width:430px){.wt-cart-page{padding-left:16px;padding-right:16px}.wt-cart-lines{padding:16px}.wt-cart-line{gap:12px}.wt-cart-summary{padding:20px}}
 @media(max-width:1023px){.wt-cart-page .wt-cart-composition{grid-template-columns:1fr}.wt-cart-page .wt-cart-summary{position:static}.wt-cart-page-inner{padding:48px 24px}}
 @media(max-width:430px){.wt-cart-page{padding-left:0;padding-right:0}.wt-cart-page-inner{padding:32px 16px}.wt-cart-page .wt-cart-line{gap:12px}}
 @media(prefers-reduced-motion:reduce){#wt-cart-drawer[open]{animation:none}}
</style>
<script src="/website/js/cart.js"></script>
