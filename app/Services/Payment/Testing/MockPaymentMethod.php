<?php

namespace App\Services\Payment\Testing;

use App\Services\LangService;
use App\Services\Payment\Methods\AbstractPaymentMethod;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use Illuminate\Http\Request;

class MockPaymentMethod extends AbstractPaymentMethod
{
    private bool $shouldSucceed;
    private ?string $errorMessage;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->shouldSucceed = $config['should_succeed'] ?? true;
        $this->errorMessage = $config['error_message'] ?? null;
    }

    public function getName(): string
    {
        return 'mock';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', 'Mock Payment (Testing)');
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        // Simulate processing delay
        if ($this->getConfig('simulate_delay', false)) {
            sleep(2);
        }

        if ($this->shouldSucceed) {
            return PaymentResult::success(
                transactionId: 'MOCK_' . time() . '_' . $order->id,
                message: 'Mock payment successful',
                metadata: ['test_mode' => true, 'order_id' => $order->id]
            );
        }

        return PaymentResult::failed(
            $this->errorMessage ?? 'Mock payment failed',
            ['test_mode' => true, 'order_id' => $order->id]
        );
    }

    public function verifyPayment(Order $order, string $transactionId): PaymentResult
    {
        if (str_starts_with($transactionId, 'MOCK_')) {
            return PaymentResult::success($transactionId, LangService::instance()->setDefault('Mock verification successful')->getLang('payment_mock_verification_success'));
        }

        return PaymentResult::failed(LangService::instance()->setDefault('Invalid mock transaction ID')->getLang('payment_mock_invalid_transaction'));
    }

    public function handleWebhook(Request $request): array
    {
        $this->log('info', 'Mock webhook received', $request->all());

        return [
            'success' => true,
            'message' => LangService::instance()->setDefault('Mock webhook processed')->getLang('webhook_mock_processed'),
            'data' => $request->all()
        ];
    }

    public function getRequiredFields(): array
    {
        return $this->getConfig('required_fields', []);
    }

    public function getConfigurationFields(): array
    {
        return [
            'enabled' => 'boolean',
            'display_name' => 'string',
            'should_succeed' => 'boolean',
            'error_message' => 'string',
            'simulate_delay' => 'boolean',
            'required_fields' => 'array',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'display_name' => $this->getDisplayName(),
            'icon' => 'fa-test',
            'should_succeed' => true,
            'error_message' => null,
            'simulate_delay' => false,
            'required_fields' => [],
            'min_amount' => 1,
            'max_amount' => 999999,
        ];
    }
}
