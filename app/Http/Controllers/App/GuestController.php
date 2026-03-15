<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Guests\GuestStoreRequest;
use App\Services\GuestService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class GuestController extends Controller
{
    public function addGuest(GuestStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'identifier' => GuestService::instance()->storeGuest($validated['device_type'] ?? null),
            'message' => LangService::instance()
                ->setDefault('Guest created successfully!')
                ->getLang('guest_created'),
        ]);
    }
}
