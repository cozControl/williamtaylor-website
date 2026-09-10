<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_order_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
        });
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('order_number', 40)->unique();
            $table->char('confirmation_reference', 64)->unique();
            $table->char('submission_key', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->char('cart_fingerprint', 64);
            $table->json('customer_snapshot');
            $table->json('delivery_snapshot');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('shipping_status', 32)->default('pending');
            $table->string('status', 32)->index();
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('fulfillment_status', 32)->default('unfulfilled');
            $table->timestamp('placed_at')->index();
            $table->timestamps();
        });
        Schema::create('commerce_order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('product_title_snapshot');
            $table->string('sku_snapshot');
            $table->json('options_snapshot');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->unique(['order_id', 'variant_id']);
        });
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $table->foreignUlid('order_line_id')->unique()->constrained('commerce_order_lines')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 16);
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['stock_location_id', 'variant_id', 'status'], 'reservation_availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('commerce_order_lines');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_order_numbers');
    }
};
