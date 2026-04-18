<?php

namespace App\Http\Resources\Sellers\StoreProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $day_of_week
 * @property mixed $is_closed
 * @property mixed $open_time
 * @property mixed $close_time
 */
class StoreProfileWorkingHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week,
            'is_closed'   => $this->is_closed,
            'open_time'   => $this->open_time?->format('H:i'),
            'close_time'  => $this->close_time?->format('H:i'),
        ];
    }
}
