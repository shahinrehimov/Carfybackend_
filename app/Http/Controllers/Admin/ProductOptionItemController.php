<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductOptionItems\ProductOptionItemChangeSortMultipleRequest;
use App\Http\Requests\Admin\ProductOptionItems\ProductOptionItemChangeSortRequest;
use App\Http\Requests\Admin\ProductOptionItems\ProductOptionItemStoreRequest;
use App\Http\Requests\Admin\ProductOptionItems\ProductOptionItemUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\ProductOptionItems\ProductOptionItemsListResource;
use App\Http\Resources\Admin\ProductOptionItems\ProductOptionItemsResource;
use App\Http\Resources\Admin\ProductOptionItems\ProductOptionItemResource;
use App\Http\Resources\GeneralResource;
use App\Models\ProductOption;
use App\Models\ProductOptionItem;
use App\Repositories\ProductOptionItemRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductOptionItemController extends Controller
{
    private ProductOptionItemRepository $repo;

    public function __construct(ProductOptionItemRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request, ProductOption $productOption): AnonymousResourceCollection
    {
        return ProductOptionItemsResource::collection($this->repo->load($productOption, $request->validated()));
    }

    public function list(ProductOption $productOption): AnonymousResourceCollection
    {
        return ProductOptionItemsListResource::collection($this->repo->list($productOption));
    }

    public function show(ProductOption $productOption, ProductOptionItem $productOptionItem): ProductOptionItemResource
    {
        $productOptionItem = $this->repo->show($productOption, $productOptionItem);

        return ProductOptionItemResource::make($productOptionItem);
    }

    public function store(ProductOptionItemStoreRequest $request, ProductOption $productOption): JsonResponse
    {
        $productOptionItem = $this->repo->store($productOption, $request);

        $productOptionItem = $this->repo->loadRelations($productOptionItem);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_option_item_form_saved_successfully'),
            'data' => ProductOptionItemsResource::make($productOptionItem),
        ]));
    }

    public function update(ProductOptionItemUpdateRequest $request, ProductOption $productOption, ProductOptionItem $productOptionItem): JsonResponse
    {
        $productOptionItem = $this->repo->update($productOption, $productOptionItem, $request);

        $productOptionItem = $this->repo->loadRelations($productOptionItem);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_option_item_form_saved_successfully'),
            'data' => ProductOptionItemsResource::make($productOptionItem),
        ]));
    }

    public function destroy(ProductOption $productOption, ProductOptionItem $productOptionItem): JsonResponse
    {
        $this->repo->destroy($productOption, $productOptionItem);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_option_item_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(ProductOption $productOption, ProductOptionItem $productOptionItem): JsonResponse
    {
        $this->repo->changeActiveStatus($productOption, $productOptionItem);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(ProductOptionItemChangeSortRequest $request, ProductOption $productOption, ProductOptionItem $productOptionItem): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($productOption, $productOptionItem, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(ProductOptionItemChangeSortMultipleRequest $request, ProductOption $productOption): JsonResponse
    {
        $this->repo->changeSortingMultiple($productOption, $request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
