@php($asset=isset($section['data']['desktop_media']['asset_id'])?$media->get($section['data']['desktop_media']['asset_id']):null)
<section class="preview-section preview-hero is-{{ $section['data']['variant'] }}">
    @if($asset)<img src="{{ app(\App\Domain\Media\Contracts\MediaProvider::class)->deliveryUrl($asset->provider_public_id,$asset->resource_type->value,'hero_desktop',$asset->focal_x,$asset->focal_y) }}" alt="{{ $section['data']['desktop_media']['decorative'] ? '' : ($section['data']['desktop_media']['alt_override'] ?: $asset->default_alt_text) }}">@endif
    <div>@if($section['data']['eyebrow'])<p>{{ $section['data']['eyebrow'] }}</p>@endif<h1>{{ $section['data']['heading'] }}</h1><p>{{ $section['data']['copy'] }}</p></div>
</section>
