<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Groups\GroupLoadWithProductsRequest;
use App\Http\Requests\App\Groups\GroupsLoadWithProductsRequest;
use App\Http\Resources\App\Groups\GroupsResource;
use App\Models\Group;
use App\Repositories\GroupRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupController extends Controller
{
    private GroupRepository $repo;

    public function __construct(GroupRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(): AnonymousResourceCollection
    {
        $groups = Group::query()
            ->active()
            ->with(['translatable', 'media'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return GroupsResource::collection($groups);
    }

    public function loadGroupWithProducts(GroupLoadWithProductsRequest $request, string $groupName): GroupsResource
    {
        return GroupsResource::make($this->repo->loadGroupWithProducts($groupName, $request->validated()));
    }

    public function loadGroupsWithProducts(GroupsLoadWithProductsRequest $request): AnonymousResourceCollection
    {
        return GroupsResource::collection($this->repo->loadGroupsWithProducts($request->validated()));
    }
}
