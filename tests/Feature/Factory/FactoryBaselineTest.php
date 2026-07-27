<?php

namespace Tests\Feature\Factory;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\SiteContent\Models\SiteContent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FactoryBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('media.provider', 'deterministic');
        config()->set('factory.users', [
            'administrator' => ['name' => 'Administrator', 'email' => 'admin@example.test', 'password' => 'FactoryPassword!2026', 'role' => RoleRegistry::SUPER_ADMINISTRATOR],
            'cms_manager' => ['name' => 'CMS Manager', 'email' => 'cms@example.test', 'password' => 'FactoryPassword!2026', 'role' => RoleRegistry::CMS_MANAGER],
            'inventory_manager' => ['name' => 'Inventory Manager', 'email' => 'inventory@example.test', 'password' => 'FactoryPassword!2026', 'role' => RoleRegistry::INVENTORY_MANAGER],
        ]);
    }

    public function test_inventory_manager_is_a_reserved_admin_shell_role_only(): void
    {
        $this->assertSame([PermissionRegistry::ADMIN_ACCESS], RoleRegistry::permissionBundles()[RoleRegistry::INVENTORY_MANAGER]);
        $this->assertNotContains('inventory.view', PermissionRegistry::all());
    }

    public function test_database_seeder_is_opt_in_and_creates_no_business_data_by_default(): void
    {
        config()->set('factory.seed_enabled', false);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('site_contents', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_install_preview_is_non_mutating_and_omits_passwords(): void
    {
        $this->artisan('factory:install')->expectsOutputToContain('Preview only')->doesntExpectOutput('FactoryPassword!2026')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('site_contents', 0);
    }

    public function test_factory_install_creates_exact_identities_roles_and_published_content_idempotently(): void
    {
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('site_contents', 4);
        $this->assertDatabaseCount('content_revisions', 4);
        $this->assertDatabaseCount('site_content_publication_transitions', 12);
        foreach (config('factory.users') as $identity) {
            $user = User::query()->where('email', $identity['email'])->sole();
            $this->assertNotNull($user->email_verified_at);
            $this->assertSame([$identity['role']], $user->getRoleNames()->all());
        }
        $inventory = Role::findByName(RoleRegistry::INVENTORY_MANAGER);
        $this->assertSame([PermissionRegistry::ADMIN_ACCESS], $inventory->permissions->pluck('name')->all());
        $password = User::query()->where('email', 'admin@example.test')->sole()->password;
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        $this->assertSame($password, User::query()->where('email', 'admin@example.test')->sole()->password);
        $this->assertDatabaseCount('content_revisions', 4);
        $this->assertDatabaseCount('site_content_publication_transitions', 12);
    }

    public function test_existing_normalized_email_is_reused_and_password_reset_is_explicit(): void
    {
        $user = User::factory()->create(['email' => 'ADMIN@EXAMPLE.TEST', 'password' => 'OriginalPassword!2026']);
        $oldHash = $user->password;
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        $user->refresh();
        $this->assertSame($oldHash, $user->password);
        $this->artisan('factory:reset', ['--apply' => true, '--scope' => 'identities', '--include-password-reset' => true])->assertSuccessful();
        $this->assertTrue(Hash::check('FactoryPassword!2026', $user->fresh()->password));
    }

    public function test_missing_credentials_fail_closed_without_leaking_values(): void
    {
        config()->set('factory.users.cms_manager.password', null);
        $this->expectException(ValidationException::class);
        $this->artisan('factory:install', ['--apply' => true])->run();
    }

    public function test_media_manifest_is_unique_and_all_local_checksums_match(): void
    {
        $entries = app(FactoryManifest::class)->media();
        $keys = array_column($entries, 'logical_key');
        $this->assertCount(count(array_unique($keys)), $keys);
        foreach ($entries as $entry) {
            if ($entry['source_kind'] !== 'local') {
                continue;
            }
            $this->assertFileExists(base_path($entry['source']));
            $this->assertSame($entry['sha256'], hash_file('sha256', base_path($entry['source'])));
        }
    }

    public function test_media_sync_is_preview_first_and_idempotent_with_fake_provider(): void
    {
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        $key = 'storefront-8d99836ea-logo-3';
        $this->artisan('factory:media-sync', ['--only' => $key])->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 0);
        $this->artisan('factory:media-sync', ['--apply' => true, '--only' => $key])->assertSuccessful();
        $this->artisan('factory:media-sync', ['--apply' => true, '--only' => $key])->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_asset_versions', 1);
    }

    public function test_content_reset_is_preview_first_and_preserves_non_factory_records(): void
    {
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        SiteContent::query()->create(['type' => 'announcement', 'key' => 'editor-created', 'locale' => 'en', 'title' => 'Editor content', 'created_by' => 1, 'updated_by' => 1]);
        $before = ContentRevision::query()->count();
        $this->artisan('factory:reset', ['--scope' => 'content'])->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertSame($before, ContentRevision::query()->count());
        $this->artisan('factory:reset', ['--scope' => 'content', '--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('site_contents', ['key' => 'editor-created']);
    }

    public function test_full_reset_requires_phrase_and_backup_acknowledgment(): void
    {
        $this->artisan('factory:install', ['--apply' => true])->assertSuccessful();
        try {
            $this->artisan('factory:reset', ['--scope' => 'all', '--apply' => true])->run();
            $this->fail('Full reset without confirmation must fail.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
        $this->artisan('factory:reset', [
            '--scope' => 'all', '--apply' => true,
            '--confirm' => 'RESET WILLIAM TAYLOR TO FACTORY V1', '--backup-acknowledged' => true,
        ])->assertSuccessful();
        $this->assertDatabaseCount('users', 3);
    }
}
