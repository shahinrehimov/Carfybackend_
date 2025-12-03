<?php

namespace App\Rules;

use App\Services\LangService;
use App\Services\Payment\PaymentMethodFactory;
use Illuminate\Contracts\Validation\Rule;

class ValidPaymentMethod implements Rule
{
    private ?int $amount;
    private ?string $errorMessage = null;

    public function __construct(?int $amount = null)
    {
        $this->amount = $amount;
    }

    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            $this->errorMessage = LangService::instance()->setDefault('Invalid payment method')->getLang('invalid_payment_method');
            return false;
        }

        try {
            $method = PaymentMethodFactory::create($value);

            if (!$method->isEnabled()) {
                $this->errorMessage = LangService::instance()->setDefault('Payment method is disabled')->getLang('payment_method_disabled');
                return false;
            }

            if ($this->amount !== null) {
                $min = $method->getMinimumAmount();
                $max = $method->getMaximumAmount();

                if ($min && $this->amount < $min) {
                    $minFormatted = number_format($min / 100, 2);
                    $this->errorMessage = LangService::instance()->setDefault("Min payment is @min")->getLang('payment_min_amount', ['@min' => $minFormatted]);
                    return false;
                }

                if ($max && $this->amount > $max) {
                    $maxFormatted = number_format($max / 100, 2);
                    $this->errorMessage = LangService::instance()->setDefault("Max payment is @max")->getLang('payment_max_amount', ['@max' => $maxFormatted]);
                    return false;
                }
            }

            return true;
        } catch (\Exception) {
            $this->errorMessage = LangService::instance()->setDefault('Invalid payment method')->getLang('invalid_payment_method');
            return false;
        }
    }

    public function message(): string
    {
        return $this->errorMessage ?? LangService::instance()->setDefault('Invalid payment method')->getLang('invalid_payment_method');
    }
}
