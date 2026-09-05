<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CollectionAdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email' => 'collections@example.com', 'email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_collection_routes_are_permission_aware_and_empty_state_is_actionable(): void
    {
        $this->get(route('admin.collections.index'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.collections.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.collections.index'))->assertOk()->assertSeeText('No Collections yet')->assertSeeText('New Collection');
        $this->actingAs($this->manager)->get(route('admin.collections.create'))->assertOk()
            ->assertSeeText('Save Collection')->assertSeeText('Choose media')
            ->assertSeeText('Back to Collections')
            ->assertSee('data-admin-form-actions', false)
            ->assertSee('data-collection-section="details"', false)
            ->assertSee('data-collection-section="media"', false)
            ->assertSee('data-collection-section="products"', false)
            ->assertSee('for="collection-name"', false)->assertSee('id="collection-description"', false)
            ->assertSee('data-collection-product-search', false)->assertSeeText('No image selected.');
    }

    public function test_manager_creates_updates_and_publishes_ordered_collection_with_ready_media(): void
    {
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $this->manager->email])->assertSuccessful();
        $oxford = Product::query()->where('slug', 'the-taylor-oxford-shirt')->sole();
        $oxford->forceFill(['catalogue_status' => 'ready'])->save();
        $second = Product::query()->create(['stable_key' => 'second-product', 'slug' => 'second-product', 'product_type' => 'apparel', 'catalogue_status' => 'draft', 'currency' => 'TZS', 'created_by' => $this->manager->id]);
        $image = $this->image(MediaAssetState::Ready);

        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'New Arrivals Test', 'slug' => 'new-arrivals-test', 'description' => 'The newest William Taylor pieces.',
            'media_asset_id' => $image->id, 'product_ids' => [$second->id, $oxford->id],
            'product_order' => [$second->id => 1, $oxford->id => 0], 'visibility' => 'visible',
        ])->assertRedirect(route('admin.collections.index'))
            ->assertSessionHas('status', 'Collection created successfully.');

        $collection = Collection::query()->where('slug', 'new-arrivals-test')->sole();
        $this->assertSame([$oxford->id, $second->id], CollectionProduct::query()->active()->where('collection_id', $collection->id)->orderBy('position')->pluck('product_id')->all());
        $this->assertDatabaseHas('media_usages', ['owner_type' => Collection::class, 'owner_identifier' => $collection->id, 'media_asset_id' => $image->id, 'field_role' => 'card']);
        $this->get(route('collections.show', $collection))->assertOk()->assertSeeText('New Arrivals Test')->assertSeeText('The Taylor Oxford Shirt');

        $this->actingAs($this->manager)->put(route('admin.collections.update', $collection), [
            'name' => 'New Arrivals Updated', 'slug' => 'new-arrivals-updated', 'description' => 'Updated storefront collection description.',
            'media_asset_id' => '', 'product_ids' => [$oxford->id], 'product_order' => [$oxford->id => 0], 'visibility' => 'hidden',
        ])->assertRedirect(route('admin.collections.edit', $collection))
            ->assertSessionHas('status', 'Collection updated successfully.');
        $this->assertSame(1, Collection::query()->count());
        $this->assertDatabaseHas('collection_revisions', ['collection_id' => $collection->id, 'title' => 'New Arrivals Updated', 'short_description' => 'Updated storefront collection description.']);
        $this->assertDatabaseHas('audit_records', ['resource_identifier' => $collection->id, 'action' => 'collection.visibility.changed']);
        $this->assertTrue(MediaAsset::query()->whereKey($image->id)->exists());
        $this->assertFalse(MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->exists());
        $this->get('/collections/new-arrivals-updated')->assertNotFound();
    }

    public function test_non_ready_media_is_rejected(): void
    {
        $image = $this->image(MediaAssetState::PendingUpload);
        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'Unsafe Media', 'slug' => 'unsafe-media', 'description' => 'Must not save pending media.',
            'media_asset_id' => $image->id, 'product_ids' => [], 'visibility' => 'hidden',
        ])->assertSessionHasErrors('media_asset_id');
    }

    public function test_collection_media_requires_effective_alt_without_returning_500_and_preserves_input(): void
    {
        $image = $this->image(MediaAssetState::Ready, '');

        $this->actingAs($this->manager)->from(route('admin.collections.create'))->post(route('admin.collections.store'), [
            'name' => 'Alt Validation', 'slug' => 'alt-validation', 'description' => 'Values must survive validation.',
            'media_asset_id' => $image->id, 'media_alt' => '', 'product_ids' => [], 'visibility' => 'hidden',
        ])->assertRedirect(route('admin.collections.create'))
            ->assertSessionHasErrors(['media_alt' => 'Alt text is required for this Collection image.'])
            ->assertSessionHasInput('name', 'Alt Validation')
            ->assertSessionHasInput('media_asset_id', $image->id);

        $this->assertDatabaseMissing('collections', ['slug' => 'alt-validation']);
    }

    public function test_collection_usage_alt_override_is_saved_and_restored(): void
    {
        $image = $this->image(MediaAssetState::Ready, '');

        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'Contextual Image', 'slug' => 'contextual-image', 'description' => 'A contextual Collection image.',
            'media_asset_id' => $image->id, 'media_alt' => 'Model wearing an ivory linen suit', 'product_ids' => [], 'visibility' => 'hidden',
        ])->assertRedirect();

        $collection = Collection::query()->where('slug', 'contextual-image')->sole();
        $this->assertDatabaseHas('media_usages', [
            'owner_type' => Collection::class, 'owner_identifier' => $collection->id,
            'media_asset_id' => $image->id, 'alt_text_override' => 'Model wearing an ivory linen suit',
        ]);
        $this->actingAs($this->manager)->get(route('admin.collections.edit', $collection))
            ->assertOk()->assertSee('value="Model wearing an ivory linen suit"', false)
            ->assertSee('Change media', false);
    }

    public function test_index_exposes_edit_and_edit_prefills_saved_collection_state(): void
    {
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $this->manager->email])->assertSuccessful();
        $product = Product::query()->where('slug', 'the-taylor-oxford-shirt')->sole();
        $image = $this->image(MediaAssetState::Ready, 'Default Collection image');

        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'Collection Edit Test', 'slug' => 'collection-edit-test', 'description' => 'Saved Collection description.',
            'media_asset_id' => $image->id, 'media_alt' => 'Collection-specific image description',
            'product_ids' => [$product->id], 'product_order' => [$product->id => 27], 'visibility' => 'visible',
        ])->assertRedirect(route('admin.collections.index'));

        $collection = Collection::query()->where('slug', 'collection-edit-test')->sole();
        $this->assertDatabaseHas('collection_products', ['collection_id' => $collection->id, 'product_id' => $product->id, 'position' => 27]);
        $this->actingAs($this->manager)->get(route('admin.collections.index'))
            ->assertOk()->assertSeeText('Collection Edit Test')->assertSeeText('1 Product')
            ->assertSeeText('Visible')->assertSeeText('Edit')
            ->assertSee(route('admin.collections.edit', $collection), false);

        $this->actingAs($this->manager)->get(route('admin.collections.edit', $collection))
            ->assertOk()->assertSee('value="Collection Edit Test"', false)
            ->assertSee('value="collection-edit-test"', false)->assertSeeText('Saved Collection description.')
            ->assertSee((string) $image->id, false)->assertSeeText('The Taylor Oxford Shirt')
            ->assertSee('value="Collection-specific image description"', false)
            ->assertSee('collection-product-'.$product->id, false)->assertSee('value="27"', false)
            ->assertSee('value="visible"', false)
            ->assertSeeText('Save changes')->assertSeeText('Back to Collections')->assertSeeText('View storefront')
            ->assertSee('data-collection-editor-summary', false)
            ->assertSeeText('Lower numbers appear first.');
    }

    public function test_edit_requires_manage_permission_and_archived_collections_are_read_only(): void
    {
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $viewer->givePermissionTo('products.view'));

        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'Archived Edit Test', 'slug' => 'archived-edit-test', 'description' => 'Archived collection.',
            'product_ids' => [], 'visibility' => 'hidden',
        ]);
        $collection = Collection::query()->where('slug', 'archived-edit-test')->sole();

        $this->actingAs($viewer)->get(route('admin.collections.edit', $collection))->assertForbidden();
        $collection->forceFill(['archived_at' => now()])->save();
        $this->actingAs($this->manager)->get(route('admin.collections.edit', $collection))->assertNotFound();
        $this->actingAs($this->manager)->put(route('admin.collections.update', $collection), [])->assertNotFound();
    }

    public function test_validation_feedback_is_concise_field_specific_and_preserves_edit_input(): void
    {
        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => 'Validation Edit Test', 'slug' => 'validation-edit-test', 'description' => 'Original description.',
            'product_ids' => [], 'visibility' => 'hidden',
        ]);
        $collection = Collection::query()->where('slug', 'validation-edit-test')->sole();

        $this->actingAs($this->manager)->followingRedirects()->from(route('admin.collections.edit', $collection))->put(route('admin.collections.update', $collection), [
            'name' => '', 'slug' => 'validation-edit-test', 'description' => 'Preserved edit description.',
            'product_ids' => [], 'visibility' => 'hidden',
        ])->assertOk()->assertSeeText('Please correct the highlighted fields.')
            ->assertSeeText('The name field is required.')->assertSeeText('Preserved edit description.');
    }

    public function test_media_picker_is_permissioned_eligible_searchable_and_bounded(): void
    {
        foreach (range(1, 26) as $number) {
            $asset = $this->image(MediaAssetState::Ready, 'Ready image '.$number);
            $asset->forceFill(['internal_title' => 'Picker '.$number, 'original_filename' => 'picker-'.$number.'.jpg'])->save();
        }
        $this->image(MediaAssetState::PendingUpload, 'Pending image');

        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->getJson(route('admin.media.picker'))->assertForbidden();
        $response = $this->actingAs($this->manager)->getJson(route('admin.media.picker'))
            ->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('total', 26);
        $this->assertSame(24, count($response->json('data')));

        $this->actingAs($this->manager)->getJson(route('admin.media.picker', ['search' => 'picker-26.jpg']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'picker-26.jpg');
    }

    private function image(MediaAssetState $state, string $alt = 'Collection editorial image'): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'collections/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'collection.jpg', 'internal_title' => 'Collection image', 'default_alt_text' => $alt, 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => $state, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => $state === MediaAssetState::Ready ? now() : null]);
    }
}
