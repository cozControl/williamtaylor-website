<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_section_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('homepage_hero_id')->constrained('homepage_heroes')->cascadeOnDelete();
            $table->string('section_key', 64);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['homepage_hero_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_section_settings');
    }
};
