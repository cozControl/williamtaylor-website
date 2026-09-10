<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inventory_reservations', 'commerce_order_lines'] as $table) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $operation) {
                    DB::statement("CREATE TRIGGER {$table}_positive_{$operation} BEFORE {$operation} ON {$table} WHEN NEW.quantity < 1 OR NEW.quantity > 2147483647 BEGIN SELECT RAISE(ABORT, 'Quantity must be positive whole units'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_positive_quantity CHECK (quantity >= 1 AND quantity <= 2147483647)");
            }
        }
    }

    public function down(): void
    {
        foreach (['inventory_reservations', 'commerce_order_lines'] as $table) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $operation) {
                    DB::statement("DROP TRIGGER IF EXISTS {$table}_positive_{$operation}");
                }
            } else {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$table}_positive_quantity");
            }
        }
    }
};
