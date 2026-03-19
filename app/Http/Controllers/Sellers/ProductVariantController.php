<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\CompanyProductVariants\LoadRequest as CatalogSearchRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantSearchRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantStoreRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantUpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Services\CompanyAdminService;
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

    public function list(ProductVariantSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsListResource::collection($this->repo->list($request->validated()));
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

    public function searchCatalog(CatalogSearchRequest $request): JsonResponse
    {
        $results = $this->repo->searchCatalog($request->validated());
        $searchToken = null;

        if (CompanyAdminService::instance()->checkUser()) {
            $searchToken = $this->repo->generateSearchToken();
        }

        return response()->json(GeneralResource::make([
            'search_token' => $searchToken,
            'data' => ProductVariantsResource::collection($results),
        ]));
    }
}
