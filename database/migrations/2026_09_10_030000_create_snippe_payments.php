<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
        });
        Schema::create('commerce_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('commerce_orders')->restrictOnDelete();
            $table->foreignUlid('active_order_id')->nullable()->unique()->constrained('commerce_orders')->restrictOnDelete();
            $table->string('provider', 20)->default('snippe');
            $table->string('status', 24)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('expected_amount_internal_minor');
            $table->unsignedBigInteger('provider_amount_tzs');
            $table->string('attempt_key', 30)->unique();
            $table->char('return_reference', 64)->unique();
            $table->string('provider_session_reference', 191)->nullable()->unique();
            $table->string('provider_payment_reference', 191)->nullable()->unique();
            $table->text('provider_checkout_url')->nullable();
            $table->string('last_provider_status', 32)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('last_failure_reference', 191)->nullable();
            $table->string('reconciliation_issue', 64)->nullable()->index();
            $table->timestamp('request_started_at')->nullable();
            $table->timestamp('io_lease_until')->nullable();
            $table->timestamp('next_reconcile_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('snippe_webhook_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('event_key', 64)->unique();
            $table->char('body_hash', 64);
            $table->string('type', 64);
            $table->string('session_reference', 191)->nullable()->index();
            $table->string('outcome', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snippe_webhook_receipts');
        Schema::dropIfExists('commerce_payments');
        Schema::table('commerce_orders', fn (Blueprint $table) => $table->dropColumn(['confirmed_at', 'closed_at']));
        // System-issued ledger entries have null actors; never invent a staff actor on rollback.
    }
};
