<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Languages\LanguageStoreRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Languages\LanguageResource;
use App\Http\Resources\Admin\Languages\LanguagesListResource;
use App\Http\Resources\Admin\Languages\LanguagesResource;
use App\Http\Resources\GeneralResource;
use App\Models\Language;
use App\Repositories\LanguageRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LanguageController extends Controller
{
    private LanguageRepository $repo;

    public function __construct(LanguageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return LanguagesResource::collection($this->repo->load($request->validated()));
    }

    public function list(): AnonymousResourceCollection
    {
        return LanguagesListResource::collection($this->repo->list());
    }

    public function show(Language $language): LanguageResource
    {
        return LanguageResource::make($language);
    }

    public function store(LanguageStoreRequest $request): JsonResponse
    {
        $language = $this->repo->store($request->validated());

        $this->repo->loadRelations($language);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
            'data' => LanguageResource::make($language),
        ]));
    }

    public function update(LanguageStoreRequest $request, Language $language): JsonResponse
    {
        $updatedLanguage = $this->repo->update($language, $request->validated());

        $this->repo->loadRelations($updatedLanguage);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
            'data' => LanguageResource::make($updatedLanguage),
        ]));
    }

    public function changeActiveStatus(Language $language): JsonResponse
    {
        $this->repo->changeActiveStatus($language);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function destroy(Language $language): JsonResponse
    {
        $this->repo->destroy($language);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('admin_deleted_successfully'),
        ]));
    }
}
