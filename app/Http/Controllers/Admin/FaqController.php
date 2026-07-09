<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Faqs\FaqChangeSortMultipleRequest;
use App\Http\Requests\Admin\Faqs\FaqChangeSortRequest;
use App\Http\Requests\Admin\Faqs\FaqStoreRequest;
use App\Http\Requests\Admin\Faqs\FaqUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Faqs\FaqResource;
use App\Http\Resources\Admin\Faqs\FaqsListResource;
use App\Http\Resources\Admin\Faqs\FaqsResource;
use App\Http\Resources\GeneralResource;
use App\Models\Faq;
use App\Repositories\FaqRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaqController extends Controller
{
    private FaqRepository $repo;

    public function __construct(FaqRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return FaqsResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return FaqsListResource::collection($this->repo->list());
    }

    public function show(Faq $faq): FaqResource
    {
        $faq->load(['translatable']);
        return FaqResource::make($faq);
    }

    public function store(FaqStoreRequest $request): JsonResponse
    {
        $faq = $this->repo->store($request);
        $faq = $this->repo->loadRelations($faq);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => FaqsResource::make($faq),
        ]));
    }

    public function update(FaqUpdateRequest $request, Faq $faq): JsonResponse
    {
        $faq = $this->repo->update($faq, $request);
        $faq = $this->repo->loadRelations($faq);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Saved successfully!')->getLang('admin_saved_successfully'),
            'data'    => FaqsResource::make($faq),
        ]));
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $this->repo->destroy($faq);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')->getLang('admin_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Faq $faq): JsonResponse
    {
        $this->repo->changeActiveStatus($faq);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Status changed successfully!')->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(FaqChangeSortRequest $request, Faq $faq): JsonResponse
    {
        $this->repo->changeSorting($faq, $request->validated('sort'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(FaqChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Sorting changed successfully!')->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
