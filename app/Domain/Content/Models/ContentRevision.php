<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $resource_type
 * @property string $resource_id
 * @property int $revision_number
 * @property int $schema_version
 * @property array<string, mixed> $payload
 * @property string $checksum
 * @property string $sanitizer_version
 * @property string|null $change_summary
 * @property int $created_by
 * @property Carbon $created_at
 */
final class ContentRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Content revisions are immutable.'));
        self::deleting(fn () => throw new LogicException('Content revisions cannot be deleted.'));
    }
}
