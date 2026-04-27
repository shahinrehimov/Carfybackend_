<?php

namespace App\Services\Payment\Methods;

use App\DTOs\Payment\PaymentResult;
use App\Models\Order;
use App\Services\LangService;

class KapitalbankInstallmentPaymentMethod extends KapitalbankPaymentMethod
{
    public function getName(): string
    {
        return 'kapitalbank_installment';
    }

    public function getDisplayName(): string
    {
        return $this->getConfig('display_name', 'Kapital Bank Installment');
    }

    public function getRequiredFields(): array
    {
        return ['installment_months'];
    }

    public function getMetadata(): array
    {
        return [
            'available_months' => $this->getConfig('available_months', [3, 6, 12, 24]),
        ];
    }

    public function processPayment(Order $order, array $data = []): PaymentResult
    {
        if (empty($data['installment_months'])) {
            return PaymentResult::failed(
                LangService::instance()
                    ->setDefault('Installment months are required')
                    ->getLang('payment_installment_months_required')
            );
        }

        return parent::processPayment($order, $data);
    }

    protected function buildDescription(array $data): ?string
    {
        $months = (int) ($data['installment_months'] ?? 0);

        // Kapital Bank API requires this exact format in the description field
        return $months > 0 ? "TAKSIT={$months}" : null;
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'display_name' => 'Kapital Bank Installment',
            'available_months' => [3, 6, 12, 24],
        ]);
    }
}
