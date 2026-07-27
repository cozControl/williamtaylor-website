<?php

return [
    'global_enabled' => (bool) env('PUBLICATION_ROLLOUT_ENABLED', false),
    'resources' => [
        'site_content' => env('PUBLICATION_ROLLOUT_SITE_CONTENT', 'static'),
        'page' => env('PUBLICATION_ROLLOUT_ABOUT_PAGE', 'static'),
        'product' => 'static',
        'collection' => 'static',
        'campaign' => 'static',
    ],
];
