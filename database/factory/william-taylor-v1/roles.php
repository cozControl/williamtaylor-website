<?php

return [
    'schema_version' => 1,
    'factory_version' => 'william-taylor-factory-v1',
    'roles' => [
        'Super Administrator' => 'registry',
        'CMS Manager' => 'registry',
        'Inventory Manager' => ['admin.access'],
    ],
];