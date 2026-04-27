<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SpecificUsersTarget implements NotificationTargetingInterface
{
    public function __construct(private readonly array $userIds)
    {
    }

    public function query(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('id', $this->userIds);
    }
}
