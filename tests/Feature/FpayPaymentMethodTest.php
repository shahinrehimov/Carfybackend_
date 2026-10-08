<?php

namespace Tests\Feature;

use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\Methods\FpayPaymentMethod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the FPAY Ecommerce API contract (https://docs.fpay.az): request
 * signing, the response envelope, status mapping and refunds.
 */
class FpayPaymentMethodTest extends TestCase
{
    private const AUTH_KEY = 'test-auth-key';
    private const PAYMENT_KEY = '3f2a9c1e-7b4d-4e8a-9c2f-1a2b3c4d5e6f';

    protected function setUp(): void
    {
        parent::setUp();

        // LangService reads these; empty tables make it fall back to default texts
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->softDeletes();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('language_id');
            $table->string('group');
            $table->string('key');
            $table->text('text')->nullable();
            $table->softDeletes();
        });
    }

    private function method(): FpayPaymentMethod
    {
        // payment_logs is not part of this test; skip the DB write
        return new class([
            'enabled' => true,
            'api_url' => 'https://ecommerce.fpay.az',
            'username' => 'carfy_user',
            'auth_key' => self::AUTH_KEY,
            'merchant_name' => 'carfy',
            'min_amount' => null,
            'max_amount' => null,
        ]) extends FpayPaymentMethod {
            public function setPaymentLog(Order $order, PaymentLogActionEnum $action, string $status, ?string $orderStatus = null, ?string $transactionId = null, ?array $request = null, ?array $response = null): void
            {
            }
        };
    }

    private function order(): Order
    {
        $user = new User();
        $user->setRawAttributes(['name' => 'Ali', 'surname' => 'Aliyev', 'email' => 'ali@example.com', 'phone' => '994501234567'], true);

        $order = new Order();
        $order->setRawAttributes([
            'id' => 311,
            'uuid' => 'f319912a-4d95-41b2-97bb-cb1945944f1f',
            'transaction_id' => self::PAYMENT_KEY,
            'amount_final' => 1052,
            'phone' => '994501234567',
        ], true);
        $order->setRelation('user', $user);

        return $order;
    }

    private function envelope(?array $data, string $status = 'OK', string $message = 'Success'): array
    {
        return [
            'status' => $status,
            'meta' => ['message' => $message, 'developerMessage' => strtolower($status), 'requestTrackId' => 'req_1'],
            'data' => $data,
            'errors' => [],
        ];
    }

    private function assertSigned(Request $request, string $path): void
    {
        $this->assertSame('carfy_user', $request->header('X-Api-Key')[0]);

        $timestamp = $request->header('X-Timestamp')[0];
        $expected = hash_hmac('sha256', $timestamp . '.POST.' . $path . '.' . hash('sha256', $request->body()), self::AUTH_KEY);

        $this->assertSame($expected, $request->header('X-Signature')[0]);
        $this->assertLessThanOrEqual(5, abs(time() - (int) $timestamp));
    }

    public function test_payment_key_request_is_signed_and_returns_redirect(): void
    {
        Http::fake(['ecommerce.fpay.az/get-payment-key' => Http::response($this->envelope([
            'paymentKey' => self::PAYMENT_KEY,
            'redirectUrl' => 'https://ecommerce.fpay.az/pay/' . self::PAYMENT_KEY,
        ]))]);

        $result = $this->method()->processPayment($this->order());

        $this->assertTrue($result->isPending());
        $this->assertSame(self::PAYMENT_KEY, $result->transactionId);
        $this->assertSame('https://ecommerce.fpay.az/pay/' . self::PAYMENT_KEY, $result->redirectUrl);

        Http::assertSent(function (Request $request) {
            $this->assertSigned($request, '/get-payment-key');
            $body = $request->data();

            return $body['amount'] === 1052
                && $body['merchantName'] === 'carfy'
                && $body['merchantTransactionId'] === 'f319912a-4d95-41b2-97bb-cb1945944f1f'
                && $body['customer'] === ['fullName' => 'Ali Aliyev', 'phone' => '+994501234567', 'email' => 'ali@example.com']
                && str_ends_with($body['callbackUrl'], '/payment/fpay-return')
                && str_ends_with($body['webhookUrl'], '/payment/fpay-webhook');
        });
    }

    public function test_refund_sends_amount_in_minor_units_and_succeeds(): void
    {
        Http::fake(['ecommerce.fpay.az/refund' => Http::response($this->envelope([
            'paymentKey' => self::PAYMENT_KEY,
            'status' => 'REFUNDED',
            'amount' => 0,
            'refundedAmount' => 1052,
        ]))]);

        $result = $this->method()->refund($this->order());

        $this->assertTrue($result->success);
        $this->assertTrue($result->isRefund);
        $this->assertSame(1052, $result->amount);

        Http::assertSent(function (Request $request) {
            $this->assertSigned($request, '/refund');

            return $request->url() === 'https://ecommerce.fpay.az/refund'
                && $request->data() === ['paymentKey' => self::PAYMENT_KEY, 'amount' => 1052];
        });
    }

    public function test_partial_refund_succeeds(): void
    {
        Http::fake(['ecommerce.fpay.az/refund' => Http::response($this->envelope([
            'paymentKey' => self::PAYMENT_KEY,
            'status' => 'PARTIAL_REFUNDED',
            'refundedAmount' => 500,
        ]))]);

        $result = $this->method()->refund($this->order(), 500);

        $this->assertTrue($result->success);
        $this->assertSame(500, $result->amount);
    }

    public function test_refund_failure_surfaces_fpay_reason(): void
    {
        Http::fake(['ecommerce.fpay.az/refund' => Http::response(
            $this->envelope(null, 'CONFLICT', 'Payment is not refundable in its current status'),
            409
        )]);

        $result = $this->method()->refund($this->order());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Payment is not refundable in its current status', $result->message);
    }

    public function test_check_status_maps_fpay_statuses(): void
    {
        $cases = [
            'SUCCESS' => ['success' => true, 'pending' => false, 'refund' => false],
            'WAITING_FOR_PAYMENT' => ['success' => false, 'pending' => true, 'refund' => false],
            'IN_PROGRESS' => ['success' => false, 'pending' => true, 'refund' => false],
            'ERROR' => ['success' => false, 'pending' => false, 'refund' => false],
            'CANCELED' => ['success' => false, 'pending' => false, 'refund' => false],
            'REFUNDED' => ['success' => true, 'pending' => false, 'refund' => true],
        ];

        $sequence = Http::sequence();
        foreach (array_keys($cases) as $status) {
            $sequence->push($this->envelope([
                'paymentKey' => self::PAYMENT_KEY,
                'status' => $status,
                'failureReason' => $status === 'ERROR' ? 'Insufficient funds' : '',
            ]));
        }
        Http::fake(['ecommerce.fpay.az/result' => $sequence]);

        foreach ($cases as $status => $expected) {
            $result = $this->method()->checkStatus($this->order());

            $this->assertSame($expected['success'], $result->success, $status);
            $this->assertSame($expected['pending'], $result->isPending(), $status);
            $this->assertSame($expected['refund'], $result->isRefund, $status);
        }
    }

    public function test_check_status_stays_pending_when_fpay_is_unreachable(): void
    {
        Http::fake(['ecommerce.fpay.az/result' => Http::response('Bad Gateway', 502)]);

        $result = $this->method()->checkStatus($this->order());

        // A gateway outage must never flip a paid order to FAILED
        $this->assertFalse($result->success);
        $this->assertTrue($result->isPending());
    }
}
