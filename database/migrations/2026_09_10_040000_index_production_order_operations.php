<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table): void {
            $table->index(['payment_status', 'fulfillment_status', 'placed_at'], 'commerce_orders_payment_fulfillment_placed');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table): void {
            $table->dropIndex('commerce_orders_payment_fulfillment_placed');
        });
    }
};
