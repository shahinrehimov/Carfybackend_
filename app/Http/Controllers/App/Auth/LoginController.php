<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\LoginRequest;
use App\Http\Requests\App\Auth\LoginWithThirdPartyProviderRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Services\LangService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $fields = $request->validated();

        $user = User::query()
            ->whereNotNull('password')
            ->where('email', $fields['email'])
            ->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            throw new HttpResponseException(response()->json([
                'message' => LangService::instance()
                    ->setDefault('Invalid username or password!')
                    ->getLang('invalid_username_or_password'),
            ], 401));
        }

        return UserResource::make(UserService::instance()->createToken($user, $fields['device_type']));
    }

    public function loginFromThirdPartyProvider(LoginWithThirdPartyProviderRequest $request): UserResource
    {
        return UserResource::make(UserService::instance()->loginWithThirdPartyProvider($request->validated()));
    }
}
