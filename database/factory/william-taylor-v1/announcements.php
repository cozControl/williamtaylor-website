<?php

return [
    'schema_version' => 2,
    'factory_version' => 'william-taylor-factory-v1',
    'resources' => [[
        'type' => 'announcement',
        'key' => 'factory-free-shipping',
        'title' => 'Free shipping and express delivery',
        'source' => 'resources/views/frontend/partials/announcement.blade.php static fallback',
        'payload' => [
            'message' => 'Free Shipping on Orders Over TZS 500,000 | Express Delivery in Dar es Salaam',
            'cta_label' => '', 'cta' => null, 'variant' => 'neutral', 'dismissible' => true,
            'priority' => 50, 'accessibility_label' => 'Close announcement',
            'effective_from' => null, 'effective_until' => null,
        ],
    ]],
];