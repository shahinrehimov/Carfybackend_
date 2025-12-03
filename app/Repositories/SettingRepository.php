<?php

namespace App\Repositories;

use App\Enum\SettingTypeEnum;
use App\Models\Setting;
use App\Services\FileUpload;
use App\Services\LangService;
use App\Services\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Foundation\Http\FormRequest;

class SettingRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Setting::query()
            ->with([
                'creatable',
                'media',
            ])
            ->orderBy('key')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Setting::query()
            ->orderBy('key')
            ->get();
    }

    public function findByKey(string $key): ?Setting
    {
        return Setting::query()
            ->with([
                'media',
            ])
            ->where('key', $key)
            ->first();
    }

    public function store(array $validated, FormRequest $request): Setting
    {
        $validated['key'] = Str::snake($validated['key']);

        if (Setting::query()->where('key', $validated['key'])->exists()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('This key is already in use! Key: @key')
                    ->getLang('setting_key_already_in_use', ['@key' => $validated['key']])
            );
        }

        return DB::transaction(static function () use ($request, $validated) {
            if ($validated['type'] === SettingTypeEnum::FILE->value) {
                $validated['value'] = 'file';
            }

            $setting = Setting::query()->create($validated);

            if($validated['type'] === SettingTypeEnum::FILE->value) {
                FileUpload::upload($request, 'file', 'settings', $setting);
            }

            SettingService::instance()->setCache();

            return $setting;
        });
    }

    public function update(Setting $oldSetting, array $validated, FormRequest $request): Setting
    {
        $validated['type'] = $oldSetting->type;

        return DB::transaction(static function () use ($request, $validated, $oldSetting) {
            $oldSetting->delete();

            if ($validated['type'] === SettingTypeEnum::FILE->value) {
                $validated['value'] = 'file';
            }

            $setting = Setting::query()->create($validated);

            if($validated['type'] === SettingTypeEnum::FILE->value) {
                FileUpload::upload($request, 'file', 'settings', $setting);
            }

            SettingService::instance()->setCache();

            return $setting;
        });
    }

    public function destroy(Setting $setting): void
    {
        if ($setting->isForSystem()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot delete system settings!')
                    ->getLang('you_cannot_delete_system_settings')
            );
        }

        DB::transaction(static function () use ($setting) {
            $setting->delete();

            SettingService::instance()->setCache();
        });
    }
}
