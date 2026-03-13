<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FuelTypes\FuelTypeChangeSortMultipleRequest;
use App\Http\Requests\Admin\FuelTypes\FuelTypeChangeSortRequest;
use App\Http\Requests\Admin\FuelTypes\FuelTypeStoreRequest;
use App\Http\Requests\Admin\FuelTypes\FuelTypeUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\FuelTypes\FuelTypeResource;
use App\Http\Resources\Admin\FuelTypes\FuelTypesListResource;
use App\Http\Resources\Admin\FuelTypes\FuelTypesResource;
use App\Http\Resources\GeneralResource;
use App\Models\FuelType;
use App\Repositories\FuelTypeRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FuelTypeController extends Controller
{
    private FuelTypeRepository $repo;

    public function __construct(FuelTypeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return FuelTypesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return FuelTypesListResource::collection($this->repo->list());
    }

    public function show(FuelType $fuelType): FuelTypeResource
    {
        $fuelType->load(['translatable']);
        return FuelTypeResource::make($fuelType);
    }

    public function store(FuelTypeStoreRequest $request): JsonResponse
    {
        $fuelType = $this->repo->store($request);
        $fuelType = $this->repo->loadRelations($fuelType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => FuelTypesResource::make($fuelType),
        ]));
    }

    public function update(FuelTypeUpdateRequest $request, FuelType $fuelType): JsonResponse
    {
        $fuelType = $this->repo->update($fuelType, $request);
        $fuelType = $this->repo->loadRelations($fuelType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => FuelTypesResource::make($fuelType),
        ]));
    }

    public function destroy(FuelType $fuelType): JsonResponse
    {
        $this->repo->destroy($fuelType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(FuelType $fuelType): JsonResponse
    {
        $this->repo->changeActiveStatus($fuelType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(FuelTypeChangeSortRequest $request, FuelType $fuelType): JsonResponse
    {
        $this->repo->changeSorting($fuelType, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(FuelTypeChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
