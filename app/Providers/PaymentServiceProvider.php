<?php

namespace App\Providers;

use App\Console\Commands\PaymentCommand;
use App\Services\LoggerService;
use Illuminate\Support\ServiceProvider;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentMethodFactory;
use App\Services\Payment\PaymentMethodDiscovery;
use App\Services\Payment\DatabaseConfigProvider;
use App\Services\Payment\Analytics\PaymentAnalytics;
use App\Services\Payment\Webhooks\WebhookDispatcher;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Singletons
        $this->app->singleton(PaymentMethodFactory::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(PaymentMethodDiscovery::class);
        $this->app->singleton(DatabaseConfigProvider::class);
        $this->app->singleton(PaymentAnalytics::class);
        $this->app->singleton(WebhookDispatcher::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Config publish et
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/payment.php' => config_path('payment.php'),
            ], 'payment-config');

            // Commands
            $this->commands([
                PaymentCommand::class,
            ]);
        }

        $this->validatePaymentConfiguration();

        // Routes
        $this->loadRoutesIfNeeded();
    }

    private function validatePaymentConfiguration(): void
    {
        try {
            if (config('payment.logging_enabled', true)) {
                $debug = PaymentMethodFactory::debug();

                foreach ($debug as $method => $info) {
                    if (isset($info['error'])) {
                        LoggerService::instance()->log("Payment method configuration error: {$method}", $info, true, 'warning');
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerService::instance()->log("Could not validate payment configuration: " . $e->getMessage(), [], true, 'warning');
        }
    }

    private function loadRoutesIfNeeded(): void
    {

    }
}
