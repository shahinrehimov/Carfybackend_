<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\BrandSearchObserver;
use App\Observers\CategorySearchObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductSearchObserver;
use App\Observers\ProductVariantSearchObserver;
use App\Services\LoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Register the "Sign in with Apple" Socialite driver.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', AppleProvider::class);
        });

        if (config('logger.sql_debug')) {
            DB::listen(static function ($query) {
                // Never leak OTP codes into the SQL log.
                $bindings = str_contains($query->sql, 'user_otp_codes')
                    ? ['***']
                    : $query->bindings;

                LoggerService::instance()->log($query->sql, $bindings);
            });
        }

        // Observers
        Order::observe(OrderObserver::class);

        // Keep Elasticsearch in sync with denormalised product content.
        Product::observe(ProductSearchObserver::class);
        ProductVariant::observe(ProductVariantSearchObserver::class);
        Brand::observe(BrandSearchObserver::class);
        Category::observe(CategorySearchObserver::class);
    }
}
