<?php

return [
    'version' => 'william-taylor-factory-v1',
    'manifest_path' => database_path('factory/william-taylor-v1'),
    'seed_enabled' => env('FACTORY_SEED_ENABLED', false),
    'users' => [
        'administrator' => ['name' => env('FACTORY_ADMIN_NAME', 'Administrator'), 'email' => env('FACTORY_ADMIN_EMAIL'), 'password' => env('FACTORY_ADMIN_PASSWORD'), 'role' => 'Super Administrator'],
        'cms_manager' => ['name' => env('FACTORY_CMS_MANAGER_NAME', 'CMS Manager'), 'email' => env('FACTORY_CMS_MANAGER_EMAIL'), 'password' => env('FACTORY_CMS_MANAGER_PASSWORD'), 'role' => 'CMS Manager'],
        'inventory_manager' => ['name' => env('FACTORY_INVENTORY_MANAGER_NAME', 'Inventory Manager'), 'email' => env('FACTORY_INVENTORY_MANAGER_EMAIL'), 'password' => env('FACTORY_INVENTORY_MANAGER_PASSWORD'), 'role' => 'Inventory Manager'],
    ],
    'remote_video_hosts' => ['media.base44.com', 'res.cloudinary.com'],
    'full_reset_phrase' => 'RESET WILLIAM TAYLOR TO FACTORY V1',
];
