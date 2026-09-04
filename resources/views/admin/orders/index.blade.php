<x-admin.layout title="Orders" description="Search and manage protected demonstration customer Orders.">
    <div class="admin-section-heading"><p>Order Operations</p><h1>Demo customer Orders</h1><p>Demonstration price and payment snapshots only; no stock or settlement claim.</p></div>
    <div class="admin-page-actions">@can('orders.create')<a class="admin-primary-button" href="{{ route('admin.orders.create') }}">Create Demo Order</a>@endcan</div>
    <form method="GET" class="admin-panel admin-form-grid">
        <label>Search<input name="search" value="{{ request('search') }}" placeholder="Order, customer, email or telephone"></label>
        <label>Fulfilment<select name="status"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        <label>Manual payment<select name="payment_status"><option value="">All</option>@foreach($paymentStatuses as $status)<option value="{{ $status->value }}" @selected(request('payment_status') === $status->value)>{{ str($status->value)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        <label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>
        <label>Sort<select name="sort"><option value="newest">Newest</option><option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option></select></label>
        <div class="admin-page-actions"><button class="admin-primary-button">Apply filters</button><a class="admin-secondary-button" href="{{ route('admin.orders.index') }}">Clear</a></div>
    </form>
    <section class="admin-panel">
        <div class="admin-table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Created</th><th>Items</th><th>Total</th><th>Fulfilment</th><th>Manual payment</th><th>Updated</th><th></th></tr></thead><tbody>
        @forelse($orders as $order)<tr><td><strong>{{ $order->order_number }}</strong>@if(in_array($order->status->value,['new','ready']))<br><small>Needs attention</small>@endif</td><td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }} · {{ $order->customer_telephone }}</small></td><td>{{ $order->created_at->format('Y-m-d H:i') }}</td><td>{{ $order->items_count }}</td><td>{{ \App\Domain\Orders\Support\OrderMoney::format($order->total_minor,$order->currency) }}</td><td>{{ str($order->status->value)->replace('_',' ')->title() }}</td><td>{{ str($order->payment_status->value)->replace('_',' ')->title() }}</td><td>{{ $order->updated_at->diffForHumans() }}</td><td><a href="{{ route('admin.orders.show',$order) }}">Open</a></td></tr>
        @empty<tr><td colspan="9">No demo Orders match these filters.</td></tr>@endforelse
        </tbody></table></div>{{ $orders->links() }}
    </section>
</x-admin.layout>
