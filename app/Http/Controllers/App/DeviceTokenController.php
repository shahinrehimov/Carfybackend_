<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Notifications\DeviceTokenRequest;
use App\Services\LangService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        NotificationService::instance()->registerDeviceToken(
            auth()->user(),
            $request->token,
            $request->device_type
        );

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Device registered.')
                ->getLang('device_token_registered'),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        NotificationService::instance()->deregisterDeviceToken(auth()->user(), $request->token);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Device unregistered.')
                ->getLang('device_token_unregistered'),
        ]);
    }
}
