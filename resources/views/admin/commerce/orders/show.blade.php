<x-admin.layout :title="'Order '.$order->order_number" :description="'Placed '.$presenter->date($order->placed_at)" eyebrow="Commerce" :breadcrumbs="['Orders' => route('admin.commerce.orders.index'), $order->order_number => null]">
    <x-slot:actions>
        <a class="admin-secondary-button" href="{{ route('admin.commerce.orders.index') }}">Back to Orders</a>
        @can('orders.cancel')
            @if($cancellationRestriction === null)<a class="admin-danger-button" href="{{ route('admin.commerce.orders.cancel.confirm', $order) }}">Cancel order</a>@endif
        @endcan
    </x-slot:actions>
    @include('admin.commerce.orders.styles')
    <div class="inventory-workspace">
        <x-admin.flash :errors="$errors" />
        @if($order->cancelled_at)
            <section class="admin-panel"><div class="admin-section-heading"><p>Order operations</p><h2>Cancellation</h2></div><dl class="order-fields"><dt>Reason</dt><dd>{{ $order->cancellation_reason }}</dd><dt>Cancelled by</dt><dd>{{ $cancelledBy ?? 'Staff' }}</dd><dt>Cancelled at</dt><dd>{{ $presenter->date($order->cancelled_at) }}</dd></dl></section>
        @else
            @can('orders.cancel')@if($cancellationRestriction)<p class="inventory-muted">{{ $cancellationRestriction }}</p>@endif@endcan
        @endif
        <div class="order-statuses">
            <span><small>Order</small>@include('admin.commerce.orders.badge', ['value' => $order->status])</span>
            <span><small>Payment</small>@include('admin.commerce.orders.badge', ['value' => $order->payment_status])</span>
            <span><small>Fulfillment</small>@include('admin.commerce.orders.badge', ['value' => $order->fulfillment_status])</span>
        </div>
        @if($payments->contains(fn ($payment) => $payment->reconciliation_issue !== null))
            <aside class="order-attention"><strong>Payment needs attention</strong><p>Review the payment information below before taking further action.</p></aside>
        @endif
        <section class="inventory-metrics" aria-label="Order summary"><article><span>Items</span><strong>{{ $order->lines->count() }}</strong></article><article><span>Units</span><strong>{{ $order->lines->sum('quantity') }}</strong></article><article><span>Order total</span><strong style="font-size:1.4rem">{{ $money->format($order->total_minor) }}</strong></article></section>
        <div class="order-columns">
            <section class="admin-panel"><div class="admin-section-heading"><p>Contact snapshot</p><h2>Customer</h2></div><dl class="order-fields">
                @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)<dt>{{ $label }}</dt><dd>{{ $order->customer_snapshot[$field] ?? 'Not recorded' }}</dd>@endforeach
            </dl></section>
            <section class="admin-panel"><div class="admin-section-heading"><p>Delivery snapshot</p><h2>Delivery</h2></div><dl class="order-fields">
                @foreach(['name' => 'Recipient', 'phone' => 'Phone', 'address' => 'Street address', 'city' => 'City', 'region' => 'Region', 'postal' => 'Postal code'] as $field => $label)
                    @if($field !== 'postal' || filled($order->delivery_snapshot[$field] ?? null))<dt>{{ $label }}</dt><dd>{{ $order->delivery_snapshot[$field] ?? 'Not recorded' }}</dd>@endif
                @endforeach
            </dl></section>
        </div>
        <section class="admin-panel"><div class="admin-section-heading"><p>As placed</p><h2>Order items</h2><p>Product details and prices are preserved from Checkout.</p></div>
            <table class="inventory-table"><thead><tr><th class="inventory-identity">Product / Variant</th><th>Unit price</th><th>Quantity</th><th>Line total</th></tr></thead><tbody>
                @foreach($order->lines as $line)
                    <tr><td data-label="Item"><div><strong>{{ $line->product_title_snapshot }}</strong><small>{{ implode(' · ', $line->options_snapshot) }}</small><small>SKU: {{ $line->sku_snapshot ?: 'Not recorded' }}</small><div class="order-links">
                        @can('products.view')@if($products->contains($line->product_id))<a href="{{ route('admin.products.edit', $line->product_id) }}">View Product</a>@endif@endcan
                        @can('inventory.view')@if($variants->contains($line->variant_id))<a href="{{ route('admin.inventory.show', $line->variant_id) }}">View Inventory</a>@endif@endcan
                    </div></div></td><td data-label="Unit price">{{ $money->format($line->unit_price_minor) }}</td><td data-label="Quantity">{{ $line->quantity }}</td><td data-label="Line total">{{ $money->format($line->line_total_minor) }}</td></tr>
                @endforeach
            </tbody></table>
            <dl class="order-fields order-totals"><dt>Merchandise subtotal</dt><dd>{{ $money->format($order->subtotal_minor) }}</dd><dt><strong>Order total</strong></dt><dd><strong>{{ $money->format($order->total_minor) }}</strong></dd></dl>
        </section>
        <section class="admin-panel"><div class="admin-section-heading"><p>Provider evidence</p><h2>Payments</h2></div>
            @forelse($payments as $payment)
                <article class="order-payment">
                    <div class="order-section-heading"><h3>Snippe · Attempt {{ $loop->iteration }} · {{ $payment->status->value === 'completed' ? 'Successful' : ($payment->active_order_id ? 'Current' : ($payment->status->value === 'failed' ? 'Failed' : 'Closed')) }}</h3>@include('admin.commerce.orders.badge', ['value' => $payment->status])</div>
                    @if($payment->reconciliation_issue)<div class="order-attention"><strong>Needs attention</strong><p>{{ $presenter->issue($payment->reconciliation_issue) }}</p></div>@elseif($payment->failure_code)<p>{{ $presenter->issue($payment->failure_code) }}</p>@endif
                    <dl class="order-fields">
                        <dt>Attempt ID</dt><dd>{{ $payment->id }}</dd>
                        @if($payment->failure_code && $payment->reconciliation_issue)<dt>Previous operation</dt><dd>{{ $presenter->issue($payment->failure_code) }}</dd>@endif
                        <dt>Provider</dt><dd>{{ ucfirst($payment->provider) }}</dd>
                        <dt>Method</dt><dd>{{ $payment->method === 'mobile_money' ? 'Mobile Money' : 'Hosted Session' }}</dd>
                        @if($payment->method === 'mobile_money')<dt>Payer phone</dt><dd>{{ $payment->maskedPhone() }}</dd>@endif
                        @if($payment->provider_session_reference)<dt>Session reference</dt><dd>{{ $payment->provider_session_reference }}</dd>@endif
                        <dt>Payment reference</dt><dd>{{ $payment->provider_payment_reference ?? 'Not recorded' }}</dd>
                        @if($payment->last_failure_reference)<dt>Failed payment reference</dt><dd>{{ $payment->last_failure_reference }}</dd>@endif
                        <dt>Last provider status</dt><dd>{{ $presenter->label($payment->last_provider_status) }}</dd>
                        @foreach(['created_at' => 'Attempt recorded', 'request_started_at' => 'Payment requested', 'last_verified_at' => 'Last provider verification', 'expired_at' => 'Expired', 'completed_at' => 'Completed', 'failed_at' => 'Last failed', 'expires_at' => 'Provider expiry'] as $field => $label)
                            @if($payment->$field)<dt>{{ $label }}</dt><dd>{{ $presenter->date($payment->$field) }}</dd>@endif
                        @endforeach
                    </dl>
                    @if($payment->active_order_id && ($payment->provider_session_reference || $payment->provider_payment_reference) && $payment->provider === 'snippe')
                        @if(config('snippe.enabled') && filled(config('snippe.api_key')))
                            <form method="POST" action="{{ route('admin.commerce.orders.payments.check', [$order, $payment]) }}">@csrf<button class="admin-secondary-button">Check payment status</button></form>
                            <p class="inventory-muted">Checks verified Snippe status. Existing retry delays and in-progress checks are respected.</p>
                        @else<p class="inventory-muted">Payment checks are unavailable until Snippe is enabled and configured. Order history remains available.</p>@endif
                    @elseif($payment->active_order_id)<p class="inventory-muted">Payment outcome is unresolved. Scheduled checks never send a payment request. Review the existing attempt with Snippe before any separately authorized initiation recovery.</p>@endif
                </article>
            @empty<p>No online payment attempt is recorded for this Order.</p>@endforelse
        </section>
        <section class="admin-panel"><div class="admin-section-heading"><p>Stock commitment</p><h2>Inventory</h2><p>Reservations commit stock. Only verified payment issues it from on hand.</p></div>
            <table class="inventory-table"><thead><tr><th class="inventory-identity">Item</th><th>Reservation</th><th>Reserved</th><th>Issued</th><th>Released</th></tr></thead><tbody>
                @foreach($order->lines as $line)
                    @php
                        $reservation = $reservations->get($line->id);
                        $issued = -$movements->where('variant_id', $line->variant_id)->sum('quantity_delta');
                    @endphp
                    <tr><td data-label="Item"><div>{{ $line->product_title_snapshot }}<small>{{ $line->sku_snapshot }}</small></div></td><td data-label="Reservation"><span>{{ $reservation ? $presenter->label($reservation->status) : 'Not recorded' }}</span></td><td data-label="Reserved">{{ $reservation?->status->value === 'active' ? $reservation->quantity : 0 }} units</td><td data-label="Issued">{{ $issued }} units</td><td data-label="Released">{{ $reservation?->status->value === 'released' ? $reservation->quantity : 0 }} units</td></tr>
                @endforeach
            </tbody></table>
        </section>
        <section class="admin-panel"><div class="admin-section-heading"><p>Recorded lifecycle</p><h2>Timeline</h2><p>Oldest first · {{ config('app.timezone') }}. Includes up to 50 recent payment-review audit events.</p></div>
            <ol class="order-timeline">@foreach($timeline as $event)<li><time>{{ $presenter->date($event['at']) }}</time><span>{{ $event['label'] }}</span></li>@endforeach</ol>
        </section>
    </div>
</x-admin.layout>
