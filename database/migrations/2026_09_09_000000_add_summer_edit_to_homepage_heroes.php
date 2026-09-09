<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('summer_edit_managed')->default(false);
            $table->string('summer_edit_eyebrow', 120)->default('Summer 2026');
            $table->string('summer_edit_heading', 160)->default('The Summer Edit');
            $table->string('summer_edit_copy_prefix', 120)->default('Up to');
            $table->string('summer_edit_highlight', 120)->default('30% Off');
            $table->string('summer_edit_copy', 500)->default('selected styles. An invitation to acquire curated pieces at exceptional value.');
            $table->string('summer_edit_cta_label', 80)->default('Shop the Edit');
            $table->foreignUlid('summer_edit_collection_id')->nullable()->constrained('collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->dropForeign(['summer_edit_collection_id']);
            $table->dropColumn(['summer_edit_managed', 'summer_edit_eyebrow', 'summer_edit_heading', 'summer_edit_copy_prefix', 'summer_edit_highlight', 'summer_edit_copy', 'summer_edit_cta_label', 'summer_edit_collection_id']);
        });
    }
};
