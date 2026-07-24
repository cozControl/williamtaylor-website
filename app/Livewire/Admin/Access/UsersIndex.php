<?php

namespace App\Livewire\Admin\Access;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class UsersIndex extends Component
{
    use WithPagination;

    private const SORTS = ['name', 'email', 'created_at'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $verification = '';

    #[Url(as: 'admin_access', except: '')]
    public string $administrativeAccess = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'verification', 'administrativeAccess', 'sort', 'direction'], true)) {
            $this->resetPage();
        }
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = mb_substr(trim($this->search), 0, 100);
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'name';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'])
            ->with(['roles:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
                $query->where(function (Builder $searchQuery) use ($literal): void {
                    $searchQuery
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$literal}%"])
                        ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", ["%{$literal}%"]);
                });
            })
            ->when(RoleRegistry::contains($this->role), fn (Builder $query) => $query->role($this->role))
            ->when($this->verification === 'verified', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($this->verification === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when(in_array($this->administrativeAccess, ['has', 'missing'], true), function (Builder $query): void {
                $adminRoles = collect(RoleRegistry::permissionBundles())
                    ->filter(fn (array $permissions): bool => in_array(PermissionRegistry::ADMIN_ACCESS, $permissions, true))
                    ->keys()
                    ->all();
                $constraint = function (Builder $accessQuery) use ($adminRoles): void {
                    $accessQuery
                        ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', $adminRoles))
                        ->orWhereHas('permissions', fn (Builder $permissionQuery) => $permissionQuery->where('name', PermissionRegistry::ADMIN_ACCESS));
                };
                $this->administrativeAccess === 'has'
                    ? $query->where($constraint)
                    : $query->whereNot($constraint);
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function render(): View
    {
        return view('livewire.admin.access.users-index', [
            'registeredRoles' => array_keys(RoleRegistry::permissionBundles()),
        ]);
    }
}
