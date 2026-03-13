<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UserCars\UserCarStoreRequest;
use App\Http\Requests\App\UserCars\UserCarUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\UserCars\UserCarResource;
use App\Http\Resources\GeneralResource;
use App\Models\UserCar;
use App\Repositories\UserCarRepository;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserCarController extends Controller
{
    private UserCarRepository $repo;

    public function __construct(UserCarRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        $user = UserService::instance()->getUser();
        return UserCarResource::collection($this->repo->load($user, $request->validated()));
    }

    public function show(UserCar $userCar): UserCarResource
    {
        $user = UserService::instance()->getUser();
        return UserCarResource::make($this->repo->show($user, $userCar));
    }

    public function store(UserCarStoreRequest $request): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $userCar = $this->repo->store($user, $request);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Car added successfully!')->getLang('user_car_saved_successfully'),
            'data'    => UserCarResource::make($userCar),
        ]));
    }

    public function update(UserCarUpdateRequest $request, UserCar $userCar): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $userCar = $this->repo->update($user, $userCar, $request);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Car updated successfully!')->getLang('user_car_updated_successfully'),
            'data'    => UserCarResource::make($userCar),
        ]));
    }

    public function destroy(UserCar $userCar): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $this->repo->destroy($user, $userCar);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Car deleted successfully!')->getLang('user_car_deleted_successfully'),
        ]));
    }

    public function deletePhoto(UserCar $userCar): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $this->repo->deletePhoto($user, $userCar);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Photo deleted successfully!')->getLang('user_car_photo_deleted_successfully'),
        ]));
    }
}
