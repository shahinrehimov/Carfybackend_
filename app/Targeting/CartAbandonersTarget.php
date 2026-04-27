<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CartAbandonersTarget implements NotificationTargetingInterface
{
    public function __construct(private readonly int $noOrderDays = 14)
    {
    }

    public function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('baskets', function (Builder $query) {
                $query->whereHas('items');
            })
            ->whereDoesntHave('orders', function (Builder $query) {
                $query->where('created_at', '>=', now()->subDays($this->noOrderDays));
            });
    }
}
