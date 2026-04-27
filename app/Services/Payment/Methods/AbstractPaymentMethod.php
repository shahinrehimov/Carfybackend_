<?php

namespace App\Services\Payment\Methods;

use App\Enum\PaymentLogActionEnum;
use App\Interfaces\Payment\PaymentMethodInterface;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use App\Services\LoggerService;
use Illuminate\Http\Request;

abstract class AbstractPaymentMethod implements PaymentMethodInterface
{
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    abstract protected function getDefaultConfig(): array;

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    public function getIcon(): ?string
    {
        return $this->config['icon'] ?? null;
    }

    public function getMinimumAmount(): ?int
    {
        return $this->config['min_amount'] ?? null;
    }

    public function getMaximumAmount(): ?int
    {
        return $this->config['max_amount'] ?? null;
    }

    public function validatePaymentData(array $data): bool
    {
        $required = $this->getRequiredFields();

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return $this->customValidation($data);
    }

    protected function customValidation(array $data): bool
    {
        return true;
    }

    protected function validateAmount(int $amount): bool
    {
        if ($this->getMinimumAmount() && $amount < $this->getMinimumAmount()) {
            return false;
        }

        if ($this->getMaximumAmount() && $amount > $this->getMaximumAmount()) {
            return false;
        }

        return true;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if (config('payment.logging_enabled', true)) {
            LoggerService::instance()->log("PAYMENT: [{$this->getName()}] {$message}", $context, true, $level);
        }
    }

    public function getMetadata(): array
    {
        return [];
    }

    /**
     * Default verify implementation
     */
    public function verifyPayment(Order $order, string $transactionId): PaymentResult
    {
        return PaymentResult::success($transactionId, LangService::instance()->setDefault('Payment successful')->getLang('payment_successful'));
    }

    /**
     * Default webhook implementation
     */
    public function handleWebhook(Request $request): array
    {
        $this->log('info', 'Webhook received', $request->all());
        return ['success' => true];
    }

    /**
     * Configuration helper
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if in test mode
     */
    protected function isTestMode(): bool
    {
        return $this->getConfig('test_mode', false);
    }

    public function processCallback(array $data): PaymentResult
    {
        return PaymentResult::failed('Not implemented');
    }

    public function checkStatus(Order $order): PaymentResult
    {
        return PaymentResult::failed('Not implemented');
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        return PaymentResult::failed('Not implemented');
    }

    public function setPaymentLog(Order $order, PaymentLogActionEnum $action, string $status, ?string $orderStatus = null, ?string $transactionId = null, ?array $request = null, ?array $response = null): void
    {
        $order->paymentLogs()->create([
            'payment_method' => $this->getName(),
            'action' => $action,
            'status' => $status,
            'transaction_id' => $transactionId,
            'request' => $request,
            'response' => $response,
        ]);
    }
}
