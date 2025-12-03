<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brands\BrandChangeSortMultipleRequest;
use App\Http\Requests\Admin\Brands\BrandChangeSortRequest;
use App\Http\Requests\Admin\Brands\BrandStoreRequest;
use App\Http\Requests\Admin\Brands\BrandUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\Brands\BrandsResource;
use App\Http\Resources\Admin\Brands\BrandResource;
use App\Http\Resources\GeneralResource;
use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    private BrandRepository $repo;

    public function __construct(BrandRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return BrandsResource::collection($this->repo->load($request->validated()));
    }
    public function list(): AnonymousResourceCollection
    {
        return BrandsListResource::collection($this->repo->list());
    }

    public function show(Brand $brand): BrandResource
    {
        $brand = $this->repo->show($brand);

        return BrandResource::make($brand);
    }

    public function store(BrandStoreRequest $request): JsonResponse
    {
        $brand = $this->repo->store($request);

        $brand = $this->repo->loadRelations($brand);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('brand_form_saved_successfully'),
            'data' => BrandsResource::make($brand),
        ]));
    }

    public function update(BrandUpdateRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->repo->update($brand, $request);

        $brand = $this->repo->loadRelations($brand);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('brand_form_saved_successfully'),
            'data' => BrandsResource::make($brand),
        ]));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->repo->destroy($brand);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('brand_deleted_successfully'),
        ]));
    }

    public function deleteImage(Brand $brand): JsonResponse
    {
        $this->repo->deleteImage($brand);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('brand_image_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Brand $brand): JsonResponse
    {
        $this->repo->changeActiveStatus($brand);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(BrandChangeSortRequest $request, Brand $brand): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($brand, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(BrandChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
