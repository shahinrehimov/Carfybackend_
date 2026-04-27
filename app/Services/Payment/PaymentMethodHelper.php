<?php

namespace App\Services\Payment;

class PaymentMethodHelper
{
    public static function getSelectOptions(): array
    {
        $methods = PaymentMethodFactory::getEnabledMethods();

        return array_map(function ($method) {
            return $method->getDisplayName();
        }, $methods);
    }

    public static function getMethodsForApi(): array
    {
        $methods = PaymentMethodFactory::getEnabledMethods();
        $result = [];

        foreach ($methods as $name => $method) {
            $result[] = [
                'value' => $name,
                'label' => $method->getDisplayName(),
                'icon' => $method->getIcon(),
                'required_fields' => $method->getRequiredFields(),
                'metadata' => $method->getMetadata(),
                //'min_amount' => $method->getMinimumAmount(),
                //'max_amount' => $method->getMaximumAmount(),
            ];
        }

        return $result;
    }

    public static function getMethodDetails(string $methodName): ?array
    {
        try {
            $method = PaymentMethodFactory::create($methodName);

            if (!$method->isEnabled()) {
                return null;
            }

            return [
                'name' => $method->getName(),
                'display_name' => $method->getDisplayName(),
                'icon' => $method->getIcon(),
                'required_fields' => $method->getRequiredFields(),
                'min_amount' => $method->getMinimumAmount(),
                'max_amount' => $method->getMaximumAmount(),
            ];
        } catch (\Exception) {
            return null;
        }
    }

    public static function formatAmount(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2);
    }

    public static function isMethodAvailableForAmount(string $methodName, int $amount): bool
    {
        try {
            $method = PaymentMethodFactory::create($methodName);

            if (!$method->isEnabled()) {
                return false;
            }

            $min = $method->getMinimumAmount();
            $max = $method->getMaximumAmount();

            if ($min && $amount < $min) {
                return false;
            }

            if ($max && $amount > $max) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
