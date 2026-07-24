<?php

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Models\AuditRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        string $action,
        Model $resource,
        ?User $actor,
        ?array $before = null,
        ?array $after = null,
        ?string $permission = null,
        ?string $reason = null,
        ?string $correlationId = null,
    ): AuditRecord {
        $request = app()->runningInConsole() ? null : request();
        $roles = $actor?->getRoleNames()->sort()->values()->all() ?? [];
        $permissions = $actor?->getAllPermissions()->pluck('name')->sort()->values()->all() ?? [];

        return AuditRecord::query()->create([
            'id' => (string) Str::ulid(),
            'actor_user_id' => $actor?->getKey(),
            'effective_roles' => $roles,
            'effective_permissions' => $permissions,
            'action' => $action,
            'resource_type' => $resource->getMorphClass(),
            'resource_identifier' => (string) $resource->getKey(),
            'before_summary' => $before,
            'after_summary' => $after,
            'permission' => $permission,
            'reason' => $reason === null ? null : Str::limit($reason, 2000, ''),
            'request_id' => $request?->headers->get('X-Request-ID'),
            'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 512, ''),
            'job_name' => app()->runningInConsole() ? ($_SERVER['argv'][1] ?? 'console') : null,
            'correlation_id' => $correlationId ?? (string) Str::ulid(),
            'created_at' => now('UTC'),
        ]);
    }
}
