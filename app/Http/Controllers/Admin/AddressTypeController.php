<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddressTypes\AddressTypeChangeSortMultipleRequest;
use App\Http\Requests\Admin\AddressTypes\AddressTypeChangeSortRequest;
use App\Http\Requests\Admin\AddressTypes\AddressTypeStoreRequest;
use App\Http\Requests\Admin\AddressTypes\AddressTypeUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\AddressTypes\AddressTypesListResource;
use App\Http\Resources\Admin\AddressTypes\AddressTypesResource;
use App\Http\Resources\Admin\AddressTypes\AddressTypeResource;
use App\Http\Resources\GeneralResource;
use App\Models\AddressType;
use App\Repositories\AddressTypeRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressTypeController extends Controller
{
    private AddressTypeRepository $repo;

    public function __construct(AddressTypeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return AddressTypesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return AddressTypesListResource::collection($this->repo->list());
    }

    public function show(AddressType $addressType): AddressTypeResource
    {
        $addressType = $this->repo->show($addressType);

        return AddressTypeResource::make($addressType);
    }

    public function store(AddressTypeStoreRequest $request): JsonResponse
    {
        $addressType = $this->repo->store($request);

        $addressType = $this->repo->loadRelations($addressType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('address_type_form_saved_successfully'),
            'data' => AddressTypesResource::make($addressType),
        ]));
    }

    public function update(AddressTypeUpdateRequest $request, AddressType $addressType): JsonResponse
    {
        $addressType = $this->repo->update($addressType, $request);

        $addressType = $this->repo->loadRelations($addressType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('address_type_form_saved_successfully'),
            'data' => AddressTypesResource::make($addressType),
        ]));
    }

    public function destroy(AddressType $addressType): JsonResponse
    {
        $this->repo->destroy($addressType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('address_type_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(AddressType $addressType): JsonResponse
    {
        $this->repo->changeActiveStatus($addressType);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(AddressTypeChangeSortRequest $request, AddressType $addressType): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($addressType, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(AddressTypeChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
