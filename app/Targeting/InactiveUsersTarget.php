<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InactiveUsersTarget implements NotificationTargetingInterface
{
    public function __construct(private readonly int $noOrderDays = 60)
    {
    }

    public function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereDoesntHave('orders', function (Builder $query) {
                $query->where('created_at', '>=', now()->subDays($this->noOrderDays));
            });
    }
}
