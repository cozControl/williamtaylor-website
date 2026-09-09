<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->string('new_arrivals_eyebrow', 120)->default('Just Arrived');
            $table->string('new_arrivals_heading', 160)->default('New Arrivals');
            $table->string('new_arrivals_cta_label', 80)->default('View All');
            $table->ulid('new_arrivals_collection_id')->nullable()->index();
            $table->foreign('new_arrivals_collection_id', 'homepage_new_arrivals_collection_fk')
                ->references('id')->on('collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->dropForeign('homepage_new_arrivals_collection_fk');
            $table->dropIndex(['new_arrivals_collection_id']);
            $table->dropColumn(['new_arrivals_eyebrow', 'new_arrivals_heading', 'new_arrivals_cta_label', 'new_arrivals_collection_id']);
        });
    }
};
