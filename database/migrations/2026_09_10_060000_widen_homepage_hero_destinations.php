<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->string('primary_cta_destination', 64)->change();
            $table->string('secondary_cta_destination', 64)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('homepage_heroes')->whereRaw('LENGTH(primary_cta_destination) > 32 OR LENGTH(secondary_cta_destination) > 32')->exists()) {
            throw new RuntimeException('Choose legacy Hero destinations before narrowing the destination columns.');
        }

        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->string('primary_cta_destination', 32)->change();
            $table->string('secondary_cta_destination', 32)->change();
        });
    }
};
