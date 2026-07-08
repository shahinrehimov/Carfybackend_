<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\AppleNativeLoginRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Services\AppleService;
use App\Services\UserService;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Request;

class AppleController extends Controller
{
    public function redirect(): JsonResponse
    {
        return response()->json([
            'step' => 'redirect',
            'url' => AppleService::instance()->getLoginUrl(),
        ]);
    }

    public function callback(Request $request): Application|Redirector|RedirectResponse
    {
        $appleUser = AppleService::instance()->getCallbackUser();
        $refCode   = $request::query('ref_code');

        $user = UserService::instance()->getUserByApple($appleUser);

        $user = AppleService::instance()->updateOrCreateUser($user, $appleUser, $refCode);

        $loginToken = UserService::instance()->generateLoginTokenForThirdPartyApp($user, 'apple', $appleUser['sub']);

        return redirect(config('app.frontend_url') . '/auth/third-party-login?token=' . $loginToken . '&provider=apple');
    }

    public function nativeLogin(AppleNativeLoginRequest $request): UserResource
    {
        $validated = $request->validated();

        $appleUser = AppleService::instance()->loginFromNative($validated);

        $user = UserService::instance()->getUserByApple($appleUser);

        $user = AppleService::instance()->updateOrCreateUser($user, $appleUser, $validated['ref_code'] ?? null);

        return UserResource::make(UserService::instance()->createToken($user, $validated['device_type'] ?? null));
    }
}
