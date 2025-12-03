<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\RegisterRequest;
use App\Http\Resources\App\Auth\UserResource;
use App\Models\User;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(static function () use ($request) {
            $user = UserService::instance()->registerUser($request->validated());

            UserService::instance()->sendEmailVerificationNotification($user);

            return $user;
        });

        return response()->json([
            'step' => User::EMAIL_VERIFICATION_TYPE === 'otp' ? 'otp_email' : 'verify_email',
            'data' => UserResource::make($user),
            'message' => LangService::instance()
                ->setDefault('Register successfully. Email verification link sent successfully')
                ->getLang('register_successfully'),
        ]);
    }
}
