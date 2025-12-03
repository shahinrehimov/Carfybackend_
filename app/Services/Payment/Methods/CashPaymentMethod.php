<?php

namespace App\Services\Payment\Methods;

use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use Illuminate\Support\Str;

class CashPaymentMethod extends AbstractPaymentMethod
{
    public function getName(): string
    {
        return 'cash';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', LangService::instance()->setDefault('Cash')->getLang('payment_method_' . $this->getName()));
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->amount_final)) {
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount'));
        }

        $this->log('info', "Cash payment processed for order: {$order->uuid}", [
            'order_id' => $order->id,
            'amount' => $order->amount_final
        ]);

        return PaymentResult::success(
            transactionId: 'CASH_' . md5(Str::uuid()) . '_' . $order->id,
            message: LangService::instance()->setDefault('Order created successfully!')->getLang($this->getName() . '_payment_accepted'),
        );
    }

    public function getRequiredFields(): array
    {
        return [];
    }

    public function getConfigurationFields(): array
    {
        return [
            'enabled' => 'boolean',
            'display_name' => 'string',
            'icon' => 'string',
            'min_amount' => 'integer',
            'max_amount' => 'integer'
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'display_name' => $this->getDisplayName(),
            'icon' => 'fa-money-bill',
            'min_amount' => 100,
            'max_amount' => 1000000,
        ];
    }
}
