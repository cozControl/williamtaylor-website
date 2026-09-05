<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OxfordProductVerticalSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_initializer_is_explicit_complete_and_idempotent(): void
    {
        $actor = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $actor->email])->assertSuccessful();
        $product = Product::query()->where('slug', 'the-taylor-oxford-shirt')->with(['options.values', 'variants.values'])->sole();

        $this->assertSame(2, $product->options->count());
        $this->assertSame(14, $product->variants->count());
        $this->assertNotNull($product->default_variant_id);
        $this->assertSame('WT-SH-001', $product->defaultVariant->sku);

        $lock = $product->lock_version;
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $actor->email])->assertSuccessful();
        $this->assertSame(1, Product::query()->where('slug', 'the-taylor-oxford-shirt')->count());
        $this->assertSame($lock, $product->fresh()->lock_version);
    }

    public function test_public_route_falls_back_until_active_and_uses_canonical_content_when_active(): void
    {
        $actor = User::factory()->create(['email' => 'owner@example.com']);
        $this->get('/products/the-taylor-oxford-shirt')->assertOk()->assertSeeText('The Taylor Oxford Shirt');
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $actor->email])->assertSuccessful();
        $product = Product::query()->where('slug', 'the-taylor-oxford-shirt')->firstOrFail();
        $product->forceFill(['catalogue_status' => 'ready'])->save();

        $this->get('/products/the-taylor-oxford-shirt')->assertOk()->assertSeeText('Hand-finished camp collar shirt')->assertSee('product-bootstrap-data', false);
    }

    public function test_admin_products_require_permissions_and_view_only_cannot_save(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $manager->assignRole(RoleRegistry::CMS_MANAGER));

        $this->get('/admin/products')->assertRedirect('/login');
        $this->actingAs($ordinary)->get('/admin/products')->assertForbidden();
        $this->actingAs($manager)->get('/admin/products')->assertOk()->assertSeeText('New Product');

        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->givePermissionTo(['admin.access', 'products.view']);
        $this->actingAs($viewer)->post('/admin/products', ['title' => 'Forged', 'slug' => 'forged'])->assertForbidden();
    }

    public function test_manager_can_save_content_directly_and_forged_variant_is_rejected(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
        $manager = User::factory()->create(['email' => 'manager@example.com', 'email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $manager->assignRole(RoleRegistry::CMS_MANAGER));
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $manager->email])->assertSuccessful();
        $product = Product::query()->where('slug', 'the-taylor-oxford-shirt')->firstOrFail();

        $payload = [
            'lock_version' => $product->lock_version, 'title' => 'Taylor Oxford Updated', 'slug' => $product->slug,
            'short_description' => 'Updated storefront copy.', 'description' => 'Updated main description.',
            'materials' => 'Italian cotton.', 'fit' => 'Relaxed.', 'care' => '', 'features' => 'Crossover front panel.',
            'status' => 'hidden', 'gallery_media_ids' => [], 'option_labels' => [], 'variant_skus' => [],
        ];
        $this->actingAs($manager)->put(route('admin.products.update', $product), $payload)->assertRedirect(route('admin.products.edit', $product));
        $this->assertSame('Taylor Oxford Updated', $product->fresh()->currentDraftRevision->title);

        $product->refresh();
        $payload['lock_version'] = $product->lock_version;
        $payload['variant_skus'] = ['01INVALIDVARIANTID00000000' => 'FORGED'];
        $this->actingAs($manager)->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('product');
    }
}
