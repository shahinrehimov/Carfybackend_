<?php

namespace App\Services\Payment\Methods;

use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Http;

class KapitalbankPaymentMethod extends AbstractPaymentMethod
{
    public function getName(): string
    {
        return 'kapitalbank';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig(
            'display_name',
            LangService::instance()->setDefault('Kapital Bank')->getLang('payment_method_' . $this->getName())
        );
    }

    public function getToken(Order $order): string
    {
        return OrderService::instance()->getPaymentToken($order, $this->getName());
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->getRawOriginal('amount_final'))) {
            return PaymentResult::failed(
                LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount')
            );
        }

        try {
            $this->log('info', "Processing Kapital Bank payment for order: {$order->uuid}");

            if ($this->isTestMode()) {
                return $this->mockPaymentResponse($order);
            }

            $amount = $this->formatAmount($order->getRawOriginal('amount_final'));
            $description = $this->buildDescription($data);
            $callbackUrl = url('/payment/kapitalbank-return');

            $requestBody = [
                'order' => [
                    'typeRid' => 'Order_SMS',
                    'amount' => $amount,
                    'currency' => 'AZN',
                    'language' => $this->getConfig('language', 'az'),
                    'title' => $this->getConfig('title', 'Carfy'),
                    'description' => $description ?: 'ORDER-' . $order->uuid,
                    'hppRedirectUrl' => $callbackUrl,
                ],
            ];

            $response = $this->apiRequest('POST', '/order', $requestBody);

            $kapitalOrderId = $response['order']['id'] ?? null;
            $kapitalPassword = $response['order']['password'] ?? null;
            $hppUrl = $response['order']['hppUrl'] ?? $this->getConfig('hpp_url');

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECKOUT,
                status: $kapitalOrderId ? 'success' : 'failed',
                transactionId: $kapitalOrderId ? (string) $kapitalOrderId : null,
                request: $requestBody,
                response: $response,
            );

            if ($kapitalOrderId && $kapitalPassword && $hppUrl) {
                return PaymentResult::pending(
                    transactionId: (string) $kapitalOrderId,
                    message: LangService::instance()
                        ->setDefault('Redirecting to @payment_method for payment')
                        ->getLang('payment_redirecting', ['payment_method' => $this->getDisplayName()]),
                    metadata: ['kapitalbank_password' => $kapitalPassword],
                    redirectUrl: "{$hppUrl}?id={$kapitalOrderId}&password={$kapitalPassword}",
                );
            }

            $this->log('error', 'Kapital Bank create order failed', ['response' => $response]);
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
            );

        } catch (\Exception $e) {
            $this->log('error', 'Kapital Bank payment exception: ' . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error')
            );
        }
    }

    public function checkStatus(Order $order): PaymentResult
    {
        try {
            $kapitalOrderId = $order->transaction_id;

            if (!$kapitalOrderId) {
                return PaymentResult::failed(
                    LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
                );
            }

            $response = $this->apiRequest(
                'GET',
                "/order/{$kapitalOrderId}?tranDetailLevel=2&orderDetailLevel=2"
            );

            $status = $response['order']['status'] ?? null;

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECK_STATUS,
                status: $status ?? 'unknown',
                transactionId: $kapitalOrderId,
                response: $response,
            );

            return match ($status) {
                'FullyPaid' => PaymentResult::success(
                    transactionId: $kapitalOrderId,
                    message: LangService::instance()->setDefault('Payment approved')->getLang('payment_approved'),
                    metadata: $response['order'] ?? [],
                ),
                'PartiallyPaid', 'Authorized', 'Preparing' => PaymentResult::pending(
                    transactionId: $kapitalOrderId,
                    message: LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending'),
                ),
                'Refunded', 'Voided', 'Cancelled' => PaymentResult::failed(
                    LangService::instance()->setDefault('Payment cancelled')->getLang('payment_cancelled'),
                ),
                default => PaymentResult::failed(
                    LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending')
                ),
            };

        } catch (\Exception $e) {
            $this->log('error', 'Kapital Bank checkStatus exception: ' . $e->getMessage());
            return PaymentResult::failed(
                LangService::instance()->setDefault('Status check error')->getLang('payment_status_check_error')
            );
        }
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        try {
            $kapitalOrderId = $order->transaction_id;

            if (!$kapitalOrderId) {
                return PaymentResult::failed(
                    LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error')
                );
            }

            $refundAmount = $amount ?? $order->getRawOriginal('amount_final');
            $formattedAmount = $this->formatAmount($refundAmount);

            $requestBody = ['tran' => ['phase' => 'Single', 'type' => 'Refund', 'amount' => $formattedAmount]];

            $response = $this->apiRequest('POST', "/order/{$kapitalOrderId}/exec-tran", $requestBody);

            $success = isset($response['tran']['approvalCode']);

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::REFUND,
                status: $success ? 'success' : 'failed',
                transactionId: $kapitalOrderId,
                request: $requestBody,
                response: $response,
            );

            if ($success) {
                return PaymentResult::success(
                    transactionId: $kapitalOrderId,
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
            $this->log('error', 'Kapital Bank refund exception: ' . $e->getMessage());
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
            'hpp_url' => 'string',
            'display_name' => 'string',
            'username' => 'string',
            'password' => 'string',
            'language' => 'string',
            'title' => 'string',
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
            'api_url' => 'https://txpgtst.kapitalbank.az/api',
            'hpp_url' => 'https://txpgtst.kapitalbank.az/flex',
            'display_name' => 'Kapital Bank',
            'username' => '',
            'password' => '',
            'language' => 'az',
            'title' => 'Carfy',
            'icon' => 'fa-credit-card',
            'min_amount' => 50,
            'max_amount' => 10000000,
            'test_mode' => true,
        ];
    }

    protected function buildDescription(array $data): ?string
    {
        return null;
    }

    // Reserved for future use — not called currently.
    // To activate: replace the refund body with this when $allowReversal config is true.
    private function buildReversalBody(int $refundAmount, int $originalAmount): array
    {
        $isPartial = $refundAmount < $originalAmount;

        $body = ['tran' => ['phase' => 'Single', 'voidKind' => $isPartial ? 'Partial' : 'Full']];

        if ($isPartial) {
            $body['tran']['amount'] = $this->formatAmount($refundAmount);
        }

        return $body;
    }

    protected function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected function apiRequest(string $method, string $path, array $body = []): array
    {
        $request = Http::timeout(30)
            ->withBasicAuth($this->getConfig('username'), $this->getConfig('password'))
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json']);

        $url = rtrim($this->getConfig('api_url'), '/') . '/' . ltrim($path, '/');

        $response = $method === 'GET'
            ? $request->get($url)
            : $request->send($method, $url, ['json' => $body]);

        return $response->json() ?? [];
    }

    private function mockPaymentResponse(Order $order): PaymentResult
    {
        $fakeOrderId = rand(10000, 99999);

        return PaymentResult::pending(
            transactionId: (string) $fakeOrderId,
            message: LangService::instance()->setDefault('Test payment successful')->getLang('test_payment_successful'),
            metadata: ['test_mode' => true, 'kapitalbank_password' => 'testpassword'],
            redirectUrl: config('app.frontend_url') . '/payment-status?order=' . $order->uuid
                . '&method=' . $this->getName()
                . '&token=' . $this->getToken($order),
        );
    }
}
