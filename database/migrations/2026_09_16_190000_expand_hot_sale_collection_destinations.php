<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->resize(64);
    }

    public function down(): void
    {
        foreach ([1, 2, 3] as $position) {
            if (DB::table('homepage_heroes')->whereRaw('length(hot_sale_tile_'.$position.'_destination) > 32')->exists()) {
                throw new RuntimeException('Hot Sale contains Collection destinations. Choose shorter destinations before rolling back.');
            }
        }
        $this->resize(32);
    }

    private function resize(int $length): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table) use ($length): void {
            foreach ([1 => 'shop_newest', 2 => 'collections', 3 => 'shop'] as $position => $default) {
                $table->string("hot_sale_tile_{$position}_destination", $length)->default($default)->change();
            }
        });
    }
};
