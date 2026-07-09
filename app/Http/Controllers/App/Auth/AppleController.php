<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\AppleNativeLoginRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Services\AppleService;
use App\Services\UserService;
use GuzzleHttp\Exception\ClientException;
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
        // Apple posts back with `error` (e.g. user_cancelled_authorize) and no
        // `code` when the user cancels; there is nothing to exchange.
        if ($request::input('error') || !$request::input('code')) {
            return $this->redirectToFrontendWithError($request::input('error') ?: 'access_denied');
        }

        try {
            $appleUser = AppleService::instance()->getCallbackUser();
        } catch (ClientException) {
            // The code is single-use and short-lived; a refresh/replay of the
            // callback makes Apple reject the exchange with a 400.
            return $this->redirectToFrontendWithError('invalid_code');
        }

        $refCode = $request::query('ref_code');

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

    private function redirectToFrontendWithError(string $error): Application|Redirector|RedirectResponse
    {
        return redirect(config('app.frontend_url') . '/auth/third-party-login?provider=apple&error=' . urlencode($error));
    }
}
