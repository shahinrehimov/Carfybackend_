<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Users\UsersListResource;
use App\Http\Resources\Admin\Users\UsersResource;
use App\Http\Resources\Admin\Users\UserResource;
use App\Http\Resources\GeneralResource;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    private UserRepository $repo;

    public function __construct(UserRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return UsersResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return UsersListResource::collection($this->repo->list());
    }

    public function show(User $user): UserResource
    {
        $user = $this->repo->show($user);

        return UserResource::make($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->repo->destroy($user);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('user_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(User $user): JsonResponse
    {
        $this->repo->changeActiveStatus($user);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }
}
