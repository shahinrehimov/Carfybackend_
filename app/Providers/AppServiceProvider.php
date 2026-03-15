<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Observers\OrderObserver;
use App\Services\LoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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

        if (config('logger.sql_debug')) {
            DB::listen(static function ($query) {
                LoggerService::instance()->log($query->sql, $query->bindings);
            });
        }

        // Observers
        Order::observe(OrderObserver::class);
    }
}
