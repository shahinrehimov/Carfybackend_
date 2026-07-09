<?php

use App\Http\Controllers\App\AddressController;
use App\Http\Controllers\App\ReferralController;
use App\Http\Controllers\App\ContactController;
use App\Http\Controllers\App\CarLibraryController;
use App\Http\Controllers\App\FaqController;
use App\Http\Controllers\App\UserCarController;
use App\Http\Controllers\App\Auth\AppleController;
use App\Http\Controllers\App\Auth\EmailVerificationController;
use App\Http\Controllers\App\Auth\ForgotPasswordController;
use App\Http\Controllers\App\Auth\GoogleController;
use App\Http\Controllers\App\Auth\LoginController;
use App\Http\Controllers\App\Auth\LogoutController;
use App\Http\Controllers\App\Auth\RegisterWithEmailController;
use App\Http\Controllers\App\SessionController;
use App\Http\Controllers\App\Auth\RegisterWithPhoneController;
use App\Http\Controllers\App\BasketController;
use App\Http\Controllers\App\CategoryController;
use App\Http\Controllers\App\GroupController;
use App\Http\Controllers\App\GuestController;
use App\Http\Controllers\App\MetaController;
use App\Http\Controllers\App\DeviceTokenController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\OrderController;
use App\Http\Controllers\App\PaymentController;
use App\Http\Controllers\App\ProductController;
use App\Http\Controllers\App\ProductReviewController;
use App\Http\Controllers\App\ProductViewController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\ServicesController;
use App\Http\Controllers\App\PageController;
use App\Http\Controllers\App\AdvertisementController;
use App\Http\Controllers\App\SliderController;
use App\Http\Controllers\App\WishlistController;
use App\Http\Controllers\LocalTranslationsController;
use App\Http\Middleware\CheckActiveUserMiddleware;
use App\Http\Middleware\CheckUserVerifiedMiddleware;
use App\Http\Middleware\GuestMiddleware;
use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Support\Facades\Route;

