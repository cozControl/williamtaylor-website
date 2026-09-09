<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->boolean('client_stories_managed')->default(false);
            $table->string('client_stories_eyebrow', 120)->default('Client Stories');
            $table->string('client_stories_heading', 160)->default('What They Say');
        });
        Schema::create('homepage_client_stories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('homepage_hero_id')->constrained('homepage_heroes')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->boolean('is_visible')->default(false);
            $table->string('display_name', 120)->nullable();
            $table->string('location', 160)->nullable();
            $table->string('quote', 1000)->nullable();
            $table->timestamps();
            $table->unique(['homepage_hero_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_client_stories');
        Schema::table('homepage_heroes', function (Blueprint $table): void {
            $table->dropColumn(['client_stories_managed', 'client_stories_eyebrow', 'client_stories_heading']);
        });
    }
};
