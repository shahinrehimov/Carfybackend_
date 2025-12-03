<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\CategoryChangeSortMultipleRequest;
use App\Http\Requests\Admin\Categories\CategoryChangeSortRequest;
use App\Http\Requests\Admin\Categories\CategoryListRequest;
use App\Http\Requests\Admin\Categories\CategoryLoadRequest;
use App\Http\Requests\Admin\Categories\CategoryStoreRequest;
use App\Http\Requests\Admin\Categories\CategoryUpdateRequest;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\Admin\Categories\CategoriesResource;
use App\Http\Resources\Admin\Categories\CategoryResource;
use App\Http\Resources\GeneralResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    private CategoryRepository $repo;

    public function __construct(CategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(CategoryLoadRequest $request): AnonymousResourceCollection
    {
        return CategoriesResource::collection($this->repo->load($request->validated()));
    }

    public function loadSubs(CategoryLoadRequest $request, Category $category): AnonymousResourceCollection
    {
        return CategoriesResource::collection($this->repo->loadSubs($category, $request->validated()));
    }

    public function list(CategoryListRequest $request): AnonymousResourceCollection
    {
        return CategoriesListResource::collection($this->repo->list($request->validated()));
    }

    public function listSubs(Category $category): AnonymousResourceCollection
    {
        return CategoriesListResource::collection($this->repo->listSubs($category));
    }

    public function show(Category $category): CategoryResource
    {
        $category = $this->repo->show($category);

        return CategoryResource::make($category);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CategoryStoreRequest $request
     * @return JsonResponse
     */
    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $category = $this->repo->store($request);

        $category = $this->repo->loadRelations($category);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('category_form_saved_successfully'),
            'data' => CategoriesResource::make($category),
        ]));
    }

    public function update(CategoryUpdateRequest $request, Category $category): JsonResponse
    {
        $category = $this->repo->update($category, $request);

        $category = $this->repo->loadRelations($category);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('category_form_saved_successfully'),
            'data' => CategoriesResource::make($category),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->repo->destroy($category);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('category_deleted_successfully'),
        ]));
    }

    public function deleteImage(Category $category): JsonResponse
    {
        $this->repo->deleteImage($category);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('category_image_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Category $category): JsonResponse
    {
        $this->repo->changeActiveStatus($category);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(CategoryChangeSortRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($category, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(CategoryChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
