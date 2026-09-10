<x-admin.layout title="Inventory" description="Stock by Variant and location. Receive units, record counts and trace every movement." eyebrow="Catalogue" :breadcrumbs="['Inventory' => null]">
 @include('admin.inventory.styles')
 <div class="inventory-workspace">
  <x-admin.flash :errors="$errors" />
  <form method="GET" class="admin-panel inventory-filters" aria-label="Inventory filters">
   <label>Search Product or SKU<input name="search" value="{{ $search }}" placeholder="Product name or SKU"></label>
   <label>Location<select name="location">@foreach($locations as $item)<option value="{{ $item->id }}" @selected($item->is($location))>{{ $item->name }}{{ $item->active ? '' : ' (inactive)' }}</option>@endforeach</select></label>
   <label>Stock status<select name="status"><option value="all">All stock</option><option value="in" @selected($status === 'in')>In stock</option><option value="out" @selected($status === 'out')>Out of stock</option></select></label>
   @if(request('product'))<input type="hidden" name="product" value="{{ request('product') }}">@endif
   <button class="admin-primary-button">Apply filters</button>
   <label style="display:flex;align-items:center;gap:.5rem"><input style="width:auto" type="checkbox" name="include_inactive" value="1" @checked(request()->boolean('include_inactive'))> Include inactive Variants and their retained stock</label>
  </form>
  <section class="admin-panel">
   <div class="inventory-history-heading"><div class="admin-section-heading"><p>{{ $location->name }}</p><h2>Variant stock</h2><p>{{ $variants->total() }} matching Variants. Stock starts at zero until recorded.</p></div><a class="admin-secondary-button" href="{{ route('admin.inventory.index') }}">Clear filters</a></div>
   <table class="inventory-table"><thead><tr><th class="inventory-identity">Product / Variant / SKU</th><th>Location</th><th>On hand</th><th>Available</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($variants as $variant)
     @php
      $stock = $summaries[$variant->id];
     @endphp
     <tr><td data-label="Product">@include('admin.inventory.identity')</td><td data-label="Location">{{ $location->name }}</td><td data-label="On hand" class="inventory-number">{{ number_format($stock['on_hand']) }}</td><td data-label="Available" class="inventory-number">{{ number_format($stock['available']) }}</td><td data-label="Status"><span><span class="inventory-stock" data-in-stock="{{ $stock['available'] > 0 ? 'true' : 'false' }}">{{ $stock['available'] > 0 ? 'In stock' : 'Out of stock' }}</span></span></td><td data-label="Action"><a href="{{ route('admin.inventory.show', ['variant' => $variant, 'location' => $location->id]) }}">@can('inventory.manage')Manage stock @else View history @endcan</a></td></tr>
    @empty<tr><td colspan="6">No Variants match these filters. Save Product Variants before adding inventory.</td></tr>@endforelse
   </tbody></table>
   {{ $variants->links() }}
  </section>
 </div>
</x-admin.layout>
