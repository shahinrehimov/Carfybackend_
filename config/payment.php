<?php

use App\Services\Payment\Methods\Bank1PaymentMethod;
use App\Services\Payment\Methods\Bank2PaymentMethod;
use App\Services\Payment\Methods\CashPaymentMethod;
use App\Services\Payment\Methods\FlittPaymentMethod;
use App\Services\Payment\Methods\FpayPaymentMethod;
use App\Services\Payment\Methods\KapitalbankPaymentMethod;
use App\Services\Payment\Methods\KapitalbankInstallmentPaymentMethod;
use App\Services\Payment\Testing\MockPaymentMethod;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Settings
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'AZN',
    'logging_enabled' => env('PAYMENT_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Payment Method Registry
    |--------------------------------------------------------------------------
    */
    'methods' => [
        'cash' => [
            'class' => CashPaymentMethod::class,
            'priority' => 1,
            'enabled' => env('CASH_PAYMENT_ENABLED', true),
            //'display_name' => 'Cash',
            'icon' => 'fa-money-bill',
            'min_amount' => env('CASH_MIN_AMOUNT', 100),
            'max_amount' => env('CASH_MAX_AMOUNT', 1000000),
        ],

        'bank1' => [
            'class' => Bank1PaymentMethod::class,
            'priority' => 2,
            'enabled' => env('BANK1_ENABLED', false),
            //'display_name' => 'Bank 1',
            'api_url' => env('BANK1_API_URL'),
            'verify_url' => env('BANK1_VERIFY_URL'),
            'merchant_id' => env('BANK1_MERCHANT_ID'),
            'secret_key' => env('BANK1_SECRET_KEY'),
            'webhook_secret' => env('BANK1_WEBHOOK_SECRET'),
            'icon' => 'fa-university',
            'min_amount' => env('BANK1_MIN_AMOUNT', 50),
            'max_amount' => env('BANK1_MAX_AMOUNT', 500000),
            'test_mode' => env('BANK1_TEST_MODE', false),
        ],

        'bank2' => [
            'class' => Bank2PaymentMethod::class,
            'priority' => 3,
            'enabled' => env('BANK2_ENABLED', false),
            //'display_name' => 'Bank 2',
            'api_url' => env('BANK2_API_URL'),
            'api_key' => env('BANK2_API_KEY'),
            'merchant_id' => env('BANK2_MERCHANT_ID'),
            'icon' => 'fa-credit-card',
            'min_amount' => env('BANK2_MIN_AMOUNT', 100),
            'max_amount' => env('BANK2_MAX_AMOUNT', 1000000),
            'test_mode' => env('BANK2_TEST_MODE', false),
        ],

        'fpay' => [
            'class' => FpayPaymentMethod::class,
            'priority' => 2,
            'enabled' => env('FPAY_ENABLED', false),
            'api_url' => env('FPAY_API_URL', 'https://ecommerce.fpay.az'),
            'username' => env('FPAY_USERNAME'),
            'auth_key' => env('FPAY_AUTH_KEY'),
            'merchant_name' => env('FPAY_MERCHANT_NAME'),
            'icon' => 'fa-credit-card',
            'min_amount' => env('FPAY_MIN_AMOUNT', 50),
            'max_amount' => env('FPAY_MAX_AMOUNT', 10000000),
            'test_mode' => env('FPAY_TEST_MODE', false),
        ],

        'kapitalbank' => [
            'class' => KapitalbankPaymentMethod::class,
            'priority' => 3,
            'enabled' => env('KAPITALBANK_ENABLED', false),
            'api_url' => env('KAPITALBANK_API_URL', 'https://txpgtst.kapitalbank.az/api'),
            'hpp_url' => env('KAPITALBANK_HPP_URL', 'https://txpgtst.kapitalbank.az/flex'),
            'username' => env('KAPITALBANK_USERNAME'),
            'password' => env('KAPITALBANK_PASSWORD'),
            'language' => env('KAPITALBANK_LANGUAGE', 'az'),
            'title' => env('KAPITALBANK_TITLE', 'Carfy'),
            'icon' => 'fa-credit-card',
            'min_amount' => env('KAPITALBANK_MIN_AMOUNT', 50),
            'max_amount' => env('KAPITALBANK_MAX_AMOUNT', 10000000),
            'test_mode' => env('KAPITALBANK_TEST_MODE', true),
        ],

        'kapitalbank_installment' => [
            'class' => KapitalbankInstallmentPaymentMethod::class,
            'priority' => 4,
            'enabled' => env('KAPITALBANK_INSTALLMENT_ENABLED', false),
            'api_url' => env('KAPITALBANK_API_URL', 'https://txpgtst.kapitalbank.az/api'),
            'hpp_url' => env('KAPITALBANK_HPP_URL', 'https://txpgtst.kapitalbank.az/flex'),
            'username' => env('KAPITALBANK_USERNAME'),
            'password' => env('KAPITALBANK_PASSWORD'),
            'language' => env('KAPITALBANK_LANGUAGE', 'az'),
            'title' => env('KAPITALBANK_TITLE', 'Carfy'),
            'available_months' => [3, 6, 12, 24],
            'icon' => 'fa-credit-card',
            'min_amount' => env('KAPITALBANK_MIN_AMOUNT', 50),
            'max_amount' => env('KAPITALBANK_MAX_AMOUNT', 10000000),
            'test_mode' => env('KAPITALBANK_TEST_MODE', true),
        ],

        'flitt' => [
            'class' => FlittPaymentMethod::class,
            'priority' => 2,
            'enabled' => env('FLITT_ENABLED', false),
            //'display_name' => 'Bank 2',
            'api_url' => env('FLITT_API_URL'),
            'api_key' => env('FLITT_API_KEY'),
            'merchant_id' => env('FLITT_MERCHANT_ID'),
            'icon' => 'fa-credit-card',
            'min_amount' => env('FLITT_MIN_AMOUNT', 100),
            'max_amount' => env('FLITT_MAX_AMOUNT', 1000000),
            'test_mode' => env('FLITT_TEST_MODE', false),
        ],

        'mock' => [
            'class' => MockPaymentMethod::class,
            'priority' => 999,
            'enabled' => env('APP_ENV') === 'testing',
            //'display_name' => 'Mock Payment (Testing)',
            'should_succeed' => true,
            'icon' => 'fa-flask',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Settings
    |--------------------------------------------------------------------------
    */
    'auto_discovery' => [
        'enabled' => env('PAYMENT_AUTO_DISCOVERY', false),
        'scan_paths' => [
            'App\\Services\\Payment\\Methods',
            //'App\\Packages\\PaymentMethods',
        ],
        'cache_enabled' => true,
        'cache_key' => 'payment_methods_discovery',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('PAYMENT_CACHE_ENABLED', false),
        'ttl' => env('PAYMENT_CACHE_TTL', 3600), // an hour
        'key_prefix' => 'payment_method_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Integration
    |--------------------------------------------------------------------------
    */
    'database_config' => [
        'enabled' => env('PAYMENT_DB_CONFIG', false),
        'table' => 'payment_method_configs',
        'override_config' => true, // if true, DB config overrides file config
    ],
];
