<?php

use App\Http\Controllers\App\AdvertisementController;
use App\Http\Controllers\App\Auth\EmailVerificationController;
use App\Http\Controllers\App\Auth\GoogleController;
use App\Http\Controllers\App\OrderController;
use App\Http\Controllers\Test\ReferralSeedController;
use App\Http\Controllers\Webhook\WoltWebhookController;
use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return 'Access denied!';
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(RouteLogMiddleware::class)
    ->name('verification.verify');

Route::post('/payment/callback/{paymentMethodName}', [OrderController::class, 'paymentProcessCallback'])
    ->name('payment.callback')
    ->withoutMiddleware(VerifyCsrfToken::class);

Route::get('/payment/fpay-return/{paymentKey}', [OrderController::class, 'fpayReturn'])
    ->name('payment.fpay.return')
    ->withoutMiddleware(VerifyCsrfToken::class);

Route::get('/payment/kapitalbank-return', [OrderController::class, 'kapitalbankReturn'])
    ->name('payment.kapitalbank.return')
    ->withoutMiddleware(VerifyCsrfToken::class);

Route::post('/webhooks/wolt', [WoltWebhookController::class, 'handle'])
    ->name('webhooks.wolt')
    ->withoutMiddleware(VerifyCsrfToken::class);

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('auth.google.callback')
    ->withoutMiddleware(VerifyCsrfToken::class);

Route::get('/ads/{key}/click', [AdvertisementController::class, 'click'])
    ->name('advertisements.click')
    ->middleware(['throttle:30,1'])
    ->withoutMiddleware(VerifyCsrfToken::class);

// Test-only: seed mock referral/bonus data for a user (guarded by env key + active mode)
Route::get('/test/referral-seed', ReferralSeedController::class)
    ->name('test.referral.seed');
