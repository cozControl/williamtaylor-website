<?php

namespace App\Domain\Publishing\Models;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PagePublicationTransition extends Model
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

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<PagePublicationState, $this> */
    public function publicationState(): BelongsTo
    {
        return $this->belongsTo(PagePublicationState::class);
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
        self::updating(fn () => throw new LogicException('Publication transitions are immutable.'));
        self::deleting(fn () => throw new LogicException('Publication transitions cannot be deleted.'));
    }
}
