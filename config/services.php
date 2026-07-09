<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // Web (Socialite) — Web client_id acts as redirect client_id / web aud
        // and as the native serverClientId, so ID tokens are issued for it too.
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),        // https://<api-domain>/auth/google/callback

        // Additional allowed audiences when verifying native ID tokens, in case
        // the token is minted for a platform client id instead of the web one.
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
    ],

    'apple' => [
        // Web (SocialiteProviders) — Services ID acts as client_id / web aud
        'client_id' => env('APPLE_CLIENT_ID'),         // Services ID, e.g. com.carfy.web
        'client_secret' => env('APPLE_CLIENT_SECRET'), // Generated ES256 JWT (see AppleService::generateClientSecret)
        'redirect' => env('APPLE_REDIRECT_URL'),        // https://<api-domain>/auth/apple/callback

        // Shared credentials for client-secret generation and native token verification
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),       // Contents of the .p8 key (with header/footer)
        'bundle_id' => env('APPLE_BUNDLE_ID'),           // Native app aud, e.g. com.carfy.app
    ],

    'c1' => [
        'url' => env('C1_URL'),
    ],

    // Test-only referral/bonus mock data seeder (guarded by env key + active mode)
    'referral_test_seed' => [
        'enabled' => env('REFERRAL_TEST_SEED_ENABLED', false),
        'key'     => env('REFERRAL_TEST_SEED_KEY'),
    ],
];
