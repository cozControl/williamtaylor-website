<?php

namespace Tests\Unit\Catalogue;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\ProductTypeRegistry;
use App\Domain\Catalogue\Support\VariantStateFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CatalogueSupportTest extends TestCase
{
    public function test_product_type_registry_is_fail_closed_and_defines_two_supported_options(): void
    {
        $definition = (new ProductTypeRegistry)->get('apparel');
        $this->assertSame(['colour', 'size'], $definition['option_keys']);
        $this->assertSame(2, $definition['maximum_options']);

        $this->expectException(InvalidArgumentException::class);
        (new ProductTypeRegistry)->get('unknown');
    }

    public function test_archive_reason_is_trimmed_bounded_and_required(): void
    {
        $this->assertSame('Season ended', ArchiveReason::normalize(' Season ended '));

        try {
            ArchiveReason::normalize('');
            $this->fail('Empty archive reason unexpectedly accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        ArchiveReason::normalize(str_repeat('x', 1001));
    }

    public function test_product_state_fingerprint_is_deterministic_and_state_sensitive(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => '01J00000000000000000000000',
            'lock_version' => 1,
            'current_draft_revision_id' => null,
            'default_variant_id' => null,
            'archived_at' => null,
        ]);
        $states = new ProductStateFingerprint;
        $first = $states->for($product);
        $this->assertSame($first, $states->for($product));
        $product->lock_version = 2;
        $this->assertNotSame($first, $states->for($product));
    }

    public function test_variant_state_fingerprint_includes_product_and_variant_state(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => '01J00000000000000000000000',
            'lock_version' => 1,
            'default_variant_id' => null,
        ]);
        $variant = new ProductVariant;
        $variant->forceFill([
            'id' => '01J00000000000000000000001',
            'product_id' => $product->id,
            'lock_version' => 0,
            'archived_at' => null,
            'combination_fingerprint' => str_repeat('a', 64),
        ]);
        $states = new VariantStateFingerprint;
        $first = $states->for($product, $variant);
        $variant->lock_version = 1;
        $this->assertNotSame($first, $states->for($product, $variant));
    }
}
