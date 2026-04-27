<?php

namespace App\Services\Payment\Methods;

use App\Enum\OrderStatusEnum;
use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use App\Services\LangService;
use App\Services\LoggerService;
use App\Services\OrderService;
use Flitt\Checkout;
use Flitt\Configuration;
use Flitt\Order as FlittOrder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FlittPaymentMethod extends AbstractPaymentMethod
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        Configuration::setMerchantId($this->getConfig('merchant_id'));
        Configuration::setSecretKey($this->getConfig('api_key'));
    }

    public function getName(): string
    {
        return 'flitt';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', LangService::instance()->setDefault('Flitt')->getLang('payment_method_' . $this->getName()));
    }

    public function getToken(Order $order): string
    {
        return OrderService::instance()->getPaymentToken($order, $this->getName());
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (!$this->validateAmount($order->amount_final)) {
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid amount for payment')->getLang('payment_invalid_amount'));
        }

        try {
            $this->log('info', "Processing Flitt payment for order: {$order->uuid}");

            if ($this->isTestMode()) {
                return $this->mockPaymentResponse($order);
            }

            $checkoutData = [
                'order_id' => $order->uuid,
                'amount' => $order->getRawOriginal('amount_final'), // in cents
                'currency' => 'GEL',
                'order_desc' => 'Payment',
                'response_url' => config('app.frontend_url') . '/' . LangService::instance()->getCurrentLang() . '/payment-status?order=' . $order->uuid . '&method=' . $this->getName() . '&token=' . $this->getToken($order),
                'server_callback_url' => route('payment.callback', ['paymentMethodName' => $this->getName()]),
                'lang' => LangService::instance()->getCurrentLang(),
                'lifetime' => 3600,
                'merchant_data' => json_encode([
                    'user_id' => $order->user_id,
                    'metadata' => [],
                ]),
            ];

            $checkout = Checkout::url($checkoutData);

            $data = $checkout->getData();

            $this->log('info', "Flitt payment initialized", $data);
            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECKOUT,
                status: $data['response_status'] ?? '',
                transactionId: $data['payment_id'] ?? null,
                request: $checkoutData,
                response: $data,
            );

            if ($data['response_status'] === 'success') {
                return PaymentResult::pending(
                    transactionId: $data['payment_id'],
                    message: LangService::instance()->setDefault('Redirecting to @payment_method for payment')->getLang('payment_redirecting', ['payment_method' => $this->getDisplayName()]),
                    metadata: $data,
                    redirectUrl: $checkout->getUrl(),
                );
            }

            $this->log('error', "Flitt API error", ['response' => $data]);
            return PaymentResult::failed(LangService::instance()->setDefault('Payment gateway error')->getLang('payment_gateway_error'));
        } catch (\Exception $e) {
            $this->log('error', "Flitt payment exception: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error'));
        }
    }

    public function processCallback(array $data): PaymentResult
    {
        try {
            LoggerService::instance()->log('Flitt callback received', ['data' => $data], true);

            if (!isset($data['order_id'])) {
                LoggerService::instance()->log('Flitt processCallback error: Missing order_id in callback data', [], true);
                throw new BadRequestHttpException('Missing order_id in callback data');
            }

            $order = OrderService::instance()->getOrderByUuid($data['order_id']);

            if (!$order) {
                LoggerService::instance()->log('Flitt processCallback error: Order not found: ' . $data['order_id'], [], true);
                throw new BadRequestHttpException('Order not found');
            }

            if ($order->status !== OrderStatusEnum::PAYMENT_PENDING) {
                LoggerService::instance()->log('Flitt processCallback error: Order status is not pending: ' . $data['order_id'] . '. Current status: ' . $order->status->value, [], true);
                throw new BadRequestHttpException('Order status is not pending');
            }

            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CALLBACK,
                status: $data['response_status'] ?? '',
                orderStatus: $data['order_status'] ?? '',
                transactionId: $data['payment_id'] ?? null,
                response: $data,
            );

            if (($data['response_status'] ?? '') === 'success') {
                if (($data['order_status'] ?? '') === 'created') {
                    return PaymentResult::pending(
                        transactionId: $data['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment created')->getLang('payment_created'),
                        metadata: $data,
                        order: $order,
                    );
                }

                if (($data['order_status'] ?? '') === 'approved') {
                    return PaymentResult::success(
                        transactionId: $data['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment approved')->getLang('payment_approved'),
                        metadata: $data,
                        order: $order,
                    );
                }

                if (($data['order_status'] ?? '') === 'reversed') {
                    return PaymentResult::success(
                        transactionId: $data['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                        metadata: $data,
                        isRefund: true,
                        order: $order,
                    );
                }
            }

            return PaymentResult::failed(
                message: LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending'),
                order: $order,
            );
        } catch (\Exception $e) {
            $this->log('error', "Flitt callback error: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Callback error')->getLang('payment_callback_error'));
        }
    }

    public function checkStatus(Order $order): PaymentResult
    {
        try {
            $statusData = [
                'order_id' => $order->uuid,
            ];

            $response = FlittOrder::status($statusData);

            $responseData = $response->getData();

            $this->log('info', "Flitt API checkStatus", ['order_id' => $order->id, 'response' => $responseData]);
            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::CHECK_STATUS,
                status: $responseData['response_status'] ?? '',
                orderStatus: $responseData['order_status'] ?? '',
                transactionId: $responseData['payment_id'] ?? null,
                request: $statusData,
                response: $responseData,
            );

            if (($responseData['response_status'] ?? '') === 'success') {
                if (($responseData['order_status'] ?? '') === 'created') {
                    return PaymentResult::pending(
                        transactionId: $responseData['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment created')->getLang('payment_created'),
                        metadata: $responseData,
                    );
                }

                if (($responseData['order_status'] ?? '') === 'approved') {
                    return PaymentResult::success(
                        transactionId: $responseData['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment approved')->getLang('payment_approved'),
                        metadata: $responseData,
                    );
                }

                if (($responseData['order_status'] ?? '') === 'reversed') {
                    return PaymentResult::success(
                        transactionId: $responseData['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                        metadata: $responseData,
                        isRefund: true,
                    );
                }
            }

            return PaymentResult::failed(LangService::instance()->setDefault('Payment not approved yet!')->getLang('payment_status_check_pending'));
        } catch (\Exception $e) {
            $this->log('error', "Flitt Status check error: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Status check error')->getLang('payment_status_check_error'));
        }
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        try {
            $refundData = [
                'order_id' => $order->uuid,
                'amount' => $order->getRawOriginal('amount_final'), // in cents
                'currency' => 'GEL',
                'comment' => $comment ?: 'Refund'
            ];

            $response = FlittOrder::reverse($refundData);

            $responseData = $response->getData();

            $this->log('info', "Flitt API refund", ['order_id' => $order->id, 'response' => $responseData]);
            $this->setPaymentLog(
                order: $order,
                action: PaymentLogActionEnum::REFUND,
                status: $responseData['response_status'] ?? '',
                orderStatus: $responseData['reverse_status'] ?? '',
                transactionId: $responseData['payment_id'] ?? null,
                request: $refundData,
                response: $responseData,
            );

            if (($responseData['response_status'] ?? '') === 'success') {
                if (($responseData['reverse_status'] ?? '') === 'approved') {
                    return PaymentResult::success(
                        transactionId: $responseData['payment_id'] ?? null,
                        message: LangService::instance()->setDefault('Payment reversed')->getLang('payment_reversed'),
                        metadata: $responseData,
                        isRefund: true,
                        amount: $responseData['reversal_amount'] ?? 0,
                    );
                }
            }

            return PaymentResult::failed(LangService::instance()->setDefault('Payment refund failed')->getLang('payment_refund_failed'));
        } catch (\Exception $e) {
            $this->log('error', "Flitt refund error: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Refund error')->getLang('payment_refund_error'));
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
            'min_amount' => 1, // 1 cent
            'max_amount' => 10000000, // 100,000 AZN
            'test_mode' => false,
        ];
    }

    private function mockPaymentResponse(Order $order): PaymentResult
    {
        return PaymentResult::pending(
            transactionId: 'TEST_' . $this->getName() . md5(Str::uuid()) . '_' . $order->id,
            message: LangService::instance()->setDefault('Test payment successful')->getLang('test_payment_successful'),
            metadata: ['test_mode' => true],
            redirectUrl: config('app.frontend_url') . '/payment-status?order=' . $order->uuid . '&method=' . $this->getName() . '&token=' . $this->getToken($order)
        );
    }
}
