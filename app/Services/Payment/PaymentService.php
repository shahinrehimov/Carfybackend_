<?php

namespace App\Services\Payment;

use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\Models\PaymentLog;
use App\DTOs\Payment\PaymentResult;
use App\Services\BasketService;
use App\Services\LangService;
use App\Services\OrderService;
use App\Services\LoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class PaymentService
{
    public function __construct(private PaymentMethodFactory $factory)
    {}

    public function processPayment(Order $order, array $paymentData = []): PaymentResult
    {
        try {
            $paymentMethod = $this->factory->createMethod($order->payment_method);
        } catch (\Exception $e) {
            Log::error("Invalid payment method for order {$order->uuid}: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid payment method')->getLang('payment_invalid_method'));
        }

        if (!$paymentMethod->isEnabled()) {
            return PaymentResult::failed(LangService::instance()->setDefault('Payment method is disabled')->getLang('payment_method_disabled'));
        }

        if (!$paymentMethod->validatePaymentData($paymentData)) {
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid payment data')->getLang('payment_invalid_data'));
        }

        DB::beginTransaction();

        try {
            $result = $paymentMethod->processPayment($order, $paymentData);

            if ($result->success) {
                $order->update([
                    'status' => OrderStatusEnum::PROCESSING,
                    'transaction_id' => $result->transactionId,
                ]);

                // Instant-success methods (e.g. cash) are confirmed here, so clear the basket now.
                BasketService::instance()->clearBasketForOrder($order);

                LoggerService::instance()->log("Payment completed for order {$order->uuid}", [
                    'transaction_id' => $result->transactionId,
                    'payment_method' => $order->payment_method,
                ], true);
            } elseif ($result->isPending()) {
                $order->update([
                    'status' => OrderStatusEnum::PAYMENT_PENDING,
                    'payment_status' => OrderPaymentStatusEnum::PENDING,
                    'transaction_id' => $result->transactionId,
                ]);

                LoggerService::instance()->log("Payment pending for order {$order->uuid}", [
                    'transaction_id' => $result->transactionId,
                    'payment_method' => $order->payment_method,
                ], true);
            } else {
                $order->update([
                    'status' => OrderStatusEnum::FAILED,
                    'payment_status' => OrderPaymentStatusEnum::FAILED,
                ]);

                // Payment failed immediately: release the stock reserved at checkout.
                OrderService::instance()->restoreStock($order);

                LoggerService::instance()->log("Payment failed for order {$order->uuid}", [
                    'payment_method' => $order->payment_method,
                    'error' => $result->message,
                ], true, 'warning');
            }

            PaymentLog::create([
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'action' => PaymentLogActionEnum::CHECKOUT,
                'transaction_id' => $result->transactionId,
                'status' => $result->success ? 'success' : ($result->isPending() ? 'pending' : 'failed'),
                'order_status' => $order->status->value,
                'response' => $result->metadata ?? [],
            ]);

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            LoggerService::instance()->log("Payment processing failed for order {$order->uuid}: " . $e->getMessage(), [], true, 'error');

            return PaymentResult::failed(LangService::instance()->setDefault('Payment processing error')->getLang('payment_processing_error'));
        }
    }

    public function processCallback(string $paymentMethodName, array $data): PaymentResult
    {
        try {
            $paymentMethod = $this->factory->createMethod($paymentMethodName);
        } catch (\Exception $e) {
            Log::error("Invalid payment method name {$paymentMethodName}: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid payment method')->getLang('payment_invalid_method'));
        }

        if (!$paymentMethod->isEnabled()) {
            return PaymentResult::failed(LangService::instance()->setDefault('Payment method is disabled')->getLang('payment_method_disabled'));
        }

        $result = $paymentMethod->processCallback($data);

        if ($result->order) {
            LoggerService::instance()->log("Payment callback received for order {$result->order->uuid}", [
                'payment_method' => $paymentMethodName,
                'transaction_id' => $result->transactionId,
                'success' => $result->success,
            ], true);

            PaymentLog::create([
                'order_id' => $result->order->id,
                'payment_method' => $paymentMethodName,
                'action' => PaymentLogActionEnum::CALLBACK,
                'transaction_id' => $result->transactionId,
                'status' => $result->success ? 'success' : 'failed',
                'order_status' => $result->order->status->value,
                'request' => $data,
                'response' => $result->metadata ?? [],
            ]);
        }

        return $result;
    }

    public function checkStatus(Order $order): PaymentResult
    {
        try {
            $paymentMethod = $this->factory->createMethod($order->payment_method);
        } catch (\Exception $e) {
            Log::error("Invalid payment method for order {$order->uuid}: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid payment method')->getLang('payment_invalid_method'));
        }

        if (!$paymentMethod->isEnabled()) {
            return PaymentResult::failed(LangService::instance()->setDefault('Payment method is disabled')->getLang('payment_method_disabled'));
        }

        $result = $paymentMethod->checkStatus($order);

        LoggerService::instance()->log("Payment status checked for order {$order->uuid}", [
            'transaction_id' => $result->transactionId,
            'status' => $result->success ? 'success' : 'failed',
        ], true);

        PaymentLog::create([
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'action' => PaymentLogActionEnum::CHECK_STATUS,
            'transaction_id' => $result->transactionId,
            'status' => $result->success ? 'success' : 'failed',
            'order_status' => $order->status->value,
            'response' => $result->metadata ?? [],
        ]);

        return $result;
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        try {
            $paymentMethod = $this->factory->createMethod($order->payment_method);
        } catch (\Exception $e) {
            Log::error("Invalid payment method for order {$order->uuid}: " . $e->getMessage());
            return PaymentResult::failed(LangService::instance()->setDefault('Invalid payment method')->getLang('payment_invalid_method'));
        }

        if (!$paymentMethod->isEnabled()) {
            return PaymentResult::failed(LangService::instance()->setDefault('Payment method is disabled')->getLang('payment_method_disabled'));
        }

        $result = $paymentMethod->refund($order, $amount, $comment);

        LoggerService::instance()->log("Refund processed for order {$order->uuid}", [
            'amount' => $amount,
            'comment' => $comment,
            'success' => $result->success,
        ], true, $result->success ? 'info' : 'error');

        // DB log is written by the caller (OrderService::refund or processItemCancellation)
        // with the correct action (REFUND vs PARTIAL_REFUND).

        return $result;
    }

    public function getAvailablePaymentMethods(): array
    {
        return PaymentMethodFactory::getEnabledMethods();
    }

    public function getPaymentMethodsForOrder(Order $order): array
    {
        $methods = $this->getAvailablePaymentMethods();
        $availableMethods = [];

        foreach ($methods as $name => $method) {
            $minAmount = $method->getMinimumAmount();
            $maxAmount = $method->getMaximumAmount();

            if ($minAmount && $order->amount_final < $minAmount) {
                continue;
            }

            if ($maxAmount && $order->amount_final > $maxAmount) {
                continue;
            }

            $availableMethods[$name] = $method;
        }

        return $availableMethods;
    }

    public function verifyPayment(Order $order, string $transactionId): PaymentResult
    {
        try {
            $paymentMethod = $this->factory->createMethod($order->payment_method);

            $result = $paymentMethod->verifyPayment($order, $transactionId);

            if ($result->success) {
                $order->update([
                    'payment_status' => OrderPaymentStatusEnum::PAID,
                    'transaction_id' => $transactionId
                ]);

                LoggerService::instance()->log("Payment verified for order {$order->uuid}", [
                    'transaction_id' => $transactionId
                ], true);
            }

            return $result;

        } catch (\Exception $e) {
            LoggerService::instance()->log("Payment verification failed: " . $e->getMessage(), [], true, 'error');
            return PaymentResult::failed(LangService::instance()->setDefault('Payment verification error')->getLang('payment_verification_error'));
        }
    }

    public function handleWebhook(string $paymentMethod, $request): array
    {
        try {
            $method = $this->factory->createMethod($paymentMethod);
            return $method->handleWebhook($request);
        } catch (\Exception $e) {
            LoggerService::instance()->log("Webhook handling failed for {$paymentMethod}: " . $e->getMessage(), [], true, 'error');
            return ['error' => LangService::instance()->setDefault('Webhook processing failed')->getLang('webhook_processing_failed')];
        }
    }

    public function getPaymentMethodDetails(string $methodName): ?array
    {
        try {
            $method = $this->factory->createMethod($methodName);

            if (!$method->isEnabled()) {
                return null;
            }

            return [
                'name' => $method->getName(),
                'display_name' => $method->getDisplayName(),
                'icon' => $method->getIcon(),
                'required_fields' => $method->getRequiredFields(),
                'min_amount' => $method->getMinimumAmount(),
                'max_amount' => $method->getMaximumAmount(),
            ];
        } catch (\Exception) {
            return null;
        }
    }
}
