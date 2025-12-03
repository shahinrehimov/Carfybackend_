<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AppServices\GetServicesRequest;
use App\Services\AppServices;
use Illuminate\Http\JsonResponse;

class ServicesController extends Controller
{
    public function getServicesList(): JsonResponse
    {
        return response()->json([
            'data' => AppServices::instance()->getServicesList(),
        ]);
    }

    public function loadAppServices(GetServicesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'data' => AppServices::instance()->loadAppServices($validated['services']),
        ]);
    }
}
