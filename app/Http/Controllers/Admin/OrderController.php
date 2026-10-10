<?php

namespace App\Http\Controllers\Admin;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\AssignCourierRequest;
use App\Http\Requests\Admin\Orders\OrderChangeStatusRequest;
use App\Http\Requests\Admin\Orders\OrderSearchRequest;
use App\Http\Requests\Admin\Orders\RequestWoltDeliveryRequest;
use App\Http\Requests\Admin\Orders\SetFinalStatusRequest;
use App\Http\Resources\Admin\Orders\OrderResource;
use App\Http\Resources\Admin\Orders\OrdersResource;
use App\Http\Resources\GeneralResource;
use App\Models\Company;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\LangService;
use App\Services\OrderService;
use App\Services\WoltDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

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

        $order = $this->repo->loadRelations($order);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function assignCourier(AssignCourierRequest $request, Order $order): JsonResponse
    {
        $this->repo->guardStatusTransition($order, OrderStatusEnum::COURIER_ASSIGNED);

        $order->update([
            'courier_info' => $request->validated('courier_info'),
            'status' => OrderStatusEnum::COURIER_ASSIGNED,
        ]);

        $order = $this->repo->loadRelations($order);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Courier assigned successfully!')
                ->getLang('courier_assigned_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function requestWoltDelivery(RequestWoltDeliveryRequest $request, Order $order): JsonResponse
    {
        // Check before calling Wolt so no courier is ever booked for an order that cannot ship
        $this->repo->guardStatusTransition($order, OrderStatusEnum::COURIER_ASSIGNED);

        $companyId = $request->integer('company_id');
        $company   = Company::findOrFail($companyId);

        $shipping = $order->shippings()->where('company_id', $companyId)->first();

        if (!$shipping) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('No shipping record found for this company.')
                    ->getLang('wolt_no_shipping_record'),
            ], 422);
        }

        $orderItems = $order->items()
            ->where('company_product_variant_id', '!=', null)
            ->whereHas('companyProductVariant', fn($q) => $q->where('company_id', $companyId))
            ->where('status', '!=', \App\Enum\OrderItemStatusEnum::CANCELED)
            ->with(['companyProductVariant', 'productVariant'])
            ->get();

        if ($orderItems->isEmpty()) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('No active items found for this company.')
                    ->getLang('wolt_no_active_items'),
            ], 422);
        }

        if (!config('wolt.enabled')) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Wolt integration is not enabled.')
                    ->getLang('wolt_not_enabled'),
            ], 503);
        }

        $wolt   = WoltDriveService::instance();
        $groups = $wolt->splitItemsByWeight($orderItems);

        $order->load('user');
        $woltDeliveries = $shipping->wolt_deliveries ?? [];

        foreach ($groups as $index => $groupItems) {
            try {
                $result = $wolt->createDeliveryOrder($company, $order, $groupItems, $index + 1);

                $woltDeliveries[] = [
                    'wolt_order_reference_id' => $result['wolt_order_reference_id'],
                    'tracking_url'            => $result['tracking_url'],
                    'status'                  => 'pending',
                    'part'                    => $index + 1,
                ];
            } catch (\App\Exceptions\WoltException $e) {
                Log::error('Wolt delivery order failed', [
                    'order_id'   => $order->id,
                    'company_id' => $companyId,
                    'part'       => $index + 1,
                    'message'    => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Wolt message: ' . $e->getMessage(),
                ], 422);
            } catch (\RuntimeException $e) {
                Log::error('Wolt delivery request runtime error', [
                    'order_id'   => $order->id,
                    'company_id' => $companyId,
                    'message'    => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => LangService::instance()
                        ->setDefault('Wolt delivery request failed. Please try again.')
                        ->getLang('wolt_delivery_request_failed'),
                ], 502);
            }
        }

        $shipping->update([
            'wolt_deliveries' => $woltDeliveries,
            'wolt_status'     => 'pending',
        ]);

        $order->update(['status' => \App\Enum\OrderStatusEnum::COURIER_ASSIGNED]);

        $order = $this->repo->loadRelations($order);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Wolt delivery requested successfully!')
                ->getLang('wolt_delivery_requested_successfully'),
            'data' => OrdersResource::make($order),
        ]));
    }

    public function getCompanies(Order $order): JsonResponse
    {
        $order->load('shippings.company.translatable', 'items:id,order_id,company_product_variant_id,status', 'items.companyProductVariant:id,company_id');

        return response()->json([
            'data' => OrderService::instance()->buildCompaniesList($order),
        ]);
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
