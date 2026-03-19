<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\BrandModelController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DiscountTypeController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductOptionItemController;
use App\Http\Controllers\LocalTranslationsController;
use App\Http\Controllers\Sellers\Auth\ForgotPasswordController;
use App\Http\Controllers\Sellers\Auth\LoginController;
use App\Http\Controllers\Sellers\Auth\LogoutController;
use App\Http\Controllers\Sellers\Auth\RegisterController;
use App\Http\Controllers\Sellers\CompanyProductVariantController;
use App\Http\Controllers\Sellers\ProductController;
use App\Http\Controllers\Sellers\ProductVariantController;
use App\Http\Controllers\Sellers\ProfileController;
use App\Http\Controllers\Sellers\SessionController;
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
        Route::group(['prefix' => 'local-translations'], static function () {
            Route::get('languages/list', [LocalTranslationsController::class, 'getLanguages'])
                ->withoutMiddleware(CompanyAdminMiddleware::class)
                ->withoutMiddleware(['auth:company_admin']);
            Route::get('{lang}', [LocalTranslationsController::class, 'getTranslations'])
                ->withoutMiddleware(CompanyAdminMiddleware::class)
                ->withoutMiddleware(['auth:company_admin']);
        });

        // Profile routes
        Route::group(['prefix' => 'profile'], static function () {
            Route::get('check-logged-in', [ProfileController::class, 'checkLoggedIn']);
            Route::post('change-password', [ProfileController::class, 'changePassword'])
                ->withoutMiddleware(RouteLogMiddleware::class)
                ->withoutMiddleware(CompanyAdminMiddleware::class);
            Route::get('me', [ProfileController::class, 'showProfile']);
            Route::post('update', [ProfileController::class, 'updateProfile']);

            Route::group(['prefix' => 'sessions'], static function () {
                Route::get('load', [SessionController::class, 'index']);
                Route::post('{tokenId}/logout', [SessionController::class, 'logoutSession']);
            });
        });

        // Logout routes
        Route::post('logout', [LogoutController::class, 'logout'])
            ->withoutMiddleware(CompanyAdminMiddleware::class);
        Route::post('logout-all', [LogoutController::class, 'logoutAll'])
            ->withoutMiddleware(CompanyAdminMiddleware::class);

        // Routes that require company verification
        Route::group(['middleware' => [CompanyVerifiedMiddleware::class]], static function () {
            Route::group(['prefix' => 'products'], static function () {
                Route::get('load', [ProductController::class, 'index']);
                Route::get('list', [ProductController::class, 'list']);
                Route::get('show/{product}', [ProductController::class, 'show']);
                Route::post('add', [ProductController::class, 'store']);
                Route::post('update/{product}', [ProductController::class, 'update']);
                Route::delete('delete/{product}', [ProductController::class, 'destroy']);
                Route::delete('delete-hover-image/{product}', [ProductController::class, 'deleteHoverImage']);
                Route::delete('images/delete/{product}/{mediaId}', [ProductController::class, 'destroyImage']);
                Route::post('change-active-status/{product}', [ProductController::class, 'changeActiveStatus']);
            });

            Route::group(['prefix' => 'product-variants'], static function () {
                Route::get('list', [ProductVariantController::class, 'list']);
                Route::get('search-catalog', [ProductVariantController::class, 'searchCatalog']);
                Route::post('add/{product}', [ProductVariantController::class, 'store']);
                Route::post('update/{productVariant}', [ProductVariantController::class, 'update']);
                Route::delete('delete/{productVariant}', [ProductVariantController::class, 'destroy']);
                Route::delete('delete-hover-image/{productVariant}', [ProductVariantController::class, 'deleteHoverImage']);
                Route::delete('images/delete/{productVariant}/{mediaId}', [ProductVariantController::class, 'destroyImage']);
            });

            Route::group(['prefix' => 'company-product-variants'], static function () {
                Route::get('load', [CompanyProductVariantController::class, 'index']);
                Route::get('show/{companyProductVariant}', [CompanyProductVariantController::class, 'show']);
                Route::post('attach/{productVariant}', [CompanyProductVariantController::class, 'attach']);
                Route::post('update/{companyProductVariant}', [CompanyProductVariantController::class, 'update']);
                Route::delete('detach/{companyProductVariant}', [CompanyProductVariantController::class, 'detach']);
                Route::post('change-active-status/{companyProductVariant}', [CompanyProductVariantController::class, 'changeActiveStatus']);
                Route::post('images/add/{companyProductVariant}', [CompanyProductVariantController::class, 'addImages']);
                Route::delete('images/delete/{companyProductVariant}/{mediaId}', [CompanyProductVariantController::class, 'deleteImage']);

                Route::group(['prefix' => '{companyProductVariant}/approval-status'], static function () {
                    Route::get('history', [CompanyProductVariantController::class, 'approvalStatusHistory']);
                    Route::post('resend', [CompanyProductVariantController::class, 'approvalStatusResend']);
                });
            });

            Route::group(['prefix' => 'countries'], static function () {
                Route::get('list', [CountryController::class, 'list'])
                    ->withoutMiddleware(['auth:company_admin'])
                    ->withoutMiddleware([CompanyAdminMiddleware::class])
                    ->withoutMiddleware([CompanyVerifiedMiddleware::class]);

                Route::group(['prefix' => '{country}/cities'], static function () {
                    Route::get('list', [CityController::class, 'list'])
                        ->withoutMiddleware(['auth:company_admin'])
                        ->withoutMiddleware([CompanyAdminMiddleware::class])
                        ->withoutMiddleware([CompanyVerifiedMiddleware::class]);
                });
            });

            Route::group(['prefix' => 'brands'], static function () {
                Route::get('list', [BrandController::class, 'list']);

                Route::group(['prefix' => '{brand}/models'], static function () {
                    Route::get('list', [BrandModelController::class, 'list']);
                });
            });

            Route::group(['prefix' => 'product-options'], static function () {
                Route::get('list', [ProductOptionController::class, 'list']);

                Route::group(['prefix' => '{productOption}/items'], static function () {
                    Route::get('list', [ProductOptionItemController::class, 'list']);
                });
            });

            Route::group(['prefix' => 'categories'], static function () {
                Route::get('list', [CategoryController::class, 'list']);

                Route::group(['prefix' => '{category}/subs'], static function () {
                    Route::get('list', [CategoryController::class, 'listSubs']);
                });
            });

            Route::group(['prefix' => 'discount-types'], static function () {
                Route::get('list', [DiscountTypeController::class, 'list']);
            });
        });
    });
});
