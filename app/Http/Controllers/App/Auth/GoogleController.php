<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\GoogleNativeLoginRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Services\GoogleService;
use App\Services\UserService;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Request;

class GoogleController extends Controller
{
    public function redirect(): JsonResponse
    {
        return response()->json([
            'step' => 'redirect',
            'url' => GoogleService::instance()->getLoginUrl(),
        ]);
    }

    public function callback(Request $request): Application|Redirector|RedirectResponse
    {
        $gUser   = GoogleService::instance()->getCallbackUser($request);
        $refCode = $request::query('ref_code');

        $user = UserService::instance()->getUserByGoogle($gUser);

        $user = GoogleService::instance()->updateOrCreateUser($user, $gUser, $refCode);

        $loginToken = UserService::instance()->generateLoginTokenForThirdPartyApp($user, 'google', $gUser->getId());

        return redirect(config('app.frontend_url') . '/auth/third-party-login?token=' . $loginToken . '&provider=google');
    }

    public function nativeLogin(GoogleNativeLoginRequest $request): UserResource
    {
        $validated = $request->validated();

        $gUser = GoogleService::instance()->loginFromNative($validated);

        $user = UserService::instance()->getUserByGoogle($gUser);

        $user = GoogleService::instance()->updateOrCreateUser($user, $gUser, $validated['ref_code'] ?? null);

        return UserResource::make(UserService::instance()->createToken($user, $validated['device_type'] ?? null));
    }
}
