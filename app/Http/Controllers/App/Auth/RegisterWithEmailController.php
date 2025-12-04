<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\CheckEmailRequest;
use App\Http\Requests\App\Auth\RegisterWithEmailRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Models\User;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class RegisterWithEmailController extends Controller
{
    public function checkEmail(CheckEmailRequest $request): JsonResponse
    {
        UserService::instance()->checkEmail($request->validated()['email']);

        return response()->json([
            'step' => 'register_with_email',
            'message' => LangService::instance()
                ->setDefault('Email not used')
                ->getLang('email_not_used')
        ]);
    }

    public function register(RegisterWithEmailRequest $request): JsonResponse
    {
        $user = UserService::instance()->registerWithEmail($request);

        return response()->json([
            'step' => User::EMAIL_VERIFICATION_TYPE === 'otp' ? 'otp_email' : 'verify_email',
            'user' => UserResource::make($user),
            'message' => LangService::instance()
                ->setDefault('Register successfully. Email verification link sent successfully!')
                ->getLang('register_successfully_with_email'),
        ]);
    }
}
