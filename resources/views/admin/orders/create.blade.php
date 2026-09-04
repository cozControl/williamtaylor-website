<x-admin.layout title="Create manual Order" description="Create a staff-entered Order snapshot. This is not connected to public checkout or payment processing.">
    <div class="admin-section-heading"><p>Order Operations</p><h1>Create Demo Order</h1><p>Pricing, payment and inventory are not authoritative. No stock check or payment occurs.</p></div>
    <form method="POST" action="{{ route('admin.orders.store') }}" class="admin-panel admin-form-grid" data-unsaved-warning>@csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',$idempotencyKey) }}">
        <label>Customer name<input required name="customer_name" value="{{ old('customer_name') }}"></label>
        <label>Email<input required type="email" name="customer_email" value="{{ old('customer_email') }}"></label>
        <label>Telephone<input required name="customer_telephone" value="{{ old('customer_telephone') }}"></label>
        <label>Delivery address<textarea required name="delivery_address">{{ old('delivery_address') }}</textarea></label>
        <label>Delivery instructions<textarea name="delivery_instructions">{{ old('delivery_instructions') }}</textarea></label>
        <label>Customer-facing note<textarea name="customer_note">{{ old('customer_note') }}</textarea></label>
        <label>Internal note<textarea name="internal_note">{{ old('internal_note') }}</textarea></label>
        <fieldset><legend>Order line</legend><label>Product snapshot<input required name="items[0][product_name]"></label><label>Variant/options snapshot<input name="items[0][variant_name]"></label><label>SKU snapshot<input name="items[0][sku]"></label><label>Quantity<input required type="number" min="1" name="items[0][quantity]" value="1"></label><label>Demo unit amount in minor units<input required type="number" min="0" name="items[0][unit_amount_minor]"></label></fieldset>
        @if($errors->any())<div class="admin-feedback is-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="admin-page-actions"><button class="admin-primary-button">Create Demo Order</button><a class="admin-secondary-button" href="{{ route('admin.orders.index') }}">Cancel</a></div>
    </form>
    <script>document.querySelector('[data-unsaved-warning]')?.addEventListener('change',()=>window.onbeforeunload=()=>true);document.querySelector('[data-unsaved-warning]')?.addEventListener('submit',()=>window.onbeforeunload=null);</script>
</x-admin.layout>
