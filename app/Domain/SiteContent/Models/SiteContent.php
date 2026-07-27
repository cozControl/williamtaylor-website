<?php

namespace App\Domain\SiteContent\Models;

use App\Domain\Content\Models\ContentRevision;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $type
 * @property string $key
 * @property string $title
 * @property CarbonInterface|null $archived_at
 * @property string $locale
 * @property string|null $current_draft_revision_id
 * @property int $lock_version
 * @property ContentRevision|null $currentDraftRevision
 * @property SiteContentPublicationState|null $publicationState
 */ final class SiteContent extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'current_draft_revision_id');
    }

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'resource_id')->where('resource_type', self::class);
    }

    /** @return HasOne<SiteContentPublicationState, $this> */
    public function publicationState(): HasOne
    {
        return $this->hasOne(SiteContentPublicationState::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
