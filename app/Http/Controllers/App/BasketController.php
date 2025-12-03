<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Baskets\BasketAddRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\ProductVariants\ProductVariantsInBasketResource;
use App\Models\ProductVariant;
use App\Services\BasketService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BasketController extends Controller
{
    public function load(GeneralListRequest $request): AnonymousResourceCollection
    {
        [$list, $basketData] = BasketService::instance()->load($request->validated());

        return ProductVariantsInBasketResource::collection($list)
            ->additional([
                'basket_data' => $basketData,
            ]);
    }

    public function add(BasketAddRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validated();

        $res = BasketService::instance()->add($productVariant, $validated['quantity'], $validated['sets'] ?? []);

        return response()->json([
            'message' => $res ?
                LangService::instance()
                    ->setDefault('Product added to basket!')
                    ->getLang('product_added_to_basket') :
                LangService::instance()
                    ->setDefault('Product quantity updated in basket!')
                    ->getLang('product_quantity_updated_in_basket'),
            'data' => ProductVariantsInBasketResource::make($productVariant),
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ]);
    }

    public function remove(ProductVariant $productVariant): JsonResponse
    {
        $res = BasketService::instance()->remove($productVariant);

        return response()->json([
            'message' => $res ?
                LangService::instance()
                    ->setDefault('Product removed from basket!')
                    ->getLang('product_removed_from_basket') :
                LangService::instance()
                    ->setDefault('Product is not in basket!')
                    ->getLang('product_not_in_basket'),
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ], $res ? 200 : 400);
    }

    public function increment(ProductVariant $productVariant): JsonResponse
    {
        $quantity = BasketService::instance()->increment($productVariant);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Product quantity increased in basket!')
                ->getLang('product_quantity_increased_in_basket'),
            'quantity' => $quantity,
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ]);
    }

    public function decrement(ProductVariant $productVariant): JsonResponse
    {
        $quantity = BasketService::instance()->decrement($productVariant);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Product quantity decreased in basket!')
                ->getLang('product_quantity_decreased_in_basket'),
            'quantity' => $quantity,
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ]);
    }

    public function removeSetItem(ProductVariant $productVariant, ProductVariant $setItem): JsonResponse
    {
        $res = BasketService::instance()->removeSetItem($productVariant, $setItem);

        return response()->json([
            'message' => $res ?
                LangService::instance()
                    ->setDefault('Product set item removed from basket!')
                    ->getLang('product_set_item_removed_from_basket') :
                LangService::instance()
                    ->setDefault('Product set item is not in basket!')
                    ->getLang('product_set_item_not_in_basket'),
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ], $res ? 200 : 400);
    }

    public function incrementSetItem(ProductVariant $productVariant, ProductVariant $setItem): JsonResponse
    {
        $quantity = BasketService::instance()->incrementSetItem($productVariant, $setItem);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Product set item quantity increased in basket!')
                ->getLang('product_set_item_quantity_increased_in_basket'),
            'quantity' => $quantity,
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ]);
    }

    public function decrementSetItem(ProductVariant $productVariant, ProductVariant $setItem): JsonResponse
    {
        $quantity = BasketService::instance()->decrementSetItem($productVariant, $setItem);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Product set item quantity decreased in basket!')
                ->getLang('product_set_item_quantity_decreased_in_basket'),
            'quantity' => $quantity,
            'basket_data' => BasketService::instance()->calculateBasketData(),
        ]);
    }
}
