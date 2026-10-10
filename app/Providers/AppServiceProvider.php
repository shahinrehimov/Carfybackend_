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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiting();
    }

    /**
     * Brute-force protection for login, OTP and password reset endpoints.
     * Limits apply per account+IP, per IP, and per account across all IPs (distributed guessing).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', static function (Request $request) {
            $identifier = mb_strtolower(trim((string) $request->input('username', $request->input('email', $request->input('phone', '')))));

            return [
                Limit::perMinute(5)->by('auth:account-ip:' . $identifier . '|' . $request->ip()),
                Limit::perMinute(20)->by('auth:ip:' . $request->ip()),
                Limit::perMinutes(15, 20)->by('auth:account:' . $identifier),
            ];
        });
    }
}
