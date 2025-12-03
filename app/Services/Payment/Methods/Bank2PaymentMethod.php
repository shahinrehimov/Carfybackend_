<?php

namespace App\Services\Payment\Methods;

use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Bank2PaymentMethod extends AbstractPaymentMethod
{
    public function getName(): string
    {
        return 'bank2';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', LangService::instance()->setDefault('Bank 2')->getLang('payment_method_' . $this->getName()));
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->amount_final)) {
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount'));
        }

        try {
            $this->log('info', "Processing Bank2 payment for order: {$order->uuid}");

            if ($this->isTestMode()) {
                return $this->mockPaymentResponse($order);
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
                    'Content-Type' => 'application/json'
                ])
                ->post($this->getConfig('api_url'), [
                    'amount' => $order->amount_final,
                    'currency' => 'AZN',
                    'order_reference' => $order->uuid,
                    'return_url' => route('payment.return', ['order' => $order->uuid]),
                    'cancel_url' => route('payment.cancel', ['order' => $order->uuid]),
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                $this->log('info', "Bank2 payment initialized", ['payment_id' => $responseData['payment_id']]);

                return PaymentResult::pending(
                    transactionId: $responseData['payment_id'],
                    message: LangService::instance()->setDefault('Redirecting to @payment_method for payment')->getLang('payment_redirecting', ['payment_method' => $this->getDisplayName()]),
                    metadata: $responseData,
                    redirectUrl: $responseData['payment_url']
                );
            }

            $this->log('error', "Bank2 API error", ['response' => $response->body()]);
            return PaymentResult::failed(LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error'));

        } catch (\Exception $e) {
            $this->log('error', "Bank2 payment exception: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error'));
        }
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
            'api_url' => 'string',
            'api_key' => 'string',
            'merchant_id' => 'string',
            'icon' => 'string',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'test_mode' => 'boolean'
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'display_name' => $this->getDisplayName(),
            'api_url' => '',
            'api_key' => '',
            'merchant_id' => '',
            'icon' => 'fa-credit-card',
            'min_amount' => 100, // 1 AZN
            'max_amount' => 1000000, // 10,000 AZN
            'test_mode' => false,
        ];
    }

    private function mockPaymentResponse(Order $order): PaymentResult
    {
        return PaymentResult::pending(
            transactionId: 'TEST_' . $this->getName() . md5(Str::uuid()) . '_' . $order->id,
            message: LangService::instance()->setDefault('Test payment successful')->getLang('test_payment_successful'),
            metadata: ['test_mode' => true],
            redirectUrl: "https://example.com/mock-payment-success"
        );
    }
}
