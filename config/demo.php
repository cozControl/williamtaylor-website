<?php

return [
    'enabled' => (bool) env('DEMO_MODE', false),
    'allowed_environments' => ['demo', 'testing'],
    'order_currency' => env('DEMO_ORDER_CURRENCY', 'TZS'),
];
