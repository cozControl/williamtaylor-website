<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 32)->default('cloudinary');
            $table->string('provider_asset_id')->unique();
            $table->string('provider_public_id')->unique();
            $table->string('provider_version')->nullable();
            $table->string('resource_type', 16);
            $table->string('delivery_type', 32)->default('upload');
            $table->string('format', 16);
            $table->string('mime_type', 100);
            $table->string('original_filename');
            $table->string('internal_title');
            $table->text('default_alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->string('rights_source')->nullable();
            $table->text('rights_notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('collection_key')->nullable()->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('bytes');
            $table->string('checksum')->nullable()->index();
            $table->string('perceptual_hash')->nullable();
            $table->decimal('focal_x', 5, 4)->nullable();
            $table->decimal('focal_y', 5, 4)->nullable();
            $table->string('dominant_color', 16)->nullable();
            $table->string('accessibility_classification', 24)->default('informative');
            $table->boolean('is_decorative')->default(false);
            $table->string('state', 24)->default('processing')->index();
            $table->text('processing_error')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('media_asset_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('provider_asset_id')->unique();
            $table->string('provider_public_id');
            $table->string('provider_version')->nullable();
            $table->string('resource_type', 16);
            $table->string('format', 16);
            $table->string('mime_type', 100);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('bytes');
            $table->string('checksum')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at');
            $table->unique(['media_asset_id', 'version_number']);
            $table->index(['media_asset_id', 'is_current']);
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('owner_type');
            $table->string('owner_identifier');
            $table->string('field_role');
            $table->string('locale', 16)->nullable();
            $table->text('alt_text_override')->nullable();
            $table->boolean('decorative_override')->nullable();
            $table->text('caption_override')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_identifier', 'field_role', 'locale', 'sort_order'], 'media_usage_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_asset_versions');
        Schema::dropIfExists('media_assets');
    }
};
