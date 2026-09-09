<?php

namespace Tests\Feature\Homepage;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomepageDeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        config(['public_site_content.enabled' => true]);
    }

    public function test_access_validation_unmanaged_prefill_and_missing_contact(): void
    {
        $this->get(route('admin.homepage.delivery.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('admin.homepage.delivery.edit'))->assertForbidden();
        $this->put(route('admin.homepage.delivery.update'), HomepageHero::deliveryDefaults())->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.homepage.delivery.edit'))->assertOk()->assertSeeText('Using storefront default')->assertSeeText('Manage Site Settings')->assertSee('admin-panel', false);
        $payload = [...HomepageHero::deliveryDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'delivery_heading' => 'Preserved delivery copy'];
        $this->put(route('admin.homepage.delivery.update'), [...$payload, 'delivery_managed' => true])->assertSessionHasErrors('delivery_destination')->assertSessionHasInput('delivery_heading', 'Preserved delivery copy');
        $this->put(route('admin.homepage.delivery.update'), [...$payload, 'delivery_destination' => '/arbitrary'])->assertSessionHasErrors('delivery_destination');
        $this->put(route('admin.homepage.delivery.update'), [...$payload, 'delivery_heading' => '<script>bad</script>'])->assertSessionHasErrors('delivery_heading');
        $this->put(route('admin.homepage.delivery.update'), $payload)->assertSessionHasNoErrors();
        $this->get(route('admin.homepage.delivery.edit'))->assertSee('Preserved delivery copy')->assertSeeText('Back to Homepage')->assertSeeText('Save changes');
        $this->get(route('home'))->assertOk()->assertSeeText('Complimentary Delivery in Dar es Salaam')->assertDontSeeText('Preserved delivery copy')->assertDontSee('<template id="homepage-delivery-projection">', false);
    }

    public function test_published_contact_is_reused_and_managed_strip_is_isolated(): void
    {
        $profile = $this->publishEmail('client-service@example.test');
        $this->actingAs($this->manager)->get(route('admin.homepage.delivery.edit'))->assertOk()->assertSee('mailto:client-service@example.test');
        $this->put(route('admin.homepage.delivery.update'), [...HomepageHero::deliveryDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'delivery_managed' => true, 'delivery_heading' => 'Managed delivery service'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('homepage_heroes', ['delivery_destination' => 'contact_email', 'delivery_managed' => true]);
        $html = $this->get(route('home'))->assertOk()->assertSeeText('Managed delivery service')->assertSee('mailto:client-service@example.test', false)->assertSee('homepage-delivery-projection', false)->assertSee('homepage-future-style-projection', false)->getContent();
        $this->assertIsString($html);
        $this->assertLessThan(strpos($html, 'Managed delivery service'), strpos($html, 'The Summer Edit'));
        $this->assertStringNotContainsString('Complimentary Delivery in Dar es Salaam</h3>', $html);
        $this->assertSame(2, substr_count($html, 'data-homepage-delivery>'));
        $this->get(route('admin.homepage.edit'))->assertSeeTextInOrder(['Manage The Summer Edit', 'Manage Complimentary Delivery']);
        $this->publishEmail('updated-service@example.test', $profile);
        $this->get(route('home'))->assertSee('mailto:updated-service@example.test', false)->assertDontSee('mailto:client-service@example.test', false);
        $workflow = app(SiteContentWorkflow::class);
        $workflow->unpublish($this->manager, $profile, 'Contact withdrawn', app(SiteContentFingerprint::class)->for($profile->fresh()));
        app()->forgetInstance(ResolvePublicSiteChrome::class);
        $this->get(route('home'))->assertDontSeeText('Managed delivery service')->assertSee('data-homepage-delivery hidden', false)->assertSeeText('The Summer Edit');
        $this->get(route('admin.homepage.delivery.edit'))->assertSeeText('Needs attention');
    }

    private function publishEmail(string $email, ?SiteContent $profile = null): SiteContent
    {
        $profile ??= app(EnsureSiteContent::class)->handle($this->manager, SiteContentTypeRegistry::SITE_PROFILE);
        $profile->refresh();
        $payload = $profile->currentDraftRevision->payload;
        $payload['contact']['email'] = $email;
        app(SaveSiteContentDraft::class)->handle($this->manager, $profile, $profile->current_draft_revision_id, $payload, 'Delivery contact fixture');
        $workflow = app(SiteContentWorkflow::class);
        $fingerprint = fn () => app(SiteContentFingerprint::class)->for($profile->fresh());
        $workflow->submit($this->manager, $profile, 'Review contact', $fingerprint());
        $workflow->approve($this->manager, $profile, 'Approved contact', $fingerprint());
        $workflow->publish($this->manager, $profile, $fingerprint());
        app()->forgetInstance(ResolvePublicSiteChrome::class);

        return $profile->fresh();
    }
}