return static function () {
    Route::middleware([RouteLogMiddleware::class, GuestMiddleware::class])
        ->group(static function () {

            Route::group(['prefix' => 'local-translations'], static function () {
                Route::get('languages/list', [LocalTranslationsController::class, 'getLanguages']);
                Route::get('{lang}', [LocalTranslationsController::class, 'getTranslations']);
            });

            Route::group(['prefix' => 'metas'], static function () {
                Route::get('show/{pageKey}', [MetaController::class, 'getByKey']);
            });

            Route::group(['prefix' => 'services'], static function () {
                Route::get('list', [ServicesController::class, 'getServicesList']);
                Route::get('load', [ServicesController::class, 'loadAppServices']);
            });

            Route::group(['prefix' => 'faqs'], static function () {
                Route::get('list', [FaqController::class, 'list']);
            });

            Route::group(['prefix' => 'car-libraries'], static function () {
                Route::get('brands', [CarLibraryController::class, 'brands']);
                Route::get('brands/{brand}/models', [CarLibraryController::class, 'brandModels']);
                Route::get('fuel-types', [CarLibraryController::class, 'fuelTypes']);
                Route::get('transmission-types', [CarLibraryController::class, 'transmissionTypes']);
                Route::get('car-types', [CarLibraryController::class, 'carTypes']);
                Route::get('engine-volumes', [CarLibraryController::class, 'engineVolumes']);
                Route::get('car-colors', [CarLibraryController::class, 'carColors']);
            });

            Route::group(['prefix' => 'categories'], static function () {
                Route::get('list', [CategoryController::class, 'list']);

                Route::group(['prefix' => '{category}/subs'], static function () {
                    Route::get('list', [CategoryController::class, 'listSubs']);
                });
            });

            Route::group(['prefix' => 'products'], static function () {
                Route::get('filters', [ProductController::class, 'getProductsSearchFilters']);
                Route::get('load', [ProductController::class, 'index']);
                Route::get('show/{cpvUuid}', [ProductController::class, 'showByUuid']);
                Route::get('show/{parentCategorySlug}/{subCategorySlug}/{productSlug}', [ProductController::class, 'showBySlug']);
                Route::get('show/{subCategorySlug}/{productSlug}', [ProductController::class, 'showBySlugOnlySubCategory']);
                Route::get('{productVariant}/other-sellers', [ProductController::class, 'otherSellers']);
                Route::post('{companyProductVariant:uuid}/track-view', [ProductViewController::class, 'track'])->middleware(['throttle:30,1']);

                Route::prefix('{companyProductVariant:uuid}/reviews')->group(function () {
                    Route::post('store', [ProductReviewController::class, 'store'])->middleware(['throttle:2,1']);
                    Route::get('load', [ProductReviewController::class, 'load']);
                });
            });

            Route::group(['prefix' => 'groups'], static function () {
                Route::get('list', [GroupController::class, 'list']);
                Route::get('single/{groupName}/products/load', [GroupController::class, 'loadGroupWithProducts']);
                Route::get('bulk/products/load', [GroupController::class, 'loadGroupsWithProducts']);
            });

            Route::group(['prefix' => 'contact'], static function () {
                Route::post('send', [ContactController::class, 'store'])->middleware(['throttle:3,1']);
            });

            // guests
            Route::group(['prefix' => 'guests'], static function () {
                Route::post('generate-identifier', [GuestController::class, 'addGuest'])->withoutMiddleware(GuestMiddleware::class);
            });

            Route::group(['prefix' => 'wishlist'], static function () {
                Route::get('ids', [WishlistController::class, 'ids']);
                Route::get('load', [WishlistController::class, 'load']);
                Route::post('add/{companyProductVariant:uuid}', [WishlistController::class, 'add']);
                Route::post('remove/{companyProductVariant:uuid}', [WishlistController::class, 'remove']);
            });

            Route::group(['prefix' => 'sliders'], static function () {
                Route::get('load/{type}', [SliderController::class, 'loadByType']);
                Route::get('show/{slug}', [SliderController::class, 'getBySlug']);
            });

            Route::group(['prefix' => 'pages'], static function () {
                Route::get('list', [PageController::class, 'list']);
                Route::get('show/{slug}', [PageController::class, 'show']);
            });

            Route::group(['prefix' => 'advertisements'], static function () {
                Route::get('active', [AdvertisementController::class, 'active']);
                Route::get('by-key/{key}', [AdvertisementController::class, 'byKey']);
            });

            // Device token registration is available to guests too, so the app
            // can register its FCM token on launch before the user logs in.
            Route::group(['prefix' => 'device-token'], static function () {
                Route::post('register', [DeviceTokenController::class, 'store']);
                Route::delete('unregister', [DeviceTokenController::class, 'destroy']);
            });

            Route::group(['prefix' => 'basket'], static function () {
                Route::get('ids', [BasketController::class, 'ids']);
                Route::get('load', [BasketController::class, 'load']);
                Route::post('add/{companyProductVariant:uuid}', [BasketController::class, 'add']);
                Route::post('remove/{companyProductVariant:uuid}', [BasketController::class, 'remove']);
                Route::post('increment/{companyProductVariant:uuid}', [BasketController::class, 'increment']);
                Route::post('decrement/{companyProductVariant:uuid}', [BasketController::class, 'decrement']);
                Route::post('remove/{companyProductVariant:uuid}/set/{setItem}', [BasketController::class, 'removeSetItem']);
                Route::post('increment/{companyProductVariant:uuid}/set/{setItem}', [BasketController::class, 'incrementSetItem']);
                Route::post('decrement/{companyProductVariant:uuid}/set/{setItem}', [BasketController::class, 'decrementSetItem']);
            });

            // register
            Route::group(['prefix' => 'register'], static function () {
                Route::group(['prefix' => 'with-email'], static function () {
                    Route::get('check-email', [RegisterWithEmailController::class, 'checkEmail']);
                    Route::post('submit', [RegisterWithEmailController::class, 'register'])->withoutMiddleware(RouteLogMiddleware::class);
                    Route::post('verify-otp/{userUuid}', [EmailVerificationController::class, 'verifyOtp']);
                });

                Route::group(['prefix' => 'with-phone'], static function () {
                    Route::post('send-otp', [RegisterWithPhoneController::class, 'sendOtp'])->middleware(['throttle:2,1']);
                    Route::post('check-otp', [RegisterWithPhoneController::class, 'checkOtp']);
                    Route::post('submit', [RegisterWithPhoneController::class, 'register']);
                });
            });

            // google auth
            Route::get('auth/google', [GoogleController::class, 'redirect']);
            Route::post('auth/google/native', [GoogleController::class, 'nativeLogin'])->withoutMiddleware(RouteLogMiddleware::class);

            // apple auth
            Route::get('auth/apple', [AppleController::class, 'redirect']);
            Route::post('auth/apple/native', [AppleController::class, 'nativeLogin'])->withoutMiddleware(RouteLogMiddleware::class);

            // login
            Route::group(['prefix' => 'login'], static function () {
                Route::post('with-email', [LoginController::class, 'loginWithEmail'])->withoutMiddleware(RouteLogMiddleware::class);

                Route::group(['prefix' => 'with-phone'], static function () {
                    Route::post('send-otp', [LoginController::class, 'sendPhoneOtp'])->middleware(['throttle:2,1']);
                    Route::post('submit', [LoginController::class, 'loginWithPhone']);
                });

                Route::post('with-third-party', [LoginController::class, 'loginFromThirdPartyProvider'])->withoutMiddleware(RouteLogMiddleware::class);
            });

            Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword'])->middleware(['throttle:3,1']);

            Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword'])->withoutMiddleware(RouteLogMiddleware::class);

            Route::group([
                'middleware' => [
                    'auth:user',
                    CheckUserVerifiedMiddleware::class,
                    CheckActiveUserMiddleware::class,
                ],
            ], static function () {
                Route::group(['prefix' => 'profile'], static function () {
                    Route::get('check-logged-in', [ProfileController::class, 'checkLoggedIn']);
                    Route::get('me', [ProfileController::class, 'getUserInfo']);
                    Route::post('change-password', [ProfileController::class, 'changePassword'])->withoutMiddleware(RouteLogMiddleware::class);
                    Route::post('change-password/verify-otp', [ProfileController::class, 'changePasswordVerifyOtp'])->withoutMiddleware(RouteLogMiddleware::class);
                    Route::post('update', [ProfileController::class, 'updateProfile']);
                    Route::post('image', [ProfileController::class, 'updateProfileImage']);
                    Route::delete('image', [ProfileController::class, 'deleteProfileImage']);
                    Route::delete('delete-account', [ProfileController::class, 'deleteAccount']);

                    Route::group(['prefix' => 'addresses'], static function () {
                        Route::get('form-data', [AddressController::class, 'formData']);
                        Route::get('load', [AddressController::class, 'index']);
                        Route::get('show/{address}', [AddressController::class, 'show']);
                        Route::post('add', [AddressController::class, 'store']);
                        Route::post('update/{address}', [AddressController::class, 'update']);
                        Route::delete('delete/{address}', [AddressController::class, 'destroy']);
                        Route::post('set-default/{address}', [AddressController::class, 'setDefault']);
                    });

                    Route::group(['prefix' => 'cars'], static function () {
                        Route::get('load', [UserCarController::class, 'index']);
                        Route::get('list', [UserCarController::class, 'list']);
                        Route::get('show/{userCar}', [UserCarController::class, 'show']);
                        Route::post('add', [UserCarController::class, 'store']);
                        Route::post('update/{userCar}', [UserCarController::class, 'update']);
                        Route::delete('delete/{userCar}', [UserCarController::class, 'destroy']);
                        Route::delete('delete-photo/{userCar}', [UserCarController::class, 'deletePhoto']);
                    });

                    Route::group(['prefix' => 'sessions'], static function () {
                        Route::get('load', [SessionController::class, 'index']);
                        Route::post('{tokenId}/logout', [SessionController::class, 'logoutSession']);
                    });
                });

                Route::post('send-verification-email', [EmailVerificationController::class, 'sendVerificationEmail'])
                    ->withoutMiddleware(CheckUserVerifiedMiddleware::class)
                    ->middleware(['throttle:3,1']);

                Route::post('logout', [LogoutController::class, 'logout'])
                    ->withoutMiddleware(CheckUserVerifiedMiddleware::class)
                    ->withoutMiddleware(CheckActiveUserMiddleware::class);
                Route::post('logout-all', [LogoutController::class, 'logoutAll'])
                    ->withoutMiddleware(CheckUserVerifiedMiddleware::class)
                    ->withoutMiddleware(CheckActiveUserMiddleware::class);

                Route::group(['prefix' => 'orders'], static function () {
                    Route::get('checkout-preview', [OrderController::class, 'checkoutPreview']);
                    Route::post('checkout', [OrderController::class, 'checkout']);
                    Route::get('check-payment-status/{orderUuid}/{paymentMethod}/{token}', [OrderController::class, 'checkPaymentStatus'])->middleware(['throttle:5,1']);
                    Route::get('statuses/list', [OrderController::class, 'getOrderStatusesList']);
                    Route::get('load', [OrderController::class, 'load']);
                    Route::get('show/{order}', [OrderController::class, 'show']);
                    Route::get('details/{order}', [OrderController::class, 'details']);
                });

                Route::prefix('payment')->group(function () {
                    Route::get('methods', [PaymentController::class, 'getPaymentMethods']);
                });

                Route::group(['prefix' => 'notifications'], static function () {
                    Route::get('load', [NotificationController::class, 'index']);
                    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
                    Route::post('{notification}/read', [NotificationController::class, 'read']);
                    Route::post('read-all', [NotificationController::class, 'readAll']);
                    Route::delete('{notification}/delete', [NotificationController::class, 'destroy']);
                    Route::delete('delete-all', [NotificationController::class, 'destroyAll']);
                });

                Route::group(['prefix' => 'referral'], static function () {
                    Route::get('my', [ReferralController::class, 'my']);
                    Route::get('balance', [ReferralController::class, 'balance']);
                    Route::get('earnings', [ReferralController::class, 'earnings']);
                    Route::get('referrals', [ReferralController::class, 'referrals']);
                    Route::get('perks', [ReferralController::class, 'perks']);
                    Route::post('update-code', [ReferralController::class, 'updateCode']);
                    Route::post('withdrawals', [ReferralController::class, 'createWithdrawal']);
                    Route::get('withdrawals', [ReferralController::class, 'withdrawals']);
                });
            });
        });
};
