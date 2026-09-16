<?php

namespace Tests\Feature\Catalogue;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class CollectionCategoryOwnershipMigrationTest extends TestCase
{
    private const CATEGORY_ONE = '01m1r5txaw7hm5h8wp83kfkn8b';

    private const NEW_ARRIVALS = '01m1v480fhmfn6beca07smkh0n';

    private const CATEGORY_ONE_PRODUCTS = ['01m1r803qg9ffj90ka7tc13jcd', '01m1ras8bewrcyt3c19vm3cf5h', '01m1v2fvqe716mg6qmby7pwrvp', '01m1vppcdz07ksrbgews3tey9k'];

    private const BAGS = '01m1ygv5n6mx3h6ch7768e2n4v';

    private const BAGS_COLLECTION = '01m1yhwdsmh6pxy8172gc19b2z';

    private const PURSE = '01m1yh6hsjs8rw20h33b0gf1n6';

    private const ALL_PRODUCTS = '01m2b0gxbr8h7qabca74g85xbj';

    private const ALL_COLLECTION = '01m20v3yyzyz14r32jdx1k5gyb';

    private const SHOES = '01m1yv4pmqssmdqexfsb459xrx';

    private const SHOES_COLLECTION = '01m1yv0sth3kjgq25fsmeraz6b';

    private const SHOES_PRODUCT = '01m20t5psc584gq63jxb9tm33a';

