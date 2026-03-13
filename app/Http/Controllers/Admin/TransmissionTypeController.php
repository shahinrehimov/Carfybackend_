<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransmissionTypes\TransmissionTypeChangeSortMultipleRequest;
use App\Http\Requests\Admin\TransmissionTypes\TransmissionTypeChangeSortRequest;
use App\Http\Requests\Admin\TransmissionTypes\TransmissionTypeStoreRequest;
use App\Http\Requests\Admin\TransmissionTypes\TransmissionTypeUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\TransmissionTypes\TransmissionTypeResource;
use App\Http\Resources\Admin\TransmissionTypes\TransmissionTypesListResource;
use App\Http\Resources\Admin\TransmissionTypes\TransmissionTypesResource;
use App\Http\Resources\GeneralResource;
use App\Models\TransmissionType;
use App\Repositories\TransmissionTypeRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransmissionTypeController extends Controller
{
    private TransmissionTypeRepository $repo;

    public function __construct(TransmissionTypeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return TransmissionTypesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return TransmissionTypesListResource::collection($this->repo->list());
    }

    public function show(TransmissionType $transmissionType): TransmissionTypeResource
    {
        $transmissionType->load(['translatable']);
        return TransmissionTypeResource::make($transmissionType);
    }

    public function store(TransmissionTypeStoreRequest $request): JsonResponse
    {
        $transmissionType = $this->repo->store($request);
        $transmissionType = $this->repo->loadRelations($transmissionType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => TransmissionTypesResource::make($transmissionType),
        ]));
    }

    public function update(TransmissionTypeUpdateRequest $request, TransmissionType $transmissionType): JsonResponse
    {
        $transmissionType = $this->repo->update($transmissionType, $request);
        $transmissionType = $this->repo->loadRelations($transmissionType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => TransmissionTypesResource::make($transmissionType),
        ]));
    }

    public function destroy(TransmissionType $transmissionType): JsonResponse
    {
        $this->repo->destroy($transmissionType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(TransmissionType $transmissionType): JsonResponse
    {
        $this->repo->changeActiveStatus($transmissionType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(TransmissionTypeChangeSortRequest $request, TransmissionType $transmissionType): JsonResponse
    {
        $this->repo->changeSorting($transmissionType, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(TransmissionTypeChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
