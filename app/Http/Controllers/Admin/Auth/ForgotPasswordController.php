<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\ForgotPasswordRequest;
use App\Http\Requests\Admin\Auth\ResetPasswordRequest;
use App\Services\AdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::broker('admins')->sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json([
                'step' => 'reset_password_link',
                'message' => LangService::instance()
                    ->setDefault('Password reset link has been sent to your email')
                    ->getLang('forgot_password_sent_reset_link'),
            ]);
        }

        return response()->json([
            'step' => 'login',
            'message' => LangService::instance()
                ->setDefault('Unable to send reset link')
                ->getLang('forgot_password_not_sent_reset_link'),
        ], 400);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                AdminService::instance()->changePassword(['password' => $password], $user);
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'step' => 'login',
                'message' => LangService::instance()
                    ->setDefault('Password has been reset successfully')
                    ->getLang('reset_password_password_reset_success'),
            ]);
        }

        return response()->json([
            'step' => 'forgot_password',
            'message' => LangService::instance()
                ->setDefault('Invalid token or expired')
                ->getLang('reset_password_invalid_token'),
        ], 400);
    }
}
