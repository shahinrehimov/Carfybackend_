<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\SettingStoreRequest;
use App\Http\Requests\Admin\Settings\SettingUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Settings\SettingsListResource;
use App\Http\Resources\Admin\Settings\SettingsResource;
use App\Http\Resources\Admin\Settings\SettingResource;
use App\Http\Resources\GeneralResource;
use App\Models\Setting;
use App\Repositories\SettingRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    private SettingRepository $repo;

    public function __construct(SettingRepository $repo)
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
        return SettingsResource::collection($this->repo->load($request->validated()));

    }

    public function list(): AnonymousResourceCollection
    {
        return SettingsListResource::collection($this->repo->list());
    }

    public function show(string $key): SettingResource|JsonResponse
    {
        $setting = $this->repo->findByKey($key);

        if (!$setting) {
            return response()->json(GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('This key not found! Key: @key')
                    ->getLang('setting_key_not_found', ['@key' => $key]),
                'key' => $key,
            ]), 404);
        }

        return SettingResource::make($setting);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param SettingStoreRequest $request
     * @return JsonResponse
     */
    public function store(SettingStoreRequest $request): JsonResponse
    {
        $setting = $this->repo->store($request->validated(), $request);

        $setting->load([
            'creatable',
            'media',
        ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('setting_form_saved_successfully'),
            'data' => SettingsResource::make($setting),
        ]));
    }

    public function update(SettingUpdateRequest $request, string $key): JsonResponse
    {
        $validated = $request->validated();

        $oldSetting = Setting::query()->where('key', $key)->firstOrFail();

        $validated['key'] = $key;

        $setting = $this->repo->update($oldSetting, $validated, $request);

        $setting->load([
            'creatable',
            'media',
        ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('setting_form_saved_successfully'),
            'data' => SettingsResource::make($setting),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $key
     * @return JsonResponse
     */
    public function destroy(string $key): JsonResponse
    {
        $setting = $this->repo->findByKey($key);

        if (!$setting) {
            return response()->json(GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('This key not found! Key: @key')
                    ->getLang('setting_key_not_found', ['@key' => $key]),
                'key' => $key,
            ]), 404);
        }

        $this->repo->destroy($setting);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('setting_deleted_successfully'),
        ]));
    }
}
