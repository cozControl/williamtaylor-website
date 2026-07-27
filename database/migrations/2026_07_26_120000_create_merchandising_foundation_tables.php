<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_badges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('badge_key', 64);
            $table->unsignedSmallInteger('position');
            $table->string('active_key', 191)->unique();
            $table->string('position_key', 191)->unique();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'archived_at', 'position'], 'prod_badge_order_idx');
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('target_product_id')->constrained('products')->restrictOnDelete();
            $table->string('relation_kind', 64);
            $table->unsignedSmallInteger('position');
            $table->string('active_key', 191)->unique();
            $table->string('position_key', 191)->unique();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['source_product_id', 'relation_kind', 'archived_at', 'position'], 'prod_relation_order_idx');
        });

        Schema::create('product_placements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slot_key', 64);
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('active_key', 191)->unique();
            $table->string('position_key', 191)->unique();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['slot_key', 'archived_at', 'position'], 'prod_placement_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_placements');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_badges');
    }
};
