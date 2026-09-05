<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_heroes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('eyebrow', 120);
            $table->string('title', 160);
            $table->string('subtitle', 240);
            $table->string('primary_cta_label', 80);
            $table->string('primary_cta_destination', 32);
            $table->string('secondary_cta_label', 80);
            $table->string('secondary_cta_destination', 32);
            $table->boolean('scroll_indicator_enabled')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_heroes');
    }
};
