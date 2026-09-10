<x-admin.layout title="Cancel order" :description="$order->order_number" eyebrow="Order operations" :breadcrumbs="['Orders' => route('admin.commerce.orders.index'), $order->order_number => route('admin.commerce.orders.show', $order), 'Cancel order' => null]">
    @include('admin.commerce.orders.styles')
    <div class="inventory-workspace">
        <x-admin.flash :errors="$errors" />
        <section class="admin-panel admin-mutation-panel">
            <div class="admin-section-heading"><p>Confirm cancellation</p><h2>{{ $order->order_number }}</h2></div>
            @if($restriction)
                <p>{{ $restriction }}</p><a class="admin-secondary-button" href="{{ route('admin.commerce.orders.show', $order) }}">Back to Order</a>
            @else
                <p>This will cancel the unpaid order and release its reserved inventory once payment cancellation is confirmed. The order will remain in your history.</p>
                <form method="POST" action="{{ route('admin.commerce.orders.cancel', $order) }}" class="admin-change-form">
                    @csrf
                    <x-admin.field label="Cancellation reason" for="cancellation-reason" :error="$errors->first('cancellation_reason')" help="For example: customer requested cancellation, duplicate order, or unable to complete payment. Internal staff use only; omit personal or payment details.">
                        <textarea id="cancellation-reason" name="cancellation_reason" maxlength="500" rows="3" required>{{ old('cancellation_reason') }}</textarea>
                    </x-admin.field>
                    <label class="admin-confirmation"><input style="width:auto" type="checkbox" name="confirm_cancellation" value="1" required> I confirm this unpaid order should be cancelled.</label>
                    <div class="inventory-actions"><a class="admin-secondary-button" href="{{ route('admin.commerce.orders.show', $order) }}">Keep order</a><button class="admin-danger-button">Cancel order</button></div>
                </form>
            @endif
        </section>
    </div>
</x-admin.layout>
