<?php

return [
    'schema_version' => 2,
    'factory_version' => 'william-taylor-factory-v1',
    'type' => 'footer_navigation',
    'key' => 'footer_navigation',
    'title' => 'Footer navigation',
    'source' => 'resources/views/frontend/partials/footer.blade.php navigation fallback',
    'payload' => ['groups' => [
        ['key' => 'company', 'label' => 'Shop', 'links' => [
            ['key' => 'mens-wear', 'label' => "Men's Wear", 'link' => ['type' => 'internal_path', 'value' => '/website/html/mens-wear.html'], 'new_tab' => false],
            ['key' => 'unisex', 'label' => 'Unisex', 'link' => ['type' => 'internal_path', 'value' => '/website/html/unisex.html'], 'new_tab' => false],
            ['key' => 'accessories', 'label' => 'Accessories', 'link' => ['type' => 'internal_path', 'value' => '/website/html/accessories.html'], 'new_tab' => false],
            ['key' => 'limited-edition', 'label' => 'Limited Edition', 'link' => ['type' => 'internal_path', 'value' => '/limited-edition'], 'new_tab' => false],
            ['key' => 'pre-order', 'label' => 'Pre-Order', 'link' => ['type' => 'internal_path', 'value' => '/pre-order'], 'new_tab' => false],
            ['key' => 'new-arrivals', 'label' => 'New Arrivals', 'link' => ['type' => 'internal_path', 'value' => '/shop?sort=newest'], 'new_tab' => false],
        ]],
        ['key' => 'customer_care', 'label' => 'Atelier', 'links' => [
            ['key' => 'about-us', 'label' => 'About Us', 'link' => ['type' => 'internal_path', 'value' => '/website/html/about.html'], 'new_tab' => false],
            ['key' => 'membership', 'label' => 'Membership', 'link' => ['type' => 'internal_path', 'value' => '/website/html/membership.html'], 'new_tab' => false],
            ['key' => 'gift-cards', 'label' => 'Gift Cards', 'link' => ['type' => 'internal_path', 'value' => '/gift-cards'], 'new_tab' => false],
            ['key' => 'my-account', 'label' => 'My Account', 'link' => ['type' => 'internal_path', 'value' => '/login'], 'new_tab' => false],
            ['key' => 'track-order', 'label' => 'Track Order', 'link' => ['type' => 'internal_path', 'value' => '/website/html/track-order.html'], 'new_tab' => false],
        ]],
        ['key' => 'legal', 'label' => 'Support', 'links' => [
            ['key' => 'contact-us', 'label' => 'Contact Us', 'link' => ['type' => 'internal_path', 'value' => '/website/html/contact.html'], 'new_tab' => false],
            ['key' => 'shipping', 'label' => 'Shipping', 'link' => ['type' => 'internal_path', 'value' => '/website/html/shipping.html'], 'new_tab' => false],
            ['key' => 'returns', 'label' => 'Returns', 'link' => ['type' => 'internal_path', 'value' => '/website/html/returns.html'], 'new_tab' => false],
            ['key' => 'size-guide', 'label' => 'Size Guide', 'link' => ['type' => 'internal_path', 'value' => '/website/html/size-guide.html'], 'new_tab' => false],
            ['key' => 'faq', 'label' => 'FAQ', 'link' => ['type' => 'internal_path', 'value' => '/website/html/faq.html'], 'new_tab' => false],
        ]],
    ]],
];