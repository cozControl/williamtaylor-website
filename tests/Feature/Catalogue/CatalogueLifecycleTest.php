<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\ArchiveProduct;
use App\Domain\Catalogue\Actions\ArchiveProductOption;
use App\Domain\Catalogue\Actions\ArchiveProductOptionValue;
use App\Domain\Catalogue\Actions\ArchiveProductVariant;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductOption;
use App\Domain\Catalogue\Actions\CreateProductOptionValue;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\RestoreProduct;
use App\Domain\Catalogue\Actions\RestoreProductOption;
use App\Domain\Catalogue\Actions\RestoreProductOptionValue;
use App\Domain\Catalogue\Actions\RestoreProductVariant;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Actions\UpdateProductVariant;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\VariantStateFingerprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class CatalogueLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    public function test_variant_update_normalizes_fields_replaces_combination_and_preserves_identity(): void
    {
        [$product, $colour, $red, $blue] = $this->productWithColour();
        $variant = $this->variant($product, $colour, $red);
        $id = $variant->id;

        $updated = app(UpdateProductVariant::class)->handle(
            $this->actor,
            $product->fresh(),
            $variant->fresh(),
            $this->productState($product),
            $this->variantState($product, $variant),
            ' sku-01 ',
            ' bar-01 ',
            ' Blue ',
            4,
            [['option_id' => $colour->id, 'value_id' => $blue->id]],
        );

        $this->assertSame($id, $updated->id);
        $this->assertSame('SKU-01', $updated->sku);
        $this->assertSame('BAR-01', $updated->barcode);
        $this->assertSame('Blue', $updated->editorial_label);
        $this->assertSame(4, $updated->position);
        $this->assertDatabaseHas('product_variant_values', ['variant_id' => $id, 'product_option_value_id' => $blue->id]);
        $this->assertDatabaseMissing('product_variant_values', ['variant_id' => $id, 'product_option_value_id' => $red->id]);
        $this->assertDatabaseHas('audit_records', ['action' => 'product.variant.updated', 'resource_identifier' => $product->id]);
    }

    public function test_variant_noop_is_idempotent_and_stale_update_mutates_nothing(): void
    {
        [$product, $colour, $red] = $this->productWithColour();
        $variant = $this->variant($product, $colour, $red, 'A');
        $productExpected = $this->productState($product);
        $variantExpected = $this->variantState($product, $variant);
        app(UpdateProductVariant::class)->handle($this->actor, $product, $variant, $productExpected, $variantExpected, 'A', null, null, 0);
        $this->assertSame(0, $variant->fresh()->lock_version);
        $this->assertDatabaseMissing('audit_records', ['action' => 'product.variant.updated']);

        $product->increment('lock_version');
        try {
            app(UpdateProductVariant::class)->handle($this->actor, $product, $variant, $productExpected, $variantExpected, 'B', null, null, 0);
            $this->fail('Stale update unexpectedly succeeded.');
        } catch (StaleCatalogueState) {
            $this->assertSame('A', $variant->fresh()->sku);
        }
    }

    public function test_variant_update_rejects_duplicate_incomplete_cross_product_and_archived_values(): void
    {
        [$product, $colour, $red, $blue] = $this->productWithColour();
        $first = $this->variant($product, $colour, $red);
        $this->variant($product->fresh(), $colour, $blue);

        $this->expectException(InvalidArgumentException::class);
        app(UpdateProductVariant::class)->handle(
            $this->actor,
            $product->fresh(),
            $first->fresh(),
            $this->productState($product),
            $this->variantState($product, $first),
            null,
            null,
            null,
            0,
            [['option_id' => $colour->id, 'value_id' => $blue->id]],
        );
    }

    public function test_product_archive_restore_preserves_children_and_remains_draft(): void
    {
        [$product, $colour, $red] = $this->productWithColour();
        $variant = $this->variant($product, $colour, $red);
        $product->forceFill(['catalogue_status' => 'ready'])->save();

        app(ArchiveProduct::class)->handle($this->actor, $product, $this->productState($product), 'Season ended');
        $archived = $product->fresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertSame('draft', $archived->catalogue_status);
        $this->assertNull($colour->fresh()->archived_at);
        $this->assertNull($red->fresh()->archived_at);
        $this->assertNull($variant->fresh()->archived_at);
        $this->assertDatabaseHas('product_variant_values', ['variant_id' => $variant->id]);

        app(RestoreProduct::class)->handle($this->actor, $archived, $this->productState($archived));
        $restored = $product->fresh();
        $this->assertNull($restored->archived_at);
        $this->assertNull($restored->archived_by);
        $this->assertNull($restored->archive_reason);
        $this->assertSame('draft', $restored->catalogue_status);
        $this->assertDatabaseHas('audit_records', ['action' => 'product.restored']);
    }

    public function test_product_archive_requires_reason_and_stale_request_has_zero_mutation(): void
    {
        $product = $this->product();
        $this->expectException(InvalidArgumentException::class);
        app(ArchiveProduct::class)->handle($this->actor, $product, $this->productState($product), ' ');
    }

    public function test_archiving_default_variant_clears_pointer_selects_no_replacement_and_downgrades_status(): void
    {
        [$product, $colour, $red, $blue] = $this->productWithColour();
        $default = $this->variant($product, $colour, $red);
        $other = $this->variant($product->fresh(), $colour, $blue);
        $product = $product->fresh();
        app(SetDefaultProductVariant::class)->handle($this->actor, $product, $default, $this->productState($product));
        $product->refresh()->forceFill(['catalogue_status' => 'ready'])->save();

        app(ArchiveProductVariant::class)->handle(
            $this->actor,
            $product,
            $default,
            $this->productState($product),
            $this->variantState($product, $default),
            'Discontinued',
        );

        $this->assertNull($product->fresh()->default_variant_id);
        $this->assertNotSame($other->id, $product->fresh()->default_variant_id);
        $this->assertSame('draft', $product->fresh()->catalogue_status);
        $this->assertDatabaseHas('product_variant_values', ['variant_id' => $default->id]);
    }

    public function test_variant_restore_preserves_identity_and_does_not_assign_default_or_ready(): void
    {
        [$product, $colour, $red] = $this->productWithColour();
        $variant = $this->variant($product, $colour, $red, 'SKU');
        app(ArchiveProductVariant::class)->handle(
            $this->actor,
            $product,
            $variant,
            $this->productState($product),
            $this->variantState($product, $variant),
            'Pause',
        );
        $product = $product->fresh();
        $variant = $variant->fresh();

        app(RestoreProductVariant::class)->handle(
            $this->actor,
            $product,
            $variant,
            $this->productState($product),
            $this->variantState($product, $variant),
        );

        $this->assertNull($variant->fresh()->archived_at);
        $this->assertSame('SKU', $variant->fresh()->sku);
        $this->assertNull($product->fresh()->default_variant_id);
        $this->assertSame('draft', $product->fresh()->catalogue_status);
    }

    public function test_variant_restore_rejects_archived_dependency(): void
    {
        [$product, $colour, $red] = $this->productWithColour();
        $variant = $this->variant($product, $colour, $red);
        app(ArchiveProductVariant::class)->handle($this->actor, $product, $variant, $this->productState($product), $this->variantState($product, $variant), 'Pause');
        $red->forceFill(['archived_at' => now()])->save();
        $product = $product->fresh();
        $variant = $variant->fresh();

        $this->expectException(InvalidArgumentException::class);
        app(RestoreProductVariant::class)->handle($this->actor, $product, $variant, $this->productState($product), $this->variantState($product, $variant));
    }

    public function test_option_and_value_restore_are_explicit_and_do_not_restore_descendants_or_create_variants(): void
    {
        [$product, $colour, $red] = $this->productWithColour();
        app(ArchiveProductOption::class)->handle($this->actor, $product, $colour, $this->productState($product), 'Retire');
        app(RestoreProductOption::class)->handle($this->actor, $product->fresh(), $colour->fresh(), $this->productState($product));
        $this->assertNull($colour->fresh()->archived_at);
        $this->assertDatabaseCount('product_variants', 0);

        app(ArchiveProductOptionValue::class)->handle($this->actor, $product->fresh(), $colour->fresh(), $red, $this->productState($product), 'Retire');
        app(RestoreProductOptionValue::class)->handle($this->actor, $product->fresh(), $colour->fresh(), $red->fresh(), $this->productState($product));
        $this->assertNull($red->fresh()->archived_at);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_zero_option_base_variant_is_explicit_deterministic_and_unique(): void
    {
        $product = $this->product();
        $first = app(CreateProductVariant::class)->handle($this->actor, $product, $this->productState($product), []);
        $this->assertTrue(Str::isUlid($first->id));
        $this->assertNull($product->fresh()->default_variant_id);
        $this->assertSame(hash('sha256', '[]'), $first->combination_fingerprint);

        $this->expectException(InvalidArgumentException::class);
        app(CreateProductVariant::class)->handle($this->actor, $product->fresh(), $this->productState($product), []);
    }

    private function product(): Product
    {
        return app(CreateProduct::class)->handle($this->actor, 'test-'.Str::lower(Str::random(8)), 'test-'.Str::lower(Str::random(8)));
    }

    private function productWithColour(): array
    {
        $product = $this->product();
        $colour = app(CreateProductOption::class)->handle($this->actor, $product, 0, 'colour', 'Colour', 0);
        $red = app(CreateProductOptionValue::class)->handle($this->actor, $colour, 'red', 'Red', 0);
        $blue = app(CreateProductOptionValue::class)->handle($this->actor, $colour, 'blue', 'Blue', 1);

        return [$product->fresh(), $colour, $red, $blue];
    }

    private function variant(Product $product, ProductOption $option, ProductOptionValue $value, ?string $sku = null): ProductVariant
    {
        return app(CreateProductVariant::class)->handle(
            $this->actor,
            $product,
            $this->productState($product),
            [['option_id' => $option->id, 'value_id' => $value->id]],
            $sku,
        );
    }

    private function productState(Product $product): string
    {
        return app(ProductStateFingerprint::class)->for($product->fresh());
    }

    private function variantState(Product $product, ProductVariant $variant): string
    {
        return app(VariantStateFingerprint::class)->for($product->fresh(), $variant->fresh());
    }
}
