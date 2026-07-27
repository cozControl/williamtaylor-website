<?php

namespace App\Domain\SiteContent\Models;

use App\Domain\Content\Models\ContentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SiteContentPublicationTransition extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_datetime', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SiteContent, $this> */
    public function siteContent(): BelongsTo
    {
        return $this->belongsTo(SiteContent::class);
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Site Content transitions are immutable.'));
        self::deleting(fn () => throw new LogicException('Site Content transitions cannot be deleted.'));
    }
}
