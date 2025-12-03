<?php

namespace App\Http\Middleware;

use App\Http\Resources\GeneralResource;
use App\Models\User;
use App\Services\LangService;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;

class CheckEmailVerificationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = UserService::instance()->getUser();

        if (!$user->hasVerifiedEmail()) {
            return response()->json(GeneralResource::make([
                'step' => User::EMAIL_VERIFICATION_TYPE === 'otp' ? 'otp_email' : 'verify_email',
                'message' => LangService::instance()
                    ->setDefault('Please verify your email address!')
                    ->getLang('please_verify_email_address'),
            ]), 403);
        }

        return $next($request);
    }
}
