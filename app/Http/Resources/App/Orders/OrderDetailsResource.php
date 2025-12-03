<?php

namespace App\Http\Resources\App\Orders;

use App\Http\Resources\Admin\AddressTypes\AddressTypesListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Services\LangService;
use App\Services\OrderService;
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
 * @property mixed $amount
 * @property mixed $amount_final
 * @property mixed $payment_method
 * @property mixed $payment_status
 * @property mixed $address_line_1
 * @property mixed $address_line_2
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $phone
 * @property mixed $statuses
 */
class OrderDetailsResource extends JsonResource
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
            'amount' => $this->amount,
            'amount_final' => $this->amount_final,
            'payment_method' => [
                'name' => $this->payment_method,
                'label' => LangService::instance()
                    ->setDefault(Str::title(str_replace('_', ' ', $this->payment_method)))
                    ->getLang('payment_method_' . $this->payment_method),
                'payment_status' => $this->payment_status,
                'payment_status_label' => LangService::instance()
                    ->setDefault(Str::title($this->payment_status->value))
                    ->getLang('order_payment_status_' . $this->payment_status->value),
            ],
            'address' => [
                'address_type' => AddressTypesListResource::make($this->whenLoaded('addressType')),
                'city' => CitiesListResource::make($this->whenLoaded('city')),
                'address_line_1' => $this->address_line_1,
                'address_line_2' => $this->address_line_2,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'phone' => $this->phone,
            ],
            'status_tree' => $this->whenLoaded('statuses', function () {
                return OrderService::instance()->makeStatusesTree($this->statuses);
            }),
            'statuses' => OrderStatusesResource::collection($this->whenLoaded('statuses')),
            'items' => OrderItemsListResource::collection($this->whenLoaded('items')),
        ];
    }
}
