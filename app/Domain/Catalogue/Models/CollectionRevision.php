<?php

namespace App\Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CollectionRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision_number' => 'integer', 'schema_version' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Collection revisions are immutable.'));
        self::deleting(fn () => throw new LogicException('Collection revisions cannot be deleted.'));
    }
}
