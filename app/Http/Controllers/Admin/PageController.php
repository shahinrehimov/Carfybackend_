<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\PageChangeSortMultipleRequest;
use App\Http\Requests\Admin\Pages\PageChangeSortRequest;
use App\Http\Requests\Admin\Pages\PageStoreRequest;
use App\Http\Requests\Admin\Pages\PageUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Pages\PagesListResource;
use App\Http\Resources\Admin\Pages\PagesResource;
use App\Http\Resources\Admin\Pages\PageResource;
use App\Http\Resources\GeneralResource;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController extends Controller
{
    private PageRepository $repo;

    public function __construct(PageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return PagesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return PagesListResource::collection($this->repo->list());
    }

    public function show(Page $page): PageResource
    {
        $page = $this->repo->show($page);

        return PageResource::make($page);
    }

    public function store(PageStoreRequest $request): JsonResponse
    {
        $page = $this->repo->store($request);

        $page = $this->repo->loadRelations($page);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('page_form_saved_successfully'),
            'data' => PagesResource::make($page),
        ]));
    }

    public function update(PageUpdateRequest $request, Page $page): JsonResponse
    {
        $page = $this->repo->update($page, $request);

        $page = $this->repo->loadRelations($page);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('page_form_saved_successfully'),
            'data' => PagesResource::make($page),
        ]));
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->repo->destroy($page);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('page_deleted_successfully'),
        ]));
    }

    public function destroyImage(Page $page, int $mediaId): JsonResponse
    {
        $this->repo->deleteImage($page, $mediaId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Selected media file deleted successfully!')
                ->getLang('pages_media_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Page $page): JsonResponse
    {
        $this->repo->changeActiveStatus($page);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(PageChangeSortRequest $request, Page $page): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($page, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(PageChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
