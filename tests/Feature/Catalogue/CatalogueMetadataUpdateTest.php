<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\ArchiveProduct;
use App\Domain\Catalogue\Actions\ChangeProductSlug;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductOption;
use App\Domain\Catalogue\Actions\CreateProductOptionValue;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Actions\UpdateProductOption;
use App\Domain\Catalogue\Actions\UpdateProductOptionValue;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDOException;
use Tests\TestCase;

final class CatalogueMetadataUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    public function test_slug_change_normalizes_audits_and_preserves_the_aggregate(): void
    {
        [$product, $option, $value] = $this->catalogue();
        $variant = app(CreateProductVariant::class)->handle(
            $this->actor,
            $product,
            $this->state($product),
            [['option_id' => $option->id, 'value_id' => $value->id]],
        );
        $product = $product->fresh();
        app(SetDefaultProductVariant::class)->handle($this->actor, $product, $variant, $this->state($product));
        $revisionCount = $product->revisions()->count();
        $relationshipCount = DB::table('product_variant_values')->count();

        $changed = app(ChangeProductSlug::class)->handle($this->actor, $product->fresh(), $this->state($product), ' New Product Slug ');
        $this->assertSame('new-product-slug', $changed->slug);
        $this->assertSame($revisionCount, $changed->revisions()->count());
        $this->assertSame($variant->id, $changed->default_variant_id);
        $this->assertSame($relationshipCount, DB::table('product_variant_values')->count());
        $audit = DB::table('audit_records')->where('action', 'product.slug.changed')->sole();
        $this->assertSame(['slug' => $product->slug], json_decode($audit->before_summary, true));
        $this->assertSame(['slug' => 'new-product-slug'], json_decode($audit->after_summary, true));
        $this->assertFalse(\Schema::hasTable('redirects'));
    }

    public function test_slug_change_rejects_duplicate_invalid_archived_and_stale_state_without_mutation(): void
    {
        $product = $this->product('first-product');
        $other = $this->product('other-product');

        foreach ([
            fn () => app(ChangeProductSlug::class)->handle($this->actor, $product, $this->state($product), $other->slug),
            fn () => app(ChangeProductSlug::class)->handle($this->actor, $product, $this->state($product), '--'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid slug change unexpectedly succeeded.');
            } catch (InvalidArgumentException) {
                $this->assertSame('first-product', $product->fresh()->slug);
            }
        }

        $expected = $this->state($product);
        $product->increment('lock_version');
        try {
            app(ChangeProductSlug::class)->handle($this->actor, $product, $expected, 'stale-change');
            $this->fail('Stale slug change unexpectedly succeeded.');
        } catch (StaleCatalogueState) {
            $this->assertSame('first-product', $product->fresh()->slug);
        }

        $product = $product->fresh();
        app(ArchiveProduct::class)->handle($this->actor, $product, $this->state($product), 'Archived');
        $this->expectException(InvalidArgumentException::class);
        app(ChangeProductSlug::class)->handle($this->actor, $product->fresh(), $this->state($product), 'archived-change');
    }

    public function test_same_normalized_slug_is_an_explicit_noop(): void
    {
        $product = $this->product('same-slug');
        $beforeLock = $product->lock_version;
        app(ChangeProductSlug::class)->handle($this->actor, $product, $this->state($product), ' Same Slug ');
        $this->assertSame($beforeLock, $product->fresh()->lock_version);
        $this->assertDatabaseMissing('audit_records', ['action' => 'product.slug.changed']);
    }

    public function test_slug_database_race_is_converted_to_a_domain_validation_error(): void
    {
        $product = $this->product('race-source');
        Product::updating(function (Product $updating): void {
            if ($updating->slug === 'race-target') {
                $driver = new PDOException('duplicate', 23000);
                throw new QueryException('sqlite', 'update products', [], $driver);
            }
        });

        try {
            app(ChangeProductSlug::class)->handle($this->actor, $product, $this->state($product), 'race-target');
            $this->fail('Uniqueness race unexpectedly succeeded.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Product slug is already in use.', $exception->getMessage());
            $this->assertSame('race-source', $product->fresh()->slug);
        }
    }

    public function test_option_update_changes_only_display_fields_and_preserves_relationships(): void
    {
        [$product, $option, $value] = $this->catalogue();
        $variant = app(CreateProductVariant::class)->handle($this->actor, $product, $this->state($product), [['option_id' => $option->id, 'value_id' => $value->id]]);
        $fingerprint = $variant->combination_fingerprint;

        $updated = app(UpdateProductOption::class)->handle($this->actor, $product->fresh(), $option, $this->state($product), 'colour', 'Fabric colour', 2);
        $this->assertSame('Fabric colour', $updated->label);
        $this->assertSame(2, $updated->position);
        $this->assertSame($value->id, $updated->values()->sole()->id);
        $this->assertSame($fingerprint, $variant->fresh()->combination_fingerprint);
        $audit = DB::table('audit_records')->where('action', 'product.option.updated')->sole();
        $this->assertSame('Colour', json_decode($audit->before_summary, true)['label']);
        $this->assertSame('Fabric colour', json_decode($audit->after_summary, true)['label']);
    }

    public function test_option_update_rejects_identity_ownership_archive_and_stale_failures(): void
    {
        [$product, $option] = $this->catalogue();
        [$other, $otherOption] = $this->catalogue();
        $attempts = [
            fn () => app(UpdateProductOption::class)->handle($this->actor, $product, $option, $this->state($product), 'material', 'Material', 1),
            fn () => app(UpdateProductOption::class)->handle($this->actor, $product, $otherOption, $this->state($product), 'colour', 'Colour', 1),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid option update unexpectedly succeeded.');
            } catch (InvalidArgumentException) {
                $this->assertSame('Colour', $option->fresh()->label);
            }
        }
        $expected = $this->state($product);
        $product->increment('lock_version');
        $this->expectException(StaleCatalogueState::class);
        app(UpdateProductOption::class)->handle($this->actor, $product, $option, $expected, 'colour', 'Stale', 1);
    }

    public function test_option_value_update_changes_only_display_fields_and_preserves_variant_identity(): void
    {
        [$product, $option, $value] = $this->catalogue();
        $variant = app(CreateProductVariant::class)->handle($this->actor, $product, $this->state($product), [['option_id' => $option->id, 'value_id' => $value->id]]);
        $fingerprint = $variant->combination_fingerprint;

        $updated = app(UpdateProductOptionValue::class)->handle($this->actor, $product->fresh(), $option, $value, $this->state($product), 'red', 'Crimson', 3);
        $this->assertSame('Crimson', $updated->label);
        $this->assertSame(3, $updated->position);
        $this->assertDatabaseHas('product_variant_values', ['variant_id' => $variant->id, 'product_option_value_id' => $value->id]);
        $this->assertSame($fingerprint, $variant->fresh()->combination_fingerprint);
        $this->assertDatabaseHas('audit_records', ['action' => 'product.option-value.updated']);
    }

    public function test_option_value_update_rejects_identity_ownership_archive_and_stale_failures(): void
    {
        [$product, $option, $value] = $this->catalogue();
        [$other, $otherOption, $otherValue] = $this->catalogue();
        foreach ([
            fn () => app(UpdateProductOptionValue::class)->handle($this->actor, $product, $option, $value, $this->state($product), 'blue', 'Blue', 2),
            fn () => app(UpdateProductOptionValue::class)->handle($this->actor, $product, $otherOption, $otherValue, $this->state($product), 'red', 'Red', 2),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid value update unexpectedly succeeded.');
            } catch (InvalidArgumentException) {
                $this->assertSame('Red', $value->fresh()->label);
            }
        }
        $value->forceFill(['archived_at' => now()])->save();
        $this->expectException(InvalidArgumentException::class);
        app(UpdateProductOptionValue::class)->handle($this->actor, $product, $option, $value, $this->state($product), 'red', 'Archived', 2);
    }

    private function catalogue(): array
    {
        $product = $this->product('product-'.Str::lower(Str::random(8)));
        $option = app(CreateProductOption::class)->handle($this->actor, $product, 0, 'colour', 'Colour', 0);
        $value = app(CreateProductOptionValue::class)->handle($this->actor, $option, 'red', 'Red', 0);

        return [$product->fresh(), $option, $value];
    }

    private function product(string $slug): Product
    {
        return app(CreateProduct::class)->handle($this->actor, $slug, $slug);
    }

    private function state(Product $product): string
    {
        return app(ProductStateFingerprint::class)->for($product->fresh());
    }
}
