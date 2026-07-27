<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('collection_type', 32);
            $table->string('slug', 160)->unique('coll_slug_uq');
            $table->string('catalogue_status', 16)->default('draft');
            $table->ulid('current_draft_revision_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by');
            $table->timestamps();
            $table->index(['catalogue_status', 'archived_at'], 'coll_state_idx');
            $table->foreign('created_by', 'coll_created_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'coll_archived_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('collection_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('collection_id');
            $table->unsignedInteger('revision_number');
            $table->string('title', 255);
            $table->text('short_description');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->char('checksum', 64);
            $table->string('revision_note', 500)->nullable();
            $table->foreignId('created_by');
            $table->timestamp('created_at');
            $table->unique(['collection_id', 'revision_number'], 'coll_rev_num_uq');
            $table->index(['collection_id', 'created_at'], 'coll_rev_hist_idx');
            $table->foreign('collection_id', 'coll_rev_collection_fk')->references('id')->on('collections')->restrictOnDelete();
            $table->foreign('created_by', 'coll_rev_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('collection_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('collection_id');
            $table->ulid('product_id');
            $table->unsignedInteger('position');
            $table->char('active_product_key', 64);
            $table->char('position_key', 64);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique(['collection_id', 'active_product_key'], 'coll_prod_active_uq');
            $table->unique(['collection_id', 'position_key'], 'coll_prod_pos_uq');
            $table->index(['collection_id', 'archived_at', 'position'], 'coll_prod_order_idx');
            $table->foreign('collection_id', 'coll_prod_collection_fk')->references('id')->on('collections')->restrictOnDelete();
            $table->foreign('product_id', 'coll_prod_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('created_by', 'coll_prod_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'coll_prod_archived_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->foreign('current_draft_revision_id', 'coll_draft_fk')->references('id')->on('collection_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', fn (Blueprint $table) => $table->dropForeign('coll_draft_fk'));
        Schema::dropIfExists('collection_products');
        Schema::dropIfExists('collection_revisions');
        Schema::dropIfExists('collections');
    }
};
