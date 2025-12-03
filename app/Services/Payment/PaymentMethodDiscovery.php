<?php

namespace App\Services\Payment;

use App\Interfaces\Payment\PaymentMethodInterface;
use App\Services\LoggerService;
use ReflectionClass;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class PaymentMethodDiscovery
{
    public function discover(): array
    {
        if (!config('payment.auto_discovery.enabled', false)) {
            return [];
        }

        $cacheKey = config('payment.auto_discovery.cache_key', 'payment_methods_discovery');

        if (config('payment.auto_discovery.cache_enabled', true)) {
            return Cache::remember($cacheKey, 3600, fn() => $this->scanForMethods());
        }

        return $this->scanForMethods();
    }

    private function scanForMethods(): array
    {
        $methods = [];
        $scanPaths = config('payment.auto_discovery.scan_paths', [
            'App\\Services\\Payment\\Methods'
        ]);

        foreach ($scanPaths as $namespace) {
            $methods = array_merge($methods, $this->scanNamespace($namespace));
        }

        return $methods;
    }

    private function scanNamespace(string $namespace): array
    {
        $methods = [];
        $path = app_path(str_replace('App\\', '', str_replace('\\', '/', $namespace)));

        if (!is_dir($path)) {
            return [];
        }

        try {
            $files = File::allFiles($path);

            foreach ($files as $file) {
                $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if ($reflection->implementsInterface(PaymentMethodInterface::class) &&
                    !$reflection->isAbstract() &&
                    !$reflection->isInterface()) {

                    try {
                        $instance = app($className, ['config' => []]);
                        $methods[$instance->getName()] = [
                            'class' => $className,
                            'auto_discovered' => true,
                            'priority' => 999, // Auto-discovered methods lowest priority
                            'enabled' => false, // Disabled by default
                        ];
                    } catch (\Exception $e) {
                        LoggerService::instance()->log("Could not instantiate auto-discovered payment method: {$className}", [
                            'error' => $e->getMessage()
                        ], true, 'warning');
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerService::instance()->log("Auto-discovery scan failed for {$namespace}: " . $e->getMessage(), [], true, 'error');
        }

        return $methods;
    }

    public function clearCache(): void
    {
        $cacheKey = config('payment.auto_discovery.cache_key', 'payment_methods_discovery');
        Cache::forget($cacheKey);
    }

    public function getDiscoveredMethods(): array
    {
        return $this->discover();
    }
}
