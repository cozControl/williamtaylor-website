<?php

namespace App\Domain\Publishing\Models;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Enums\CandidateState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $page_id
 * @property string|null $candidate_revision_id
 * @property CandidateState|null $candidate_state
 * @property string|null $current_public_revision_id
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $scheduled_by
 * @property CarbonImmutable|null $scheduled_for
 * @property int $state_version
 * @property CarbonImmutable|null $last_transition_at
 */ final class PagePublicationState extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'candidate_state' => CandidateState::class,
            'approved_at' => 'immutable_datetime',
            'scheduled_for' => 'immutable_datetime',
            'last_transition_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function candidateRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'candidate_revision_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function currentPublicRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'current_public_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /** @return HasMany<PagePublicationTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(PagePublicationTransition::class, 'publication_state_id');
    }
}
