<?php

namespace App\Services;

use App\Enum\SettingTypeEnum;
use App\Models\Setting;
use App\Traits\AppServicesTrait;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    use AppServicesTrait;

    private static ?SettingService $instance = null;
    private string $key = 'settings';

    private function __construct() {

    }

    public static function instance(): SettingService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get()
    {
        return json_decode($this->getCache(), false);
    }

    public function getCache()
    {
        if (!Cache::has($this->key)) {
            $this->setCache();
        }

        return Cache::get($this->key);
    }

    public function setCache(): void
    {
        $this->clearCache();

        $settings = Setting::query()
            ->with([
                'media',
            ])
            ->get();

        $data = [];
        foreach ($settings as $setting) {
            $data[$setting->key] = $setting->type === SettingTypeEnum::FILE->value ? $setting->file : $setting->value;
        }

        Cache::rememberForever($this->key, function () use ($data) {
            return json_encode($data);
        });

        LoggerService::instance()->log($this->key . ": cache cleared", [], true);
    }

    public function clearCache(): void
    {
        if (Cache::has($this->key)) {
            Cache::forget($this->key);
        }
    }

    public function getItems()
    {
        return $this->get();
    }

    public function getItem(string $key): ?string
    {
        return $this->get()->$key ?? null;
    }
}
