<?php

namespace App\Http\Controllers\App\Auth;

use App\Enum\UserOtpProvidersEnum;
use App\Enum\UserOtpTypesEnum;
use App\Enum\UserRegisterTypesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\LoginWithEmailRequest;
use App\Http\Requests\App\Auth\LoginWithPhoneRequest;
use App\Http\Requests\App\Auth\LoginWithThirdPartyProviderRequest;
use App\Http\Requests\App\Auth\SendPhoneOtpRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Models\User;
use App\Models\UserOtpCode;
use App\Services\SmsService;
use App\Services\UserService;
use App\Services\LangService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function loginWithEmail(LoginWithEmailRequest $request): UserResource
    {
        $fields = $request->validated();

        $user = User::query()
            ->where('register_type', UserRegisterTypesEnum::EMAIL->value)
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

        return UserResource::make(UserService::instance()->createToken($user, $fields['device_type'] ?? null));
    }

    public function sendPhoneOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = UserService::instance()->checkPhoneForLogin($validated['phone']);

        $otp = UserService::instance()->generateOtp(UserOtpTypesEnum::LOGIN, UserOtpProvidersEnum::SMS, $user);

        $message = LangService::instance()
            ->setDefault('Your OTP code: @otp')
            ->getLang('phone_otp_message', [
                '@otp' => $otp,
            ]);

        SmsService::instance()->sendOtp($validated['phone'], $message);

        return response()->json([
            'step' => 'otp_phone',
            'otp_expires_in' => UserOtpCode::expiresInSeconds(),
            'message' => LangService::instance()
                ->setDefault('OTP sent to your phone!')
                ->getLang('phone_otp_sent')
        ]);
    }

    public function loginWithPhone(LoginWithPhoneRequest $request): UserResource
    {
        $validated = $request->validated();

        $user = UserService::instance()->checkPhoneForLogin($validated['phone']);

        UserService::instance()->checkOtp(UserOtpTypesEnum::LOGIN, $validated['otp'], $user, UserOtpProvidersEnum::SMS);

        return UserResource::make(UserService::instance()->createToken($user, $validated['device_type'] ?? null));
    }

    public function loginFromThirdPartyProvider(LoginWithThirdPartyProviderRequest $request): UserResource
    {
        return UserResource::make(UserService::instance()->loginWithThirdPartyProvider($request->validated()));
    }
}
