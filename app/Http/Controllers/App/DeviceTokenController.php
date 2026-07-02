<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Notifications\DeviceTokenRequest;
use App\Models\User;
use App\Services\LangService;
use App\Services\NotificationService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        NotificationService::instance()->registerDeviceToken(
            $this->currentUser(),
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

        NotificationService::instance()->deregisterDeviceToken($this->currentUser(), $request->token);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Device unregistered.')
                ->getLang('device_token_unregistered'),
        ]);
    }

    /**
     * Resolve the authenticated app user via the "user" guard, or null for
     * guests. This endpoint is public, so the "auth:user" middleware no longer
     * runs and the default guard cannot be relied on.
     */
    private function currentUser(): ?User
    {
        $userService = UserService::instance();

        return $userService->checkUser() ? $userService->getUser() : null;
    }
}
