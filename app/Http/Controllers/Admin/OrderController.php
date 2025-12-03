<?php

namespace App\Http\Controllers\Admin;

use App\Enum\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\OrderChangeStatusRequest;
use App\Http\Requests\Admin\Orders\OrderSearchRequest;
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
        $order = $this->repo->changeStatus($order, OrderStatusEnum::from($request->validated()['status']));

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
}
