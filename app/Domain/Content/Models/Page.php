<?php

namespace App\Domain\Content\Models;

use App\Domain\Publishing\Models\PagePublicationState;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $type
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string $template_key
 * @property string|null $current_draft_revision_id
 * @property int $created_by
 * @property int $updated_by
 * @property Carbon|null $archived_at
 * @property ContentRevision|null $currentDraftRevision
 * @property PagePublicationState|null $publicationState
 */
final class Page extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'resource_id')
            ->where('resource_type', self::class);
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'current_draft_revision_id');
    }

    /** @return HasOne<PagePublicationState, $this> */
    public function publicationState(): HasOne
    {
        return $this->hasOne(PagePublicationState::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
