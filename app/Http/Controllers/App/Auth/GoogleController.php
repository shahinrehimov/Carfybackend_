<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\GoogleNativeLoginRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Services\GoogleService;
use App\Services\UserService;
use GuzzleHttp\Exception\ClientException;
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
        // Google redirects back with `error` and no `code` when the user
        // cancels on the consent screen; there is nothing to exchange.
        if ($request::query('error') || !$request::query('code')) {
            return $this->redirectToFrontendWithError($request::query('error') ?: 'access_denied');
        }

        try {
            $gUser = GoogleService::instance()->getCallbackUser($request);
        } catch (ClientException) {
            // The code is single-use and short-lived; a refresh/replay of the
            // callback URL makes Google reject the exchange with a 400.
            return $this->redirectToFrontendWithError('invalid_code');
        }

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

    private function redirectToFrontendWithError(string $error): Application|Redirector|RedirectResponse
    {
        return redirect(config('app.frontend_url') . '/auth/third-party-login?provider=google&error=' . urlencode($error));
    }
}
