<?php

namespace App\Services;

// TODO: Replace with real shipping provider API when available.
// The real API will accept company location + user address and return actual shipping cost.
class FakeShippingService
{
    // 3.00 AZN per seller — stored in dollars to match the rest of the price system
    private const SHIPPING_AMOUNT = 3.00;

    public function calculateForCompany(int $companyId): float
    {
        return self::SHIPPING_AMOUNT;
    }
}
