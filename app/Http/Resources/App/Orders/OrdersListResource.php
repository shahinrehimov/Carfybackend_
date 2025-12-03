<?php

namespace App\Http\Resources\App\Orders;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $uuid
 * @property mixed $id
 * @property mixed $status
 * @property mixed $created_at
 * @property mixed $code
 * @property mixed $total_quantity
 */
class OrdersListResource extends JsonResource
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
            'code' => $this->code,
            'uuid' => $this->uuid,
            'products_count' => $this->total_quantity,
            'status' => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title($this->status->value))
                ->getLang('order_status_' . $this->status->value),
            'date' => $this->created_at?->format('d.m.Y'),
        ];
    }
}
