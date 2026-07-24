<?php

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class MediaAssetVersion extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['provider_metadata' => 'array', 'is_current' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Media versions are immutable.'));
        self::deleting(fn () => throw new LogicException('Media versions are immutable.'));
    }
}
