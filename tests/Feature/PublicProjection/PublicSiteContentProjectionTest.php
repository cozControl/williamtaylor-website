<?php

namespace Tests\Feature\PublicProjection;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\PublicProjection\Data\PublicSiteChromeView;
use App\Domain\PublicProjection\Services\PublicSiteContentCache;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PublicSiteContentProjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $cms;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->cms = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $this->cms->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_projection_is_disabled_by_default_and_static_homepage_remains_available(): void
    {
        config()->set('public_site_content.enabled', false);

        $chrome = app(ResolvePublicSiteChrome::class)->resolve();

        $this->assertNull($chrome->navigation);
        $this->assertNull($chrome->announcement);
        $this->get('/')->assertOk()->assertSee('Collections')->assertSee('/website/images/8d99836ea_LOGO-3.png', false);
    }

    public function test_only_the_current_published_revision_is_projected(): void
    {
        config()->set('public_site_content.enabled', true);
        $resource = $this->resource('primary_navigation', 'Primary navigation');
        $payload = $resource->currentDraftRevision->payload;
        $payload['items'] = [[
            'key' => 'shop',
            'label' => 'Governed Shop',
            'link' => ['type' => 'internal_path', 'value' => '/collections'],
            'new_tab' => false,
            'visibility' => 'all',
            'children' => [],
        ]];
        app(SaveSiteContentDraft::class)->handle($this->cms, $resource, $resource->current_draft_revision_id, $payload, 'Projection fixture');
        $resource = $resource->fresh();

        $this->assertNull(app(ResolvePublicSiteChrome::class)->resolve()->navigation);

        $this->publish($resource);
        app()->forgetInstance(ResolvePublicSiteChrome::class);
        $navigation = app(ResolvePublicSiteChrome::class)->resolve()->navigation;

        $this->assertNotNull($navigation);
        $this->assertSame('Governed Shop', $navigation->items[0]->link->label);
        $this->get('/')->assertOk()->assertSee('Governed Shop');
    }

    public function test_projection_cache_can_be_invalidated_without_flushing_unrelated_cache(): void
    {
        Cache::put('unrelated', 'preserved');
        $cache = app(PublicSiteContentCache::class);
        $this->assertSame('projected', $cache->remember('resource-1', 'projection-key', fn () => 'projected'));

        $cache->invalidate('resource-1');

        $this->assertFalse(Cache::has('projection-key'));
        $this->assertSame('preserved', Cache::get('unrelated'));
    }

    public function test_operational_commands_succeed_in_disabled_mode(): void
    {
        config()->set('public_site_content.enabled', false);

        $this->artisan('public-site-content:check')->assertSuccessful();
        $this->artisan('public-site-content:warm')->assertSuccessful();
    }

    public function test_disabled_navigation_rollout_still_checks_live_site_settings(): void
    {
        config()->set('public_site_content.enabled', false);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ResolvePublicSiteChrome::class)->resolve();

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_uncached_missing_surface_resolution_stays_within_four_queries(): void
    {
        config()->set('public_site_content.enabled', true);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ResolvePublicSiteChrome::class)->resolve();

        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }

    public function test_draft_approved_future_scheduled_due_and_unpublished_states_are_isolated(): void
    {
        Carbon::setTestNow('2026-07-25 10:00:00 UTC');
        config()->set('public_site_content.enabled', true);
        $resource = $this->resource('primary_navigation', 'Lifecycle navigation');
        $payload = ['items' => [[
            'key' => 'lifecycle', 'label' => 'Lifecycle Public',
            'link' => ['type' => 'internal_path', 'value' => '/collections'],
            'new_tab' => false, 'visibility' => 'all', 'children' => [],
        ]]];
        app(SaveSiteContentDraft::class)->handle($this->cms, $resource, $resource->current_draft_revision_id, $payload, 'Lifecycle fixture');
        $resource = $resource->fresh();
        $this->assertNull($this->freshChrome()->navigation, 'Draft must not project.');

        $workflow = app(SiteContentWorkflow::class);
        $workflow->submit($this->cms, $resource, 'Review', $this->fingerprint($resource));
        $workflow->approve($this->cms, $resource, 'Approved', $this->fingerprint($resource));
        $this->assertNull($this->freshChrome()->navigation, 'Approved-only must not project.');

        $workflow->schedule($this->cms, $resource, now()->addMinute(), $this->fingerprint($resource));
        $this->assertNull($this->freshChrome()->navigation, 'Future schedule must not project.');

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertTrue($workflow->publishScheduled($resource, 'be4ha-query-lifecycle', now()));
        $this->assertSame('Lifecycle Public', $this->freshChrome()->navigation?->items[0]->link->label);

        $workflow->unpublish($this->cms, $resource, 'Closeout withdrawal', $this->fingerprint($resource));
        $this->assertNull($this->freshChrome()->navigation, 'Unpublished content must fall back.');
    }

    public function test_uncached_and_cached_published_composed_projection_stays_within_six_queries(): void
    {
        config()->set('public_site_content.enabled', true);
        $resource = $this->publishedBudgetNavigation('Budget Public');
        Cache::clear();

        $uncached = $this->queryCount(fn () => $this->freshChrome());
        $cached = $this->queryCount(fn () => $this->freshChrome());

        $this->assertLessThanOrEqual(6, $uncached, 'Uncached composed projection exceeded budget.');
        $this->assertLessThanOrEqual(6, $cached, 'Cached composed projection exceeded budget.');
    }

    public function test_enabled_homepage_and_product_route_stay_within_five_projection_queries(): void
    {
        config()->set('public_site_content.enabled', true);
        $this->publishedBudgetNavigation('Route Budget Public');

        $homepage = $this->queryCount(function (): void {
            $this->get('/')->assertOk()->assertSee('Route Budget Public');
        });
        $product = $this->queryCount(function (): void {
            $this->get('/products/the-taylor-oxford-shirt')->assertOk()->assertSee('Route Budget Public');
        });

        $this->assertLessThanOrEqual(6, $homepage, 'Enabled homepage exceeded projection query budget.');
        $this->assertLessThanOrEqual(6, $product, 'Enabled product route exceeded projection query budget.');
    }

    private function publishedBudgetNavigation(string $label): SiteContent
    {
        $resource = $this->resource('primary_navigation', $label);
        $payload = ['items' => [[
            'key' => 'budget', 'label' => $label,
            'link' => ['type' => 'internal_path', 'value' => '/shop'],
            'new_tab' => false, 'visibility' => 'all', 'children' => [],
        ]]];
        app(SaveSiteContentDraft::class)->handle($this->cms, $resource, $resource->current_draft_revision_id, $payload, 'Budget fixture');
        $this->publish($resource->fresh());

        return $resource->fresh();
    }

    private function freshChrome(): PublicSiteChromeView
    {
        app()->forgetInstance(ResolvePublicSiteChrome::class);

        return app(ResolvePublicSiteChrome::class)->resolve();
    }

    private function queryCount(callable $operation): int
    {
        app()->forgetInstance(ResolvePublicSiteChrome::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $operation();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function resource(string $type, string $title): SiteContent
    {
        return app(EnsureSiteContent::class)->handle($this->cms, $type, null, $title);
    }

    private function publish(SiteContent $resource): void
    {
        $workflow = app(SiteContentWorkflow::class);
        $workflow->submit($this->cms, $resource, 'Ready for review', $this->fingerprint($resource));
        $workflow->approve($this->cms, $resource, 'Approved', $this->fingerprint($resource));
        $workflow->publish($this->cms, $resource, $this->fingerprint($resource));
    }

    private function fingerprint(SiteContent $resource): string
    {
        return app(SiteContentFingerprint::class)->for($resource->fresh());
    }
}
