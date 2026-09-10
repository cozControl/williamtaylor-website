<x-admin.layout title="Orders" description="Inspect customer orders placed through the William Taylor storefront." eyebrow="Commerce" :breadcrumbs="['Orders' => null]">
    @include('admin.commerce.orders.styles')
    <div class="inventory-workspace">
        <section class="inventory-metrics orders-metrics" aria-label="All production Orders">
            @foreach($metrics as $label => $count)
                <article><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong></article>
            @endforeach
        </section>
        <p class="inventory-muted">Overview includes all customer Orders. Filters below apply to the list.</p>
        <x-admin.flash :errors="$errors" />
        <form method="POST" action="{{ route('admin.commerce.orders.filters') }}" class="admin-panel orders-filters" aria-label="Order filters">
            @csrf
            <label>Search Orders<input name="search" maxlength="160" value="{{ $filters['search'] ?? '' }}" placeholder="Order, name, email or phone"></label>
            <label>Order status<select name="status"><option value="">All Order statuses</option>@foreach($presenter->orders() as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>Payment<select name="payment_status"><option value="">All payment statuses</option>@foreach(['unpaid', 'paid'] as $key)<option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $presenter->label($key) }}</option>@endforeach</select></label>
            <label>Fulfillment<select name="fulfillment_status"><option value="">All fulfillment statuses</option><option value="unfulfilled" @selected(($filters['fulfillment_status'] ?? '') === 'unfulfilled')>Unfulfilled</option></select></label>
            <label>Placed from<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label>Placed to<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label>Payment review<select name="attention"><option value="">All Orders</option><option value="1" @selected(($filters['attention'] ?? '') === '1')>Needs attention</option></select></label>
            <div class="orders-filter-actions"><button class="admin-primary-button">Apply filters</button><button class="admin-secondary-button" name="clear" value="1">Clear</button></div>
        </form>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Customer Orders</p><h2>{{ number_format($orders->total()) }} matching Orders</h2></div>
            @if($orders->isEmpty())
                <div class="order-empty"><h3>{{ $metrics['Total Orders'] === 0 ? 'No customer orders yet.' : 'No Orders match these filters.' }}</h3><p>{{ $metrics['Total Orders'] === 0 ? 'Orders placed through the William Taylor storefront will appear here.' : 'Try another search or clear the filters.' }}</p></div>
            @else
                <table class="inventory-table orders-table"><thead><tr><th class="order-identity">Order</th><th class="order-customer">Customer</th><th>Placed</th><th>Items</th><th>Total</th><th>Payment</th><th>Order status</th><th>Fulfillment</th><th>Action</th></tr></thead><tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td data-label="Order"><div><a href="{{ route('admin.commerce.orders.show', $order) }}"><strong>{{ $order->order_number }}</strong></a>@if($order->needs_attention)<small><span class="order-badge" data-tone="attention">Needs attention</span></small>@endif</div></td>
                            <td data-label="Customer"><div>{{ $order->customer_snapshot['name'] ?? 'Not recorded' }}<small>{{ $order->customer_snapshot['email'] ?? $order->customer_snapshot['phone'] ?? '' }}</small></div></td>
                            <td data-label="Placed">{{ $presenter->date($order->placed_at) }}</td>
                            <td data-label="Items"><span>{{ $order->lines_sum_quantity }} {{ Str::plural('unit', $order->lines_sum_quantity) }} · {{ $order->lines_count }} {{ Str::plural('item', $order->lines_count) }}</span></td>
                            <td data-label="Total" class="inventory-number">{{ $money->format($order->total_minor) }}</td>
                            <td data-label="Payment"><span>@include('admin.commerce.orders.badge', ['value' => $order->payment_status])</span></td>
                            <td data-label="Order status"><span>@include('admin.commerce.orders.badge', ['value' => $order->status])</span></td>
                            <td data-label="Fulfillment"><span>@include('admin.commerce.orders.badge', ['value' => $order->fulfillment_status])</span></td>
                            <td data-label="Action"><a href="{{ route('admin.commerce.orders.show', $order) }}" aria-label="View Order {{ $order->order_number }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody></table>
            @endif
            {{ $orders->links() }}
        </section>
    </div>
</x-admin.layout>
