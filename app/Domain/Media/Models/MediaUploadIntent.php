<?php

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $actor_id
 * @property string $provider
 * @property string $public_id
 * @property string $resource_type
 * @property string $mime_type
 * @property int $expected_bytes
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class MediaUploadIntent extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'expected_bytes' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
