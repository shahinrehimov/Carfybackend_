<?php

namespace App\Services\Payment\Methods;

use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FpayPaymentMethod extends AbstractPaymentMethod
{
    private const TOKEN_CACHE_TTL = 3300; // 55 minutes

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

            $session = $this->getSession();

            if (!$session) {
                return PaymentResult::failed(
                    LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
                );
            }

            $merchantName = $this->getConfig('merchant_name');
            $amount = $order->getRawOriginal('amount_final');
            $description = 'ORDER-' . $order->uuid;
            $callbackUrl = url('/payment/fpay-return/'); // FPay appends paymentKey automatically

            $requestBody = [
                'merchantName' => $merchantName,
                'amount' => $amount,
                'lang' => LangService::instance()->getCurrentLang(),
                'callbackUrl' => $callbackUrl,
                'description' => $description,
                'webUserId' => $session['webUserId'],
                'token' => $session['token'],
                'hashCode' => $this->buildHashCode($merchantName, (string)$amount, $description),
            ];

            $response = $this->apiRequest('POST', '/fizzapay/fpay/GetPaymentKey', $requestBody);

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECKOUT,
                status: ($response['status']['statusCode'] ?? 0) == 1 ? 'success' : 'failed',
                transactionId: $response['paymentKey'] ?? null,
                request: $requestBody,
                response: $response,
            );

            if (($response['status']['statusCode'] ?? 0) == 1 && isset($response['paymentKey'], $response['redirectUrl'])) {
                return PaymentResult::pending(
                    transactionId: $response['paymentKey'],
                    message: LangService::instance()
                        ->setDefault('Redirecting to @payment_method for payment')
                        ->getLang('payment_redirecting', ['payment_method' => $this->getDisplayName()]),
                    metadata: $response,
                    redirectUrl: $response['redirectUrl'],
                );
            }

            $this->log('error', "FPay GetPaymentKey error", ['response' => $response]);
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
            );

        } catch (\Exception $e) {
            $this->log('error', "FPay payment exception: " . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error')
            );
        }
    }

    public function processCallback(array $data): PaymentResult
    {
        // FPay uses browser redirect callbacks, not server-side POST
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

            $requestBody = [
                'paymentKey' => $paymentKey,
                'hashCode' => $this->buildHashCode($paymentKey),
            ];

            $response = $this->apiRequest('POST', '/fizzapay/fpay/GetPaymentResult', $requestBody);

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECK_STATUS,
                status: ($response['status']['statusCode'] ?? 0) == 1 ? 'success' : 'failed',
                transactionId: $response['paymentKey'] ?? null,
                request: $requestBody,
                response: $response,
            );

            if (($response['status']['statusCode'] ?? 0) == 1) {
                return PaymentResult::success(
                    transactionId: $response['paymentKey'] ?? null,
                    message: LangService::instance()->setDefault('Payment approved')->getLang('payment_approved'),
                    metadata: $response,
                );
            }

            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending')
            );

        } catch (\Exception $e) {
            $this->log('error', "FPay checkStatus exception: " . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Status check error')->getLang('payment_status_check_error')
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

            $refundAmount = $amount ?? $order->getRawOriginal('amount_final');

            $requestBody = [
                'paymentKey' => $paymentKey,
                'amount' => $refundAmount,
            ];

            $response = $this->apiRequest('POST', '/fizzapay/utility/ReverseTransaction', $requestBody);

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::REFUND,
                status: ($response['status']['statusCode'] ?? 0) == 1 ? 'success' : 'failed',
                transactionId: $response['paymentKey'] ?? null,
                request: $requestBody,
                response: $response,
            );

            if (($response['status']['statusCode'] ?? 0) == 1) {
                return PaymentResult::success(
                    transactionId: $response['paymentKey'] ?? null,
                    message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                    metadata: $response,
                    isRefund: true,
                    amount: $refundAmount,
                );
            }

            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment refund failed')->getLang('payment_refund_failed')
            );

        } catch (\Exception $e) {
            $this->log('error', "FPay refund exception: " . $e->getMessage());
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
            'password' => 'string',
            'secret_key' => 'string',
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
            'api_url' => 'https://payments.fpay.az',
            'display_name' => 'FPay',
            'username' => '',
            'password' => '',
            'secret_key' => '',
            'merchant_name' => '',
            'icon' => 'fa-credit-card',
            'min_amount' => 50,
            'max_amount' => 10000000,
            'test_mode' => false,
        ];
    }

    private function getSession(): ?array
    {
        $cacheKey = 'fpay_session_' . md5($this->getConfig('username'));

        $session = Cache::get($cacheKey);

        if ($session) {
            return $session;
        }

        $session = $this->login();

        if ($session) {
            Cache::put($cacheKey, $session, self::TOKEN_CACHE_TTL);
        }

        return $session;
    }

    private function login(): ?array
    {
        $response = $this->apiRequest('POST', '/fizzapay/login/LoginWebUser', [
            'username' => $this->getConfig('username'),
            'password' => $this->getConfig('password'),
        ]);

        if (($response['status']['statusCode'] ?? 0) == 1 && isset($response['token'], $response['webUserId'])) {
            return [
                'webUserId' => $response['webUserId'],
                'token' => $response['token'],
            ];
        }

        $this->log('error', "FPay login failed", ['response' => $response]);
        return null;
    }

    private function buildHashCode(string ...$parts): string
    {
        return md5($this->getConfig('secret_key') . implode('', $parts));
    }

    private function apiRequest(string $method, string $path, array $body): array
    {
        $response = Http::timeout(30)
            ->withBasicAuth($this->getConfig('username'), $this->getConfig('password'))
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->send($method, $this->getConfig('api_url') . $path, ['json' => $body]);

        return $response->json() ?? [];
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
