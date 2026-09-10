<div><strong>{{ $variant->product->currentDraftRevision?->title ?? $variant->product->slug }}</strong>
@if($variant->values->isNotEmpty())<small>{{ $variant->values->sortBy('pivot.product_option_id')->pluck('label')->join(' / ') }}</small>@endif
@if($variant->archived_at !== null || $variant->product->archived_at !== null)<small>Inactive - history retained</small>@endif
<small>SKU: {{ $variant->sku ?: 'Not assigned' }}</small></div>
