<?php

return [
    'enabled'        => env('WOLT_ENABLED', false),
    'merchant_id'    => env('WOLT_MERCHANT_ID'),
    'api_key'        => env('WOLT_API_KEY'),
    'client_secret'  => env('WOLT_CLIENT_SECRET'),
    'base_url'       => env('WOLT_BASE_URL', 'https://daas-public-api.wolt.com'),
    'support_phone'  => env('WOLT_SUPPORT_PHONE'),
    'support_email'  => env('WOLT_SUPPORT_EMAIL', 'support@carfy.az'),
    'max_weight_gram'=> 20000,
    'timeout'        => 5,
    'fee_cache_ttl'  => 900, // 15 minutes
    'fallback_fee'   => env('WOLT_FALLBACK_FEE', 300), // cents — Wolt unavailable olduqda
    'sedan_note'     => 'Məhsul maşınla daşınmalıdır və böyük həcmli bağlamadır.',
];
