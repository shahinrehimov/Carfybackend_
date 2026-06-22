<?php

namespace App\Http\Controllers\App;

use App\Enum\ProductSortEnum;
use App\Enum\SortTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Products\ProductSearchRequest;
use App\Http\Requests\App\Products\ProductShowByUuidRequest;
use App\Http\Requests\App\Products\ProductShowRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\CarColors\CarColorsListResource;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\App\CompanyProductVariants\CompanyProductVariantListResource;
use App\Http\Resources\App\ProductVariants\ProductVariantResource;
use App\Http\Resources\App\ProductVariants\ProductVariantsResource;
use App\Models\CompanyProductVariant;
use App\Models\ProductOption;
use App\Models\ProductOptionItem;
use App\Models\ProductVariant;
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
        return CompanyProductVariantListResource::collection($this->repo->load($request->validated()));
    }

    public function getProductsSearchFilters(): JsonResponse
    {
        $priceRange = CompanyProductVariant::query()
            ->where('is_active', true)
            ->where('approval_status', \App\Enum\ProductVariantApprovalStatusEnum::APPROVED->value)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $cities = \App\Models\City::query()
            ->whereHas('companyProductVariants', fn($q) => $q
                ->where('is_active', true)
                ->where('approval_status', \App\Enum\ProductVariantApprovalStatusEnum::APPROVED->value)
            )
            ->with('translatable')
            ->orderBy('id')
            ->get();

        $carColors = ProductOptionItem::query()
            ->forOption('color')
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $conditionItems = ProductOption::query()
            ->where('name', 'condition')
            ->with(['items' => fn($q) => $q->active()->with('translatable')->orderBy('sort')])
            ->first()
            ?->items
            ?->map(fn($item) => ['slug' => $item->slug, 'title' => $item->getLang('title')])
            ?? [];

        $yearRange = ProductVariant::query()
            ->whereHas('companyListings', fn($q) => $q
                ->where('is_active', true)
                ->where('approval_status', \App\Enum\ProductVariantApprovalStatusEnum::APPROVED->value)
            )
            ->whereNotNull('year')
            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
            ->first();

        return response()->json([
            'categories'   => CategoriesListResource::collection((new CategoryRepository)->list(['with_subs' => 'yes'])),
            'brands'       => BrandsListResource::collection((new BrandRepository())->list(withModels: true)),
            'statuses'     => $this->repo->getProductsSearchStatusesList(),
            'cities'       => CitiesListResource::collection($cities),
            'price_ranges' => [
                'min' => round(($priceRange->min_price ?? 0) / 100, 2),
                'max' => round(($priceRange->max_price ?? 0) / 100, 2),
            ],
            'colors'       => CarColorsListResource::collection($carColors),
            'conditions'   => $conditionItems,
            'year_range'   => [
                'min' => $yearRange->min_year,
                'max' => $yearRange->max_year,
            ],
            'sort_options' => ProductSortEnum::getList(),
            'sort_types'   => SortTypeEnum::getList(),
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

    public function showByUuid(ProductShowByUuidRequest $request, string $cpvUuid): ProductVariantResource
    {
        return ProductVariantResource::make($this->repo->showByUuid($cpvUuid, $request->validated()));
    }

    public function otherSellers(GeneralListRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $paginated = $this->repo->otherSellers($productVariant, $request->validated());

        $data = $paginated->through(function ($cpv) {
            return [
                'uuid'          => $cpv->uuid,
                'price'         => $cpv->price,
                'price_final'   => $cpv->price_final,
                'has_discount'  => $cpv->price != $cpv->price_final,
                'discount_type' => $cpv->discount_type,
                'discount'      => $cpv->discount,
                'stock'         => $cpv->stock,
                'city'          => $cpv->relationLoaded('city') ? CitiesListResource::make($cpv->city) : null,
                'seller_label'  => $cpv->company?->getSellerLabel(),
                'images'        => $cpv->relationLoaded('media')
                    ? $cpv->getMedia('company_product_variant_images')->map(fn($m) => ['url' => $m->getUrl()])
                    : [],
            ];
        });

        return response()->json($data);
    }
}
