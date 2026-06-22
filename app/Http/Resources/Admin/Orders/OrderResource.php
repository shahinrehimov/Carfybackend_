<?php

namespace App\Http\Resources\Admin\Orders;

use App\Http\Resources\Admin\AddressTypes\AddressTypesListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\App\Orders\OrderItemsListResource;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
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
 * @property mixed $statuses
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    private function groupItemsBySeller(Collection $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $company = $item->companyProductVariant?->company;
            $companyId = $company?->id ?? 0;

            if (!isset($grouped[$companyId])) {
                $grouped[$companyId] = [
                    'company_id' => $companyId,
                    'company_name' => $company?->title,
                    'store_code' => $company?->store_code,
                    'items' => [],
                ];
            }

            $grouped[$companyId]['items'][] = [
                'id' => $item->id,
                'status' => $item->status,
                'cancel_reason' => $item->cancel_reason,
                'quantity' => $item->quantity,
                'amount' => $item->amount,
                'amount_final' => $item->amount_final,
                'product_title' => $item->productVariant?->title,
                'status_history' => $item->relationLoaded('itemStatuses')
                    ? $item->itemStatuses->map(fn($s) => [
                        'status' => $s->status,
                        'date' => $s->created_at?->format('d.m.Y, H:i'),
                    ])
                    : [],
            ];
        }

        return array_values($grouped);
    }

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
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user?->id,
                'uuid' => $this->user?->uuid,
                'name' => $this->user?->name,
                'surname' => $this->user?->surname,
                'full_name' => $this->user?->full_name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ]),
            'address' => [
                'address_type' => AddressTypesListResource::make($this->whenLoaded('addressType')),
                'city' => CitiesListResource::make($this->whenLoaded('city')),
                'address_line_1' => $this->address_line_1,
                'address_line_2' => $this->address_line_2,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'phone' => $this->phone,
            ],
            'payment_method' => $this->payment_method,
            'payment_method_display' => $this->resource->getPaymentMethodDisplayName(),
            'courier_info' => $this->courier_info,
            'cancel_reason' => $this->cancel_reason,
            'status_tree' => $this->whenLoaded('statuses', function () {
                return OrderService::instance()->makeStatusesTree($this->statuses);
            }),
            'statuses' => OrderStatusesResource::collection($this->whenLoaded('statuses')),
            'items_by_seller' => $this->whenLoaded('items', function () {
                return $this->groupItemsBySeller($this->items);
            }),
            'items' => OrderItemsListResource::collection($this->whenLoaded('items')),
            'shipping_per_seller' => $this->whenLoaded('shippings', fn() =>
                $this->shippings->map(fn($s) => [
                    'company_id'     => $s->company_id,
                    'company_name'   => $s->company?->title,
                    'store_code'     => $s->company?->store_code,
                    'amount'         => $s->amount,
                    'refunded_at'    => $s->refunded_at?->toDateTimeString(),
                    'wolt_status'    => $s->wolt_status,
                    'wolt_deliveries'=> $s->wolt_deliveries,
                ])
            ),
            'companies' => $this->whenLoaded('shippings', fn() =>
                OrderService::instance()->buildCompaniesList($this->resource)
            ),
            'payment_logs' => $this->whenLoaded('paymentLogs', fn() =>
                $this->paymentLogs->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'payment_method' => $log->payment_method,
                    'transaction_id' => $log->transaction_id,
                    'status' => $log->status,
                    'order_status' => $log->order_status,
                    'request' => $log->request,
                    'response' => $log->response,
                    'created_at' => $log->created_at?->toDateTimeString(),
                ])
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
