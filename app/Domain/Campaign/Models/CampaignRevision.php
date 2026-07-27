<?php

namespace App\Domain\Campaign\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class CampaignRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Campaign revisions are immutable.'));
        self::deleting(fn () => throw new LogicException('Campaign revisions cannot be deleted.'));
    }
}
