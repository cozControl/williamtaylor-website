<?php

namespace Database\Seeders;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

final class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $configured = config('factory.users.administrator');

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The administrator seed configuration is missing.');
        }

        $administrator = Validator::make($configured, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', Password::min(12)],
            'role' => ['required', 'string', Rule::in([RoleRegistry::SUPER_ADMINISTRATOR])],
        ])->validate();
        $email = mb_strtolower(trim($administrator['email']));

        DB::transaction(function () use ($administrator, $email): void {
            app(ProvisionRegisteredAccess::class)->handle();

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();
            $created = $user === null;

            if ($created) {
                $user = User::query()->create([
                    'name' => $administrator['name'],
                    'email' => $email,
                    'password' => $administrator['password'],
                ]);
            }

            $before = [
                'created' => false,
                'email_verified' => $user->email_verified_at !== null,
                'roles' => $user->getRoleNames()->sort()->values()->all(),
            ];

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now('UTC')])->save();
            }

            if (! $user->hasRole(RoleRegistry::SUPER_ADMINISTRATOR)) {
                app(ControlledRoleMutation::class)->run(
                    fn () => $user->assignRole(RoleRegistry::SUPER_ADMINISTRATOR),
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $afterRoles = $user->fresh()->getRoleNames()->sort()->values()->all();
            $changed = $created
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
                        'email_verified' => true,
                        'roles' => $afterRoles,
                    ],
                    reason: 'Explicit administrator seed',
                );
            }
        }, 3);
    }
}
