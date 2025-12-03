<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admins\AdminStoreRequest;
use App\Http\Requests\Admin\Admins\AdminUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Admins\AdminResource;
use App\Http\Resources\Admin\Admins\AdminsListResource;
use App\Http\Resources\Admin\Admins\AdminsResource;
use App\Http\Resources\GeneralResource;
use App\Models\Admin;
use App\Repositories\AdminRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    private AdminRepository $repo;

    public function __construct(AdminRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @param GeneralListRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return AdminsResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return AdminsListResource::collection($this->repo->list());
    }

    public function listByRole(string $role): AnonymousResourceCollection
    {
        return AdminsListResource::collection($this->repo->list($role));
    }

    public function show(Admin $admin): AdminResource
    {
        $this->repo->show($admin);

        return AdminResource::make($admin);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AdminStoreRequest $request
     * @return JsonResponse
     */
    public function store(AdminStoreRequest $request): JsonResponse
    {
        $admin = $this->repo->store($request);

        $admin->load([
            'creatable',
            'media',
            'roles',
        ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
            'data' => AdminsResource::make($admin),
        ]));
    }

    public function update(AdminUpdateRequest $request, Admin $admin): JsonResponse
    {
        $admin = $this->repo->update($admin, $request);

        $admin->load([
            'creatable',
            'media',
            'roles',
        ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
            'data' => AdminsResource::make($admin),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Admin $admin
     * @return JsonResponse
     */
    public function destroy(Admin $admin): JsonResponse
    {
        $this->repo->destroy($admin);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('admin_deleted_successfully'),
        ]));
    }
}
