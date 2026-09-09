<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('hot_sale_managed')->default(false);
            $table->string('hot_sale_eyebrow', 120)->default('Limited Time');
            $table->string('hot_sale_heading', 160)->default("William's Hot Sale");
            $tiles = [
                1 => ['The Atelier Edit', 'Statement pieces from the house, hand-finished in Dar es Salaam.', 'shop_newest'],
                2 => ['The Shopping Experience', 'Carry the collection home in signature William Taylor style.', 'collections'],
                3 => ['The Signature Bag', 'Oxblood and gold — the William Taylor hallmark, carried worldwide.', 'shop'],
            ];
            foreach ($tiles as $position => [$title, $copy, $destination]) {
                $table->string("hot_sale_tile_{$position}_title", 160)->default($title);
                $table->string("hot_sale_tile_{$position}_copy", 320)->default($copy);
                $table->string("hot_sale_tile_{$position}_cta_label", 80)->default('Discover');
                $table->string("hot_sale_tile_{$position}_destination", 32)->default($destination);
            }
        });
    }

    public function down(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $columns = ['hot_sale_managed', 'hot_sale_eyebrow', 'hot_sale_heading'];
            foreach ([1, 2, 3] as $position) {
                array_push($columns, "hot_sale_tile_{$position}_title", "hot_sale_tile_{$position}_copy", "hot_sale_tile_{$position}_cta_label", "hot_sale_tile_{$position}_destination");
            }
            $table->dropColumn($columns);
        });
    }
};