    public function test_shoes_and_existing_approvals_preserve_all_relationship_rows(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $this->seedShoesApproval();
            $before = $this->snapshot();
            $this->migration()->up();
            $this->assertSame(self::SHOES_COLLECTION, DB::table('product_categories')->where('id', self::SHOES)->value('collection_id'));
            $this->assertSame(self::NEW_ARRIVALS, DB::table('product_categories')->where('id', self::CATEGORY_ONE)->value('collection_id'));
            $this->assertSame(self::BAGS_COLLECTION, DB::table('product_categories')->where('id', self::BAGS)->value('collection_id'));
            $after = $this->snapshot();
            foreach (['products', 'collections', 'product_category_assignments', 'collection_products'] as $table) {
                $this->assertSame($before[$table], $after[$table], $table.' must remain unchanged');
            }
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        });
    }

    #[DataProvider('shoesDriftCases')]
    public function test_shoes_evidence_drift_fails_before_schema_or_relationship_changes(string $drift): void
    {
        $this->withLegacyDatabase(function () use ($drift): void {
            $this->seedApprovals();
            $this->seedShoesApproval();
            $assignment = DB::table('product_category_assignments')->where('product_category_id', self::SHOES);
            $memberships = DB::table('collection_products')->where('product_id', self::SHOES_PRODUCT);
            $approvedMembership = (clone $memberships)->where('collection_id', self::SHOES_COLLECTION);
            $allProducts = (clone $memberships)->where('collection_id', self::ALL_PRODUCTS);
            $history = (clone $memberships)->where('collection_id', self::ALL_COLLECTION);
            match ($drift) {
                'no assigned product' => $assignment->delete(),
                'additional assigned product' => DB::table('product_category_assignments')->insert(['product_category_id' => self::SHOES, 'product_id' => 'other-product']),
                'replacement product' => $assignment->update(['product_id' => 'other-product']),
                'same slug different product id' => DB::table('products')->where('id', self::SHOES_PRODUCT)->update(['id' => 'replacement-shoes-product']),
                'renamed product' => DB::table('products')->where('id', self::SHOES_PRODUCT)->update(['slug' => 'different-shoes']),
                'renamed category' => DB::table('product_categories')->where('id', self::SHOES)->update(['slug' => 'different-shoes']),
                'missing approved collection' => DB::table('collections')->where('id', self::SHOES_COLLECTION)->delete(),
                'corrected collection slug' => DB::table('collections')->where('id', self::SHOES_COLLECTION)->update(['slug' => 'shoe-collection']),
                'replacement collection id' => DB::table('collections')->where('id', self::SHOES_COLLECTION)->update(['id' => 'replacement-shoes-collection']),
                'missing approved membership' => $approvedMembership->delete(),
                'archived approved membership' => $approvedMembership->update(['archived_at' => '2026-09-16 00:00:00']),
                'missing all-products membership' => $allProducts->delete(),
                'archived all-products membership' => $allProducts->update(['archived_at' => '2026-09-16 00:00:00']),
                'missing all-collection history' => $history->delete(),
                'active all-collection history' => $history->update(['archived_at' => null]),
                'unexpected membership' => DB::table('collection_products')->insert(['product_id' => self::SHOES_PRODUCT, 'collection_id' => 'extra-collection']),
                'unexpected archived membership' => DB::table('collection_products')->insert(['product_id' => self::SHOES_PRODUCT, 'collection_id' => 'extra-collection', 'archived_at' => '2026-09-16 00:00:00']),
                'duplicate historical membership' => DB::table('collection_products')->insert(['product_id' => self::SHOES_PRODUCT, 'collection_id' => self::ALL_COLLECTION, 'archived_at' => '2026-09-16 00:00:00']),
                'different historical collection id' => $history->update(['collection_id' => 'extra-collection']),
            };
            if ($drift === 'same slug different product id') {
                $assignment->update(['product_id' => 'replacement-shoes-product']);
                $memberships->update(['product_id' => 'replacement-shoes-product']);
            }
            $this->assertPreflightFailure('Shoes changed since its ownership approval');
        });
    }

    public static function shoesDriftCases(): iterable
    {
        foreach (['no assigned product', 'additional assigned product', 'replacement product', 'same slug different product id', 'renamed product', 'renamed category', 'missing approved collection', 'corrected collection slug', 'replacement collection id', 'missing approved membership', 'archived approved membership', 'missing all-products membership', 'archived all-products membership', 'missing all-collection history', 'active all-collection history', 'unexpected membership', 'unexpected archived membership', 'duplicate historical membership', 'different historical collection id'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_shoes_accepts_and_preserves_a_different_non_null_archive_timestamp(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $this->seedShoesApproval();
            DB::table('collection_products')->where('product_id', self::SHOES_PRODUCT)->where('collection_id', self::ALL_COLLECTION)
                ->update(['archived_at' => '2024-01-02 03:04:05']);
            $before = $this->snapshot();
            $this->migration()->up();
            $this->assertSame(self::SHOES_COLLECTION, DB::table('product_categories')->where('id', self::SHOES)->value('collection_id'));
            $this->assertSame($before['collection_products'], $this->snapshot()['collection_products']);
        });
    }

    public function test_shoes_slug_does_not_approve_an_unrelated_category_id(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $this->seedShoesApproval();
            DB::table('product_category_assignments')->where('product_category_id', self::SHOES)->delete();
            DB::table('product_categories')->where('id', self::SHOES)->update(['id' => 'unapproved-shoes']);
            DB::table('product_category_assignments')->insert(['product_category_id' => 'unapproved-shoes', 'product_id' => self::SHOES_PRODUCT]);
            $this->assertPreflightFailure('Category ownership requires an explicit decision: unapproved-shoes');
        });
    }

    public function test_unrelated_ambiguity_blocks_all_three_approved_backfills_before_ddl(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $this->seedShoesApproval();
            DB::table('product_categories')->insert(['id' => 'unapproved', 'slug' => 'unapproved']);
            DB::table('product_category_assignments')->insert(['product_category_id' => 'unapproved', 'product_id' => self::SHOES_PRODUCT]);
            $this->assertPreflightFailure('Category ownership requires an explicit decision: unapproved');
        });
    }

    public function test_both_explicit_approvals_preserve_all_relationship_rows(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $before = $this->snapshot();
            $this->assertSame(2, DB::table('collection_products')->where('product_id', self::PURSE)->whereNull('archived_at')->count());
            $this->assertSame(1, DB::table('collection_products')->where('product_id', self::PURSE)->whereNotNull('archived_at')->count());
            $this->migration()->up();
            $this->assertSame(self::NEW_ARRIVALS, DB::table('product_categories')->where('id', self::CATEGORY_ONE)->value('collection_id'));
            $this->assertSame(self::BAGS_COLLECTION, DB::table('product_categories')->where('id', self::BAGS)->value('collection_id'));
            $after = $this->snapshot();
            foreach (['products', 'collections', 'product_category_assignments', 'collection_products'] as $table) {
                $this->assertSame($before[$table], $after[$table], $table.' must remain unchanged');
            }
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        });
    }

    #[DataProvider('bagsDriftCases')]
    public function test_bags_evidence_drift_fails_before_any_schema_or_relationship_change(string $drift): void
    {
        $this->withLegacyDatabase(function () use ($drift): void {
            $this->seedApprovals();
            $assignment = DB::table('product_category_assignments')->where('product_category_id', self::BAGS);
            $membership = DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::BAGS_COLLECTION);
            match ($drift) {
                'no assigned product' => $assignment->delete(),
                'additional assigned product' => DB::table('product_category_assignments')->insert(['product_category_id' => self::BAGS, 'product_id' => 'other-product']),
                'replacement product' => $assignment->update(['product_id' => 'other-product']),
                'same slug different product id' => DB::table('products')->where('id', self::PURSE)->update(['id' => 'replacement-purse']),
                'renamed product' => DB::table('products')->where('id', self::PURSE)->update(['slug' => 'another-purse']),
                'renamed category' => DB::table('product_categories')->where('id', self::BAGS)->update(['slug' => 'another-bags']),
                'missing approved collection' => DB::table('collections')->where('id', self::BAGS_COLLECTION)->delete(),
                'renamed approved collection' => DB::table('collections')->where('id', self::BAGS_COLLECTION)->update(['slug' => 'another-bags-collection']),
                'replacement collection id' => DB::table('collections')->where('id', self::BAGS_COLLECTION)->update(['id' => 'replacement-collection']),
                'missing approved membership' => $membership->delete(),
                'archived approved membership' => $membership->update(['archived_at' => '2026-09-16 00:00:00']),
                'missing all-products membership' => DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::ALL_PRODUCTS)->delete(),
                'archived all-products membership' => DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::ALL_PRODUCTS)->update(['archived_at' => '2026-09-16 00:00:00']),
                'active all-collection history' => DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::ALL_COLLECTION)->update(['archived_at' => null]),
                'missing all-collection history' => DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::ALL_COLLECTION)->delete(),
                'missing historical collection' => DB::table('collections')->where('id', self::ALL_COLLECTION)->delete(),
                'renamed historical collection' => DB::table('collections')->where('id', self::ALL_COLLECTION)->update(['slug' => 'different-history']),
                'unexpected membership' => DB::table('collection_products')->insert(['product_id' => self::PURSE, 'collection_id' => 'extra-collection']),
                'unexpected archived membership' => DB::table('collection_products')->insert(['product_id' => self::PURSE, 'collection_id' => 'extra-collection', 'archived_at' => '2026-09-16 00:00:00']),
                'duplicate historical membership' => DB::table('collection_products')->insert(['product_id' => self::PURSE, 'collection_id' => self::ALL_COLLECTION, 'archived_at' => '2026-09-16 00:00:00']),
            };
            if ($drift === 'same slug different product id') {
                $assignment->update(['product_id' => 'replacement-purse']);
                DB::table('collection_products')->where('product_id', self::PURSE)->update(['product_id' => 'replacement-purse']);
            }
            $this->assertPreflightFailure('Bags changed since its ownership approval');
        });
    }

    public static function bagsDriftCases(): iterable
    {
        foreach (['no assigned product', 'additional assigned product', 'replacement product', 'same slug different product id', 'renamed product', 'renamed category', 'missing approved collection', 'renamed approved collection', 'replacement collection id', 'missing approved membership', 'archived approved membership', 'missing all-products membership', 'archived all-products membership', 'active all-collection history', 'missing all-collection history', 'missing historical collection', 'renamed historical collection', 'unexpected membership', 'unexpected archived membership', 'duplicate historical membership'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_historical_archive_timestamp_is_not_pinned_or_modified(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::ALL_COLLECTION)
                ->update(['archived_at' => '2025-03-10 11:12:13']);
            $before = $this->snapshot();
            $this->migration()->up();
            $this->assertSame(self::BAGS_COLLECTION, DB::table('product_categories')->where('id', self::BAGS)->value('collection_id'));
            $this->assertSame($before['collection_products'], $this->snapshot()['collection_products']);
        });
    }

    #[DataProvider('categoryOneDriftCases')]
    public function test_original_category_one_approval_keeps_its_existing_guards(string $drift): void
    {
        $this->withLegacyDatabase(function () use ($drift): void {
            $this->seedApprovals();
            $assignment = DB::table('product_category_assignments')->where('product_category_id', self::CATEGORY_ONE)->where('product_id', self::CATEGORY_ONE_PRODUCTS[0]);
            $membership = DB::table('collection_products')->where('collection_id', self::NEW_ARRIVALS)->where('product_id', self::CATEGORY_ONE_PRODUCTS[0]);
            match ($drift) {
                'missing assignment' => $assignment->delete(),
                'additional assignment' => DB::table('product_category_assignments')->insert(['product_category_id' => self::CATEGORY_ONE, 'product_id' => 'other-product']),
                'replacement product' => $assignment->update(['product_id' => 'other-product']),
                'missing approved collection' => DB::table('collections')->where('id', self::NEW_ARRIVALS)->delete(),
                'missing membership' => $membership->delete(),
                'archived membership' => $membership->update(['archived_at' => '2026-09-16 00:00:00']),
            };
            $this->assertPreflightFailure($drift === 'missing approved collection' ? self::CATEGORY_ONE : 'Category One changed since its ownership approval');
        });
    }

    public static function categoryOneDriftCases(): iterable
    {
        foreach (['missing assignment', 'additional assignment', 'replacement product', 'missing approved collection', 'missing membership', 'archived membership'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_the_bags_slug_does_not_approve_an_unrelated_category_id(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            DB::table('product_category_assignments')->where('product_category_id', self::BAGS)->delete();
            DB::table('product_categories')->where('id', self::BAGS)->update(['id' => 'unapproved-bags']);
            DB::table('product_category_assignments')->insert(['product_category_id' => 'unapproved-bags', 'product_id' => self::PURSE]);
            $this->assertPreflightFailure('unapproved-bags');
        });
    }

    public function test_an_unrelated_ambiguous_category_still_blocks_both_approved_backfills(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            DB::table('product_categories')->insert(['id' => 'unapproved', 'slug' => 'unapproved']);
            DB::table('product_category_assignments')->insert(['product_category_id' => 'unapproved', 'product_id' => self::PURSE]);
            $this->assertPreflightFailure('unapproved');
        });
    }

    public function test_empty_installations_need_no_approval_records_or_fallback_owner(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->migration()->up();
            $this->assertTrue(Schema::hasColumn('product_categories', 'collection_id'));
            $this->assertSame(0, DB::table('collections')->count());
            $this->assertSame(0, DB::table('product_categories')->count());
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        });
    }

    public function test_the_same_migration_can_run_after_a_pre_schema_failure_is_resolved(): void
    {
        $this->withLegacyDatabase(function (): void {
            $this->seedApprovals();
            $membership = DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::BAGS_COLLECTION)->first();
            DB::table('collection_products')->where('product_id', self::PURSE)->where('collection_id', self::BAGS_COLLECTION)->delete();
            $this->assertPreflightFailure('Bags changed since its ownership approval');
            // Restore this test's deliberately removed fixture; production is never touched.
            DB::table('collection_products')->insert((array) $membership);
            $before = $this->snapshot();
            $this->migration()->up();
            $this->assertSame(self::BAGS_COLLECTION, DB::table('product_categories')->where('id', self::BAGS)->value('collection_id'));
            $after = $this->snapshot();
            $this->assertSame($before['product_category_assignments'], $after['product_category_assignments']);
            $this->assertSame($before['collection_products'], $after['collection_products']);
        });
    }

    private function assertPreflightFailure(string $message): void
    {
        $before = $this->snapshot();
        try {
            $this->migration()->up();
            $this->fail('Changed ownership evidence was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('product_categories', 'collection_id'));
        $this->assertSame($before, $this->snapshot());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_16_180000_scope_product_categories_to_collections.php');
    }

    private function snapshot(): array
    {
        return collect(['products', 'collections', 'product_categories', 'product_category_assignments', 'collection_products'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()])->all();
    }

    private function seedApprovals(): void
    {
        DB::table('collections')->insert([
            ['id' => self::NEW_ARRIVALS, 'slug' => 'new-arrivals'], ['id' => self::BAGS_COLLECTION, 'slug' => 'bags-collection'],
            ['id' => self::ALL_COLLECTION, 'slug' => 'all-collection'], ['id' => self::ALL_PRODUCTS, 'slug' => 'all-products'], ['id' => 'extra-collection', 'slug' => 'extra-collection'],
        ]);
        DB::table('product_categories')->insert([['id' => self::CATEGORY_ONE, 'slug' => 'category-one'], ['id' => self::BAGS, 'slug' => 'bags']]);
        foreach (self::CATEGORY_ONE_PRODUCTS as $index => $product) {
            DB::table('products')->insert(['id' => $product, 'slug' => 'category-one-product-'.$index]);
            DB::table('product_category_assignments')->insert(['product_category_id' => self::CATEGORY_ONE, 'product_id' => $product, 'is_primary' => true, 'position' => $index]);
            DB::table('collection_products')->insert(['collection_id' => self::NEW_ARRIVALS, 'product_id' => $product, 'position' => $index]);
        }
        DB::table('collection_products')->insert(['collection_id' => 'extra-collection', 'product_id' => self::CATEGORY_ONE_PRODUCTS[0]]);
        DB::table('products')->insert([['id' => self::PURSE, 'slug' => 'purse'], ['id' => 'other-product', 'slug' => 'other-product']]);
        DB::table('product_category_assignments')->insert(['product_category_id' => self::BAGS, 'product_id' => self::PURSE, 'is_primary' => true, 'position' => 9]);
        foreach ([self::BAGS_COLLECTION, self::ALL_COLLECTION, self::ALL_PRODUCTS] as $index => $collection) {
            DB::table('collection_products')->insert(['collection_id' => $collection, 'product_id' => self::PURSE, 'position' => $index + 2, 'archived_at' => $collection === self::ALL_COLLECTION ? '2026-09-15 12:00:00' : null]);
        }
    }

    private function withLegacyDatabase(Closure $check): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.explicit_ownership_test' => [...config('database.connections.sqlite'), 'database' => ':memory:']]);
        DB::setDefaultConnection('explicit_ownership_test');
        try {
            foreach (['collections', 'products'] as $table) {
                Schema::create($table, function ($t): void {
                    $t->ulid('id')->primary();
                    $t->string('slug')->unique();
                });
            }
            Schema::create('product_categories', function ($t): void {
                $t->ulid('id')->primary();
                $t->string('slug')->unique('product_categories_slug_unique');
                $t->ulid('parent_id')->nullable();
                $t->timestamp('archived_at')->nullable();
                $t->boolean('is_visible')->default(true);
                $t->integer('position')->default(0);
            });
            Schema::create('product_category_assignments', function ($t): void {
                $t->ulid('product_id');
                $t->foreignUlid('product_category_id')->constrained('product_categories')->restrictOnDelete();
                $t->boolean('is_primary')->default(false);
                $t->integer('position')->default(0);
            });
            Schema::create('collection_products', function ($t): void {
                $t->ulid('collection_id');
                $t->ulid('product_id');
                $t->timestamp('archived_at')->nullable();
                $t->integer('position')->default(0);
            });
            $check();
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('explicit_ownership_test');
        }
    }

    private function seedShoesApproval(): void
    {
        DB::table('collections')->insert(['id' => self::SHOES_COLLECTION, 'slug' => 'shoe-c-ollection']);
        DB::table('product_categories')->insert(['id' => self::SHOES, 'slug' => 'shoes']);
        DB::table('products')->insert(['id' => self::SHOES_PRODUCT, 'slug' => 'men-classic-leather-shoes']);
        DB::table('product_category_assignments')->insert(['product_category_id' => self::SHOES, 'product_id' => self::SHOES_PRODUCT, 'is_primary' => true, 'position' => 8]);
        foreach ([self::SHOES_COLLECTION, self::ALL_COLLECTION, self::ALL_PRODUCTS] as $index => $collection) {
            DB::table('collection_products')->insert(['collection_id' => $collection, 'product_id' => self::SHOES_PRODUCT, 'position' => $index + 5, 'archived_at' => $collection === self::ALL_COLLECTION ? '2026-09-15 11:00:00' : null]);
        }
    }
}
