<?php

namespace App\Domain\Homepage\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class HomepageHero extends Model
{
    use HasUlids;

    public const SINGLETON_ID = '01M1H0ME000000000000000000';

    public const MEDIA_ROLE = 'background';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scroll_indicator_enabled' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'eyebrow' => 'Tanzania · 2026 Collection',
            'title' => 'William Taylor',
            'subtitle' => 'Contemporary Menswear',
            'primary_cta_label' => 'Shop New Arrivals',
            'primary_cta_destination' => 'new_arrivals',
            'secondary_cta_label' => 'Explore Collections',
            'secondary_cta_destination' => 'collections',
            'scroll_indicator_enabled' => true,
        ];
    }
}
