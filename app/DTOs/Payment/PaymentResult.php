<?php

namespace App\DTOs\Payment;

use App\Models\Order;

readonly class PaymentResult
{
    public function __construct(
        public bool    $success,
        public ?string $transactionId = null,
        public ?string $message = null,
        public ?array  $metadata = null,
        public ?string $redirectUrl = null,
        public bool    $isPending = false,
        public bool    $isRefund = false,
        public ?int    $amount = null,
        public ?Order  $order = null,
    )
    {
    }

    public static function success(
        string $transactionId = null,
        string $message = null,
        array  $metadata = [],
        string $redirectUrl = null,
        bool   $isRefund = false,
        int    $amount = null,
        Order  $order = null,
    ): self
    {
        return new self(true, $transactionId, $message, $metadata, $redirectUrl, false, $isRefund, $amount, $order);
    }

    public static function failed(string $message, array $metadata = [], ?Order $order = null): self
    {
        return new self(false, null, $message, $metadata, null, false, false, null, $order);
    }

    public static function pending(
        string $transactionId = null,
        string $message = null,
        array  $metadata = [],
        string $redirectUrl = null,
        ?Order $order = null,
    ): self
    {
        return new self(false, $transactionId, $message, $metadata, $redirectUrl, true, false, null, $order);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'redirect_url' => $this->redirectUrl,
            'is_pending' => $this->isPending,
            'is_refund' => $this->isRefund,
            'amount' => $this->amount,
            'order' => $this->order?->id ?? null,
        ];
    }

    public function isPending(): bool
    {
        return $this->isPending;
    }

    public function needsRedirect(): bool
    {
        return !empty($this->redirectUrl);
    }
}
