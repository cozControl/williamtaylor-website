<?php

namespace App\Domain\Homepage\Models;

use App\Domain\Catalogue\Models\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HomepageHero extends Model
{
    use HasUlids;

    public const SINGLETON_ID = '01M1H0ME000000000000000000';

    public const MEDIA_ROLE = 'background';

    public const MOBILE_MEDIA_ROLE = 'background_mobile';

    public const HOT_SALE_MEDIA_ROLES = [
        1 => 'hot_sale_tile_1',
        2 => 'hot_sale_tile_2',
        3 => 'hot_sale_tile_3',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, mixed> */
    public static function clientStoriesDefaults(): array
    {
        return ['client_stories_managed' => false, 'client_stories_eyebrow' => 'Client Stories', 'client_stories_heading' => 'What They Say'];
    }

    public const HANDBAGS_MEDIA_ROLES = [1 => 'handbags_hero_1', 2 => 'handbags_hero_2'];

    /** @return array<string, mixed> */
    public static function handbagsDefaults(): array
    {
        return ['handbags_managed' => false, 'handbags_eyebrow' => 'For Her', 'handbags_heading' => "Women's Handbags", 'handbags_cta_label' => 'View All', 'handbags_hero_eyebrow' => 'New Collection', 'handbags_hero_heading' => 'Crafted for Her', 'handbags_hero_copy' => 'From totes to clutches — each piece handcrafted in our Dar es Salaam atelier.', 'handbags_hero_cta_label' => 'Shop the Collection', 'handbags_collection_id' => null];
    }

    /** @return array<string, mixed> */
    public static function deliveryDefaults(): array
    {
        return ['delivery_managed' => false, 'delivery_eyebrow' => 'Dar es Salaam', 'delivery_heading' => 'Complimentary Delivery in Dar es Salaam', 'delivery_cta_label' => 'Shop with Confidence', 'delivery_destination' => 'contact_email'];
    }

    /** @return array<string, mixed> */
    public static function summerEditDefaults(): array
    {
        return ['summer_edit_managed' => false, 'summer_edit_eyebrow' => 'Summer 2026', 'summer_edit_heading' => 'The Summer Edit', 'summer_edit_copy_prefix' => 'Up to', 'summer_edit_highlight' => '30% Off', 'summer_edit_copy' => 'selected styles. An invitation to acquire curated pieces at exceptional value.', 'summer_edit_cta_label' => 'Shop the Edit', 'summer_edit_collection_id' => null];
    }

    protected function casts(): array
    {
        return [
            'scroll_indicator_enabled' => 'boolean',
            'hot_sale_managed' => 'boolean',
            'future_style_managed' => 'boolean',
            'limited_edition_managed' => 'boolean',
            'explore_collections_managed' => 'boolean',
            'summer_edit_managed' => 'boolean',
            'delivery_managed' => 'boolean',
            'handbags_managed' => 'boolean',
            'client_stories_managed' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Collection, $this> */
    public function newArrivalsCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'new_arrivals_collection_id');
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

    /** @return array<string, string> */
    public static function newArrivalsDefaults(): array
    {
        return [
            'new_arrivals_eyebrow' => 'Just Arrived',
            'new_arrivals_heading' => 'New Arrivals',
            'new_arrivals_cta_label' => 'View All',
        ];
    }

    /** @return array<string, string|bool> */
    public static function hotSaleDefaults(): array
    {
        return [
            'hot_sale_managed' => false,
            'hot_sale_eyebrow' => 'Limited Time',
            'hot_sale_heading' => "William's Hot Sale",
            'hot_sale_tile_1_title' => 'The Atelier Edit',
            'hot_sale_tile_1_copy' => 'Statement pieces from the house, hand-finished in Dar es Salaam.',
            'hot_sale_tile_1_cta_label' => 'Discover',
            'hot_sale_tile_1_destination' => 'shop_newest',
            'hot_sale_tile_2_title' => 'The Shopping Experience',
            'hot_sale_tile_2_copy' => 'Carry the collection home in signature William Taylor style.',
            'hot_sale_tile_2_cta_label' => 'Discover',
            'hot_sale_tile_2_destination' => 'collections',
            'hot_sale_tile_3_title' => 'The Signature Bag',
            'hot_sale_tile_3_copy' => 'Oxblood and gold — the William Taylor hallmark, carried worldwide.',
            'hot_sale_tile_3_cta_label' => 'Discover',
            'hot_sale_tile_3_destination' => 'shop',
        ];
    }

    /** @return array<string, string|bool|null> */
    public static function futureStyleDefaults(): array
    {
        return [
            'future_style_managed' => false,
            'future_style_eyebrow' => 'Exclusive Access',
            'future_style_heading' => 'The Future of Style',
            'future_style_intro' => 'Reserve exclusive pieces before they launch. Limited quantities. Reserve yours today.',
            'future_style_cta_label' => 'View All Pre-Orders',
            'future_style_campaign_1_id' => null,
            'future_style_campaign_2_id' => null,
        ];
    }

    /** @return array<string, string|bool|null> */
    public static function limitedEditionDefaults(): array
    {
        return [
            'limited_edition_managed' => false,
            'limited_edition_eyebrow' => 'Exclusive',
            'limited_edition_heading' => 'LIMITED EDITION',
            'limited_edition_cta_label' => 'View All Limited Editions',
            'limited_edition_campaign_1_id' => null,
            'limited_edition_campaign_2_id' => null,
            'limited_edition_campaign_3_id' => null,
        ];
    }

    /** @return array<string, string|bool|null> */
    public static function exploreCollectionsDefaults(): array
    {
        return [
            'explore_collections_managed' => false,
            'explore_collections_eyebrow' => 'Shop By Category',
            'explore_collections_heading' => 'Explore the Collection',
            'explore_collection_1_id' => null,
            'explore_collection_2_id' => null,
            'explore_collection_3_id' => null,
        ];
    }
}
