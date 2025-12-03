<?php

namespace App\Http\Controllers;

use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class LocalTranslationsController extends Controller
{
    public function getLanguages(): JsonResponse
    {
        return response()->json([
            'data' => LangService::instance()->getLanguages(),
            'versions' => json_decode(Storage::disk('public')->get('versions.json')),
        ]);
    }

    public function getTranslations(string $lang): JsonResponse
    {
        LangService::instance()->getLanguageByKey($lang);

        return response()->json([
            'data' => [
                'version' => LangService::instance()->getVersion(),
                'translations' => LangService::instance()->setLanguage($lang)->getStaticTranslations(),
            ]
        ]);
    }
}
