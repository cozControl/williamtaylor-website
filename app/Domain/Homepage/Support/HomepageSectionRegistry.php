<?php

namespace App\Domain\Homepage\Support;

final class HomepageSectionRegistry
{
    /** @return array<string, array{title: string, position: int, default_visible: bool, edit_route: string, marker: string, heading: string, heading_selector: string, boundary: string}> */
    public static function all(): array
    {
        $sections = [
            'hero' => ['Homepage Hero', 'hero', 'data-homepage-hero', '', 'main h1', 'section'],
            'new-arrivals' => ['New Arrivals', 'new-arrivals', 'data-homepage-new-arrivals', 'New Arrivals', 'main h2', 'section'],
            'hot-sale' => ["William's Hot Sale", 'hot-sale', 'data-homepage-hot-sale', "William's Hot Sale", 'main h2', 'section'],
            'future-style' => ['The Future of Style', 'future-style', 'data-homepage-future-style', 'The Future of Style', 'main h2', 'section'],
            'limited-edition' => ['Limited Edition', 'limited-edition', 'data-homepage-limited-edition', 'LIMITED EDITION', 'main h2', 'section'],
            'explore-collections' => ['Explore the Collection', 'explore-collections', 'data-homepage-explore-collections', 'Explore the Collection', 'main h2', 'section'],
            'summer-edit' => ['The Summer Edit', 'summer-edit', 'data-homepage-summer-edit', 'The Summer Edit', 'main h2', '.relative.z-10.px-4'],
            'delivery' => ['Complimentary Delivery', 'delivery', 'data-homepage-delivery', 'Complimentary Delivery in Dar es Salaam', 'main h3', '.relative.z-10.bg-wt-cream'],
            'handbags' => ["Women's Handbags", 'handbags', 'data-homepage-handbags', "Women's Handbags", 'main h2', 'section'],
            'client-stories' => ['Client Stories', 'client-stories', 'data-homepage-client-stories', 'What They Say', 'main h2', 'section'],
        ];
        $result = [];
        foreach ($sections as $key => [$title, $route, $marker, $heading, $headingSelector, $boundary]) {
            $result[$key] = ['title' => $title, 'position' => count($result) + 1, 'default_visible' => true, 'edit_route' => 'admin.homepage.'.$route.'.edit', 'marker' => $marker, 'heading' => $heading, 'heading_selector' => $headingSelector, 'boundary' => $boundary];
        }

        return $result;
    }

    /** @return array{title: string, position: int, default_visible: bool, edit_route: string, marker: string, heading: string, heading_selector: string, boundary: string} */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException('Unknown Homepage section.');
    }
}
