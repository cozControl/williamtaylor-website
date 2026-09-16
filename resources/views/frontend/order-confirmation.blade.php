@extends('layouts.frontend')
@section('content')
@include('frontend.partials.checkout-styles')
@php
 $state = \App\Domain\Payments\Support\CheckoutPaymentState::for($order, $payment);
 $mobile = $payment?->method === 'mobile_money';
 $paid = $order->payment_status === 'paid';
 $terminal = in_array($state['attempt_status'], ['failed', 'expired', 'voided', 'cancelled']) || in_array($order->status->value, ['cancelled', 'payment_expired']);
 $title = $paid ? 'Payment received' : ($order->status->value === 'cancelled' ? 'Order cancelled' : (!$mobile ? 'Order Received' : ($state['attempt_status'] === 'attention_required' ? 'Checking your payment' : ($terminal ? 'Payment not completed' : 'Check your phone'))));
@endphp
<header class="wt-checkout-header"><a href="{{ route('home') }}"><img src="{{ asset('website/images/8d99836ea_LOGO-3.png') }}" alt="William Taylor"></a></header>
<main class="wt-checkout wt-checkout-received font-body">
 <h1 class="font-heading text-3xl text-wt-oxblood">{{ $title }}</h1>
 <p class="font-heading text-xl">{{ $order->order_number }}</p>
 @if($paid)
 <p>Confirmed &middot; Paid. Thank you for your order.</p>
 <p>Fulfillment: {{ ucfirst(str_replace('_', ' ', $order->fulfillment_status)) }}.</p>
 <p>We will contact you using the details provided to arrange delivery.</p>
 @elseif($mobile)
 <section class="wt-checkout-panel" role="status" aria-live="polite">
 @if($state['retry_allowed'])
 <p>Your order is saved. The payment request could not be sent. You can safely try again.</p>
 <form method="POST" action="{{ route('snippe.retry', $order->confirmation_reference) }}" data-payment-retry>@csrf<button type="submit" class="btn-gold">Try Mobile Money again</button></form>
 @elseif($state['attempt_status'] === 'attention_required')
 <p>We're checking your payment status. Your order is saved. Please do not send another payment while we confirm the result.</p>
 @elseif($terminal)
 <p>{{ match($state['attempt_status']) { 'expired' => 'The payment request has expired.', 'voided', 'cancelled' => 'The payment request was cancelled.', default => 'The payment was not completed.' } }}</p>
 <p>No payment has been confirmed. Return to your bag or contact us with your order reference for help.</p>
 @elseif($payment->provider_payment_reference)
 <p>A payment request has been sent to {{ $payment->maskedPhone() }}.</p>
 <p>Approve the payment using your mobile-money PIN on your phone.</p>
 <p><span class="wt-payment-pending" aria-hidden="true"></span>Waiting for payment confirmation&hellip;</p>
 @else
 <p>Your order is saved. We're confirming your payment request. Please do not send another payment.</p>
 @endif
 <p>Payment is not yet confirmed.</p>
 </section>
 @elseif($payment && $order->status->value === 'pending_confirmation')
 @if($payment->reconciliation_issue)
 <p role="status">We're checking your payment status. Your order is saved. Please check again shortly.</p>
 @else
 <p role="status">We're confirming your payment. This page will show confirmation once payment is verified.</p>
 @endif
 @else
 <p>{{ $order->status->value === 'cancelled' ? 'This order has been cancelled.' : 'Thank you. We have received your order.' }}</p>
 <p>Pending confirmation &middot; Unpaid</p>
 @endif
 <section class="wt-checkout-panel">
 @foreach($order->lines as $line)
 <article class="wt-checkout-row"><div><h2 class="font-heading">{{ $line->product_title_snapshot }}</h2><p>{{ implode(' / ', $line->options_snapshot) }}</p><p>{{ app(\App\Domain\Cart\CartPresenter::class)->format($line->unit_price_minor) }} each × {{ $line->quantity }}</p></div><span>{{ app(\App\Domain\Cart\CartPresenter::class)->format($line->line_total_minor) }}</span></article>
 @endforeach
 <p>Order total: {{ app(\App\Domain\Cart\CartPresenter::class)->format($order->total_minor) }}</p>
 @if(!in_array($order->status->value, ['payment_expired','cancelled']))
 <p>Delivery arrangements and any delivery charges remain pending.</p>
 @endif
 </section>
 <p>Need help? <a href="{{ $publicSiteChrome?->profile?->whatsApp ? 'https://wa.me/'.preg_replace('/\D+/', '', $publicSiteChrome->profile->whatsApp) : 'https://wa.me/255656464876' }}" rel="noopener noreferrer" target="_blank">Contact William Taylor</a> and quote your order reference.</p>
 <a href="{{ route('products.index') }}" class="btn-gold">Continue Shopping</a>
</main>
<script>
document.querySelector('[data-payment-retry]')?.addEventListener('submit', function(event) {
 if (this.dataset.submitting) { event.preventDefault(); return; }
 this.dataset.submitting = 'true';
 const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Sending request...';
});
</script>
@if($mobile && $state['poll'])
<script>
(() => {
 const url = @json(route('checkout.payment-status', $order->confirmation_reference));
 const initial = @json($state);
 let stopped = false;
 const poll = async () => {
  if (stopped) return;
  try {
   const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store', signal: AbortSignal.timeout(15000)});
   if (!response.ok) { stopped = true; return; }
   const state = await response.json();
   if (!state.poll || state.attempt_status !== initial.attempt_status || state.order_status !== initial.order_status || state.payment_status !== initial.payment_status || state.retry_allowed !== initial.retry_allowed) {
    stopped = true; window.location.reload(); return;
   }
  } catch { /* A lost connection is never evidence that payment failed. */ }
  if (!stopped) window.setTimeout(poll, 10000);
 };
 window.addEventListener('pagehide', () => { stopped = true; }, {once: true});
 window.setTimeout(poll, 10000);
})();
</script>
@endif
@endsection
