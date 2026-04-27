<?php

namespace App\Interfaces\Payment;

use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\DTOs\Payment\PaymentResult;
use Illuminate\Http\Request;

interface PaymentMethodInterface
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function getIcon(): ?string;

    public function isEnabled(): bool;

    public function processPayment(Order $order, array $data = []): PaymentResult;
    public function processCallback(array $data): PaymentResult;
    public function checkStatus(Order $order): PaymentResult;
    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult;

    public function validatePaymentData(array $data): bool;

    public function getRequiredFields(): array;

    public function getMetadata(): array;

    public function getConfigurationFields(): array;

    // with cents
    public function getMinimumAmount(): ?int;

    // with cents
    public function getMaximumAmount(): ?int;

    public function verifyPayment(Order $order, string $transactionId): PaymentResult;

    public function handleWebhook(Request $request): array;

    public function setPaymentLog(Order $order, PaymentLogActionEnum $action, string $status, ?string $orderStatus = null, ?string $transactionId = null, ?array $request = null, ?array $response = null): void;
}
