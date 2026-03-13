<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\UserCars\UserCarsResource;
use App\Http\Resources\GeneralResource;
use App\Models\User;
use App\Models\UserCar;
use App\Repositories\UserCarRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserCarController extends Controller
{
    private UserCarRepository $repo;

    public function __construct(UserCarRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request, User $user): AnonymousResourceCollection
    {
        return UserCarsResource::collection($this->repo->load($user, $request->validated()));
    }

    public function show(User $user, UserCar $userCar): UserCarsResource
    {
        return UserCarsResource::make($this->repo->show($user, $userCar));
    }

    public function destroy(User $user, UserCar $userCar): JsonResponse
    {
        $this->repo->destroy($user, $userCar);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('admin_deleted_successfully'),
        ]));
    }
}
