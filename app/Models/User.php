<?php

namespace App\Models;

use App\Domain\Identity\Exceptions\FinalSuperAdministratorException;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles {
        assignRole as private assignRoleThroughPackage;
        removeRole as private removeRoleThroughPackage;
        syncRoles as private syncRolesThroughPackage;
    }

    public function assignRole(mixed ...$roles): static
    {
        app(ControlledRoleMutation::class)->assertActive();

        return $this->assignRoleThroughPackage(...$roles);
    }

    public function removeRole(mixed ...$roles): static
    {
        app(ControlledRoleMutation::class)->assertActive();

        return $this->removeRoleThroughPackage(...$roles);
    }

    public function syncRoles(mixed ...$roles): static
    {
        app(ControlledRoleMutation::class)->assertActive();

        return $this->syncRolesThroughPackage(...$roles);
    }

    public function delete()
    {
        if ($this->hasRole(RoleRegistry::SUPER_ADMINISTRATOR)
            && static::query()->role(RoleRegistry::SUPER_ADMINISTRATOR)->count() <= 1) {
            throw new FinalSuperAdministratorException;
        }

        return parent::delete();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
