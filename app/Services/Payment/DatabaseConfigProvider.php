<?php

namespace App\Services\Payment;

use App\Models\PaymentMethodConfig;
use App\Services\LoggerService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class DatabaseConfigProvider
{
    public function getConfig(string $methodName): array
    {
        if (!config('payment.database_config.enabled', false)) {
            return [];
        }

        $cacheKey = "payment_db_config_{$methodName}";

        return Cache::remember($cacheKey, 300, function () use ($methodName) {
            $dbConfig = PaymentMethodConfig::query()->where('method_name', $methodName)->first();

            if (!$dbConfig) {
                return [];
            }

            return array_merge(
                $dbConfig->config ?? [],
                [
                    'enabled' => $dbConfig->enabled,
                    'priority' => $dbConfig->priority,
                    'display_name' => $dbConfig->display_name,
                ]
            );
        });
    }

    public function mergeWithFileConfig(string $methodName, array $fileConfig): array
    {
        $dbConfig = $this->getConfig($methodName);

        if (empty($dbConfig)) {
            return $fileConfig;
        }

        if (config('payment.database_config.override_config', true)) {
            return array_merge($fileConfig, $dbConfig);
        }

        return array_merge($dbConfig, $fileConfig);
    }

    public function clearCache(string $methodName = null): void
    {
        if ($methodName) {
            Cache::forget("payment_db_config_{$methodName}");
        } else {
            $methods = PaymentMethodConfig::query()->pluck('method_name');
            foreach ($methods as $method) {
                Cache::forget("payment_db_config_{$method}");
            }
        }
    }

    public function saveConfig(string $methodName, array $config): bool
    {
        try {
            $dbConfig = PaymentMethodConfig::query()->firstOrCreate(['method_name' => $methodName]);

            $dbConfig->update([
                'display_name' => $config['display_name'] ?? $methodName,
                'enabled' => $config['enabled'] ?? false,
                'priority' => $config['priority'] ?? 100,
                'config' => Arr::except($config, ['display_name', 'enabled', 'priority']),
            ]);

            $this->clearCache($methodName);
            return true;
        } catch (\Exception $e) {
            LoggerService::instance()->log("Could not save payment method config for {$methodName}: " . $e->getMessage(), [], true, 'error');
            return false;
        }
    }
}
