<?php

namespace App\Http\Controllers\Admin;

use App\Enum\TranslationGroupEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Translations\GetTranslationsRequest;
use App\Http\Requests\Admin\Translations\TranslationStoreRequest;
use App\Http\Requests\Admin\Translations\TranslationUpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Repositories\TranslationRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TranslationController extends Controller
{
    private TranslationRepository $repo;

    public function __construct(TranslationRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GetTranslationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $languages = LangService::instance()->getLanguages();

        $translations = $this->repo->load($languages, $validated);

        return response()->json([
            'data' => [
                'translations' => $translations,
                'languages' => $languages,
            ]
        ]);
    }

    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'groups' => TranslationGroupEnum::getList(),
            ]
        ]);
    }

    public function show(string $group, string $key): JsonResponse
    {
        $translations = $this->repo->findByKey($group, $key);

        if (count($translations) === 0) {
            return response()->json(GeneralResource::make([
                'message' => 'Translations not found!',
            ]), 404);
        };

        return response()->json([
            'data' => [
                'translations' => $translations,
            ]
        ]);
    }

    public function update(TranslationUpdateRequest $request, string $group, string $key): JsonResponse
    {
        if (! $this->repo->checkByKey($group, $key)) {
            return response()->json(GeneralResource::make([
                'message' => 'Translations not found!',
            ]), 404);
        }

        $this->repo->update($group, $key, $request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
        ]));
    }

    public function create(): JsonResponse
    {
        return response()->json([
            'data' => [
                'input' => [
                    'key' => '',
                    'group' => '',
                    'translations' => $this->repo->create(),
                ],
                'groups' => TranslationGroupEnum::getList(),
            ]
        ]);
    }

    public function store(TranslationStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['key'] = Str::snake($validated['key']);

        if ($this->repo->isExists($validated['group'], $validated['key'], array_column($validated['translations'], 'language_id'))) {
            return response()->json(GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('This translation is exits!')
                    ->getLang('translation_is_exits'),
                'key' => $validated['key'],
            ]), 400);
        }

        $this->repo->store($validated);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
        ]));
    }

    public function destroy(string $group, string $key): JsonResponse
    {
        $translations = $this->repo->getByKey($group, $key);

        if (count($translations) === 0) {
            return response()->json(GeneralResource::make([
                'message' => 'Translations not found!',
            ]), 404);
        }

        $this->repo->destroy($translations);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('admin_deleted_successfully'),
        ]));
    }
}
