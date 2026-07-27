<?php

namespace Database\Seeders;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

final class FactoryIdentitySeeder extends Seeder
{
    /** @var array<string, int> */
    private array $result = ['created' => 0, 'reused' => 0, 'assigned' => 0, 'passwords_reset' => 0];

    public function run(): void
    {
        $this->install(false);
    }

    /** @return array<string, int> */
    public function install(bool $includePasswordReset): array
    {
        $configured = config('factory.users');
        if (! is_array($configured) || count($configured) !== 3) {
            throw new InvalidArgumentException('Exactly three factory identities must be configured.');
        }

        DB::transaction(function () use ($configured, $includePasswordReset): void {
            foreach ($configured as $key => $identity) {
                $validated = Validator::make($identity, [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email:rfc', 'max:255'],
                    'password' => ['required', 'string', Password::min(12)],
                    'role' => ['required', 'string'],
                ])->validate();
                if (! RoleRegistry::contains($validated['role'])) {
                    throw new InvalidArgumentException("Factory identity [{$key}] references an unregistered role.");
                }
                $email = mb_strtolower(trim($validated['email']));
                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
                $created = $user === null;
                if ($created) {
                    $user = User::query()->create([
                        'name' => $validated['name'], 'email' => $email, 'password' => $validated['password'],
                    ]);
                    $user->forceFill(['email_verified_at' => now('UTC')])->save();
                    $this->result['created']++;
                } else {
                    $this->result['reused']++;
                    $changes = ['name' => $validated['name'], 'email_verified_at' => $user->email_verified_at ?? now('UTC')];
                    if ($includePasswordReset) {
                        $changes['password'] = $validated['password'];
                        $this->result['passwords_reset']++;
                    }
                    $user->forceFill($changes)->save();
                }
                $before = $user->getRoleNames()->sort()->values()->all();
                app(ControlledRoleMutation::class)->run(fn () => $user->syncRoles([$validated['role']]));
                $this->result['assigned']++;
                app(RecordAuditEvent::class)->handle(
                    'factory.identity.reconciled', $user, null,
                    ['roles' => $before], ['factory_version' => FactoryManifest::VERSION, 'role' => $validated['role'], 'created' => $created],
                    null, 'Explicit factory identity installation'
                );
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 3);

        return $this->result;
    }
}
