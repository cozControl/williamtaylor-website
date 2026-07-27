<?php

return [
    'schema_version' => 1,
    'factory_version' => 'william-taylor-factory-v1',
    'identities' => [
        'administrator' => ['name_key' => 'administrator', 'role' => 'Super Administrator'],
        'cms_manager' => ['name_key' => 'cms_manager', 'role' => 'CMS Manager'],
        'inventory_manager' => ['name_key' => 'inventory_manager', 'role' => 'Inventory Manager'],
    ],
];