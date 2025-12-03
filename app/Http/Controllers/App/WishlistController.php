<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\ProductVariants\ProductVariantsResource;
use App\Models\ProductVariant;
use App\Services\LangService;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    public function load(GeneralListRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsResource::collection(WishlistService::instance()->load($request->validated()));
    }

    public function add(ProductVariant $productVariant): JsonResponse
    {
        $res = WishlistService::instance()->add($productVariant);

        return response()->json([
            'message' => $res ?
                LangService::instance()
                    ->setDefault('Product added to wishlist!')
                    ->getLang('product_added_to_wishlist') :
                LangService::instance()
                    ->setDefault('Product is already in wishlist!')
                    ->getLang('product_already_in_wishlist'),
            'data' => $res ? ProductVariantsResource::make($productVariant) : null,
        ], $res ? 200 : 400);
    }

    public function remove(ProductVariant $productVariant): JsonResponse
    {
        $res = WishlistService::instance()->remove($productVariant);

        return response()->json([
            'message' => $res ?
                LangService::instance()
                    ->setDefault('Product removed from wishlist!')
                    ->getLang('product_removed_from_wishlist') :
                LangService::instance()
                    ->setDefault('Product is not in wishlist!')
                    ->getLang('product_not_in_wishlist'),
        ], $res ? 200 : 400);
    }
}
