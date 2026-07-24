<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 32);
            $table->string('locale', 16)->default('en');
            $table->string('title');
            $table->string('slug');
            $table->string('template_key', 64);
            $table->ulid('current_draft_revision_id')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('resource_type', 64);
            $table->ulid('resource_id');
            $table->unsignedInteger('revision_number');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('payload');
            $table->char('checksum', 64);
            $table->string('sanitizer_version', 32)->default('1');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['resource_type', 'resource_id', 'revision_number'], 'content_revision_number_unique');
            $table->index(['resource_type', 'resource_id', 'created_at'], 'content_revision_resource_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('pages');
    }
};
