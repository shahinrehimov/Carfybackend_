<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\MetaService;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function getByKey(string $pageKey): JsonResponse
    {
        return response()->json([
            'data' => MetaService::instance()->get($pageKey),
        ]);
    }
}
