<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarColors\CarColorChangeSortMultipleRequest;
use App\Http\Requests\Admin\CarColors\CarColorChangeSortRequest;
use App\Http\Requests\Admin\CarColors\CarColorStoreRequest;
use App\Http\Requests\Admin\CarColors\CarColorUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\CarColors\CarColorResource;
use App\Http\Resources\Admin\CarColors\CarColorsListResource;
use App\Http\Resources\Admin\CarColors\CarColorsResource;
use App\Http\Resources\GeneralResource;
use App\Models\CarColor;
use App\Repositories\CarColorRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarColorController extends Controller
{
    private CarColorRepository $repo;

    public function __construct(CarColorRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return CarColorsResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return CarColorsListResource::collection($this->repo->list());
    }

    public function show(CarColor $carColor): CarColorResource
    {
        $carColor->load(['translatable']);
        return CarColorResource::make($carColor);
    }

    public function store(CarColorStoreRequest $request): JsonResponse
    {
        $carColor = $this->repo->store($request);
        $carColor = $this->repo->loadRelations($carColor);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => CarColorsResource::make($carColor),
        ]));
    }

    public function update(CarColorUpdateRequest $request, CarColor $carColor): JsonResponse
    {
        $carColor = $this->repo->update($carColor, $request);
        $carColor = $this->repo->loadRelations($carColor);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => CarColorsResource::make($carColor),
        ]));
    }

    public function destroy(CarColor $carColor): JsonResponse
    {
        $this->repo->destroy($carColor);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(CarColor $carColor): JsonResponse
    {
        $this->repo->changeActiveStatus($carColor);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(CarColorChangeSortRequest $request, CarColor $carColor): JsonResponse
    {
        $this->repo->changeSorting($carColor, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(CarColorChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
