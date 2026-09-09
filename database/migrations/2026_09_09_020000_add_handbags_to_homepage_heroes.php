<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('handbags_managed')->default(false);
            $table->string('handbags_eyebrow', 120)->default('For Her');
            $table->string('handbags_heading', 160)->default("Women's Handbags");
            $table->string('handbags_cta_label', 80)->default('View All');
            $table->string('handbags_hero_eyebrow', 120)->default('New Collection');
            $table->string('handbags_hero_heading', 160)->default('Crafted for Her');
            $table->string('handbags_hero_copy', 500)->default('From totes to clutches — each piece handcrafted in our Dar es Salaam atelier.');
            $table->string('handbags_hero_cta_label', 80)->default('Shop the Collection');
            $table->foreignUlid('handbags_collection_id')->nullable()->constrained('collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->dropForeign(['handbags_collection_id']);
            $table->dropColumn(['handbags_managed', 'handbags_eyebrow', 'handbags_heading', 'handbags_cta_label', 'handbags_hero_eyebrow', 'handbags_hero_heading', 'handbags_hero_copy', 'handbags_hero_cta_label', 'handbags_collection_id']);
        });
    }
};
