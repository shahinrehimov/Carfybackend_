<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarTypes\CarTypeChangeSortMultipleRequest;
use App\Http\Requests\Admin\CarTypes\CarTypeChangeSortRequest;
use App\Http\Requests\Admin\CarTypes\CarTypeStoreRequest;
use App\Http\Requests\Admin\CarTypes\CarTypeUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\CarTypes\CarTypeResource;
use App\Http\Resources\Admin\CarTypes\CarTypesListResource;
use App\Http\Resources\Admin\CarTypes\CarTypesResource;
use App\Http\Resources\GeneralResource;
use App\Models\CarType;
use App\Repositories\CarTypeRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarTypeController extends Controller
{
    private CarTypeRepository $repo;

    public function __construct(CarTypeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return CarTypesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return CarTypesListResource::collection($this->repo->list());
    }

    public function show(CarType $carType): CarTypeResource
    {
        $carType->load(['translatable']);
        return CarTypeResource::make($carType);
    }

    public function store(CarTypeStoreRequest $request): JsonResponse
    {
        $carType = $this->repo->store($request);
        $carType = $this->repo->loadRelations($carType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => CarTypesResource::make($carType),
        ]));
    }

    public function update(CarTypeUpdateRequest $request, CarType $carType): JsonResponse
    {
        $carType = $this->repo->update($carType, $request);
        $carType = $this->repo->loadRelations($carType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => CarTypesResource::make($carType),
        ]));
    }

    public function destroy(CarType $carType): JsonResponse
    {
        $this->repo->destroy($carType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(CarType $carType): JsonResponse
    {
        $this->repo->changeActiveStatus($carType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(CarTypeChangeSortRequest $request, CarType $carType): JsonResponse
    {
        $this->repo->changeSorting($carType, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(CarTypeChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
