<?php

return [
    'enabled' => env('FLOOSAK_ENABLED', false),

    'base_url' => env('FLOOSAK_BASE_URL', 'https://staging.fintech-expert.net'),

    'merchant_phone' => env('FLOOSAK_MERCHANT_PHONE'),
    'merchant_password' => env('FLOOSAK_MERCHANT_PASSWORD'),
    'source_wallet_id' => env('FLOOSAK_SOURCE_WALLET_ID'),

    'timeout_seconds' => (int) env('FLOOSAK_TIMEOUT_SECONDS', 15),
    'token_cache_ttl_seconds' => (int) env('FLOOSAK_TOKEN_CACHE_TTL_SECONDS', 3300),

    'reconciliation' => [
        'limit' => (int) env('FLOOSAK_RECONCILE_LIMIT', 100),
        'max_attempts' => (int) env('FLOOSAK_RECONCILE_MAX_ATTEMPTS', 12),
    ],

    'bypass' => env('FLOOSAK_BYPASS', false),
];
