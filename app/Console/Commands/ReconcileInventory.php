<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {--location= : Optional Stock Location ID} {--variant= : Optional Variant ID}';

    protected $description = 'Read-only comparison of inventory ledger totals and balance projections';

    public function handle(): int
    {
        $ledger = DB::table('inventory_movements')->selectRaw('stock_location_id, variant_id, SUM(quantity_delta) as total')->groupBy('stock_location_id', 'variant_id');
        $balances = DB::table('inventory_balances')->selectRaw('stock_location_id, variant_id, on_hand as total');
        foreach (['location' => 'stock_location_id', 'variant' => 'variant_id'] as $option => $column) {
            if ($this->option($option) !== null) {
                $ledger->where($column, $this->option($option));
                $balances->where($column, $this->option($option));
            }
        }
        $keys = (clone $ledger)->selectRaw('0 as projection')->unionAll((clone $balances)->selectRaw('1 as projection'));
        $rows = DB::query()->fromSub($keys, 'totals')->selectRaw('stock_location_id, variant_id, SUM(CASE WHEN projection = 0 THEN total ELSE 0 END) as ledger_total, SUM(CASE WHEN projection = 1 THEN total ELSE 0 END) as balance_total')->groupBy('stock_location_id', 'variant_id')->orderBy('stock_location_id')->orderBy('variant_id')->cursor();
        $checked = 0;
        $drift = 0;
        foreach ($rows as $row) {
            $checked++;
            if ((int) $row->ledger_total !== (int) $row->balance_total) {
                $drift++;
                $this->error("Drift: location {$row->stock_location_id}, Variant {$row->variant_id}: ledger {$row->ledger_total}, projection {$row->balance_total}");
            }
        }
        $this->info("Checked {$checked} balances; {$drift} drifted. Read-only: no stock was changed.");

        return $drift === 0 ? self::SUCCESS : self::FAILURE;
    }
}
