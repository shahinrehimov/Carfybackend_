<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\LoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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
        if (config('logger.sql_debug')) {
            DB::listen(static function ($query) {
                LoggerService::instance()->log($query->sql, $query->bindings);
            });
        }

        // Observers
        Order::observe(OrderObserver::class);
    }
}
