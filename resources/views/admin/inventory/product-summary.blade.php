@can('inventory.view')
 @include('admin.inventory.styles')
 <section class="admin-panel inventory-workspace inventory-product-summary" aria-labelledby="product-inventory-title">
  <div class="inventory-history-heading"><div class="admin-section-heading"><p>Inventory / Main Store</p><h2 id="product-inventory-title">Stock by Variant</h2><p>Stock is separate from Product content readiness. New Variants start at zero.</p></div><a class="admin-secondary-button" href="{{ route('admin.inventory.index', ['product' => $product->id]) }}">Manage Inventory</a></div>
  <table class="inventory-table"><thead><tr><th>Variant / SKU</th><th>On hand</th><th>Available</th><th>Action</th></tr></thead><tbody>
   @foreach($product->variants->whereNull('archived_at') as $variant)<tr><td data-label="Variant"><div>{{ $variant->values->pluck('label')->join(' / ') ?: $product->currentDraftRevision?->title }}<small>{{ $variant->sku }}</small></div></td><td data-label="On hand">{{ number_format($inventorySummary[$variant->id]['on_hand']) }}</td><td data-label="Available">{{ number_format($inventorySummary[$variant->id]['available']) }}</td><td data-label="Action"><a href="{{ route('admin.inventory.show', $variant) }}">@can('inventory.manage')Add / manage stock @else View history @endcan</a></td></tr>@endforeach
  </tbody></table>
 </section>
@endcan
