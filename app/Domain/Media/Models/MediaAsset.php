<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MediaResourceType $resource_type
 * @property MediaAssetState $state
 * @property AccessibilityClassification $accessibility_classification
 */
final class MediaAsset extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resource_type' => MediaResourceType::class, 'state' => MediaAssetState::class, 'accessibility_classification' => AccessibilityClassification::class, 'tags' => 'array', 'provider_metadata' => 'array', 'is_decorative' => 'boolean', 'confirmed_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime', 'focal_x' => 'decimal:4', 'focal_y' => 'decimal:4'];
    }

    /** @return HasMany<MediaAssetVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(MediaAssetVersion::class);
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
