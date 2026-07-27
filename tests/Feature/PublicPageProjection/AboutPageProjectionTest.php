<?php

namespace Tests\Feature\PublicPageProjection;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\RevisionPayload;
use App\Domain\PublicProjection\Registry\PublicPageRouteRegistry;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class AboutPageProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'publication_rollout.global_enabled' => true,
            'publication_rollout.resources.page' => 'enabled',
        ]);
    }

    public function test_route_is_exact_and_disabled_projection_renders_complete_static_page_without_page_queries(): void
    {
        config(['public_page_projection.enabled' => false, 'public_site_content.enabled' => false]);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->get('/about')->assertOk()->assertSee('Our Story')->assertSee('Crafted for the modern gentleman')->assertDontSee('data-public-page-projected', false);
        $this->assertSame(0, $queries);
        $this->assertSame('/about', route('about', [], false));
    }

    public function test_registry_has_exactly_one_deterministic_fail_closed_entry(): void
    {
        $registry = app(PublicPageRouteRegistry::class);
        $this->assertSame(['about'], array_keys($registry->all()));
        $this->assertSame($registry->checksum(), app(PublicPageRouteRegistry::class)->checksum());
        $this->expectException(InvalidArgumentException::class);
        $registry->get('user-input');
    }

    public function test_only_designated_revision_renders_and_query_or_preview_input_cannot_select_draft(): void
    {
        config(['public_page_projection.enabled' => true, 'public_site_content.enabled' => false]);
        [$page,$published] = $this->published('Published Story');
        $draft = $this->revision($page, 2, 'Secret Draft');
        $page->update(['current_draft_revision_id' => $draft->id]);
        $response = $this->get('/about?revision='.$draft->id.'&preview=1')->assertOk()->assertSee('Published Story')->assertDontSee('Secret Draft')->assertSee('data-public-page-projected="about"', false)->assertDontSee('page_publication_states');
        foreach ([$published->id, $draft->id, $page->id, $page->publicationState->id, $published->checksum, $draft->checksum, 'data-public-revision', 'data-revision-id', 'currentPublicRevision'] as $internalValue) {
            $response->assertDontSee((string) $internalValue, false);
        }
        $this->withCookie('revision', (string) $draft->id)->get('/about')->assertOk()->assertSee('Published Story')->assertDontSee('Secret Draft');
        $this->withSession(['revision' => $draft->id])->get('/about')->assertOk()->assertSee('Published Story')->assertDontSee('Secret Draft');
        $this->get('/about?signature=forged&expires=4102444800')->assertOk()->assertSee('Published Story')->assertDontSee('Secret Draft');
    }

    public function test_missing_wrong_or_unpublished_resource_falls_back_to_static(): void
    {
        config(['public_page_projection.enabled' => true, 'public_site_content.enabled' => false]);
        $this->get('/about')->assertOk()->assertDontSee('data-public-page-projected', false);
        [$page] = $this->published('Wrong Template');
        $page->update(['template_key' => 'standard_page']);
        $this->get('/about')->assertOk()->assertDontSee('data-public-page-projected', false);
        $page->update(['template_key' => 'about']);
        $page->publicationState->update(['current_public_revision_id' => null]);
        $this->get('/about')->assertOk()->assertDontSee('data-public-page-projected', false);
    }

    public function test_all_global_and_page_flag_combinations_are_independent(): void
    {
        [$page] = $this->published('Flag Story');
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$site,$pageFlag]) {
            config(['public_site_content.enabled' => $site, 'public_page_projection.enabled' => $pageFlag]);
            $response = $this->get('/about')->assertOk();
            $pageFlag ? $response->assertSee('data-public-page-projected="about"', false) : $response->assertDontSee('data-public-page-projected', false);
        }
    }

    public function test_rollout_modes_keep_static_and_shadow_publicly_static_while_enabled_projects(): void
    {
        config(['public_page_projection.enabled' => true]);
        $this->published('Governed Story');

        foreach (['static', 'emergency_disabled'] as $mode) {
            config(['publication_rollout.resources.page' => $mode]);
            $this->get('/about')
                ->assertOk()
                ->assertDontSee('data-public-page-projected', false)
                ->assertSee('/website/js/index-DxdnTNDA.js', false);
        }

        config(['publication_rollout.resources.page' => 'shadow']);
        $this->get('/about')
            ->assertOk()
            ->assertDontSee('data-public-page-projected', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);

        config(['publication_rollout.resources.page' => 'enabled']);
        $this->get('/about')
            ->assertOk()
            ->assertSee('data-public-page-projected="about"', false)
            ->assertSee('Governed Story')
            ->assertDontSee('/website/js/index-DxdnTNDA.js', false);

        config(['publication_rollout.global_enabled' => false]);
        $this->get('/about')
            ->assertOk()
            ->assertDontSee('data-public-page-projected', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_enabled_projection_stays_within_uncached_and_cached_query_budgets(): void
    {
        config(['public_page_projection.enabled' => true, 'public_site_content.enabled' => false]);
        $this->published('Budget Story');
        Cache::clear();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get('/about')->assertOk()->assertSee('Budget Story');
        $uncached = $queries;
        $this->get('/about')->assertOk()->assertSee('Budget Story');
        $cached = $queries - $uncached;

        $this->assertLessThanOrEqual(3, $uncached);
        $this->assertLessThanOrEqual(3, $cached);
        $this->assertLessThanOrEqual($uncached, $cached);
    }

    public function test_check_and_warm_commands_are_safe_when_disabled_and_fail_for_unknown_route(): void
    {
        config(['public_page_projection.enabled' => false]);
        $this->artisan('public-page:check about')->assertSuccessful();
        $this->artisan('public-page:warm about')->assertSuccessful();
        $this->artisan('public-page:check unknown')->assertFailed();
    }

    /** @return array{Page,ContentRevision} */
    private function published(string $heading): array
    {
        $user = User::factory()->create();
        $page = Page::create(['type' => 'standard', 'locale' => 'en', 'title' => 'Our Story', 'slug' => 'about', 'template_key' => 'about', 'created_by' => $user->id, 'updated_by' => $user->id]);
        $revision = $this->revision($page, 1, $heading);
        $page->update(['current_draft_revision_id' => $revision->id]);
        PagePublicationState::create(['page_id' => $page->id, 'current_public_revision_id' => $revision->id, 'state_version' => 3]);

        return [$page->fresh('publicationState.currentPublicRevision'), $revision];
    }

    private function revision(Page $page, int $number, string $heading): ContentRevision
    {
        $user = User::query()->first() ?? User::factory()->create();
        $sections = $this->sections($heading);
        $payload = ['page' => ['title' => 'Our Story'], 'sections' => $sections];

        return ContentRevision::create(['resource_type' => Page::class, 'resource_id' => $page->id, 'revision_number' => $number, 'schema_version' => 1, 'payload' => $payload, 'checksum' => app(RevisionPayload::class)->checksum($payload), 'sanitizer_version' => '1', 'change_summary' => 'Test revision', 'created_by' => $user->id, 'created_at' => now('UTC')]);
    }

    private function sections(string $heading): array
    {
        $key = fn () => (string) Str::ulid();

        return [
            ['key' => $key(), 'type' => 'hero', 'schema_version' => 1, 'data' => ['eyebrow' => 'William Taylor', 'heading' => $heading, 'copy' => 'Contemporary menswear.', 'desktop_media' => null, 'mobile_media' => null, 'primary_cta' => null, 'secondary_cta' => null, 'alignment' => 'left', 'variant' => 'overlay']],
            ['key' => $key(), 'type' => 'editorial_split', 'schema_version' => 1, 'data' => ['heading' => 'Designed to move with you', 'copy' => 'A considered wardrobe.', 'media' => null, 'media_position' => 'left', 'cta' => null, 'variant' => 'plain']],
            ['key' => $key(), 'type' => 'promotional_cards', 'schema_version' => 1, 'data' => ['heading' => 'Our approach', 'cards' => [['key' => $key(), 'heading' => 'Refined simplicity', 'copy' => 'Purposeful details.', 'media' => null, 'cta' => null, 'variant' => 'portrait']]]],
            ['key' => $key(), 'type' => 'rich_text', 'schema_version' => 1, 'data' => ['document' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Safe editorial copy.']]]]]]],
            ['key' => $key(), 'type' => 'cta', 'schema_version' => 1, 'data' => ['heading' => 'Discover menswear', 'copy' => 'Explore the wardrobe.', 'primary_cta' => ['kind' => 'internal_path', 'label' => 'Shop All', 'target' => '/shop'], 'background_media' => null, 'variant' => 'dark']],
        ];
    }
}
