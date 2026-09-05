<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->foreignUlid('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignUlid('image_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['parent_id', 'is_visible', 'position'], 'prod_cat_tree_idx');
        });

        Schema::create('product_category_assignments', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->primary(['product_id', 'product_category_id'], 'prod_cat_assignment_pk');
            $table->index(['product_id', 'is_primary'], 'prod_cat_primary_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('base_price_minor')->nullable()->after('default_variant_id');
            $table->unsignedBigInteger('compare_at_price_minor')->nullable()->after('base_price_minor');
            $table->char('currency', 3)->default('TZS')->after('compare_at_price_minor');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_override_minor')->nullable()->after('editorial_label');
            $table->unsignedBigInteger('compare_at_price_override_minor')->nullable()->after('price_override_minor');
        });

        Schema::table('product_option_values', function (Blueprint $table): void {
            $table->char('swatch_hex', 7)->nullable()->after('label');
            $table->foreignUlid('swatch_media_asset_id')->nullable()->after('swatch_hex')->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('swatch_media_asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_option_values', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('swatch_media_asset_id');
            $table->dropColumn(['swatch_hex', 'is_active']);
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['price_override_minor', 'compare_at_price_override_minor']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['base_price_minor', 'compare_at_price_minor', 'currency']));
        Schema::dropIfExists('product_category_assignments');
        Schema::dropIfExists('product_categories');
    }
};
