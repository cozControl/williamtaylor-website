<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['content_revisions', 'product_revisions', 'collection_revisions', 'campaign_revisions'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->ulid('source_revision_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        foreach (['content_revisions', 'product_revisions', 'collection_revisions', 'campaign_revisions'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('source_revision_id'));
        }
    }
};
