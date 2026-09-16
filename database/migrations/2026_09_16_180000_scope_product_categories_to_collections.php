<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Resolve every record before the first DDL statement (MySQL DDL is not transactional).
        $owners = [];
        $categories = DB::table('product_categories')->orderBy('id')->get();
        foreach ($categories as $category) {
            $products = DB::table('product_category_assignments')->where('product_category_id', $category->id)->pluck('product_id')->sort()->values()->all();
            $memberships = DB::table('collection_products')->whereIn('product_id', $products)->whereNull('archived_at')->get();
            $candidates = $memberships->pluck('collection_id')->unique()->values()->all();
            $owner = count($candidates) === 1 && count($memberships->pluck('product_id')->unique()) === count($products) ? $candidates[0] : null;

            // User-approved historical exception, 2026-09-16: retain the original four
            // assignments and all memberships; Category One belongs to New Arrivals.
            if ($category->id === '01m1r5txaw7hm5h8wp83kfkn8b') {
                $expected = ['01m1r803qg9ffj90ka7tc13jcd', '01m1ras8bewrcyt3c19vm3cf5h', '01m1v2fvqe716mg6qmby7pwrvp', '01m1vppcdz07ksrbgews3tey9k'];
                $approved = '01m1v480fhmfn6beca07smkh0n';
                if ($products !== $expected || $memberships->where('collection_id', $approved)->pluck('product_id')->unique()->count() !== 4) {
                    throw new RuntimeException('Category One changed since its ownership approval; review before migrating.');
                }
                $owner = $approved;
            }
            if ($owner === null || ! DB::table('collections')->where('id', $owner)->exists()) {
                throw new RuntimeException("Category ownership requires an explicit decision: {$category->id} ({$category->slug}). No schema changes were made.");
            }
            $owners[$category->id] = $owner;
        }
        foreach ($categories as $category) {
            if ($category->parent_id !== null && $owners[$category->parent_id] !== $owners[$category->id]) {
                throw new RuntimeException("Category parent crosses Collection ownership: {$category->id}. No schema changes were made.");
            }
        }

        $this->changeSchema(function () use ($owners): void {
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->foreignUlid('collection_id')->nullable()->constrained('collections')->restrictOnDelete();
            });
            foreach ($owners as $categoryId => $collectionId) {
                DB::table('product_categories')->where('id', $categoryId)->update(['collection_id' => $collectionId]);
            }
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->ulid('collection_id')->nullable(false)->change();
                $table->dropUnique('product_categories_slug_unique');
                $table->unique(['collection_id', 'slug'], 'prod_cat_collection_slug_unique');
                $table->index(['collection_id', 'archived_at', 'is_visible', 'position'], 'prod_cat_collection_visibility_idx');
            });
        });
    }

    public function down(): void
    {
        if (DB::table('product_categories')->select('slug')->groupBy('slug')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Collection-scoped Category slugs overlap; resolve before restoring global uniqueness.');
        }
        $this->changeSchema(function (): void {
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->unique('slug');
                $table->dropUnique('prod_cat_collection_slug_unique');
                $table->dropIndex('prod_cat_collection_visibility_idx');
                $table->dropConstrainedForeignId('collection_id');
            });
        });
    }

    private function changeSchema(Closure $change): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $change();

            return;
        }
        // SQLite rebuilds the referenced table. Disable FK enforcement outside the
        // transaction, then check the rebuilt references before committing.
        Schema::withoutForeignKeyConstraints(fn () => DB::transaction(function () use ($change): void {
            $change();
            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('Category ownership migration violated a foreign key.');
            }
        }));
    }
};
