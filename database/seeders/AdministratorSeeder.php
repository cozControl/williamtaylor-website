<?php

namespace Database\Seeders;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

final class AdministratorSeeder extends Seeder
{
    private const NAME = 'Administrator';

    private const EMAIL = 'admin@example.com';

    private const PASSWORD = 'password123!@';

    public function run(): void
    {
        DB::transaction(function (): void {
            app(ProvisionRegisteredAccess::class)->handle();

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [self::EMAIL])
                ->lockForUpdate()
                ->first();
            $created = $user === null;

            if ($created) {
                $user = User::query()->create([
                    'name' => self::NAME,
                    'email' => self::EMAIL,
                    'password' => self::PASSWORD,
                ]);
            }

            $before = [
                'created' => false,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'roles' => $user->getRoleNames()->sort()->values()->all(),
            ];

            $identityChanged = $user->name !== self::NAME
                || $user->email !== self::EMAIL
                || ! Hash::check(self::PASSWORD, $user->password);

            if ($identityChanged || $user->email_verified_at === null) {
                $user->forceFill([
                    'name' => self::NAME,
                    'email' => self::EMAIL,
                    'password' => self::PASSWORD,
                    'email_verified_at' => $user->email_verified_at ?? now('UTC'),
                ])->save();
            }

            if (! $user->hasRole(RoleRegistry::SUPER_ADMINISTRATOR)) {
                app(ControlledRoleMutation::class)->run(
                    fn () => $user->assignRole(RoleRegistry::SUPER_ADMINISTRATOR),
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $afterRoles = $user->fresh()->getRoleNames()->sort()->values()->all();
            $changed = $created
                || $identityChanged
                || $before['email_verified'] === false
                || $before['roles'] !== $afterRoles;

            if ($changed) {
                app(RecordAuditEvent::class)->handle(
                    action: 'identity.administrator.seeded',
                    resource: $user,
                    actor: null,
                    before: $before,
                    after: [
                        'created' => $created,
                        'name' => self::NAME,
                        'email' => self::EMAIL,
                        'email_verified' => true,
                        'roles' => $afterRoles,
                    ],
                    reason: 'Explicit administrator seed',
                );
            }
        }, 3);
    }
}
