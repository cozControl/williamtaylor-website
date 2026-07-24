<?php

namespace App\Livewire\Admin\Access;

use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Actions\RemoveRoleFromUser;
use App\Domain\Identity\Data\EffectiveAccessPreview;
use App\Domain\Identity\Data\EffectiveUserAccess;
use App\Domain\Identity\Exceptions\FinalSuperAdministratorException;
use App\Domain\Identity\Exceptions\SelfLockoutException;
use App\Domain\Identity\Queries\EffectiveUserAccessQuery;
use App\Domain\Identity\Support\PermissionMetadata;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleMetadata;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class UserAccessDetail extends Component
{
    public int $userId;

    public string $operation = '';

    public string $selectedRole = '';

    public string $reason = '';

    public bool $confirmed = false;

    /** @var array<string, mixed> */
    public array $preview = [];

    public string $previewFingerprint = '';

    public string $feedback = '';

    public string $feedbackType = '';

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function target(): User
    {
        return User::query()->with(['roles.permissions', 'permissions'])->findOrFail($this->userId);
    }

    #[Computed]
    public function access(): EffectiveUserAccess
    {
        return app(EffectiveUserAccessQuery::class)->for($this->target());
    }

    public function previewChange(): void
    {
        $this->feedback = '';
        $this->authorizeMutation();
        $validated = $this->validate($this->rules());
        $preview = $this->authoritativePreview($validated['operation'], $validated['selectedRole']);

        $this->storePreview($preview);
        $this->confirmed = false;
    }

    public function applyChange(AssignRoleToUser $assign, RemoveRoleFromUser $remove): void
    {
        $this->feedback = '';
        $this->authorizeMutation();
        $validated = $this->validate([
            ...$this->rules(),
            'confirmed' => ['accepted'],
        ]);

        $preview = $this->authoritativePreview($validated['operation'], $validated['selectedRole']);
        if ($this->previewFingerprint === '' || ! hash_equals($this->previewFingerprint, $preview->fingerprint)) {
            $this->storePreview($preview);
            $this->confirmed = false;
            $this->feedbackType = 'error';
            $this->feedback = 'Access changed after this preview was prepared. Review the updated access changes and confirm again.';
            $this->dispatch('access-preview-stale');

            return;
        }

        try {
            if ($validated['operation'] === 'assign') {
                $assign->handle(auth()->user(), $this->target(), $validated['selectedRole'], $validated['reason']);
            } else {
                $remove->handle(auth()->user(), $this->target(), $validated['selectedRole'], $validated['reason']);
            }
        } catch (FinalSuperAdministratorException|SelfLockoutException) {
            $this->feedbackType = 'error';
            $this->feedback = 'This change was blocked because it would violate an administrator safety safeguard.';

            return;
        }

        unset($this->target, $this->access);
        $this->feedbackType = 'success';
        $this->feedback = 'Access was updated and the audit record was committed.';
        $this->reset(['operation', 'selectedRole', 'reason', 'confirmed', 'preview', 'previewFingerprint']);
    }

    private function authoritativePreview(string $operation, string $selectedRole): EffectiveAccessPreview
    {
        unset($this->target, $this->access);
        $target = User::query()->with(['roles.permissions', 'permissions'])->findOrFail($this->userId);
        $currentRoles = $target->getRoleNames()->all();
        $proposedRoles = $operation === 'assign'
            ? array_values(array_unique([...$currentRoles, $selectedRole]))
            : array_values(array_diff($currentRoles, [$selectedRole]));

        return app(EffectiveUserAccessQuery::class)->preview($target, $proposedRoles);
    }

    private function storePreview(EffectiveAccessPreview $preview): void
    {
        $this->preview = [
            'currentRoles' => $preview->currentRoles,
            'proposedRoles' => $preview->proposedRoles,
            'currentEffectivePermissions' => $preview->currentEffectivePermissions,
            'proposedEffectivePermissions' => $preview->proposedEffectivePermissions,
            'gainedPermissions' => $preview->gainedPermissions,
            'lostPermissions' => $preview->lostPermissions,
            'currentPermissionSources' => $preview->currentPermissionSources,
            'proposedPermissionSources' => $preview->proposedPermissionSources,
            'duplicateGrants' => $preview->duplicateGrants,
            'administrativeAccessChanges' => $preview->administrativeAccessChanges,
            'willHaveAdministrativeAccess' => $preview->willHaveAdministrativeAccess,
            'superAdministratorChanges' => $preview->superAdministratorChanges,
            'willBeSuperAdministrator' => $preview->willBeSuperAdministrator,
            'warnings' => $preview->warnings,
        ];
        $this->previewFingerprint = $preview->fingerprint;
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['assign', 'revoke'])],
            'selectedRole' => ['required', Rule::in(array_keys(RoleRegistry::permissionBundles()))],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    private function authorizeMutation(): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User
            || ! Gate::forUser($actor)->allows(PermissionRegistry::USERS_MANAGE)
            || ! Gate::forUser($actor)->allows(PermissionRegistry::ROLES_MANAGE)) {
            throw new AuthorizationException;
        }
    }

    public function render(): View
    {
        return view('livewire.admin.access.user-access-detail', [
            'roles' => RoleMetadata::all(),
            'permissions' => PermissionMetadata::all(),
        ]);
    }
}
