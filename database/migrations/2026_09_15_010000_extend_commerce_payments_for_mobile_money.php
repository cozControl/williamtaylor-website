<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_payments', function (Blueprint $table) {
            // Existing hosted attempts keep their original contract and evidence.
            $table->string('method', 24)->default('hosted_session');
            $table->char('io_lease_token', 32)->nullable();
            $table->text('customer_phone')->nullable();
            $table->text('request_snapshot')->nullable();
            $table->json('last_evidence')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('expired_at')->nullable();
        });
        Schema::table('snippe_webhook_receipts', function (Blueprint $table) {
            $table->string('provider_reference', 191)->nullable()->index();
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('snippe_webhook_receipts', fn (Blueprint $table) => $table->dropColumn(['provider_reference', 'processed_at']));
        Schema::table('commerce_payments', fn (Blueprint $table) => $table->dropColumn(['io_lease_token', 'method', 'customer_phone', 'request_snapshot', 'last_evidence', 'last_verified_at', 'expired_at']));
    }
};
