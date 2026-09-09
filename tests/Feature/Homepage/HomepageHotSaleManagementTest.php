<?php

namespace Tests\Feature\Homepage;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomepageHotSaleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_hot_sale_editor_is_authorized_and_loads_template_fields_and_lazy_media_pickers(): void
    {
        $this->get(route('admin.homepage.hot-sale.edit'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $ordinary->givePermissionTo('admin.access');
        $this->actingAs($ordinary)->get(route('admin.homepage.hot-sale.edit'))->assertForbidden();
        $this->actingAs($ordinary)->put(route('admin.homepage.hot-sale.update'), [])->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.homepage.hot-sale.edit'))->assertOk()
            ->assertSeeText("William's Hot Sale")->assertSee('value="The Atelier Edit"', false)
            ->assertSee('value="The Shopping Experience"', false)->assertSee('value="The Signature Bag"', false)
            ->assertSee('data-media-picker="hot-sale-1-media"', false)
            ->assertSee('data-media-picker="hot-sale-2-media"', false)
            ->assertSee('data-media-picker="hot-sale-3-media"', false)
            ->assertSeeTextInOrder(['Back to Homepage', 'Save changes']);
    }

    public function test_manager_saves_three_editorial_tiles_and_public_homepage_uses_exact_composition(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->video('experience'), $this->image('signature')];

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $this->payload($homepage, $images))
            ->assertRedirect(route('admin.homepage.hot-sale.edit'))
            ->assertSessionHas('status', "William's Hot Sale updated successfully.");

        $this->assertDatabaseHas('homepage_heroes', ['id' => $homepage->id, 'hot_sale_managed' => true, 'hot_sale_heading' => "William's Hot Sale"]);
        $this->assertDatabaseCount('media_usages', 3);
        $this->assertDatabaseHas('media_usages', ['media_asset_id' => $images[0]->id, 'alt_text_override' => null]);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.hot-sale.updated', 'resource_identifier' => $homepage->id]);

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-hot-sale', false)
            ->assertSee('<template id="homepage-hot-sale-projection">', false)
            ->assertSee('synchronizeHotSale', false)
            ->assertSeeTextInOrder(['The Atelier Edit', 'The Shopping Experience', 'The Signature Bag'])
            ->assertSeeText('Discover')->assertSee(route('products.index', ['sort' => 'newest']), false)
            ->assertSee(route('collections.index'), false)->assertSee(route('products.index'), false)
            ->assertSee('aspect-[3/4]', false)->assertSee('md:grid-cols-3', false)
            ->assertSee('<video', false)->assertSee('autoplay muted loop playsinline', false)
            ->assertSee('group-hover:scale-108', false)->assertSee('group-hover:-translate-y-2', false);
        $this->assertSame(6, substr_count($response->getContent(), 'data-hot-sale-tile'));
    }

    public function test_invalid_media_preserves_content_and_unavailable_config_falls_back_safely(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->image('experience'), $this->image('signature')];
        $payload = $this->payload($homepage, $images);
        $payload['hot_sale_tile_1_title'] = 'Preserved title';
        $payload['hot_sale_tile_2_media_id'] = 'missing-media';
        $this->actingAs($this->manager)->from(route('admin.homepage.hot-sale.edit'))->put(route('admin.homepage.hot-sale.update'), $payload)
            ->assertRedirect(route('admin.homepage.hot-sale.edit'))->assertSessionHasErrors('hot_sale_tile_2_media_id')
            ->assertSessionHasInput('hot_sale_tile_1_title', 'Preserved title');

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $this->payload($homepage->fresh(), $images))->assertRedirect();
        $images[1]->forceFill(['archived_at' => now()])->save();
        $this->get(route('home'))->assertOk()->assertDontSee('data-homepage-hot-sale', false)->assertSeeText("William's Hot Sale");
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSeeText('One or more editorial feature tiles needs attention.')
            ->assertSeeText('Needs attention');
    }

    public function test_contextual_alt_is_optional_and_anomalous_ready_media_returns_actionable_error(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->image('experience'), $this->image('signature')];
        $payload = $this->payload($homepage, $images);
        $payload['hot_sale_tile_1_alt_override'] = 'Editorial tailoring in the atelier';

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $payload)->assertRedirect();
        $this->assertDatabaseHas('media_usages', [
            'media_asset_id' => $images[0]->id,
            'alt_text_override' => 'Editorial tailoring in the atelier',
        ]);
        $this->get(route('home'))->assertOk()->assertSee('alt="Editorial tailoring in the atelier"', false);

        $images[1]->forceFill(['default_alt_text' => null])->save();
        $payload = $this->payload($homepage->fresh(), $images);
        $payload['hot_sale_tile_1_title'] = 'Preserved after alt failure';
        $response = $this->actingAs($this->manager)->from(route('admin.homepage.hot-sale.edit'))
            ->put(route('admin.homepage.hot-sale.update'), $payload);
        $response->assertRedirect(route('admin.homepage.hot-sale.edit'))
            ->assertSessionHasErrors(['hot_sale_tile_2_media_id' => 'This Media Asset needs descriptive alt text before it can be used here. Update it in Media Library.'])
            ->assertSessionHasInput('hot_sale_tile_1_title', 'Preserved after alt failure')
            ->assertSessionHasInput('hot_sale_tile_2_media_id', $images[1]->id);
    }

    /** @param array<int, MediaAsset> $images @return array<string, mixed> */
    private function payload(HomepageHero $homepage, array $images): array
    {
        $payload = [...HomepageHero::hotSaleDefaults(), 'lock_version' => $homepage->lock_version];
        foreach ([1, 2, 3] as $position) {
            $payload["hot_sale_tile_{$position}_media_id"] = $images[$position - 1]->id;
        }

        return $payload;
    }

    private function image(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/hot-sale/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg', 'internal_title' => $name, 'default_alt_text' => ucfirst($name).' editorial image',
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }

    private function video(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/hot-sale/'.$id,
            'resource_type' => MediaResourceType::Video, 'format' => 'mp4', 'mime_type' => 'video/mp4',
            'original_filename' => $name.'.mp4', 'internal_title' => $name, 'default_alt_text' => ucfirst($name).' editorial video',
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }
}
