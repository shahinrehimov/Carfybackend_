<?php

namespace App\Repositories;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SellerOrderRepository
{
    public function loadOrders(Company $company, array $filters = []): LengthAwarePaginator
    {
        $limit = $filters['limit'] ?? 15;

        return Order::query()
            ->whereHas('items', function ($q) use ($company) {
                $q->whereHas('companyProductVariant', function ($q2) use ($company) {
                    $q2->where('company_id', $company->id);
                });
            })
            ->with([
                'items' => function ($q) use ($company) {
                    $q->whereHas('companyProductVariant', function ($q2) use ($company) {
                        $q2->where('company_id', $company->id);
                    })->with(['productVariant', 'companyProductVariant']);
                },
                'shippings' => function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                },
            ])
            ->orderByDesc('id')
            ->paginate($limit);
    }

    public function showOrder(Company $company, Order $order): Order
    {
        $hasItems = $order->items()
            ->whereHas('companyProductVariant', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->exists();

        if (!$hasItems) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order not found')
                    ->getLang('order_not_found')
            );
        }

        $order->load([
            'items' => function ($q) use ($company) {
                $q->whereHas('companyProductVariant', function ($q2) use ($company) {
                    $q2->where('company_id', $company->id);
                })->with([
                    'productVariant',
                    'companyProductVariant',
                    'itemStatuses',
                ]);
            },
            'shippings' => function ($q) use ($company) {
                $q->where('company_id', $company->id);
            },
        ]);

        return $order;
    }

    public function changeItemStatus(
        Company $company,
        OrderItem $item,
        OrderItemStatusEnum $newStatus,
        ?string $cancelReason = null
    ): OrderItem {
        $this->authorizeItem($company, $item);
        $this->validateTransition($item->status, $newStatus, $item->order);

        if ($newStatus === OrderItemStatusEnum::CANCELED && empty($cancelReason)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Cancel reason is required')
                    ->getLang('cancel_reason_required')
            );
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === OrderItemStatusEnum::CANCELED) {
            $admin = CompanyAdminService::instance()->getUser();
            $updateData['cancel_reason'] = $cancelReason;
            $updateData['cancelled_by_type'] = get_class($admin);
            $updateData['cancelled_by_id'] = $admin->id;
        }

        $item->update($updateData);

        OrderItemStatus::create([
            'order_item_id' => $item->id,
            'status' => $newStatus,
        ]);

        if ($newStatus === OrderItemStatusEnum::CANCELED) {
            OrderService::instance()->processItemCancellation($item);
        }

        return $item->fresh();
    }

    public function bulkChangeItemStatus(
        Company $company,
        Order $order,
        array $itemIds,
        OrderItemStatusEnum $newStatus,
        ?string $cancelReason = null
    ): void {
        $items = $order->items()
            ->whereIn('id', $itemIds)
            ->whereHas('companyProductVariant', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->get();

        if ($items->isEmpty()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('No items found')
                    ->getLang('no_items_found')
            );
        }

        foreach ($items as $item) {
            $this->changeItemStatus($company, $item, $newStatus, $cancelReason);
        }
    }

    private function authorizeItem(Company $company, OrderItem $item): void
    {
        $belongs = $item->companyProductVariant()->where('company_id', $company->id)->exists();

        if (!$belongs) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order item not found')
                    ->getLang('order_item_not_found')
            );
        }
    }

    private function validateTransition(OrderItemStatusEnum $current, OrderItemStatusEnum $next, Order $order): void
    {
        $allowed = match ($current) {
            OrderItemStatusEnum::PENDING => [
                OrderItemStatusEnum::PROCESSING,
                OrderItemStatusEnum::CANCELED,
            ],
            OrderItemStatusEnum::PROCESSING => [
                OrderItemStatusEnum::READY_FOR_DELIVERY,
                OrderItemStatusEnum::CANCELED,
            ],
            OrderItemStatusEnum::READY_FOR_DELIVERY => [
                OrderItemStatusEnum::HANDED_TO_COURIER,
            ],
            default => [],
        };

        if (!in_array($next, $allowed)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid status transition')
                    ->getLang('invalid_status_transition')
            );
        }

        if ($next === OrderItemStatusEnum::HANDED_TO_COURIER) {
            if ($order->status !== OrderStatusEnum::COURIER_ASSIGNED) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Courier has not been assigned yet')
                        ->getLang('courier_not_assigned')
                );
            }
        }
    }
}
