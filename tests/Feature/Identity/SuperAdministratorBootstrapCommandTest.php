<?php

namespace Tests\Feature\Identity;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdministratorBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_bootstraps_only_an_existing_verified_user_and_audits_it(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('rbac:bootstrap-super-admin', [
            'email' => 'OWNER@example.com',
            '--reason' => 'Initial owner authorization',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $audit = AuditRecord::query()->sole();
        $this->assertSame('identity.super-administrator.bootstrapped', $audit->action);
        $this->assertNull($audit->actor_user_id);
        $this->assertSame('Initial owner authorization', $audit->reason);
    }

    public function test_command_never_creates_a_user_for_an_unknown_email(): void
    {
        $this->artisan('rbac:bootstrap-super-admin', [
            'email' => 'missing@example.com',
            '--force' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('audit_records', 0);
    }

    public function test_command_refuses_a_second_bootstrap(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);

        $this->artisan('rbac:bootstrap-super-admin', [
            'email' => $first->email,
            '--force' => true,
        ])->assertSuccessful();
        $this->artisan('rbac:bootstrap-super-admin', [
            'email' => $second->email,
            '--force' => true,
        ])->assertFailed();

        $this->assertTrue($first->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->assertFalse($second->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->assertDatabaseCount('audit_records', 1);
    }

    public function test_command_refuses_an_unverified_user(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $this->artisan('rbac:bootstrap-super-admin', [
            'email' => $user->email,
            '--force' => true,
        ])->assertFailed();

        $this->assertFalse($user->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->assertDatabaseCount('audit_records', 0);
    }
}
