<?php

use App\Http\Controllers\App\Auth\EmailVerificationController;
use App\Http\Controllers\App\Auth\GoogleController;
use App\Http\Controllers\App\OrderController;
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

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('auth.google.callback')
    ->withoutMiddleware(VerifyCsrfToken::class);
