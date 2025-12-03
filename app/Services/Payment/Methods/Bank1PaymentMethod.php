<?php

namespace App\Services\Payment\Methods;

use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Bank1PaymentMethod extends AbstractPaymentMethod
{
    public function getName(): string
    {
        return 'bank1';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', LangService::instance()->setDefault('Bank 1')->getLang('payment_method_' . $this->getName()));
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->amount_final)) {
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount'));
        }

        try {
            $this->log('info', "Processing Bank1 payment for order: {$order->uuid}");

            if ($this->isTestMode()) {
                return $this->mockPaymentResponse($order, $data);
            }

            $response = Http::timeout(30)->post($this->getConfig('api_url'), [
                'amount' => $order->amount_final,
                'order_id' => $order->uuid,
                'merchant_id' => $this->getConfig('merchant_id'),
                'card_number' => $data['card_number'] ?? '',
                'expiry_date' => $data['expiry_date'] ?? '',
                'cvv' => $data['cvv'] ?? '',
                'cardholder_name' => $data['cardholder_name'] ?? '',
                'signature' => $this->generateSignature($order, $data)
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['status'] === 'success') {
                    $this->log('info', "Bank1 payment successful", ['transaction_id' => $responseData['transaction_id']]);

                    return PaymentResult::success(
                        transactionId: $responseData['transaction_id'],
                        message: LangService::instance()->setDefault('Payment accepted')->getLang('payment_accepted'),
                        metadata: $responseData
                    );
                }

                if ($responseData['status'] === 'pending') {
                    return PaymentResult::pending(
                        transactionId: $responseData['transaction_id'],
                        message: LangService::instance()->setDefault('Payment pending')->getLang('payment_pending'),
                        metadata: $responseData,
                        redirectUrl: $responseData['redirect_url'] ?? null
                    );
                }
            }

            $this->log('error', "Bank1 API error", ['response' => $response->body()]);
            return PaymentResult::failed(LangService::instance()->setDefault('Payment failed')->getLang('payment_failed'));

        } catch (\Exception $e) {
            $this->log('error', "Bank1 payment exception: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Payment error')->getLang('payment_error'));
        }
    }

    public function verifyPayment(Order $order, string $transactionId): PaymentResult
    {
        try {
            $response = Http::post($this->getConfig('verify_url'), [
                'transaction_id' => $transactionId,
                'merchant_id' => $this->getConfig('merchant_id'),
                'signature' => hash('sha256', $transactionId . $this->getConfig('secret_key'))
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'confirmed') {
                    return PaymentResult::success($transactionId, LangService::instance()->setDefault('Payment accepted')->getLang('payment_accepted'));
                }
            }

            return PaymentResult::failed(LangService::instance()->setDefault('Payment not accepted')->getLang('payment_not_accepted'));
        } catch (\Exception $e) {
            $this->log('error', "Payment verification failed: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Payment accepting error')->getLang('payment_accepting_error'));
        }
    }

    public function handleWebhook(Request $request): array
    {
        $this->log('info', 'Bank1 webhook received', $request->all());

        // Webhook signature verification
        $signature = $request->header('X-Bank1-Signature');
        $payload = $request->getContent();
        $expectedSignature = hash('sha256', $payload . $this->getConfig('webhook_secret'));

        if (!hash_equals($expectedSignature, $signature)) {
            $this->log('error', 'Invalid webhook signature');
            return ['error' => LangService::instance()->setDefault('Invalid signature')->getLang('invalid_signature')];
        }

        // Process webhook data
        $data = $request->json()->all();

        if (isset($data['order_id']) && isset($data['status'])) {
            // Update order status based on webhook
            // TODO:: Implement order status update logic here
            $this->log('info', "Webhook processed for order: {$data['order_id']} with status: {$data['status']}");
        }

        return ['success' => true];
    }

    public function getRequiredFields(): array
    {
        return ['card_number', 'expiry_date', 'cvv', 'cardholder_name'];
    }

    public function getConfigurationFields(): array
    {
        return [
            'enabled' => 'boolean',
            'display_name' => 'string',
            'api_url' => 'string',
            'verify_url' => 'string',
            'merchant_id' => 'string',
            'secret_key' => 'string',
            'webhook_secret' => 'string',
            'icon' => 'string',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'test_mode' => 'boolean'
        ];
    }

    protected function customValidation(array $data): bool
    {
        if (isset($data['card_number'])) {
            $cardNumber = preg_replace('/\D/', '', $data['card_number']);
            if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
                return false;
            }
        }

        if (isset($data['cvv'])) {
            if (!preg_match('/^\d{3,4}$/', $data['cvv'])) {
                return false;
            }
        }

        return true;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'display_name' => $this->getDisplayName(),
            'api_url' => '',
            'verify_url' => '',
            'merchant_id' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'icon' => 'fa-university',
            'min_amount' => 50, // 0.50 AZN
            'max_amount' => 500000, // 5,000 AZN
            'test_mode' => false,
        ];
    }

    private function generateSignature(Order $order, array $data): string
    {
        $string = $order->amount_final . $order->uuid . $this->getConfig('merchant_id') . $this->getConfig('secret_key');
        return hash('sha256', $string);
    }

    private function mockPaymentResponse(Order $order, array $data): PaymentResult
    {
        return PaymentResult::success(
            transactionId: 'TEST_' . $this->getName() . md5(Str::uuid()) . '_' . $order->id,
            message: LangService::instance()->setDefault('Test payment successful')->getLang('test_payment_successful'),
            metadata: ['test_mode' => true]
        );
    }
}
