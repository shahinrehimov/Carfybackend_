<?php

namespace App\Http\Controllers\App;

use App\Enum\UserOtpProvidersEnum;
use App\Enum\UserOtpTypesEnum;
use App\Enum\UserRegisterTypesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\ChangePasswordRequest;
use App\Http\Requests\App\Auth\ChangePasswordVerifyOtpRequest;
use App\Http\Requests\App\Profile\UpdateProfileRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Http\Resources\GeneralResource;
use App\Models\User;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProfileController extends Controller
{
    public function getUserInfo(): UserResource
    {
        return UserResource::make(UserService::instance()->getUser());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = UserService::instance()->getUser();

        $user = DB::transaction(static function () use ($request, $user) {
            return UserService::instance()->updateProfile($user, $request->validated());
        });

        return response()->json([
            'user' => UserResource::make($user),
            'message' => LangService::instance()
                ->setDefault('Profile updated successfully')
                ->getLang('profile_updated_successfully'),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): GeneralResource
    {
        if (UserService::instance()->getUser()->register_type !== UserRegisterTypesEnum::EMAIL->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid register type')
                    ->getLang('change_password_invalid_register_type')
            );
        }

        if (User::EMAIL_VERIFICATION_TYPE === 'otp') {
            UserService::instance()->sendChangePasswordNotification(UserService::instance()->getUser(), $request->validated());

            return GeneralResource::make([
                'step' => 'otp_email',
                'message' => LangService::instance()
                    ->setDefault('An OTP has been sent to your email. Please verify to change your password.')
                    ->getLang('change_password_otp_sent'),
            ]);
        } else {
            UserService::instance()->changePassword($request->validated());

            return GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('Your password has been changed! You are logged out of other devices.')
                    ->getLang('user_password_changed'),
            ]);
        }
    }

    public function changePasswordVerifyOtp(ChangePasswordVerifyOtpRequest $request): GeneralResource
    {
        $user = UserService::instance()->getUser();

        if ($user->register_type !== UserRegisterTypesEnum::EMAIL->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid register type')
                    ->getLang('change_password_invalid_register_type')
            );
        }

        if (User::EMAIL_VERIFICATION_TYPE !== 'otp') {
            return GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('OTP verification is not required.')
                    ->getLang('otp_verification_not_required'),
            ]);
        }

        $validated = $request->validated();

        UserService::instance()->checkOtp(UserOtpTypesEnum::CHANGE_PASSWORD, $validated['otp'], $user, UserOtpProvidersEnum::EMAIL);

        UserService::instance()->changePassword($validated);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Your password has been changed! You are logged out of other devices.')
                ->getLang('user_password_changed'),
        ]);
    }

    public function checkLoggedIn(): JsonResponse
    {
        return response()->json([
            'message' => 'LoggedIn',
        ]);
    }
}
