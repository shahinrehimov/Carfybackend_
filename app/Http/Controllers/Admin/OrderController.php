<?php

namespace App\Http\Controllers\Admin;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\AssignCourierRequest;
use App\Http\Requests\Admin\Orders\OrderChangeStatusRequest;
use App\Http\Requests\Admin\Orders\OrderSearchRequest;
use App\Http\Requests\Admin\Orders\SetFinalStatusRequest;
use App\Http\Resources\Admin\Orders\OrderResource;
use App\Http\Resources\Admin\Orders\OrdersResource;
use App\Http\Resources\GeneralResource;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    private OrderRepository $repo;

    public function __construct(OrderRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getSearchFilters(): JsonResponse
    {
        return response()->json([
            'data' => $this->repo->getSearchFilters(),
        ]);
    }

    public function index(OrderSearchRequest $request): AnonymousResourceCollection
    {
        return OrdersResource::collection($this->repo->load($request->validated()));
    }

    public function show(Order $order): OrderResource
    {
        $order = $this->repo->show($order);

        return OrderResource::make($order);
    }

    public function changeStatus(OrderChangeStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $order = $this->repo->changeStatus(
            $order,
            OrderStatusEnum::from($validated['status']),
            $validated['cancel_reason'] ?? null,
        );

        $order = $this->repo->loadRelations($order);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function updatePaymentStatus(Order $order): JsonResponse
    {
        $result = OrderService::instance()->updatePaymentStatus($order);

        return response()->json([
            'message' => $result->message,
            'transaction_id' => $result->transactionId,
            'is_refund' => $result->isRefund,
        ], $result->success ? 200 : 400);
    }

    public function refund(Order $order): JsonResponse
    {
        $result = OrderService::instance()->refund($order);

        return response()->json([
            'message' => $result->message,
            'transaction_id' => $result->transactionId,
        ], $result->success ? 200 : 400);
    }

    public function setFinalStatus(SetFinalStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $newStatus = OrderStatusEnum::from($validated['status']);

        // Validate: if setting non-canceled status, at least one active item must exist
        if ($newStatus !== OrderStatusEnum::CANCELED) {
            $hasActiveItem = $order->items()
                ->where('status', '!=', OrderItemStatusEnum::CANCELED)
                ->exists();

            if (!$hasActiveItem) {
                return response()->json([
                    'message' => LangService::instance()
                        ->setDefault('All items are canceled. Cannot set this status.')
                        ->getLang('order_all_items_canceled'),
                ], 422);
            }
        }

        // Validate: courier_assigned requires at least one ready_for_delivery item
        if ($newStatus === OrderStatusEnum::COURIER_ASSIGNED) {
            $hasReadyItem = $order->items()
                ->where('status', OrderItemStatusEnum::READY_FOR_DELIVERY)
                ->exists();

            if (!$hasReadyItem) {
                return response()->json([
                    'message' => LangService::instance()
                        ->setDefault('No items are ready for delivery.')
                        ->getLang('order_no_items_ready'),
                ], 422);
            }
        }

        $order = $this->repo->changeStatus(
            $order,
            $newStatus,
            $validated['cancel_reason'] ?? null,
        );

        $order->statuses()->create(['status' => $newStatus]);

        $order = $this->repo->loadRelations($order);

        // TODO: Notify user - order final status changed

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function assignCourier(AssignCourierRequest $request, Order $order): JsonResponse
    {
        $order->update([
            'courier_info' => $request->validated('courier_info'),
            'status' => OrderStatusEnum::COURIER_ASSIGNED,
        ]);

        $order->statuses()->create(['status' => OrderStatusEnum::COURIER_ASSIGNED]);

        $order = $this->repo->loadRelations($order);

        // TODO: Notify sellers - courier assigned
        // TODO: Notify user - courier assigned

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Courier assigned successfully!')
                ->getLang('courier_assigned_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function getPaymentLogs(Order $order): JsonResponse
    {
        $order->load(['paymentLogs' => fn($q) => $q->orderBy('id')]);

        return response()->json([
            'data' => $order->paymentLogs->map(fn($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'payment_method' => $log->payment_method,
                'transaction_id' => $log->transaction_id,
                'status' => $log->status,
                'order_status' => $log->order_status,
                'request' => $log->request,
                'response' => $log->response,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]),
        ]);
    }
}
