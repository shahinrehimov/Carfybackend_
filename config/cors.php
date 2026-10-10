<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Only our own frontends; reflecting any Origin together with credentials lets any site call the API as the user
    'allowed_origins' => array_values(array_filter(array_unique(array_map(static fn (?string $origin) => rtrim(trim((string) $origin), '/'), [
        env('FRONTEND_URL'),
        env('COMPANY_FRONTEND_URL'),
        env('ADMIN_FRONTEND_URL'),
        ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ])))),

    // Local development servers on any port
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 0,

    'supports_credentials' => true,
];
