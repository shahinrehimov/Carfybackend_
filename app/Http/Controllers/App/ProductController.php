<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Products\ProductSearchRequest;
use App\Http\Requests\App\Products\ProductShowRequest;
use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\App\ProductVariants\ProductVariantResource;
use App\Http\Resources\App\ProductVariants\ProductVariantsResource;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductVariantRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private ProductVariantRepository $repo;

    public function __construct(ProductVariantRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ProductSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsResource::collection($this->repo->load($request->validated()));
    }

    public function getProductsSearchFilters(): JsonResponse
    {
        return response()->json([
            'categories' => CategoriesListResource::collection((new CategoryRepository)->list(['with_subs' => 'yes'])),
            'brands' => BrandsListResource::collection((new BrandRepository())->list()),
            'statuses' => $this->repo->getProductsSearchStatusesList(),
            'price_ranges' => [
                'min' => 0,
                'max' => 1000000, // TODO: Adjust this value based on your product price range
            ],
        ]);
    }

    public function showBySlug(ProductShowRequest $request, string $parentCategorySlug, string $subCategorySlug, string $productSlug): ProductVariantResource
    {
        return ProductVariantResource::make($this->repo->showBySlug($productSlug, $subCategorySlug, $parentCategorySlug, $request->validated()));
    }

    public function showBySlugOnlySubCategory(ProductShowRequest $request, string $subCategorySlug, string $productSlug): ProductVariantResource
    {
        return ProductVariantResource::make($this->repo->showBySlug($productSlug, $subCategorySlug, null, $request->validated()));
    }
}
