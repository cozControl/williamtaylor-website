<?php

return [
    'enabled' => (bool) env('SNIPPE_ENABLED', false),
    'base_url' => env('SNIPPE_BASE_URL', 'https://api.snippe.sh'),
    'api_key' => env('SNIPPE_API_KEY'),
    // Only historical hosted Session records use these settings.
    'profile_id' => env('SNIPPE_PROFILE_ID'),
    'webhook_secret' => env('SNIPPE_WEBHOOK_SECRET'),
    'session_expires_in' => (int) env('SNIPPE_SESSION_EXPIRES_IN', 3600),
    'checkout_hosts' => ['snippe.me'],
];
