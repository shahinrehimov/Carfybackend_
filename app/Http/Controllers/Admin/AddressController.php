<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Addresses\AddressesListResource;
use App\Http\Resources\Admin\Addresses\AddressesResource;
use App\Http\Resources\Admin\Addresses\AddressResource;
use App\Http\Resources\GeneralResource;
use App\Models\Address;
use App\Models\User;
use App\Repositories\AddressRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    private AddressRepository $repo;

    public function __construct(AddressRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request, User $user): AnonymousResourceCollection
    {
        return AddressesResource::collection($this->repo->load($user, $request->validated()));
    }

    public function list(User $user): AnonymousResourceCollection
    {
        return AddressesListResource::collection($this->repo->list($user));
    }

    public function show(User $user, Address $address): AddressResource
    {
        $address = $this->repo->show($user, $address);

        return AddressResource::make($address);
    }

    public function destroy(User $user, Address $address): JsonResponse
    {
        $this->repo->destroy($user, $address);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('address_deleted_successfully'),
        ]));
    }
}
