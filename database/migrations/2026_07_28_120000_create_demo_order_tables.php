<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('order_number', 32)->unique();
            $table->boolean('is_demo')->default(true)->index();
            $table->string('fixture_key', 80)->nullable()->unique();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name', 160);
            $table->string('customer_email', 254);
            $table->string('customer_telephone', 40);
            $table->text('delivery_address');
            $table->text('delivery_instructions')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->bigInteger('adjustment_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('status', 32)->index();
            $table->string('payment_status', 32)->index();
            $table->text('customer_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('source', 32)->default('admin-demo');
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('receipt_reference', 48)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name', 200);
            $table->string('variant_name', 200)->nullable();
            $table->string('sku', 100)->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['order_id', 'position']);
        });

        Schema::create('order_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('visibility', 32)->default('internal');
            $table->text('note');
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_payment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_events');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
