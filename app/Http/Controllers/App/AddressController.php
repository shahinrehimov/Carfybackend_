<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Addresses\AddressStoreRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\Addresses\AddressesResource;
use App\Http\Resources\GeneralResource;
use App\Models\Address;
use App\Repositories\AddressRepository;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    private AddressRepository $repo;

    public function __construct(AddressRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return AddressesResource::collection($this->repo->load(UserService::instance()->getUser(), $request->validated()));
    }

    public function show(Address $address): AddressesResource
    {
        $address = $this->repo->show(UserService::instance()->getUser(), $address);

        return AddressesResource::make($address);
    }

    public function store(AddressStoreRequest $request): JsonResponse
    {
        $address = $this->repo->store(UserService::instance()->getUser(), $request);

        $address = $this->repo->loadRelations($address);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('user_address_form_saved_successfully'),
            'data' => AddressesResource::make($address),
        ]));
    }

    public function update(AddressStoreRequest $request, Address $address): JsonResponse
    {
        $address = $this->repo->update(UserService::instance()->getUser(), $address, $request);

        $address = $this->repo->loadRelations($address);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('user_address_form_saved_successfully'),
            'data' => AddressesResource::make($address),
        ]));
    }

    public function destroy(Address $address): JsonResponse
    {
        $this->repo->destroy(UserService::instance()->getUser(), $address);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('user_address_deleted_successfully'),
        ]));
    }

    public function setDefault(Address $address): JsonResponse
    {
        $this->repo->setDefault(UserService::instance()->getUser(), $address);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Set as default successfully!')
                ->getLang('user_address_set_default_successfully'),
        ]));
    }
}
