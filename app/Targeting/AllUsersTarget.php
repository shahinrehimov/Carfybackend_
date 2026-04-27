<?php

namespace App\Targeting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AllUsersTarget implements NotificationTargetingInterface
{
    public function query(): Builder
    {
        return User::query()->where('is_active', true);
    }
}
