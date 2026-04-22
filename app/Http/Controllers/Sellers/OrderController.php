<?php

namespace App\Http\Controllers\Sellers;

use App\Enum\OrderItemStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Requests\Sellers\Orders\BulkChangeItemStatusRequest;
use App\Http\Requests\Sellers\Orders\ChangeItemStatusRequest;
use App\Http\Resources\Sellers\Orders\SellerOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\SellerOrderRepository;
use App\Services\CompanyAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly SellerOrderRepository $repo)
    {
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        $company = CompanyAdminService::instance()->getCompany();
        $orders = $this->repo->loadOrders($company, $request->validated());

        return SellerOrderResource::collection($orders);
    }

    public function show(Order $order): SellerOrderResource
    {
        $company = CompanyAdminService::instance()->getCompany();
        $order = $this->repo->showOrder($company, $order);

        return SellerOrderResource::make($order);
    }

    public function changeItemStatus(ChangeItemStatusRequest $request, OrderItem $orderItem): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();
        $validated = $request->validated();

        $item = $this->repo->changeItemStatus(
            $company,
            $orderItem,
            OrderItemStatusEnum::from($validated['status']),
            $validated['cancel_reason'] ?? null
        );

        return response()->json([
            'message' => 'OK',
            'status' => $item->status,
        ]);
    }

    public function bulkChangeItemStatus(BulkChangeItemStatusRequest $request, Order $order): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();
        $validated = $request->validated();

        $this->repo->bulkChangeItemStatus(
            $company,
            $order,
            $validated['item_ids'],
            OrderItemStatusEnum::from($validated['status']),
            $validated['cancel_reason'] ?? null
        );

        return response()->json(['message' => 'OK']);
    }
}
