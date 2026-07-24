<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AuditRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_roles' => 'array',
            'effective_permissions' => 'array',
            'before_summary' => 'array',
            'after_summary' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Audit records are append-only.'));
        self::deleting(fn () => throw new LogicException('Audit records are append-only.'));
    }
}
