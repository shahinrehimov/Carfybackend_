<?php

use App\Http\Controllers\Sellers\Auth\ForgotPasswordController;
use App\Http\Controllers\Sellers\Auth\LoginController;
use App\Http\Controllers\Sellers\Auth\LogoutController;
use App\Http\Controllers\Sellers\Auth\RegisterController;
use App\Http\Controllers\Sellers\ProfileController;
use App\Http\Middleware\CompanyAdminMiddleware;
use App\Http\Middleware\CompanyVerifiedMiddleware;
use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['general_access:sellers', RouteLogMiddleware::class])->prefix('sellers')->group(callback: static function () {

    // Public routes (no authentication required)
    Route::post('register', [RegisterController::class, 'register'])->withoutMiddleware(RouteLogMiddleware::class);
    Route::post('login', [LoginController::class, 'login'])->withoutMiddleware(RouteLogMiddleware::class);
    Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword'])->middleware(['throttle:3,1']);
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword'])->withoutMiddleware(RouteLogMiddleware::class);

    // Authenticated routes
    Route::group(['middleware' => ['auth:company_admin', CompanyAdminMiddleware::class]], static function () {
        // Profile routes
        Route::group(['prefix' => 'profile'], static function () {
            Route::get('check-logged-in', [ProfileController::class, 'checkLoggedIn']);
            Route::post('change-password', [ProfileController::class, 'changePassword'])
                ->withoutMiddleware(RouteLogMiddleware::class)
                ->withoutMiddleware(CompanyAdminMiddleware::class);
            Route::get('me', [ProfileController::class, 'showProfile']);
            Route::post('update', [ProfileController::class, 'updateProfile']);
        });

        // Logout routes
        Route::post('logout', [LogoutController::class, 'logout'])
            ->withoutMiddleware(CompanyAdminMiddleware::class);
        Route::post('logout-all', [LogoutController::class, 'logoutAll'])
            ->withoutMiddleware(CompanyAdminMiddleware::class);

        // Routes that require company verification
        Route::group(['middleware' => [CompanyVerifiedMiddleware::class]], static function () {
            // Future routes for verified companies will go here
            // Example: products, orders, etc.
        });
    });
});
