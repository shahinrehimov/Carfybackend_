<?php

use App\Enum\RoleEnum;
use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\AddressTypeController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CarColorController;
use App\Http\Controllers\Admin\CarTypeController;
use App\Http\Controllers\Admin\EngineVolumeController;
use App\Http\Controllers\Admin\FuelTypeController;
use App\Http\Controllers\Admin\TransmissionTypeController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\LogoutController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\BrandModelController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CompanyAdminController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyProductVariantController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DiscountTypeController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MetaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductOptionItemController;
use App\Http\Controllers\Admin\ProductReviewController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserCarController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LocalTranslationsController;
use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['general_access:admin', RouteLogMiddleware::class])->prefix('control')->group(callback: static function () {
    Route::post("login", [LoginController::class, 'login'])->withoutMiddleware(RouteLogMiddleware::class);

    Route::group(['middleware' => ['auth:admin']], static function () {
        Route::group(['prefix' => 'profile'], static function () {
            Route::get("check-logged-in", [ProfileController::class, 'checkLoggedIn']);
            Route::post("change-password", [ProfileController::class, 'changePassword'])->withoutMiddleware(RouteLogMiddleware::class);
            Route::get("me", [ProfileController::class, 'showProfile']);
            Route::post("update", [ProfileController::class, 'updateProfile']);

            Route::group(['prefix' => 'sessions'], static function () {
                Route::get('load', [SessionController::class, 'index']);
                Route::post('{tokenId}/logout', [SessionController::class, 'logoutSession']);
            });
        });

        Route::post("logout", [LogoutController::class, 'logout']);
        Route::post("logout-all", [LogoutController::class, 'logoutAll']);

        Route::group(['prefix' => 'local-translations'], static function () {
            Route::get('languages/list', [LocalTranslationsController::class, 'getLanguages'])->withoutMiddleware(['auth:admin']);
            Route::get('{lang}', [LocalTranslationsController::class, 'getTranslations'])->withoutMiddleware(['auth:admin']);
        });

        Route::group(['prefix' => 'admins', 'middleware' => ['role:' . RoleEnum::ADMIN->value]], static function () {
            Route::get('load', [AdminController::class, 'index']);
            Route::get('show/{admin}', [AdminController::class, 'show']);
            Route::post('add', [AdminController::class, 'store'])->withoutMiddleware(RouteLogMiddleware::class);
            Route::post('update/{admin}', [AdminController::class, 'update'])->withoutMiddleware(RouteLogMiddleware::class);
            Route::delete('delete/{admin}', [AdminController::class, 'destroy']);
            Route::get('list', [AdminController::class, 'list']);
            Route::get('list/{role}', [AdminController::class, 'listByRole']);
        });

        Route::group(['prefix' => 'roles', 'middleware' => ['role:' . RoleEnum::ADMIN->value]], static function () {
            Route::get('list', [RoleController::class, 'list']);
        });

        Route::group(['prefix' => 'languages'], static function () {
            Route::get('load', [LanguageController::class, 'index']);
            Route::get('list', [LanguageController::class, 'list']);
            Route::get('show/{language}', [LanguageController::class, 'show']);
            Route::post('add', [LanguageController::class, 'store']);
            Route::post('update/{language}', [LanguageController::class, 'update']);
            Route::post('change-active-status/{language}', [LanguageController::class, 'changeActiveStatus']);
            Route::delete('delete/{language}', [LanguageController::class, 'destroy']);
        });

        Route::group(['prefix' => 'translations', 'middleware' => ['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value]], static function () {
            Route::get('load', [TranslationController::class, 'index']);
            Route::get('filters', [TranslationController::class, 'filters']);
            Route::get('show/{group}/{key}', [TranslationController::class, 'show']);
            Route::post('update/{group}/{key}', [TranslationController::class, 'update']);
            Route::get('create', [TranslationController::class, 'create']);
            Route::post('add', [TranslationController::class, 'store']);
            Route::delete('delete/{group}/{key}', [TranslationController::class, 'destroy'])
                ->withoutMiddleware(['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value])
                ->middleware(['role:' . RoleEnum::ADMIN->value]);
        });

        Route::group(['prefix' => 'settings', 'middleware' => ['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value]], static function () {
            Route::get('load', [SettingController::class, 'index']);
            Route::get('list', [SettingController::class, 'list']);
            Route::get('show/{key}', [SettingController::class, 'show']);
            Route::post('add', [SettingController::class, 'store']);
            Route::post('update/{key}', [SettingController::class, 'update']);
            Route::delete('delete/{key}', [SettingController::class, 'destroy'])
                ->withoutMiddleware(['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value])
                ->middleware(['role:' . RoleEnum::ADMIN->value]);
        });

        Route::group(['prefix' => 'metas', 'middleware' => ['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value]], static function () {
            Route::get('load', [MetaController::class, 'index']);
            Route::get('list', [MetaController::class, 'list']);
            Route::get('show/{meta}', [MetaController::class, 'show']);
            Route::post('add', [MetaController::class, 'store']);
            Route::post('update/{meta}', [MetaController::class, 'update']);
            Route::delete('delete/{meta}', [MetaController::class, 'destroy'])
                ->withoutMiddleware(['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value])
                ->middleware(['role:' . RoleEnum::ADMIN->value]);
        });

        Route::group(['prefix' => 'socials', 'middleware' => ['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::MARKETING->value]], static function () {
            Route::get('load', [SocialController::class, 'index']);
            Route::get('list', [SocialController::class, 'list']);
            Route::get('show/{social}', [SocialController::class, 'show']);
            Route::post('add', [SocialController::class, 'store']);
            Route::post('update/{social}', [SocialController::class, 'update']);
            Route::delete('delete/{social}', [SocialController::class, 'destroy']);
        });

        Route::group(['prefix' => 'categories'], static function () {
            Route::get('load', [CategoryController::class, 'index']);
            Route::get('list', [CategoryController::class, 'list']);
            Route::get('show/{category}', [CategoryController::class, 'show']);
            Route::post('add', [CategoryController::class, 'store']);
            Route::post('update/{category}', [CategoryController::class, 'update']);
            Route::delete('delete/{category}', [CategoryController::class, 'destroy']);
            Route::delete('delete-image/{category}', [CategoryController::class, 'deleteImage']);
            Route::post('change-active-status/{category}', [CategoryController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{category}', [CategoryController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [CategoryController::class, 'changeSortingMultiple']);

            Route::group(['prefix' => '{category}/subs'], static function () {
                Route::get('load', [CategoryController::class, 'loadSubs']);
                Route::get('list', [CategoryController::class, 'listSubs']);
            });
        });

        Route::group(['prefix' => 'product-options'], static function () {
            Route::get('load', [ProductOptionController::class, 'index']);
            Route::get('list', [ProductOptionController::class, 'list']);
            Route::get('show/{productOption}', [ProductOptionController::class, 'show']);
            Route::post('add', [ProductOptionController::class, 'store']);
            Route::post('update/{productOption}', [ProductOptionController::class, 'update']);
            Route::delete('delete/{productOption}', [ProductOptionController::class, 'destroy']);
            Route::post('change-active-status/{productOption}', [ProductOptionController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{productOption}', [ProductOptionController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [ProductOptionController::class, 'changeSortingMultiple']);

            Route::group(['prefix' => '{productOption}/items'], static function () {
                Route::get('load', [ProductOptionItemController::class, 'index']);
                Route::get('list', [ProductOptionItemController::class, 'list']);
                Route::get('show/{productOptionItem}', [ProductOptionItemController::class, 'show']);
                Route::post('add', [ProductOptionItemController::class, 'store']);
                Route::post('update/{productOptionItem}', [ProductOptionItemController::class, 'update']);
                Route::delete('delete/{productOptionItem}', [ProductOptionItemController::class, 'destroy']);
                Route::post('change-active-status/{productOptionItem}', [ProductOptionItemController::class, 'changeActiveStatus']);
                Route::post('change-sorting/{productOptionItem}', [ProductOptionItemController::class, 'changeSorting']);
                Route::post('change-sorting-multiple', [ProductOptionItemController::class, 'changeSortingMultiple']);
            });
        });

        Route::group(['prefix' => 'brands'], static function () {
            Route::get('load', [BrandController::class, 'index']);
            Route::get('list', [BrandController::class, 'list']);
            Route::get('show/{brand}', [BrandController::class, 'show']);
            Route::post('add', [BrandController::class, 'store']);
            Route::post('update/{brand}', [BrandController::class, 'update']);
            Route::delete('delete/{brand}', [BrandController::class, 'destroy']);
            Route::delete('delete-image/{brand}', [BrandController::class, 'deleteImage']);
            Route::post('change-active-status/{brand}', [BrandController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{brand}', [BrandController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [BrandController::class, 'changeSortingMultiple']);

            Route::group(['prefix' => '{brand}/models'], static function () {
                Route::get('load', [BrandModelController::class, 'index']);
                Route::get('list', [BrandModelController::class, 'list']);
                Route::get('show/{brandModel}', [BrandModelController::class, 'show']);
                Route::post('add', [BrandModelController::class, 'store']);
                Route::post('update/{brandModel}', [BrandModelController::class, 'update']);
                Route::delete('delete/{brandModel}', [BrandModelController::class, 'destroy']);
                Route::post('change-active-status/{brandModel}', [BrandModelController::class, 'changeActiveStatus']);
                Route::post('change-sorting/{brandModel}', [BrandModelController::class, 'changeSorting']);
                Route::post('change-sorting-multiple', [BrandModelController::class, 'changeSortingMultiple']);
            });
        });

        Route::group(['prefix' => 'discount-types'], static function () {
            Route::get('list', [DiscountTypeController::class, 'list']);
        });

        Route::group(['prefix' => 'groups'], static function () {
            Route::get('load', [GroupController::class, 'index']);
            Route::get('list', [GroupController::class, 'list']);
            Route::get('show/{group}', [GroupController::class, 'show']);
            Route::post('add', [GroupController::class, 'store']);
            Route::post('update/{group}', [GroupController::class, 'update']);
            Route::delete('delete/{group}', [GroupController::class, 'destroy']);
            Route::delete('delete-image/{group}', [GroupController::class, 'deleteImage']);
            Route::post('change-active-status/{group}', [GroupController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{group}', [GroupController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [GroupController::class, 'changeSortingMultiple']);

            Route::group(['prefix' => '{group}/products'], static function () {
                Route::post('add/{productVariant}', [GroupController::class, 'addProduct']);
                Route::post('remove/{productVariant}', [GroupController::class, 'removeProduct']);
                Route::post('add-bulk', [GroupController::class, 'addBulkProducts']);
                Route::post('remove-bulk', [GroupController::class, 'removeBulkProducts']);
            });
        });

        Route::group(['prefix' => 'address-types'], static function () {
            Route::get('load', [AddressTypeController::class, 'index']);
            Route::get('list', [AddressTypeController::class, 'list']);
            Route::get('show/{addressType}', [AddressTypeController::class, 'show']);
            Route::post('add', [AddressTypeController::class, 'store']);
            Route::post('update/{addressType}', [AddressTypeController::class, 'update']);
            Route::delete('delete/{addressType}', [AddressTypeController::class, 'destroy']);
            Route::post('change-active-status/{addressType}', [AddressTypeController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{addressType}', [AddressTypeController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [AddressTypeController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'fuel-types'], static function () {
            Route::get('load', [FuelTypeController::class, 'index']);
            Route::get('list', [FuelTypeController::class, 'list']);
            Route::get('show/{fuelType}', [FuelTypeController::class, 'show']);
            Route::post('add', [FuelTypeController::class, 'store']);
            Route::post('update/{fuelType}', [FuelTypeController::class, 'update']);
            Route::delete('delete/{fuelType}', [FuelTypeController::class, 'destroy']);
            Route::post('change-active-status/{fuelType}', [FuelTypeController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{fuelType}', [FuelTypeController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [FuelTypeController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'transmission-types'], static function () {
            Route::get('load', [TransmissionTypeController::class, 'index']);
            Route::get('list', [TransmissionTypeController::class, 'list']);
            Route::get('show/{transmissionType}', [TransmissionTypeController::class, 'show']);
            Route::post('add', [TransmissionTypeController::class, 'store']);
            Route::post('update/{transmissionType}', [TransmissionTypeController::class, 'update']);
            Route::delete('delete/{transmissionType}', [TransmissionTypeController::class, 'destroy']);
            Route::post('change-active-status/{transmissionType}', [TransmissionTypeController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{transmissionType}', [TransmissionTypeController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [TransmissionTypeController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'car-types'], static function () {
            Route::get('load', [CarTypeController::class, 'index']);
            Route::get('list', [CarTypeController::class, 'list']);
            Route::get('show/{carType}', [CarTypeController::class, 'show']);
            Route::post('add', [CarTypeController::class, 'store']);
            Route::post('update/{carType}', [CarTypeController::class, 'update']);
            Route::delete('delete/{carType}', [CarTypeController::class, 'destroy']);
            Route::post('change-active-status/{carType}', [CarTypeController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{carType}', [CarTypeController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [CarTypeController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'car-colors'], static function () {
            Route::get('load', [CarColorController::class, 'index']);
            Route::get('list', [CarColorController::class, 'list']);
            Route::get('show/{carColor}', [CarColorController::class, 'show']);
            Route::post('add', [CarColorController::class, 'store']);
            Route::post('update/{carColor}', [CarColorController::class, 'update']);
            Route::delete('delete/{carColor}', [CarColorController::class, 'destroy']);
            Route::post('change-active-status/{carColor}', [CarColorController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{carColor}', [CarColorController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [CarColorController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'engine-volumes'], static function () {
            Route::get('load', [EngineVolumeController::class, 'index']);
            Route::get('list', [EngineVolumeController::class, 'list']);
            Route::get('show/{engineVolume}', [EngineVolumeController::class, 'show']);
            Route::post('add', [EngineVolumeController::class, 'store']);
            Route::post('update/{engineVolume}', [EngineVolumeController::class, 'update']);
            Route::delete('delete/{engineVolume}', [EngineVolumeController::class, 'destroy']);
            Route::post('change-active-status/{engineVolume}', [EngineVolumeController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{engineVolume}', [EngineVolumeController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [EngineVolumeController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'countries'], static function () {
            Route::get('load', [CountryController::class, 'index']);
            Route::get('list', [CountryController::class, 'list']);
            Route::get('show/{country}', [CountryController::class, 'show']);
            Route::post('add', [CountryController::class, 'store']);
            Route::post('update/{country}', [CountryController::class, 'update']);
            Route::delete('delete/{country}', [CountryController::class, 'destroy']);
            Route::post('change-active-status/{country}', [CountryController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{country}', [CountryController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [CountryController::class, 'changeSortingMultiple']);

            Route::group(['prefix' => '{country}/cities'], static function () {
                Route::get('load', [CityController::class, 'index']);
                Route::get('list', [CityController::class, 'list']);
                Route::get('show/{city}', [CityController::class, 'show']);
                Route::post('add', [CityController::class, 'store']);
                Route::post('update/{city}', [CityController::class, 'update']);
                Route::delete('delete/{city}', [CityController::class, 'destroy']);
                Route::post('change-active-status/{city}', [CityController::class, 'changeActiveStatus']);
                Route::post('change-sorting/{city}', [CityController::class, 'changeSorting']);
                Route::post('change-sorting-multiple', [CityController::class, 'changeSortingMultiple']);
            });
        });

        Route::group(['prefix' => 'users'], static function () {
            Route::get('load', [UserController::class, 'index']);
            Route::get('list', [UserController::class, 'list']);
            Route::get('show/{user}', [UserController::class, 'show']);
            Route::delete('delete/{user}', [UserController::class, 'destroy']);
            Route::post('change-active-status/{user}', [UserController::class, 'changeActiveStatus']);

            Route::group(['prefix' => '{user}/addresses'], static function () {
                Route::get('load', [AddressController::class, 'index']);
                Route::get('list', [AddressController::class, 'list']);
                Route::get('show/{address}', [AddressController::class, 'show']);
                Route::delete('delete/{address}', [AddressController::class, 'destroy']);
            });

            Route::group(['prefix' => '{user}/cars'], static function () {
                Route::get('load', [UserCarController::class, 'index']);
                Route::get('show/{userCar}', [UserCarController::class, 'show']);
                Route::delete('delete/{userCar}', [UserCarController::class, 'destroy']);
            });
        });

        Route::group(['prefix' => 'companies'], static function () {
            Route::get('load', [CompanyController::class, 'index']);
            Route::get('list', [CompanyController::class, 'list']);
            Route::get('show/{company}', [CompanyController::class, 'show']);
            Route::post('add', [CompanyController::class, 'store']);
            Route::post('update/{company}', [CompanyController::class, 'update']);
            Route::delete('delete/{company}', [CompanyController::class, 'destroy']);
            Route::post('verify/{company}', [CompanyController::class, 'verify']);
            Route::post('change-active-status/{company}', [CompanyController::class, 'changeActiveStatus']);
            Route::delete('delete-logo/{company}', [CompanyController::class, 'deleteLogo']);
            Route::delete('delete-banner/{company}', [CompanyController::class, 'deleteBanner']);
            Route::post('working-hours/{company}', [CompanyController::class, 'updateWorkingHours']);

            Route::group(['prefix' => '{company}/admins'], static function () {
                Route::get('load', [CompanyAdminController::class, 'index']);
                Route::get('list', [CompanyAdminController::class, 'list']);
                Route::get('show/{companyAdmin}', [CompanyAdminController::class, 'show']);
                Route::post('add', [CompanyAdminController::class, 'store']);
                Route::post('update/{companyAdmin}', [CompanyAdminController::class, 'update']);
                Route::delete('delete/{companyAdmin}', [CompanyAdminController::class, 'destroy']);
                Route::post('change-active-status/{companyAdmin}', [CompanyAdminController::class, 'changeActiveStatus']);
            });
        });

        Route::group(['prefix' => 'company-admins'], static function () {
            Route::get('load', [CompanyAdminController::class, 'index']);
            Route::get('list', [CompanyAdminController::class, 'list']);
        });

        Route::group(['prefix' => 'company-product-variants'], static function () {
            Route::get('load', [CompanyProductVariantController::class, 'index']);

            Route::group(['prefix' => '{companyProductVariant}/approval-status'], static function () {
                Route::get('history', [CompanyProductVariantController::class, 'approvalStatusHistory']);
                Route::post('approve', [CompanyProductVariantController::class, 'approvalStatusApprove']);
                Route::post('reject', [CompanyProductVariantController::class, 'approvalStatusReject']);
            });
        });

        Route::group(['prefix' => 'product-reviews'], static function () {
            Route::get('load', [ProductReviewController::class, 'index']);
            Route::delete('delete/{productReview}', [ProductReviewController::class, 'destroy']);
            Route::post('approve-toggle/{productReview}', [ProductReviewController::class, 'approveToggle']);
        });

        Route::group(['prefix' => 'orders'], static function () {
            Route::get('search-filters', [OrderController::class, 'getSearchFilters']);
            Route::get('load', [OrderController::class, 'index']);
            Route::get('show/{order}', [OrderController::class, 'show']);
            Route::post('change-status/{order}', [OrderController::class, 'changeStatus']);
            Route::post('update-payment-status/{order}', [OrderController::class, 'updatePaymentStatus']);
            Route::post('refund/{order}', [OrderController::class, 'refund']);
        });

        Route::group(['prefix' => 'statistics'], static function () {
            Route::get('orders', [StatisticsController::class, 'orderStats']);
            Route::get('top-stores', [StatisticsController::class, 'topStores']);
            Route::get('top-products', [StatisticsController::class, 'topProducts']);
            Route::get('users', [StatisticsController::class, 'userStats']);
            Route::get('cancellations', [StatisticsController::class, 'cancellations']);
            Route::get('commissions', [StatisticsController::class, 'commissions']);
        });

        Route::prefix('sliders')->group(function () {
            Route::get('load', [SliderController::class, 'index']);
            Route::get('list', [SliderController::class, 'list']);
            Route::get('show/{slider}', [SliderController::class, 'show']);
            Route::post('add', [SliderController::class, 'store']);
            Route::post('update/{slider}', [SliderController::class, 'update']);
            Route::delete('delete/{slider}', [SliderController::class, 'destroy']);
            Route::post('change-active-status/{slider}', [SliderController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{slider}', [SliderController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [SliderController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'products'], static function () {
            Route::get('load', [ProductController::class, 'index']);
            Route::get('list', [ProductController::class, 'list']);
            Route::get('show/{product}', [ProductController::class, 'show']);
            Route::delete('delete/{product}', [ProductController::class, 'destroy']);
            Route::delete('delete-hover-image/{product}', [ProductController::class, 'deleteHoverImage']);
            Route::delete('images/delete/{product}/{mediaId}', [ProductController::class, 'destroyImage']);
            Route::post('change-active-status/{product}', [ProductController::class, 'changeActiveStatus']);
            Route::get('{product}/revisions', [ProductController::class, 'revisions']);
            Route::post('revisions/{revision}/approve', [ProductController::class, 'approveRevision']);
            Route::post('revisions/{revision}/reject', [ProductController::class, 'rejectRevision']);
        });

        Route::group(['prefix' => 'product-variants'], static function () {
            Route::get('load', [ProductVariantController::class, 'index']);
            Route::get('list', [ProductVariantController::class, 'list']);
            Route::get('show/{productVariant}', [ProductVariantController::class, 'show']);
            Route::delete('delete/{productVariant}', [ProductVariantController::class, 'destroy']);
            Route::delete('delete-hover-image/{productVariant}', [ProductVariantController::class, 'deleteHoverImage']);
            Route::delete('images/delete/{productVariant}/{mediaId}', [ProductVariantController::class, 'destroyImage']);
            Route::post('change-active-status/{productVariant}', [ProductVariantController::class, 'changeActiveStatus']);
            Route::get('{productVariant}/revisions', [ProductVariantController::class, 'revisions']);
            Route::post('revisions/{revision}/approve', [ProductVariantController::class, 'approveRevision']);
            Route::post('revisions/{revision}/reject', [ProductVariantController::class, 'rejectRevision']);
        });

        Route::group(['prefix' => 'contact-messages'], static function () {
            Route::get('load', [ContactMessageController::class, 'index']);
            Route::get('show/{contactMessage}', [ContactMessageController::class, 'show']);
            Route::post('mark-as-read/{contactMessage}', [ContactMessageController::class, 'markAsRead']);
            Route::post('mark-as-unread/{contactMessage}', [ContactMessageController::class, 'markAsUnread']);
            Route::delete('delete/{contactMessage}', [ContactMessageController::class, 'destroy']);
        });

        Route::group(['prefix' => 'pages'], static function () {
            Route::get('load', [PageController::class, 'index']);
            Route::get('list', [PageController::class, 'list']);
            Route::get('show/{page}', [PageController::class, 'show']);
            Route::post('add', [PageController::class, 'store']);
            Route::post('update/{page}', [PageController::class, 'update']);
            Route::delete('delete/{page}', [PageController::class, 'destroy']);
            Route::delete('images/delete/{page}/{mediaId}', [PageController::class, 'destroyImage']);
            Route::post('change-active-status/{page}', [PageController::class, 'changeActiveStatus']);
            Route::post('change-sorting/{page}', [PageController::class, 'changeSorting']);
            Route::post('change-sorting-multiple', [PageController::class, 'changeSortingMultiple']);
        });

        Route::group(['prefix' => 'advertisements'], static function () {
            Route::get('load', [AdvertisementController::class, 'index']);
            Route::get('show/{advertisement}', [AdvertisementController::class, 'show']);
            Route::post('add', [AdvertisementController::class, 'store']);
            Route::post('update/{advertisement}', [AdvertisementController::class, 'update']);
            Route::delete('delete/{advertisement}', [AdvertisementController::class, 'destroy']);
            Route::post('change-active-status/{advertisement}', [AdvertisementController::class, 'changeActiveStatus']);
            Route::get('clicks/{advertisement}', [AdvertisementController::class, 'clicks']);
        });
    });
});
