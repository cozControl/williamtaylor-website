<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_contents', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_contents', 'type')) {
                $table->string('type', 48)->nullable()->after('id');
            }
            if (! Schema::hasColumn('site_contents', 'title')) {
                $table->string('title', 160)->nullable()->after('locale');
            }
            if (! Schema::hasColumn('site_contents', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('site_contents', 'archived_at')) {
                $table->timestampTz('archived_at')->nullable()->after('archived_by');
            }
        });
    }

    public function down(): void
    {
        // The corrective architecture preserves reconciled resources and is not destructively reversed.
    }
};
