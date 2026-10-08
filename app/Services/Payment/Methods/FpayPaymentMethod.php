<?php

namespace App\Services\Payment\Methods;

use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * FPAY Ecommerce API (https://docs.fpay.az).
 *
 * Every request is a signed POST: X-Api-Key (username), X-Timestamp and
 * X-Signature = HMAC-SHA256(authKey, timestamp.METHOD.path.sha256(body)).
 * Amounts are in minor units (100 = 1 AZN). Responses use the envelope
 * { status, meta: { message, developerMessage, requestTrackId }, data, errors }.
 */
class FpayPaymentMethod extends AbstractPaymentMethod
{
    private const PENDING_STATUSES = ['INITIAL', 'WAITING_FOR_PAYMENT', 'IN_PROGRESS'];
    private const REFUNDED_STATUSES = ['REFUNDED', 'PARTIAL_REFUNDED', 'REVERSED'];

    public function getName(): string
    {
        return 'fpay';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig(
            'display_name',
            LangService::instance()->setDefault('FPay')->getLang('payment_method_' . $this->getName())
        );
    }

    public function getToken(Order $order): string
    {
        return OrderService::instance()->getPaymentToken($order, $this->getName());
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->amount_final)) {
            return PaymentResult::failed(
                LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount')
            );
        }

        try {
            $this->log('info', "Processing FPay payment for order: {$order->uuid}");

            if ($this->isTestMode()) {
                return $this->mockPaymentResponse($order);
            }

            $requestBody = [
                'merchantName' => $this->getConfig('merchant_name'),
                'merchantTransactionId' => $order->uuid,
                'amount' => (int) $order->getRawOriginal('amount_final'),
                'transactionType' => 'SMS',
                'paymentMethod' => 'CARD',
                'lang' => LangService::instance()->getCurrentLang(),
                // FPay appends /{paymentKey} to both URLs
                'callbackUrl' => url('/payment/fpay-return'),
                'webhookUrl' => url('/payment/fpay-webhook'),
                'description' => 'ORDER-' . $order->uuid,
                'customer' => $this->buildCustomer($order),
            ];

            $response = $this->apiRequest('/get-payment-key', $requestBody);
            $paymentData = $response['data'] ?? [];

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECKOUT,
                status: $this->isOk($response) ? 'success' : 'failed',
                transactionId: $paymentData['paymentKey'] ?? null,
                request: $requestBody,
                response: $response,
            );

            if ($this->isOk($response) && isset($paymentData['paymentKey'], $paymentData['redirectUrl'])) {
                return PaymentResult::pending(
                    transactionId: $paymentData['paymentKey'],
                    message: LangService::instance()
                        ->setDefault('Redirecting to @payment_method for payment')
                        ->getLang('payment_redirecting', ['payment_method' => $this->getDisplayName()]),
                    metadata: $response,
                    redirectUrl: $paymentData['redirectUrl'],
                );
            }

            $this->log('error', 'FPay get-payment-key error', ['response' => $response]);
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
            );

        } catch (\Exception $e) {
            $this->log('error', 'FPay payment exception: ' . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error')
            );
        }
    }

    public function processCallback(array $data): PaymentResult
    {
        // FPay notifies via GET {webhookUrl}/{paymentKey} with no body; handled by the webhook route
        return PaymentResult::failed('Not applicable for FPay');
    }

    public function checkStatus(Order $order): PaymentResult
    {
        try {
            $paymentKey = $order->transaction_id;

            if (!$paymentKey) {
                return PaymentResult::failed(
                    LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
                );
            }

            $requestBody = ['paymentKey' => $paymentKey];

            $response = $this->apiRequest('/result', $requestBody);
            $paymentData = $response['data'] ?? [];
            $status = $paymentData['status'] ?? null;

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECK_STATUS,
                status: $status === 'SUCCESS' ? 'success' : 'failed',
                transactionId: $paymentData['paymentKey'] ?? $paymentKey,
                request: $requestBody,
                response: $response,
            );

            if ($status === 'SUCCESS') {
                return PaymentResult::success(
                    transactionId: $paymentData['paymentKey'] ?? $paymentKey,
                    message: LangService::instance()->setDefault('Payment approved')->getLang('payment_approved'),
                    metadata: $paymentData,
                );
            }

            // Already refunded/reversed — the payment did go through; never mark it as failed
            if (in_array($status, self::REFUNDED_STATUSES, true)) {
                return PaymentResult::success(
                    transactionId: $paymentData['paymentKey'] ?? $paymentKey,
                    message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                    metadata: $paymentData,
                    isRefund: true,
                );
            }

            // Not final yet, or the request itself failed (network, 5xx) — keep the order pending
            if ($status === null || in_array($status, self::PENDING_STATUSES, true)) {
                return PaymentResult::pending(
                    transactionId: $paymentKey,
                    message: LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending'),
                    metadata: $response,
                );
            }

            // ERROR, FAILED, CANCELED, TIMEOUT
            return PaymentResult::failed(
                ($paymentData['failureReason'] ?? '') ?: LangService::instance()->setDefault('Payment failed')->getLang('payment_failed'),
                $paymentData,
            );

        } catch (\Exception $e) {
            $this->log('error', 'FPay checkStatus exception: ' . $e->getMessage());
            return PaymentResult::pending(
                transactionId: $order->transaction_id,
                message: LangService::instance()->setDefault('Status check error')->getLang('payment_status_check_error'),
            );
        }
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        try {
            $paymentKey = $order->transaction_id;

            if (!$paymentKey) {
                return PaymentResult::failed(
                    LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
                );
            }

            $refundAmount = $amount ?? (int) $order->getRawOriginal('amount_final');

            // /refund handles both full and partial; /reverse is full-only and meant for voids
            $requestBody = [
                'paymentKey' => $paymentKey,
                'amount' => $refundAmount,
            ];

            $response = $this->apiRequest('/refund', $requestBody);
            $paymentData = $response['data'] ?? [];
            $status = $paymentData['status'] ?? null;
            $isRefunded = $this->isOk($response) && in_array($status, ['REFUNDED', 'PARTIAL_REFUNDED'], true);

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::REFUND,
                status: $isRefunded ? 'success' : 'failed',
                transactionId: $paymentData['paymentKey'] ?? $paymentKey,
                request: $requestBody,
                response: $response,
            );

            if ($isRefunded) {
                return PaymentResult::success(
                    transactionId: $paymentData['paymentKey'] ?? $paymentKey,
                    message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                    metadata: $response,
                    isRefund: true,
                    amount: $refundAmount,
                );
            }

            $this->log('error', 'FPay refund error', ['response' => $response]);

            // Surface FPay's reason (e.g. "amount exceeds the refundable remainder") to the admin
            $message = LangService::instance()->setDefault('Payment refund failed')->getLang('payment_refund_failed');
            if (!empty($response['meta']['message'])) {
                $message .= ': ' . $response['meta']['message'];
            }

            return PaymentResult::failed($message, $response);

        } catch (\Exception $e) {
            $this->log('error', 'FPay refund exception: ' . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Refund error')->getLang('payment_refund_error')
            );
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
            'api_url' => 'string',
            'display_name' => 'string',
            'username' => 'string',
            'auth_key' => 'string',
            'merchant_name' => 'string',
            'icon' => 'string',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'test_mode' => 'boolean',
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'api_url' => 'https://ecommerce.fpay.az',
            'display_name' => 'FPay',
            'username' => '',
            'auth_key' => '',
            'merchant_name' => '',
            'icon' => 'fa-credit-card',
            'min_amount' => 50,
            'max_amount' => 10000000,
            'test_mode' => false,
        ];
    }

    private function buildCustomer(Order $order): array
    {
        $user = $order->user;

        $fullName = trim(($user?->name ?? '') . ' ' . ($user?->surname ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($order->phone ?: $user?->phone));

        $customer = [
            'fullName' => $fullName !== '' ? $fullName : 'Carfy Customer',
            'phone' => $phone !== '' ? '+' . $phone : '',
        ];

        if ($user?->email) {
            $customer['email'] = $user->email;
        }

        return $customer;
    }

    private function isOk(array $response): bool
    {
        return ($response['status'] ?? null) === 'OK';
    }

    /**
     * Signed POST. The body is serialized once and the exact same bytes are
     * hashed and sent — re-serializing after signing breaks the signature.
     */
    private function apiRequest(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signing = $timestamp . '.POST.' . $path . '.' . hash('sha256', $body);

        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Api-Key' => $this->getConfig('username'),
                'X-Timestamp' => $timestamp,
                'X-Signature' => hash_hmac('sha256', $signing, $this->getConfig('auth_key')),
            ])
            ->withBody($body, 'application/json')
            ->post(rtrim($this->getConfig('api_url'), '/') . $path);

        return $response->json() ?? ['status' => 'HTTP_' . $response->status(), 'meta' => ['message' => $response->body()]];
    }

    private function mockPaymentResponse(Order $order): PaymentResult
    {
        return PaymentResult::pending(
            transactionId: 'TEST_FPAY_' . Str::uuid() . '_' . $order->id,
            message: LangService::instance()->setDefault('Test payment successful')->getLang('test_payment_successful'),
            metadata: ['test_mode' => true],
            redirectUrl: config('app.frontend_url') . '/payment-status?order=' . $order->uuid
                . '&method=' . $this->getName()
                . '&token=' . $this->getToken($order),
        );
    }
}
