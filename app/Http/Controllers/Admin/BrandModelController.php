<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandModels\BrandModelChangeSortMultipleRequest;
use App\Http\Requests\Admin\BrandModels\BrandModelChangeSortRequest;
use App\Http\Requests\Admin\BrandModels\BrandModelStoreRequest;
use App\Http\Requests\Admin\BrandModels\BrandModelUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use App\Http\Resources\Admin\BrandModels\BrandModelsResource;
use App\Http\Resources\Admin\BrandModels\BrandModelResource;
use App\Http\Resources\GeneralResource;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Repositories\BrandModelRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandModelController extends Controller
{
    private BrandModelRepository $repo;

    public function __construct(BrandModelRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request, Brand $brand): AnonymousResourceCollection
    {
        return BrandModelsResource::collection($this->repo->load($brand, $request->validated()));
    }

    public function list(Brand $brand): AnonymousResourceCollection
    {
        return BrandModelsListResource::collection($this->repo->list($brand));
    }

    public function show(Brand $brand, BrandModel $brandModel): BrandModelResource
    {
        $brandModel = $this->repo->show($brand, $brandModel);

        return BrandModelResource::make($brandModel);
    }

    public function store(BrandModelStoreRequest $request, Brand $brand): JsonResponse
    {
        $brandModel = $this->repo->store($brand, $request);

        $brandModel = $this->repo->loadRelations($brandModel);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('brand_form_saved_successfully'),
            'data' => BrandModelsResource::make($brandModel),
        ]));
    }

    public function update(BrandModelUpdateRequest $request, Brand $brand, BrandModel $brandModel): JsonResponse
    {
        $brandModel = $this->repo->update($brand, $brandModel, $request);

        $brandModel = $this->repo->loadRelations($brandModel);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('brand_form_saved_successfully'),
            'data' => BrandModelsResource::make($brandModel),
        ]));
    }

    public function destroy(Brand $brand, BrandModel $brandModel): JsonResponse
    {
        $this->repo->destroy($brand, $brandModel);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('brand_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Brand $brand, BrandModel $brandModel): JsonResponse
    {
        $this->repo->changeActiveStatus($brand, $brandModel);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(BrandModelChangeSortRequest $request, Brand $brand, BrandModel $brandModel): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($brand, $brandModel, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(BrandModelChangeSortMultipleRequest $request, Brand $brand): JsonResponse
    {
        $this->repo->changeSortingMultiple($brand, $request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
