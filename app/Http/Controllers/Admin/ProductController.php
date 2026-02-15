<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\ProductSearchRequest;
use App\Http\Requests\Sellers\Products\ProductStoreRequest;
use App\Http\Requests\Sellers\Products\ProductUpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\Products\ProductResource;
use App\Http\Resources\Sellers\Products\ProductsListResource;
use App\Http\Resources\Sellers\Products\ProductsResource;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private ProductRepository $repo;

    public function __construct(ProductRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ProductSearchRequest $request): AnonymousResourceCollection
    {
        return ProductsResource::collection($this->repo->load($request->validated(), true));
    }
    public function list(ProductSearchRequest $request): AnonymousResourceCollection
    {
        return ProductsListResource::collection($this->repo->list($request->validated()));
    }

    public function show(Product $product): ProductResource
    {
        $product = $this->repo->show($product);

        return ProductResource::make($product);
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $product = $this->repo->store($request);

        $product = $this->repo->loadRelations($product);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_form_saved_successfully'),
            'data' => ProductsResource::make($product),
        ]));
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $product = $this->repo->update($product, $request);

        $product = $this->repo->loadRelations($product);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_form_saved_successfully'),
            'data' => ProductsResource::make($product),
        ]));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->repo->destroy($product);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_deleted_successfully'),
        ]));
    }

    public function deleteHoverImage(Product $product): JsonResponse
    {
        $this->repo->deleteHoverImage($product);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('product_image_deleted_successfully'),
        ]));
    }

    public function destroyImage(Product $product, int $mediaId): JsonResponse
    {
        $this->repo->deleteImage($product, $mediaId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Selected media file deleted successfully!')
                ->getLang('product_media_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Product $product): JsonResponse
    {
        $this->repo->changeActiveStatus($product);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }
}
