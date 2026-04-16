<?php

namespace App\Http\Resources\Admin\Advertisements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $user_id
 * @property mixed $guest_identifier
 * @property mixed $ip_address
 * @property mixed $user_agent
 * @property mixed $created_at
 */
class AdvertisementClicksResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_type'        => match (true) {
                $this->user_id !== null          => 'authenticated',
                $this->guest_identifier !== null => 'guest',
                default                          => 'anonymous',
            },
            'user_id'          => $this->user_id,
            'guest_identifier' => $this->guest_identifier,
            'ip_address'       => $this->ip_address,
            'user_agent'       => $this->user_agent,
            'clicked_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
