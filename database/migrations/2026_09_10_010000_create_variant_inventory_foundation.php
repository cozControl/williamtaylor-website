<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->boolean('fulfillment_enabled')->default(true);
            $table->timestamps();
        });
        DB::table('stock_locations')->insert(['id' => '01M1NVENT0RY00000000000000', 'code' => 'MAIN', 'name' => 'Main Store', 'active' => true, 'fulfillment_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->timestamps();
            $table->unique(['stock_location_id', 'variant_id']);
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('type', 40);
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 255);
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('created_at');
            $table->index(['stock_location_id', 'variant_id', 'created_at'], 'inventory_movement_history');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('stock_locations');
    }
};
