<?php

namespace App\Services\Payment\Webhooks;

use App\Services\LangService;
use App\Services\LoggerService;
use App\Services\Payment\PaymentMethodFactory;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class WebhookDispatcher
{
    public function __construct(
        private PaymentMethodFactory $factory,
        private PaymentService $paymentService
    ) {}

    public function handle(Request $request, string $paymentMethod): array
    {
        try {
            LoggerService::instance()->log("Webhook received for payment method: {$paymentMethod}", [
                'headers' => $request->headers->all(),
                'payload' => $request->all()
            ], true);

            $method = $this->factory->createMethod($paymentMethod);
            $result = $method->handleWebhook($request);

            // Additional processing if needed
            $this->processWebhookResult($request, $paymentMethod, $result);

            return $result;

        } catch (\Exception $e) {
            LoggerService::instance()->log("Webhook handling failed for {$paymentMethod}: " . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ], true, 'error');

            return [
                'success' => false,
                'error' => LangService::instance()->setDefault('Webhook processing failed')->getLang('webhook_processing_failed'),
            ];
        }
    }

    private function processWebhookResult(Request $request, string $paymentMethod, array $result): void
    {
        // Additional webhook processing logic
        // - Order status update
        // - Email notification
        // - Analytics tracking
        // - Third-party integrations

        if (isset($result['order_id']) && isset($result['status'])) {
            LoggerService::instance()->log("Processing webhook result for order {$result['order_id']}", [
                'payment_method' => $paymentMethod,
                'status' => $result['status']
            ], true);
        }
    }

    public function verifyWebhookSignature(Request $request, string $paymentMethod): bool
    {
        try {
            $method = $this->factory->createMethod($paymentMethod);

            if (method_exists($method, 'verifyWebhookSignature')) {
                return $method->verifyWebhookSignature($request);
            }

            // Default signature verification
            return true;

        } catch (\Exception $e) {
            LoggerService::instance()->log("Webhook signature verification failed: " . $e->getMessage(), [], true, 'error');
            return false;
        }
    }
}
