<?php

return [
    'provider' => env('SMS_PROVIDER', 'pg365'),
    'debug'    => env('SMS_DEBUG_MODE', false),

    'providers' => [
        'pg365' => [
            'base_url'   => env('SMS_PG365_BASE_URL', 'https://api.poctgoyercini.com'),
            'public_key' => env('SMS_PG365_PUBLIC_KEY'),
            'private_key' => env('SMS_PG365_PRIVATE_KEY'),
            'originator'  => env('SMS_PG365_ORIGINATOR'),
        ],
    ],
];
