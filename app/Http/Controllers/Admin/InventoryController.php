<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InventoryController
{
    public function index(Request $request, InventoryAvailabilityService $availability): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'location' => ['nullable', 'string', Rule::exists('stock_locations', 'id')], 'status' => ['nullable', Rule::in(['all', 'in', 'out'])], 'product' => ['nullable', 'string', Rule::exists('products', 'id')], 'include_inactive' => ['nullable', 'boolean']]);
        $location = isset($data['location']) ? StockLocation::query()->whereKey($data['location'])->firstOrFail() : StockLocation::main();
        $search = trim($data['search'] ?? '');
        $status = $data['status'] ?? 'all';
        $stocked = InventoryBalance::query()->select('variant_id')->where('stock_location_id', $location->id)->where('on_hand', '>', 0);
        $variants = ProductVariant::query()->whereHas('product')->when(! ($data['include_inactive'] ?? false), fn ($q) => $q->active()->whereHas('product', fn ($q) => $q->whereNull('archived_at')))
            ->with(['product.currentDraftRevision', 'values'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")->orWhereHas('product.currentDraftRevision', fn ($r) => $r->where('title', 'like', "%{$search}%"))))
            ->when(isset($data['product']), fn ($q) => $q->where('product_id', $data['product']))
            ->when($status === 'in', fn ($q) => $location->active && $location->fulfillment_enabled ? $q->active()->whereHas('product', fn ($q) => $q->whereNull('archived_at'))->whereIn('id', $stocked)->whereNotNull('sku')->where('sku', '!=', '') : $q->whereRaw('1 = 0'))
            ->when($status === 'out' && $location->active && $location->fulfillment_enabled, fn ($q) => $q->where(fn ($q) => $q->whereNotIn('id', $stocked)->orWhereNull('sku')->orWhere('sku', '')->orWhereNotNull('archived_at')->orWhereHas('product', fn ($q) => $q->whereNotNull('archived_at'))))
            ->orderBy('product_id')->orderBy('position')->orderBy('id')->paginate(25)->withQueryString();
        $summaries = $availability->summaries($variants->getCollection(), $location);
        $locations = StockLocation::query()->orderBy('code')->get();

        return view('admin.inventory.index', compact('variants', 'summaries', 'locations', 'location', 'search', 'status'));
    }

    public function show(Request $request, ProductVariant $variant, InventoryAvailabilityService $availability): View
    {
        $data = $request->validate(['location' => ['nullable', 'string', Rule::exists('stock_locations', 'id')]]);
        $location = isset($data['location']) ? StockLocation::query()->whereKey($data['location'])->firstOrFail() : StockLocation::main();
        $variant->load(['product.currentDraftRevision', 'values']);
        $summary = $availability->summaries([$variant], $location)[$variant->id];
        $movements = InventoryMovement::query()->where('variant_id', $variant->id)->where('stock_location_id', $location->id)->with('actor')->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $canOpen = ! InventoryMovement::query()->where('variant_id', $variant->id)->where('stock_location_id', $location->id)->exists();
        $locations = StockLocation::query()->orderBy('code')->get();

        return view('admin.inventory.show', compact('variant', 'location', 'summary', 'movements', 'canOpen', 'locations'));
    }

    public function store(Request $request, ProductVariant $variant, InventoryLedgerService $ledger): RedirectResponse
    {
        $data = $request->validate([
            'location' => ['required', 'string', Rule::exists('stock_locations', 'id')],
            'operation' => ['required', Rule::in(['opening', 'receipt', 'count'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.InventoryLedgerService::MAX_UNITS],
            'expected_on_hand' => ['required_if:operation,count', 'nullable', 'integer', 'min:0', 'max:'.InventoryLedgerService::MAX_UNITS],
            'reason' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $location = StockLocation::query()->whereKey($data['location'])->firstOrFail();
        if ($data['operation'] === 'count') {
            $ledger->count($request->user(), $variant, $location, (int) $data['quantity'], (int) $data['expected_on_hand'], $data['reason'], $data['note'] ?? null, $data['idempotency_key']);
        } else {
            $ledger->post($request->user(), $variant, $location, MovementType::from($data['operation']), (int) $data['quantity'], $data['reason'], $data['note'] ?? null, $data['idempotency_key']);
        }

        return redirect()->route('admin.inventory.show', ['variant' => $variant, 'location' => $location->id])->with('status', 'Stock recorded successfully. The movement history and balance are up to date.');
    }
}
