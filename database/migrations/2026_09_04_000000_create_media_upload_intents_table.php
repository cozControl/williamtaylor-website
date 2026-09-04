<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_upload_intents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('public_id', 255)->unique();
            $table->string('resource_type', 20);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('expected_bytes');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['actor_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_upload_intents');
    }
};
