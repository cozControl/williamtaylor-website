<?php

return [
    'enabled' => (bool) env('PUBLIC_SITE_CONTENT_PROJECTION', false),
    'locale' => 'en',
    'projection_version' => 1,
    'cache_ttl_seconds' => 3600,
];
