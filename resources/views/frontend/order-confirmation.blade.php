@extends('layouts.frontend')
@section('content')
@include('frontend.partials.checkout-styles')
<main class="wt-checkout wt-checkout-received font-body">
 <h1 class="font-heading text-3xl text-wt-oxblood">{{ $order->status->value === 'cancelled' ? 'Order cancelled' : ($order->payment_status === 'paid' ? 'Payment received' : 'Order Received') }}</h1>
 <p>{{ $order->status->value === 'cancelled' ? 'This order has been cancelled.' : 'Thank you. We have received your order.' }}</p>
 <p class="font-heading text-xl">{{ $order->order_number }}</p>
 <p>{{ match($order->status->value) { 'confirmed' => 'Confirmed · Paid', 'payment_expired' => 'Payment session expired · Unpaid', 'cancelled' => 'Cancelled · Unpaid', default => 'Pending confirmation · Unpaid' } }}</p>
 @if(session('payment_notice') && $order->status->value === 'pending_confirmation')
 <p role="status">{{ session('payment_notice') }}</p>
 @endif
 @if($order->status->value === 'pending_confirmation' && config('snippe.enabled'))
 @if(isset($payment) && $payment->reconciliation_issue)
 <p role="status">We're checking your payment status. Your order is saved. Please check again shortly.</p>
 @elseif(isset($payment) && $payment->failure_code)
 <p role="status">Your order is saved. You can try the secure payment page again.</p>
 @elseif(isset($payment))
 <p role="status">We're confirming your payment. This page will show confirmation once payment is verified.</p>
 @endif
 <form method="POST" action="{{ route('snippe.retry', $order->confirmation_reference) }}">
 @csrf
 <button type="submit" class="btn-gold">{{ isset($payment) && $payment->reconciliation_issue ? 'Check payment status' : 'Continue to secure payment' }}</button>
 </form>
 @elseif(in_array($order->status->value, ['payment_expired','cancelled']))
 <p>No payment has been confirmed. You can return to the collection to place a new order.</p>
 @endif
 @if(!in_array($order->status->value, ['payment_expired','cancelled']))
 <p>We will confirm the next steps using the contact details provided.</p>
 @endif
 <section class="wt-checkout-panel">
 @foreach($order->lines as $line)
 <article class="wt-checkout-row"><div><h2 class="font-heading">{{ $line->product_title_snapshot }}</h2><p>{{ implode(' / ', $line->options_snapshot) }}</p><p>{{ app(\App\Domain\Cart\CartPresenter::class)->format($line->unit_price_minor) }} each × {{ $line->quantity }}</p></div><span>{{ app(\App\Domain\Cart\CartPresenter::class)->format($line->line_total_minor) }}</span></article>
 @endforeach
 <p>Merchandise subtotal: {{ app(\App\Domain\Cart\CartPresenter::class)->format($order->subtotal_minor) }}</p>
 @if(!in_array($order->status->value, ['payment_expired','cancelled']))
 <p>Delivery arrangements and any delivery charges remain pending.</p>
 @endif
 </section>
 <a href="{{ route('products.index') }}" class="btn-gold">Continue Shopping</a>
</main>
@endsection
