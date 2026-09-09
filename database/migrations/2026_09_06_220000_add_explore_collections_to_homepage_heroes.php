<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('explore_collections_managed')->default(false);
            $table->string('explore_collections_eyebrow', 120)->default('Shop By Category');
            $table->string('explore_collections_heading', 160)->default('Explore the Collection');

            foreach ([1, 2, 3] as $position) {
                $table->ulid("explore_collection_{$position}_id")->nullable();
                $table->foreign("explore_collection_{$position}_id", "home_explore_collection_{$position}_fk")
                    ->references('id')->on('collections')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            foreach ([1, 2, 3] as $position) {
                $table->dropForeign("home_explore_collection_{$position}_fk");
            }

            $table->dropColumn([
                'explore_collections_managed',
                'explore_collections_eyebrow',
                'explore_collections_heading',
                'explore_collection_1_id',
                'explore_collection_2_id',
                'explore_collection_3_id',
            ]);
        });
    }
};
