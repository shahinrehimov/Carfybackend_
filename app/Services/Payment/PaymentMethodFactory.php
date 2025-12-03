<?php

namespace App\Services\Payment;

use App\Interfaces\Payment\PaymentMethodInterface;
use App\Services\LoggerService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PaymentMethodFactory
{
    private static ?array $cachedMethods = null;
    private PaymentMethodDiscovery $discovery;
    private DatabaseConfigProvider $dbConfig;

    public function __construct(PaymentMethodDiscovery $discovery, DatabaseConfigProvider $dbConfig)
    {
        $this->discovery = $discovery;
        $this->dbConfig = $dbConfig;
    }

    public static function create(string $methodName): PaymentMethodInterface
    {
        $factory = app(self::class);
        return $factory->createMethod($methodName);
    }

    public function createMethod(string $methodName): PaymentMethodInterface
    {
        $methods = $this->getAllRegisteredMethods();

        if (!isset($methods[$methodName])) {
            throw new InvalidArgumentException("Payment method '{$methodName}' not found");
        }

        $methodConfig = $methods[$methodName];
        $class = $methodConfig['class'];

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Payment method class '{$class}' not found");
        }

        // Instance cache (optional)
        if (config('payment.cache.enabled', false)) {
            $cacheKey = config('payment.cache.key_prefix', 'payment_method_') . $methodName;
            $ttl = config('payment.cache.ttl', 3600);

            return Cache::remember($cacheKey, $ttl, function() use ($class, $methodName, $methodConfig) {
                return $this->instantiateMethod($class, $methodName, $methodConfig);
            });
        }

        return $this->instantiateMethod($class, $methodName, $methodConfig);
    }

    private function instantiateMethod(string $class, string $methodName, array $methodConfig): PaymentMethodInterface
    {
        // File config
        $fileConfig = $methodConfig;
        unset($fileConfig['class'], $fileConfig['priority'], $fileConfig['auto_discovered']);

        // DB config merge
        $finalConfig = $this->dbConfig->mergeWithFileConfig($methodName, $fileConfig);

        if (!in_array(PaymentMethodInterface::class, class_implements($class))) {
            throw new InvalidArgumentException("Class '{$class}' must implement PaymentMethodInterface");
        }

        return new $class($finalConfig);
    }

    public function getAllRegisteredMethods(): array
    {
        if (self::$cachedMethods !== null) {
            return self::$cachedMethods;
        }

        $configMethods = $this->getMethodsFromConfig();

        // Auto-discovery
        $discoveredMethods = $this->discovery->discover();

        // Merge and sort by priority
        $allMethods = array_merge($configMethods, $discoveredMethods);

        // Sort by priority
        uasort($allMethods, fn($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        self::$cachedMethods = $allMethods;
        return $allMethods;
    }

    private function getMethodsFromConfig(): array
    {
        $methods = config('payment.methods', []);
        $result = [];

        foreach ($methods as $name => $config) {
            $result[$name] = array_merge($config, ['method_name' => $name]);
        }

        return $result;
    }

    public static function getEnabledMethods(): array
    {
        $factory = app(self::class);
        $methods = [];

        foreach ($factory->getAllRegisteredMethods() as $name => $config) {
            try {
                $method = $factory->createMethod($name);
                if ($method->isEnabled()) {
                    $methods[$name] = $method;
                }
            } catch (\Exception $e) {
                LoggerService::instance()->log("Could not create enabled payment method: {$name}", [
                    'error' => $e->getMessage()
                ], true, 'warning');
            }
        }

        return $methods;
    }

    public static function exists(string $methodName): bool
    {
        $factory = app(self::class);
        $methods = $factory->getAllRegisteredMethods();
        return isset($methods[$methodName]);
    }

    public static function clearCache(): void
    {
        self::$cachedMethods = null;

        if (config('payment.cache.enabled', false)) {
            $factory = app(self::class);
            $methods = $factory->getAllRegisteredMethods();
            $prefix = config('payment.cache.key_prefix', 'payment_method_');

            foreach ($methods as $name => $config) {
                Cache::forget($prefix . $name);
            }
        }

        app(PaymentMethodDiscovery::class)->clearCache();
    }

    public static function debug(): array
    {
        $factory = app(self::class);
        $methods = $factory->getAllRegisteredMethods();
        $debug = [];

        foreach ($methods as $name => $config) {
            $debug[$name] = [
                'class' => $config['class'],
                'priority' => $config['priority'] ?? 100,
                'auto_discovered' => $config['auto_discovered'] ?? false,
                'exists' => class_exists($config['class']),
                'implements_interface' => class_exists($config['class']) && in_array(PaymentMethodInterface::class, class_implements($config['class'])),
                'enabled' => false,
                'method_name' => $name
            ];

            try {
                $method = $factory->createMethod($name);
                $debug[$name]['enabled'] = $method->isEnabled();
                $debug[$name]['display_name'] = $method->getDisplayName();
                $debug[$name]['icon'] = $method->getIcon();
            } catch (\Exception $e) {
                $debug[$name]['error'] = $e->getMessage();
            }
        }

        return $debug;
    }
}
