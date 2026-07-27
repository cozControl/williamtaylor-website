<?php

return [
    'schema_version' => 2,
    'factory_version' => 'william-taylor-factory-v1',
    'type' => 'site_profile',
    'key' => 'site_profile',
    'title' => 'Site settings',
    'source' => 'resources/views/frontend/partials/footer.blade.php and whatsapp-action.blade.php static fallbacks',
    'payload' => [
        'brand' => ['name' => 'William Taylor', 'description' => 'Contemporary menswear designed in Tanzania', 'copyright_holder' => 'William Taylor', 'header_logo_id' => null, 'footer_logo_id' => null],
        'contact' => ['email' => 'info@williamtaylor.co.tz', 'telephone' => '+255656464876', 'whatsapp' => '+255656464876', 'address' => 'Dar Village Mall, Dar Es Salaam, Tanzania', 'business_hours' => '', 'cta_label' => 'Contact us'],
        'social_links' => [
            ['platform' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/williamtaylor'],
            ['platform' => 'facebook', 'label' => 'Facebook', 'url' => 'https://facebook.com'],
            ['platform' => 'x', 'label' => 'X', 'url' => 'https://twitter.com'],
            ['platform' => 'youtube', 'label' => 'YouTube', 'url' => 'https://youtube.com'],
        ],
        'footer' => ['description' => 'Crafted for the Modern Gentleman. Contemporary menswear designed in Tanzania, worn worldwide.', 'copyright' => 'Copyright 2026 William Taylor. All Rights Reserved.', 'newsletter_heading' => 'Join the Inner Circle', 'newsletter_copy' => 'Be the first to discover new collections, private releases, and atelier stories.', 'footer_image_id' => null],
    ],
];