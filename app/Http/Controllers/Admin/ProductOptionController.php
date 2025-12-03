<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductOptions\ProductOptionChangeSortMultipleRequest;
use App\Http\Requests\Admin\ProductOptions\ProductOptionChangeSortRequest;
use App\Http\Requests\Admin\ProductOptions\ProductOptionListRequest;
use App\Http\Requests\Admin\ProductOptions\ProductOptionStoreRequest;
use App\Http\Requests\Admin\ProductOptions\ProductOptionUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\ProductOptions\ProductOptionsListResource;
use App\Http\Resources\Admin\ProductOptions\ProductOptionsResource;
use App\Http\Resources\Admin\ProductOptions\ProductOptionResource;
use App\Http\Resources\GeneralResource;
use App\Models\ProductOption;
use App\Repositories\ProductOptionRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductOptionController extends Controller
{
    private ProductOptionRepository $repo;

    public function __construct(ProductOptionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return ProductOptionsResource::collection($this->repo->load($request->validated()));
    }

    public function list(ProductOptionListRequest $request): AnonymousResourceCollection
    {
        return ProductOptionsListResource::collection($this->repo->list($request->validated()));
    }

    public function show(ProductOption $productOption): ProductOptionResource
    {
        $productOption = $this->repo->show($productOption);

        return ProductOptionResource::make($productOption);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProductOptionStoreRequest $request
     * @return JsonResponse
     */
    public function store(ProductOptionStoreRequest $request): JsonResponse
    {
        $productOption = $this->repo->store($request);

        $productOption = $this->repo->loadRelations($productOption);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_option_form_saved_successfully'),
            'data' => ProductOptionsResource::make($productOption),
        ]));
    }

    public function update(ProductOptionUpdateRequest $request, ProductOption $productOption): JsonResponse
    {
        $productOption = $this->repo->update($productOption, $request);

        $productOption = $this->repo->loadRelations($productOption);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('product_option_form_saved_successfully'),
            'data' => ProductOptionsResource::make($productOption),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ProductOption $productOption
     * @return JsonResponse
     */
    public function destroy(ProductOption $productOption): JsonResponse
    {
        $this->repo->destroy($productOption);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_option_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(ProductOption $productOption): JsonResponse
    {
        $this->repo->changeActiveStatus($productOption);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(ProductOptionChangeSortRequest $request, ProductOption $productOption): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($productOption, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(ProductOptionChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
