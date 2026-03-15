<?php

namespace App\Http\Resources\App\Sessions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $ip_address
 * @property string|null $location
 * @property bool        $is_current
 * @property \Carbon\Carbon $created_at
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'device_name' => $this->name,
            'ip_address'  => $this->ip_address,
            'location'    => $this->location,
            'created_at'  => $this->created_at?->toDateTimeString(),
            'is_current'  => $this->is_current ?? false,
        ];
    }
}
