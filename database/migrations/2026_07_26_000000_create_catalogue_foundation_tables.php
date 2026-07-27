<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('stable_key', 160);
            $table->string('slug', 160);
            $table->string('product_type', 32);
            $table->string('catalogue_status', 16)->default('draft');
            $table->ulid('current_draft_revision_id')->nullable();
            $table->ulid('default_variant_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique('stable_key', 'prod_stable_uq');
            $table->unique('slug', 'prod_slug_uq');
            $table->index(['catalogue_status', 'archived_at'], 'prod_state_idx');
            $table->foreign('created_by', 'prod_created_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'prod_archived_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('product_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('product_id');
            $table->unsignedInteger('revision_number');
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('short_description')->nullable();
            $table->json('description_document');
            $table->longText('description_html');
            $table->text('materials')->nullable();
            $table->text('fit')->nullable();
            $table->text('care')->nullable();
            $table->json('features');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('sanitizer_version', 16);
            $table->char('checksum', 64);
            $table->foreignId('created_by');
            $table->timestamp('created_at');
            $table->unique(['product_id', 'revision_number'], 'prod_rev_num_uq');
            $table->index(['product_id', 'created_at'], 'prod_rev_hist_idx');
            $table->foreign('product_id', 'prod_rev_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('created_by', 'prod_rev_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('product_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('product_id');
            $table->string('key', 32);
            $table->string('label', 80);
            $table->unsignedTinyInteger('position');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'key'], 'prod_opt_key_uq');
            $table->unique(['product_id', 'position'], 'prod_opt_pos_uq');
            $table->foreign('product_id', 'prod_opt_product_fk')->references('id')->on('products')->restrictOnDelete();
        });
        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('product_option_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['product_option_id', 'key'], 'prod_val_key_uq');
            $table->unique(['product_option_id', 'position'], 'prod_val_pos_uq');
            $table->foreign('product_option_id', 'prod_val_option_fk')->references('id')->on('product_options')->restrictOnDelete();
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('product_id');
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('editorial_label', 160)->nullable();
            $table->char('combination_fingerprint', 64);
            $table->string('catalogue_status', 16)->default('draft');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique('sku', 'prod_var_sku_uq');
            $table->unique('barcode', 'prod_var_barcode_uq');
            $table->unique(['product_id', 'combination_fingerprint'], 'prod_var_combo_uq');
            $table->index(['product_id', 'archived_at'], 'prod_var_active_idx');
            $table->foreign('product_id', 'prod_var_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('created_by', 'prod_var_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'prod_var_archived_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('product_variant_values', function (Blueprint $table): void {
            $table->ulid('variant_id');
            $table->ulid('product_option_id');
            $table->ulid('product_option_value_id');
            $table->timestamp('created_at');
            $table->primary(['variant_id', 'product_option_id'], 'prod_var_val_pk');
            $table->unique(['variant_id', 'product_option_value_id'], 'prod_var_val_uq');
            $table->foreign('variant_id', 'prod_var_val_variant_fk')->references('id')->on('product_variants')->restrictOnDelete();
            $table->foreign('product_option_id', 'prod_var_val_option_fk')->references('id')->on('product_options')->restrictOnDelete();
            $table->foreign('product_option_value_id', 'prod_var_val_value_fk')->references('id')->on('product_option_values')->restrictOnDelete();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('current_draft_revision_id', 'prod_draft_fk')->references('id')->on('product_revisions')->nullOnDelete();
            $table->foreign('default_variant_id', 'prod_default_var_fk')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('prod_draft_fk');
            $table->dropForeign('prod_default_var_fk');
        });
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_revisions');
        Schema::dropIfExists('products');
    }
};
