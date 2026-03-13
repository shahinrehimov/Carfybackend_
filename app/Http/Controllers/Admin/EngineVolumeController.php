<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EngineVolumes\EngineVolumeChangeSortMultipleRequest;
use App\Http\Requests\Admin\EngineVolumes\EngineVolumeChangeSortRequest;
use App\Http\Requests\Admin\EngineVolumes\EngineVolumeStoreRequest;
use App\Http\Requests\Admin\EngineVolumes\EngineVolumeUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\EngineVolumes\EngineVolumeResource;
use App\Http\Resources\Admin\EngineVolumes\EngineVolumesListResource;
use App\Http\Resources\Admin\EngineVolumes\EngineVolumesResource;
use App\Http\Resources\GeneralResource;
use App\Models\EngineVolume;
use App\Repositories\EngineVolumeRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EngineVolumeController extends Controller
{
    private EngineVolumeRepository $repo;

    public function __construct(EngineVolumeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return EngineVolumesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return EngineVolumesListResource::collection($this->repo->list());
    }

    public function show(EngineVolume $engineVolume): EngineVolumeResource
    {
        $engineVolume->load(['translatable']);
        return EngineVolumeResource::make($engineVolume);
    }

    public function store(EngineVolumeStoreRequest $request): JsonResponse
    {
        $engineVolume = $this->repo->store($request);
        $engineVolume = $this->repo->loadRelations($engineVolume);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => EngineVolumesResource::make($engineVolume),
        ]));
    }

    public function update(EngineVolumeUpdateRequest $request, EngineVolume $engineVolume): JsonResponse
    {
        $engineVolume = $this->repo->update($engineVolume, $request);
        $engineVolume = $this->repo->loadRelations($engineVolume);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => EngineVolumesResource::make($engineVolume),
        ]));
    }

    public function destroy(EngineVolume $engineVolume): JsonResponse
    {
        $this->repo->destroy($engineVolume);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(EngineVolume $engineVolume): JsonResponse
    {
        $this->repo->changeActiveStatus($engineVolume);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(EngineVolumeChangeSortRequest $request, EngineVolume $engineVolume): JsonResponse
    {
        $this->repo->changeSorting($engineVolume, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(EngineVolumeChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
