<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Orders\CheckoutPreviewRequest;
use App\Http\Requests\App\Orders\OrderStoreRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\CompanyProductVariants\CompanyProductVariantListResource;
use App\Http\Resources\App\Orders\OrderDetailsResource;
use App\Http\Resources\App\Orders\OrderShowResource;
use App\Http\Resources\App\Orders\OrdersListResource;
use App\Models\Order;
use App\Services\LangService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function checkoutPreview(CheckoutPreviewRequest $request): JsonResponse
    {
        $data = OrderService::instance()->checkoutPreview($request->integer('address_id') ?: null);

        foreach ($data['sellers'] as &$seller) {
            $seller['items'] = array_map(fn($item) => [
                'quantity' => $item['quantity'],
                'price'    => $item['price'],
                'product'  => CompanyProductVariantListResource::make($item['_cpv']),
            ], $seller['items']);
        }
        unset($seller);

        return response()->json($data);
    }

    public function checkout(OrderStoreRequest $request): JsonResponse
    {
        $result = OrderService::instance()->checkout($request->validated());

        $response = [
            'message' => $result->message,
            'redirect' => false,
        ];

        if ($result->isPending()) {
            $response['redirect'] = true;
            $response['redirect_url'] = $result->redirectUrl;
        }

        return response()->json($response, ($result->success || $result->isPending()) ? 200 : 400);
    }

    public function paymentProcessCallback(Request $request, string $paymentMethodName): JsonResponse
    {
        OrderService::instance()->processCallback($paymentMethodName, $request->all());

        return response()->json([
            'message' => 'OK',
        ]);
    }

    public function checkPaymentStatus(string $orderUuid, string $paymentMethod, string $token): JsonResponse
    {
        $order = OrderService::instance()->getOrderByUuid($orderUuid);

        if (!$order) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Order not found!')
                    ->getLang('order_not_found'),
            ], 404);
        }

        return response()->json([
            'status' => OrderService::instance()->checkPaymentStatusFromDatabase($order, $paymentMethod, $token),
        ]);
    }

    public function load(GeneralListRequest $request): AnonymousResourceCollection
    {
        return OrdersListResource::collection(OrderService::instance()->load($request->validated()));
    }

    public function show(Order $order): OrderShowResource
    {
        $order = OrderService::instance()->show($order);

        return OrderShowResource::make($order);
    }

    public function details(Order $order): OrderDetailsResource
    {
        $order = OrderService::instance()->details($order);

        return OrderDetailsResource::make($order);
    }
}
