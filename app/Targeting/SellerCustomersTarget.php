<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SellerCustomersTarget implements NotificationTargetingInterface
{
    public function __construct(private readonly int $companyId)
    {
    }

    public function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('orders', function (Builder $query) {
                $query->whereHas('items', function (Builder $q) {
                    $q->whereHas('companyProductVariant', function (Builder $inner) {
                        $inner->where('company_id', $this->companyId);
                    });
                });
            });
    }
}
