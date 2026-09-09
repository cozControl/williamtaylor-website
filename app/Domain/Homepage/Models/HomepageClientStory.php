<?php

namespace App\Domain\Homepage\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class HomepageClientStory extends Model
{
    use HasUlids;

    public const CAPACITY = 3;

    public const MEDIA_ROLE = 'portrait';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'position' => 'integer'];
    }
}
