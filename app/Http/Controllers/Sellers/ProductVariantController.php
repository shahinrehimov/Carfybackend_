<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantSearchRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantStoreRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantUpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantApprovalStatusHistoryResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductVariantController extends Controller
{
    private ProductVariantRepository $repo;

    public function __construct(ProductVariantRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ProductVariantSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsResource::collection($this->repo->load($request->validated(), true));
    }

    public function list(ProductVariantSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsListResource::collection($this->repo->list($request->validated()));
    }

    public function show(ProductVariant $productVariant): ProductVariantResource
    {
        $productVariant = $this->repo->show($productVariant);

        return ProductVariantResource::make($productVariant);
    }

    public function store(ProductVariantStoreRequest $request, Product $product): JsonResponse
    {
        $productVariant = $this->repo->store($product, $request);

        $productVariant = $this->repo->loadRelations($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_form_saved_successfully'),
            'data' => ProductVariantsResource::make($productVariant),
        ]));
    }

    public function update(ProductVariantUpdateRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $productVariant = $this->repo->update($productVariant, $request);

        $productVariant = $this->repo->loadRelations($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_form_saved_successfully'),
            'data' => ProductVariantsResource::make($productVariant),
        ]));
    }

    public function destroy(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->destroy($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_deleted_successfully'),
        ]));
    }

    public function deleteHoverImage(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->deleteHoverImage($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('product_image_deleted_successfully'),
        ]));
    }

    public function destroyImage(ProductVariant $productVariant, int $mediaId): JsonResponse
    {
        $this->repo->deleteImage($productVariant, $mediaId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Selected media file deleted successfully!')
                ->getLang('product_media_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->changeActiveStatus($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function approvalStatusHistory(GeneralListRequest $request, ProductVariant $productVariant): AnonymousResourceCollection
    {
        return ProductVariantApprovalStatusHistoryResource::collection($this->repo->approvalStatusHistory($productVariant, $request->validated()));
    }

    public function approvalStatusResend(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->approvalStatusResend($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }
}
