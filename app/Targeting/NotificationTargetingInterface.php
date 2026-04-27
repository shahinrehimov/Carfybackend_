<?php

namespace App\Targeting;

use Illuminate\Database\Eloquent\Builder;

interface NotificationTargetingInterface
{
    public function query(): Builder;
}
