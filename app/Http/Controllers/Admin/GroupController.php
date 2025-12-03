<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Groups\GroupBulkProductsRequest;
use App\Http\Requests\Admin\Groups\GroupChangeSortMultipleRequest;
use App\Http\Requests\Admin\Groups\GroupChangeSortRequest;
use App\Http\Requests\Admin\Groups\GroupStoreRequest;
use App\Http\Requests\Admin\Groups\GroupUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Admin\Groups\GroupsResource;
use App\Http\Resources\Admin\Groups\GroupResource;
use App\Http\Resources\GeneralResource;
use App\Models\Group;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\GroupRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupController extends Controller
{
    private GroupRepository $repo;

    public function __construct(GroupRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return GroupsResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return GroupsListResource::collection($this->repo->list());
    }

    public function show(Group $group): GroupResource
    {
        $group = $this->repo->show($group);

        return GroupResource::make($group);
    }

    public function store(GroupStoreRequest $request): JsonResponse
    {
        $group = $this->repo->store($request);

        $group = $this->repo->loadRelations($group);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('group_form_saved_successfully'),
            'data' => GroupsResource::make($group),
        ]));
    }

    public function update(GroupUpdateRequest $request, Group $group): JsonResponse
    {
        $group = $this->repo->update($group, $request);

        $group = $this->repo->loadRelations($group);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('group_form_saved_successfully'),
            'data' => GroupsResource::make($group),
        ]));
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->repo->destroy($group);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('group_deleted_successfully'),
        ]));
    }

    public function deleteImage(Group $group): JsonResponse
    {
        $this->repo->deleteImage($group);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('group_image_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Group $group): JsonResponse
    {
        $this->repo->changeActiveStatus($group);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(GroupChangeSortRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($group, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(GroupChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function addProduct(Group $group, ProductVariant $productVariant): JsonResponse
    {
        $this->repo->addProduct($group, $productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Product added successfully!')
                ->getLang('group_product_added_successfully'),
        ]));
    }

    public function removeProduct(Group $group, ProductVariant $productVariant): JsonResponse
    {
        $this->repo->removeProduct($group, $productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Product removed successfully!')
                ->getLang('group_product_removed_successfully'),
        ]));
    }

    public function addBulkProducts(GroupBulkProductsRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->addBulkProducts($group, $validated['products'] ?? []);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Products added successfully!')
                ->getLang('group_products_added_successfully'),
        ]));
    }

    public function removeBulkProducts(GroupBulkProductsRequest $request, Group $group): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->removeBulkProducts($group, $validated['products'] ?? []);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Products removed successfully!')
                ->getLang('group_products_removed_successfully'),
        ]));
    }
}
