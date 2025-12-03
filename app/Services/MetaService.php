<?php

namespace App\Services;

use App\Models\Meta;
use Illuminate\Support\Facades\Cache;

class MetaService
{
    private static ?MetaService $instance = null;
    private string $key = 'site_meta_';

    private function __construct() {

    }

    public static function instance(): MetaService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get(string $pageKey)
    {
        return json_decode($this->getCache($pageKey), false);
    }

    public function getCache(string $pageKey)
    {
        $cacheKey = $this->key . $pageKey . '_' . LangService::instance()->getCurrentLang();

        if (! Cache::has($cacheKey)) {
            $this->setCache($pageKey);
        }

        if (! Cache::has($cacheKey)) {
            return json_encode([]);
        }

        return Cache::get($cacheKey);
    }

    public function setCache(string $pageKey): void
    {
        $this->clearCache($pageKey);

        $meta = Meta::query()
            ->where('page_key', $pageKey)
            ->with([
                'translatable',
            ])
            ->first();

        if ($meta) {
            $languages = collect(LangService::instance()->getLanguages());

            foreach ($meta->translations as $translation) {
                $lang = $languages->where('id', $translation['language_id'])->first();

                if ($lang) {
                    $data = [
                        'title' => $translation['title'],
                        'keywords' => $translation['keywords'],
                        'description' => $translation['description'],
                    ];

                    $cacheKey = $this->key . $pageKey . '_' . $lang['key'];

                    Cache::rememberForever($cacheKey, function () use ($data) {
                        return json_encode($data);
                    });

                    LoggerService::instance()->log($cacheKey . ": cache cleared", [], true);
                }
            }
        }
    }

    public function clearCache(string $pageKey): void
    {
        foreach (LangService::instance()->getLanguages() as $language) {
            if (Cache::has($this->key . $pageKey . '_' . $language['key'])) {
                Cache::forget($this->key . $pageKey . '_' . $language['key']);
            }
        }
    }
}
