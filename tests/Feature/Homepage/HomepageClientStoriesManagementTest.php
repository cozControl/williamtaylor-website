<?php

namespace Tests\Feature\Homepage;

use App\Domain\Homepage\Models\HomepageClientStory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageClientStoriesPresenter;
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

final class HomepageClientStoriesManagementTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.client-stories.edit'))->assertOk();
        $image = $this->image('Test portrait', 'Fictional portrait description');
        $stories = [];
        foreach ([1 => 'Asha Test Client', 2 => 'Neema Test Client', 3 => 'Juma Test Client'] as $position => $name) {
            $stories[$position] = ['is_visible' => '1', 'display_name' => $name, 'location' => 'Test Location '.$position, 'quote' => 'Fictional editorial quote '.$position, 'image_id' => $image->id, 'alt' => ''];
        }

        return [...HomepageHero::clientStoriesDefaults(), 'client_stories_managed' => '1', 'client_stories_heading' => 'Managed Test Stories', 'stories' => $stories, 'lock_version' => HomepageHero::query()->sole()->lock_version];
    }

    public function test_authority_validation_capacity_and_stale_save(): void
    {
        $edit = route('admin.homepage.client-stories.edit');
        $update = route('admin.homepage.client-stories.update');
        $this->get($edit)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($edit)->assertForbidden();
        $this->put($update, [])->assertForbidden();
        $data = $this->payload();
        $this->get($edit)->assertSeeText('Using storefront default')->assertSeeText('Back to Homepage')->assertSee('data-client-story-position="3"', false)->assertSee('data-media-picker', false);
        $invalid = $data;
        $invalid['stories'][1]['display_name'] = '';
        $invalid['stories'][2]['quote'] = '<script>bad</script>';
        $this->from($edit)->put($update, $invalid)->assertSessionHasErrors(['stories.1.display_name', 'stories.2.quote'])->assertSessionHasInput('stories.3.display_name', 'Juma Test Client');
        $extra = $data;
        $extra['stories'][4] = $data['stories'][1];
        $this->put($update, $extra)->assertSessionHasErrors('stories');
        $this->put($update, $data)->assertSessionHasNoErrors();
        $this->put($update, $data)->assertSessionHasErrors('lock_version');
        $this->assertDatabaseCount('homepage_client_stories', 3);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.client_stories.updated']);
    }

    public function test_managed_order_media_full_partial_zero_and_template_suppression(): void
    {
        $data = $this->payload();
        $this->put(route('admin.homepage.client-stories.update'), $data)->assertSessionHasNoErrors();
        $presented = app(HomepageClientStoriesPresenter::class)->present();
        $this->assertSame(['Asha Test Client', 'Neema Test Client', 'Juma Test Client'], array_column($presented['stories'], 'display_name'));
        $this->assertSame('Fictional portrait description', $presented['stories'][0]['image']['alt']);
        $response = $this->get(route('home'))->assertOk()->assertSeeTextInOrder(['Managed Test Stories', 'Fictional editorial quote 1', 'Asha Test Client', 'Fictional editorial quote 2', 'Neema Test Client', 'Juma Test Client'])->assertDontSeeText('James M.')->assertDontSeeText('David K.')->assertDontSeeText('Marcus A.')->assertSee('homepage-client-stories-projection', false)->assertSee('synchronizeClientStories();', false)->assertSee('<blockquote', false)->assertSee('<figcaption', false)->assertSee('synchronizeSummerEdit();', false);
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Managed Test Stories'), strpos($html, 'Crafted for Her'));
        $this->assertLessThan(strpos($html, 'Follow the Journey'), strpos($html, 'Managed Test Stories'));
        $this->assertStringContainsString($presented['stories'][0]['image']['url'], $html);
        $this->assertSame(6, substr_count($html, 'data-client-story-card style='));
        $this->get(route('admin.homepage.edit'))->assertSeeTextInOrder(["Manage Women's Handbags", 'Manage Client Stories']);
        HomepageClientStory::where('position', 2)->update(['is_visible' => false]);
        $this->assertSame(['Asha Test Client', 'Juma Test Client'], array_column(app(HomepageClientStoriesPresenter::class)->present()['stories'], 'display_name'));
        $this->get(route('home'))->assertDontSeeText('Neema Test Client')->assertDontSeeText('David K.');
        HomepageClientStory::where('position', 1)->update(['display_name' => null]);
        $this->assertSame(1, app(HomepageClientStoriesPresenter::class)->present()['attention_count']);
        $this->get(route('admin.homepage.client-stories.edit'))->assertSeeText('Needs attention');
        MediaAsset::whereKey($data['stories'][1]['image_id'])->update(['archived_at' => now()]);
        $this->get(route('home'))->assertSee('data-homepage-client-stories hidden', false)->assertDontSeeText('Asha Test Client')->assertDontSeeText('James M.');
    }

    public function test_alt_fallback_and_unmanaged_content_preservation(): void
    {
        $data = $this->payload();
        MediaAsset::whereKey($data['stories'][1]['image_id'])->update(['default_alt_text' => null]);
        $this->put(route('admin.homepage.client-stories.update'), $data)->assertSessionHasErrors(['stories.1.alt', 'stories.2.alt', 'stories.3.alt'])->assertSessionDoesntHaveErrors(['stories.1.image_id']);
        foreach ([1, 2, 3] as $position) {
            $data['stories'][$position]['alt'] = 'Contextual test portrait '.$position;
        }
        $this->put(route('admin.homepage.client-stories.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Contextual test portrait 1', app(HomepageClientStoriesPresenter::class)->present()['stories'][0]['image']['alt']);
        $data['lock_version'] = HomepageHero::query()->sole()->lock_version;
        $data['stories'][1]['quote'] = 'Revised fictional editorial quote.';
        $this->put(route('admin.homepage.client-stories.update'), $data)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSeeText('Revised fictional editorial quote.');
        $data['lock_version'] = HomepageHero::query()->sole()->lock_version;
        $data['client_stories_managed'] = '0';
        $this->put(route('admin.homepage.client-stories.update'), $data)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSeeText('James M.')->assertSeeText('David K.')->assertSeeText('Marcus A.')->assertDontSeeText('Asha Test Client')->assertDontSee('<template id="homepage-client-stories-projection">', false);
        $this->get(route('admin.homepage.client-stories.edit'))->assertSee('Asha Test Client')->assertSeeText('Using storefront default');
    }

    private function image(string $name, string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg',
            'internal_title' => $name,
            'default_alt_text' => $alt,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->manager->id,
            'confirmed_at' => now(),
        ]);
    }
}
