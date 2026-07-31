<?php

namespace App\Repositories;

use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Order;
use App\Services\Payment\PaymentMethodHelper;
use App\Services\ProductService;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function load(array $validated = []): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'user',
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'shippings.company',
                'items:id,order_id,company_product_variant_id,status',
                'items.companyProductVariant:id,company_id',
            ])
            ->withCount([
                'items',
            ])
            ->when($validated['code'] ?? null, fn($q) => $q->where('id', (int)preg_replace('/[^0-9]/', '', $validated['code'])))
            ->when($validated['status'] ?? null, fn($q) => $q->where('status', $validated['status']))
            ->when($validated['payment_status'] ?? null, fn($q) => $q->where('payment_status', $validated['payment_status']))
            ->when($validated['payment_method'] ?? null, fn($q) => $q->where('payment_method', $validated['payment_method']))
            ->when(isset($validated['amount_min']), fn($q) => $q->where('amount_final', '>=', $validated['amount_min']))
            ->when(isset($validated['amount_max']), fn($q) => $q->where('amount_final', '<=', $validated['amount_max']))
            ->when($validated['date_from'] ?? null, fn($q) => $q->where('created_at', '>=', $validated['date_from']))
            ->when($validated['date_to'] ?? null, fn($q) => $q->where('created_at', '<=', $validated['date_to']))
            ->when($validated['user'] ?? null, function ($q) use ($validated) {
                $user = $validated['user'];
                $q->whereHas('user', function ($q) use ($user) {
                    $q->where('name', 'ILIKE', '%' . $user . '%');
                    $q->orWhere('surname', 'ILIKE', '%' . $user . '%');
                    $q->orWhere('email', 'ILIKE', '%' . $user . '%');
                });
            })
            ->when($validated['search'] ?? null, function ($q) use ($validated) {
                $search = $validated['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', '%' . $search . '%');
                    $q->orWhere('transaction_id', 'like', '%' . $search . '%');
                    $q->orWhere('address_line_1', 'like', '%' . $search . '%');
                    $q->orWhere('address_line_2', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function getSearchFilters(): array
    {
        return [
            'statuses' => OrderStatusEnum::getList(),
            'payment_statuses' => OrderPaymentStatusEnum::getList(),
            'payment_methods' => PaymentMethodHelper::getMethodsForApi(),
        ];
    }

    public function loadRelations(Order $order): Order
    {
        $order
            ->load([
                'user',
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
            ])
            ->loadCount([
                'items',
            ]);

        return $order;
    }

    public function show(Order $order): Order
    {
        $productVariantRelations = ProductService::instance()->getVariantRelationsForSite('', true);

        $order
            ->load([
                'user',
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'shippings.company',
                'items' => function ($query) use ($productVariantRelations) {
                    $query->with([
                        'productVariant' => function ($q) use ($productVariantRelations) {
                            $q->withTrashed();
                            $q->when(count($productVariantRelations['with']) > 0, fn($q) => $q->with($productVariantRelations['with']));
                        },
                        // media is required by the listing image cascade in
                        // OrderItemsListResource — without it the seller's own
                        // photo is skipped and the product image wins.
                        'companyProductVariant' => function ($q) {
                            $q->with(['company', 'media']);
                        },
                        'itemStatuses' => function ($q) {
                            $q->orderBy('id');
                        },
                    ]);
                },
                'statuses' => function ($query) {
                    $query->orderBy('id');
                    $query->with([
                        'creatable' => function ($q) {
                            $q->withTrashed();
                        }
                    ]);
                },
                'paymentLogs' => function ($query) {
                    $query->orderBy('id');
                },
            ]);

        return $order;
    }

    public function changeStatus(Order $order, OrderStatusEnum $status, ?string $cancelReason = null): Order
    {
        $data = ['status' => $status];

        if ($status === OrderStatusEnum::CANCELED) {
            $actor = auth()->user();
            $data['cancel_reason']      = $cancelReason;
            $data['cancelled_by_type']  = $actor?->getMorphClass();
            $data['cancelled_by_id']    = $actor?->id;
        }

        $order->update($data);

        return $order;
    }
}
