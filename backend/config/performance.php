<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance & Caching Settings
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('CACHE_ENABLED', true),
        'default_ttl' => env('CACHE_TTL', 3600), // 1 hour
        'products_ttl' => env('CACHE_PRODUCTS_TTL', 1800), // 30 minutes
        'reports_ttl' => env('CACHE_REPORTS_TTL', 600), // 10 minutes
    ],

    'queue' => [
        'enabled' => env('QUEUE_ENABLED', false),
        'default' => env('QUEUE_CONNECTION', 'database'),
    ],

    'rate_limiting' => [
        'api' => [
            'requests_per_minute' => env('API_RATE_LIMIT', 60),
        ],
        'login' => [
            'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => env('LOGIN_DECAY_MINUTES', 1),
        ],
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    'image_optimization' => [
        'enabled' => env('IMAGE_OPTIMIZATION_ENABLED', true),
        'max_width' => 1920,
        'max_height' => 1920,
        'quality' => 85,
    ],
];
