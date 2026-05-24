<?php

namespace App\Http\Resources\Admin\Orders;

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
 * @property mixed $payment_status
 * @property mixed $transaction_id
 * @property mixed $paid_at
 * @property mixed $refund_at
 * @property mixed $amount
 * @property mixed $amount_final
 * @property mixed $total_discount_in_basket
 * @property mixed $shipping
 * @property mixed $address_line_1
 * @property mixed $address_line_2
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $phone
 */
class OrdersResource extends JsonResource
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
            'items_count' => $this->whenCounted('items'),
            'products_count' => $this->total_quantity,
            'status' => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title($this->status->value))
                ->getLang('order_status_' . $this->status->value),
            'payment_status' => $this->payment_status,
            'payment_status_label' => LangService::instance()
                ->setDefault(Str::title($this->payment_status->value))
                ->getLang('order_payment_status_' . $this->payment_status->value),
            'transaction_id' => $this->transaction_id,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'refund_at' => $this->refund_at?->toDateTimeString(),
            'amount' => $this->amount,
            'amount_final' => $this->amount_final,
            'total_discount_in_basket' => $this->total_discount_in_basket,
            'shipping' => $this->shipping,
            'address' => [
                'address_type' => AddressTypesListResource::make($this->whenLoaded('addressType')),
                'city' => CitiesListResource::make($this->whenLoaded('city')),
                'address_line_1' => $this->address_line_1,
                'address_line_2' => $this->address_line_2,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'phone' => $this->phone,
            ],
            'companies' => $this->whenLoaded('shippings', fn() =>
                OrderService::instance()->buildCompaniesList($this->resource)
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
