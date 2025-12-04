<?php

namespace App\Http\Controllers\App\Auth;

use App\Enum\UserOtpProvidersEnum;
use App\Enum\UserOtpTypesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\CheckPhoneOtpRequest;
use App\Http\Requests\App\Auth\RegisterWithPhoneRequest;
use App\Http\Requests\App\Auth\SendPhoneOtpRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Services\LangService;
use App\Services\SmsService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class RegisterWithPhoneController extends Controller
{
    public function sendOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        UserService::instance()->checkPhone($validated['phone']);

        $otp = UserService::instance()->generateOtp(UserOtpTypesEnum::REGISTER, UserOtpProvidersEnum::SMS, null, $validated['phone']);

        $message = LangService::instance()
            ->setDefault('Your OTP code: @otp')
            ->getLang('phone_otp_message', [
                '@otp' => $otp,
            ]);

        SmsService::instance()->send($validated['phone'], $message);

        return response()->json([
            'step' => 'otp_phone',
            'message' => LangService::instance()
                ->setDefault('OTP sent to your phone!')
                ->getLang('phone_otp_sent')
        ]);
    }

    public function checkOtp(CheckPhoneOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $confirmationToken = UserService::instance()->checkOtp(UserOtpTypesEnum::REGISTER, $validated['otp'], null, UserOtpProvidersEnum::SMS, $validated['phone']);

        return response()->json([
            'step' => 'register_with_phone',
            'phone_confirmation_token' => $confirmationToken,
            'message' => LangService::instance()
                ->setDefault('Otp verified successfully')
                ->getLang('phone_otp_verified_successfully')
        ]);
    }

    public function register(RegisterWithPhoneRequest $request): JsonResponse
    {
        $user = UserService::instance()->registerWithPhone($request);

        return response()->json([
            'step' => 'login',
            'user' => UserResource::make($user),
            'message' => LangService::instance()
                ->setDefault('Register successfully!')
                ->getLang('register_successfully_with_phone'),
        ]);
    }
}
