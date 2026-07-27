<?php

return [
    'schema_version' => 2,
    'factory_version' => 'william-taylor-factory-v1',
    'type' => 'primary_navigation',
    'key' => 'primary_navigation',
    'title' => 'Primary navigation',
    'source' => 'resources/views/frontend/partials/header.blade.php desktop navigation fallback',
    'payload' => ['items' => [
        ['key' => 'shop', 'label' => 'Shop', 'link' => ['type' => 'internal_path', 'value' => '/shop'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
        ['key' => 'collections', 'label' => 'Collections', 'link' => ['type' => 'internal_path', 'value' => '/collections'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
        ['key' => 'new-arrivals', 'label' => 'New Arrivals', 'link' => ['type' => 'internal_path', 'value' => '/shop?sort=newest'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
        ['key' => 'pre-order', 'label' => 'Pre-Order', 'link' => ['type' => 'internal_path', 'value' => '/pre-order'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
        ['key' => 'limited-edition', 'label' => 'Limited Edition', 'link' => ['type' => 'internal_path', 'value' => '/limited-edition'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
    ]],
];