<?php

namespace App\Http\Controllers\App\Auth;

use App\Enum\UserOtpTypesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Routing\Redirector;

class EmailVerificationController extends Controller
{
    public function sendVerificationEmail(): JsonResponse
    {
        $user = UserService::instance()->getUser();

        UserService::instance()->sendEmailVerificationNotification($user);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Email verification link sent successfully')
                ->getLang('email_verification_notification_sent')
        ]);
    }

    public function verify(int $id, string $hash): Application|Redirector|RedirectResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return redirect(config('app.frontend_url') . '/email/verify/error');
        }

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url') . '/email/verify/error');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url') . '/email/verify/already-verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/email/verify/success');
    }

    public function verifyOtp(VerifyOtpRequest $request, int $userId): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('id', $userId)
            ->where('email', $validated['email'])
            ->active()
            ->first();

        if (!$user) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('User not found')
                    ->getLang('verify_otp_user_not_found')
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => LangService::instance()
                    ->setDefault('Email already verified')
                    ->getLang('email_already_verified')
            ], 400);
        }

        UserService::instance()->checkOtp($user, UserOtpTypesEnum::REGISTER, $validated['otp']);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Email verified successfully')
                ->getLang('email_verified_successfully')
        ]);
    }
}
