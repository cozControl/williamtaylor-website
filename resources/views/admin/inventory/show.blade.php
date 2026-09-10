<x-admin.layout :title="$variant->product->currentDraftRevision?->title ?? 'Variant inventory'" description="Record stock and review its movement history." eyebrow="Inventory" :breadcrumbs="['Inventory' => route('admin.inventory.index'), 'Variant stock' => null]">
 @include('admin.inventory.styles')
 <div class="inventory-workspace">
  <x-admin.flash :errors="$errors" />
  <section class="admin-panel"><div class="inventory-history-heading"><div>@include('admin.inventory.identity')</div><form method="GET"><label for="inventory-location">Stock location</label><select id="inventory-location" name="location">@foreach($locations as $item)<option value="{{ $item->id }}" @selected($item->is($location))>{{ $item->name }}</option>@endforeach</select><button class="admin-secondary-button">View location</button></form></div></section>
  <section class="inventory-metrics" aria-label="Stock summary"><article><span>On hand at {{ $location->name }}</span><strong>{{ number_format($summary['on_hand']) }}</strong></article><article><span>Available to sell</span><strong>{{ number_format($summary['available']) }}</strong></article><article><span>Stock status</span><strong style="font-size:1rem">{{ $summary['available'] > 0 ? 'In stock' : 'Out of stock' }}</strong></article></section>
  @can('inventory.manage')
   @if($variant->archived_at === null && $variant->product->archived_at === null && $location->active)
    <section class="admin-panel"><div class="admin-section-heading"><p>Stock operations</p><h2>Record stock</h2></div>
     <p class="inventory-help">Receive Stock adds units. Opening Stock establishes the first balance at this location. For a stock count, enter the total units you counted; the system records the difference.</p>
     <form method="POST" action="{{ route('admin.inventory.store', $variant) }}" class="inventory-form">@csrf
      <input type="hidden" name="location" value="{{ $location->id }}"><input type="hidden" name="expected_on_hand" value="{{ $summary['on_hand'] }}"><input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
      <div class="inventory-operation-grid">
       <x-admin.field label="Operation" for="stock-operation" :error="$errors->first('operation')"><select id="stock-operation" name="operation"><option value="receipt" @selected(old('operation') === 'receipt')>Receive Stock</option>@if($canOpen)<option value="opening" @selected(old('operation') === 'opening')>Opening Stock</option>@endif<option value="count" @selected(old('operation') === 'count')>Stock count adjustment</option></select></x-admin.field>
       <x-admin.field label="Units received / total counted" for="stock-quantity" :error="$errors->first('quantity')" help="Whole units only. For a count, enter the new total, not the difference."><input id="stock-quantity" type="number" inputmode="numeric" name="quantity" min="0" max="2147483647" step="1" required value="{{ old('quantity') }}" @if($errors->has('quantity')) aria-invalid="true" aria-describedby="stock-quantity-error stock-quantity-help" @endif></x-admin.field>
       <x-admin.field class="inventory-wide" label="Reason" for="stock-reason" :error="$errors->first('reason')" help="For example: supplier delivery, initial stock count, damage or stock count correction."><input id="stock-reason" name="reason" maxlength="255" required value="{{ old('reason') }}"></x-admin.field>
       <x-admin.field class="inventory-wide" label="Reference / note (optional)" for="stock-note" :error="$errors->first('note')"><textarea id="stock-note" name="note" rows="3" maxlength="2000" placeholder="Delivery reference or additional context">{{ old('note') }}</textarea></x-admin.field>
      </div>
      <div class="inventory-actions"><a class="admin-secondary-button" href="{{ route('admin.inventory.index', ['location' => $location->id]) }}">Back to Inventory</a><button class="admin-primary-button">Record stock</button></div>
     </form>
    </section>
   @else<p class="admin-panel">This Product, Variant or location is inactive. Movement history remains available.</p>@endif
  @endcan
  <section class="admin-panel"><div class="admin-section-heading"><p>{{ $location->name }}</p><h2>Movement history</h2><p>Posted movements are permanent. Correct mistakes with a new adjustment.</p></div>
   <table class="inventory-table"><thead><tr><th>Date / actor</th><th>Movement</th><th>Change</th><th>Balance after</th><th>Reason / reference</th></tr></thead><tbody>
    @forelse($movements as $movement)<tr><td data-label="Date / actor"><div>{{ $movement->occurred_at->timezone(config('app.timezone'))->format('d M Y H:i') }}<small>{{ $movement->actor?->name ?? 'System payment confirmation' }}</small></div></td><td data-label="Movement">{{ $movement->type->label() }}</td><td data-label="Change" class="inventory-number">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ number_format($movement->quantity_delta) }}</td><td data-label="Balance after" class="inventory-number">{{ number_format($movement->balance_after) }}</td><td data-label="Reason"><div>{{ $movement->reason }}@if($movement->note)<small>{{ $movement->note }}</small>@endif</div></td></tr>@empty<tr><td colspan="5">No movements yet. This Variant starts with zero stock at {{ $location->name }}.</td></tr>@endforelse
   </tbody></table>{{ $movements->links() }}
  </section>
 </div>
</x-admin.layout>
