<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('campaign_type', 32);
            $table->string('internal_code', 100)->unique('camp_code_uq');
            $table->string('lifecycle_status', 16)->default('draft');
            $table->ulid('current_draft_revision_id')->nullable();
            $table->ulid('approved_revision_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('business_timezone', 64)->default('Africa/Nairobi');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by');
            $table->timestamps();
            $table->index(['starts_at', 'ends_at', 'archived_at'], 'camp_schedule_idx');
            $table->foreign('created_by', 'camp_created_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'camp_archived_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('campaign_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->unsignedInteger('revision_number');
            $table->string('headline', 255);
            $table->text('summary');
            $table->string('cta_label', 80);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->char('checksum', 64);
            $table->string('revision_note', 500)->nullable();
            $table->foreignId('created_by');
            $table->timestamp('created_at');
            $table->unique(['campaign_id', 'revision_number'], 'camp_rev_num_uq');
            $table->foreign('campaign_id', 'camp_rev_campaign_fk')->references('id')->on('campaigns')->restrictOnDelete();
            $table->foreign('created_by', 'camp_rev_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('campaign_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('product_id');
            $table->unsignedSmallInteger('position');
            $table->char('active_product_key', 64);
            $table->char('position_key', 64);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique(['campaign_id', 'active_product_key'], 'camp_prod_active_uq');
            $table->unique(['campaign_id', 'position_key'], 'camp_prod_pos_uq');
            $table->index(['campaign_id', 'archived_at', 'position'], 'camp_prod_order_idx');
            $table->foreign('campaign_id', 'camp_prod_campaign_fk')->references('id')->on('campaigns')->restrictOnDelete();
            $table->foreign('product_id', 'camp_prod_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('created_by', 'camp_prod_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('archived_by', 'camp_prod_archived_fk')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('campaign_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->string('claim_key', 64);
            $table->text('normalized_value');
            $table->char('value_checksum', 64);
            $table->char('approved_checksum', 64)->nullable();
            $table->string('evidence_reference', 500);
            $table->string('evidence_summary', 500);
            $table->string('approval_status', 16)->default('draft');
            $table->foreignId('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
            $table->foreignId('created_by');
            $table->foreignId('last_material_by');
            $table->timestamps();
            $table->unique(['campaign_id', 'claim_key'], 'camp_claim_key_uq');
            $table->index(['campaign_id', 'approval_status', 'archived_at'], 'camp_claim_state_idx');
            $table->foreign('campaign_id', 'camp_claim_campaign_fk')->references('id')->on('campaigns')->restrictOnDelete();
            $table->foreign('created_by', 'camp_claim_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('last_material_by', 'camp_claim_material_by_fk')->references('id')->on('users')->restrictOnDelete();
            foreach (['submitted_by', 'approved_by', 'rejected_by', 'archived_by'] as $column) {
                $table->foreign($column, 'camp_claim_'.$column.'_fk')->references('id')->on('users')->nullOnDelete();
            }
        });
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign('current_draft_revision_id', 'camp_draft_fk')->references('id')->on('campaign_revisions')->nullOnDelete();
            $table->foreign('approved_revision_id', 'camp_approved_fk')->references('id')->on('campaign_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign('camp_draft_fk');
            $table->dropForeign('camp_approved_fk');
        });
        Schema::dropIfExists('campaign_claims');
        Schema::dropIfExists('campaign_products');
        Schema::dropIfExists('campaign_revisions');
        Schema::dropIfExists('campaigns');
    }
};
