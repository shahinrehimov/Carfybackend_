<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RecentBuyersTarget implements NotificationTargetingInterface
{
    public function __construct(private readonly int $days)
    {
    }

    public function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('orders', function (Builder $query) {
                $query->where('created_at', '>=', now()->subDays($this->days));
            });
    }
}
