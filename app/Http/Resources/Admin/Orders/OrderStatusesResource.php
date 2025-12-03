<?php

namespace App\Http\Resources\Admin\Orders;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $uuid
 * @property mixed $created_at
 * @property mixed $status
 * @property mixed $id
 * @property mixed $creatable
 */
class OrderStatusesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title($this->status->value))
                ->getLang('order_status_' . $this->status->value),
            'created_at' => $this->created_at?->toDateTimeString(),
            'created_user' => $this->whenLoaded('creatable', function () {
                $roles = $this->creatable?->roles?->pluck('name')->toArray() ?? ['User'];
                return $this->creatable?->full_name . ' / ' . implode(', ', $roles);
            }),
        ];
    }
}
